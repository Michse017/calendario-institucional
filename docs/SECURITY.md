# Security

What is protected, how, and where the code lives. It doubles as a guide for
reviewing the project and as a reminder of why each decision was made this way.

## Authentication

**Passwords are stored hashed.** `App\Core\Password` uses `password_hash()` with
PHP's default algorithm, which already applies a different random salt per
password. There is no path by which a password reaches the database in the
clear, nor the error log.

**The hash is rehashed on its own as it ages.** On a successful sign-in
`password_needs_rehash()` is checked. If PHP now recommends a higher cost, the
password is hashed again right there, which is the only moment the plain text is
at hand.

**Signing in does not reveal which addresses exist.** `Usuario::autenticar()`
always runs a verification, even when the address is not registered, against a
decoy hash of the same cost. Without that, answering "no such user" would be
instantaneous while answering "wrong password" would take as long as the hash
does, and measuring that difference would let anyone inventory the accounts. The
message the user sees is the same in both cases.

**The hash never leaves the model.** `Usuario` only returns the row after
calling `unset()` on that column. The single query that reads it is private.

## Session

`App\Core\Sesion` sets the cookie flags **before** `session_start()`, which is
the only point at which they can be set:

- `HttpOnly`: JavaScript cannot read the cookie, so an XSS is not enough to
  steal the session.
- `SameSite=Lax`: the cookie does not travel with requests originating
  elsewhere.
- `Secure` outside development: it is only sent over HTTPS.

**Session fixation.** On authentication `session_regenerate_id(true)` is called
before the user identifier is written. Without it, someone could plant an
identifier in the victim's browser beforehand and end up inside their session as
soon as they signed in.

**An account deactivated mid-session.** `Auth::iniciar()` reloads the user on
every request and closes the session if the account is no longer active.
Deactivation takes effect immediately, not when the cookie expires.

## Brute force

`App\Models\IntentoAcceso` counts recent failures per address and per IP:

| Scope | Threshold | Window |
|---|---|---|
| Email address | 5 failures | 15 minutes |
| IP address | 20 failures | 15 minutes |

The per-IP threshold is deliberately higher: behind a corporate firewall many
legitimate people share an address and must not get in each other's way.

Only failures after the last success count, so a correct sign-in resets the
counter without anything having to be deleted.

**Proxy headers are ignored unless declared.** `ipCliente()` only honours
`X-Forwarded-For` when `TRUST_PROXY` is on. Without that precaution anyone could
sidestep the limit by sending a different address on each attempt.

## Data in and data out

**SQL injection.** Every query uses prepared statements with bound parameters.
No value from the request is ever concatenated. Table and column names are
constants in the code and never come from outside.

**XSS.** All template output goes through the `h()` helper, which is
`htmlspecialchars` with `ENT_QUOTES`. The content policy adds a second barrier
by limiting where code may be loaded from.

**CSRF.** `App\Core\Csrf` issues one token per session and `Csrf::exigir()`
checks it on every request that changes something. The comparison uses
`hash_equals`.

**Server-side validation.** `App\Core\Validator` validates the whole event on
the server. What the browser checks is convenience, not security.

**Questions leave the building.** The help assistant sends the question, the
conversation so far, and a summary of the installation to Google's Gemini API.
That is the deal with any hosted model and it should be stated plainly rather
than buried: do not type anything into it you would not send to a third party.
Everything else stays here — the assistant never stores what people write. The
`asistente_uso` table holds a timestamp and an IP for the rate limit, nothing
else. An installation with no API key has no assistant at all.

## Headers

`App\Core\Seguridad::cabeceras()` is applied at the entry point, before any
output:

| Header | What for |
|---|---|
| `Content-Security-Policy` | Limits where code, styles and fonts are loaded from |
| `X-Frame-Options: DENY` | Stops the app being framed to trick the user |
| `X-Content-Type-Options: nosniff` | Keeps the browser from guessing a file's type |
| `Referrer-Policy: same-origin` | The full address does not leave the site |
| `Permissions-Policy` | Gives up camera, microphone, location and payments |
| `Strict-Transport-Security` | Requires HTTPS for a year, outside development only |

### Inline scripts: a nonce, not `unsafe-inline`

The page needs one tiny inline script that applies the dark theme before the
first paint, so that no white flash shows. Opening the policy with
`unsafe-inline` would have been the easy way out, but that authorises **any**
inline script, including one that manages to slip in through an injection: it
would mean giving up precisely the protection the policy provides.

Instead, every response carries a 16-byte nonce that travels both in the header
and on the tag:

```
Content-Security-Policy: ... script-src 'self' https://cdn.jsdelivr.net 'nonce-w5iSK5zZ...' ...
<script nonce="w5iSK5zZ...">
```

Only the script carrying the mark the server has just drawn gets executed.
Whoever injects code cannot guess it.

### Integrity of external resources

Alpine, FullCalendar and ECharts are loaded from a content delivery network,
with the version pinned and the file's digest declared:

```html
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"
        integrity="sha384-9Ax3MmS9AClxJyd5/zafcXXjxmwFhZCdsT6HJoJjarvCaAkJlk5QDzjLJm+Wdx5F"
        crossorigin="anonymous"></script>
```

If the file served does not match that digest byte for byte, the browser refuses
to run it. That is the defence against the delivery network being compromised
and serving different code.

The Google Fonts stylesheet is left out: its contents change depending on the
browser asking for it, so it does not admit a fixed digest. That is a known
limitation of the service, not an oversight.

### What remains open, and why

`script-src` allows `unsafe-eval` because Alpine.js evaluates its attribute
expressions with `new Function`. Removing it requires the Alpine build prepared
for strict policies, which forces every expression to be rewritten as a
component method. It is a conscious, bounded concession on scripts, and it is
recorded here rather than glossed over.

`style-src` allows `unsafe-inline` because several views compute colours inline
and because Alpine shows and hides elements by touching the `style` attribute.
The risk of an injected style is considerably smaller than that of a script.

## The assistant's blast radius

A model that can query a database is a new kind of surface, so it is worth
saying exactly how far it reaches.

**It cannot write SQL.** It picks from three functions — count, break down,
search — and passes arguments. `App\Models\AsistenteDatos` writes the query.
This is deliberately *not* text-to-SQL: letting a model compose queries hands
it the whole database, and no amount of prompt wording takes that back.

**Arguments are checked against the catalogue before they reach the database**,
and every query is parameterised. A value that does not exist is not searched
for — it is dropped and reported. That is both safer and more honest: answering
"0 events" for a department nobody ever created lets the reader believe it
exists and is empty.

**Reads only, and capped.** The three functions only `SELECT`, they only touch
events and catalogue values, they never see users or password hashes, and every
result is limited to 25 rows. Three query rounds per question stop a strange
question from becoming a loop that burns quota.

**Scope is defended twice**, because either layer alone is weak: the provider's
safety filters, and a system instruction that fixes the topic and refuses to
take new instructions from inside a question. Tested against off-topic
questions, harmful requests and four jailbreak attempts.

**None of this makes it trustworthy with secrets.** It is a help assistant over
public demo data, not an authorisation boundary.

## Permissions

The rule lives in a single place, `Auth::puedeEditar()`: an administrator can do
anything, and anyone else only with events whose department is their own.

An event's department is **never taken from the form** for a regular user: the
server imposes their own. Tampering with the hidden field therefore achieves
nothing. There is a dedicated test for this in `tests/PermisosAreaTest.php`.

## Deployment

**The document root is `public/`.** Code, configuration and scripts stay out of
the web server's reach even if someone guesses the path.

**Errors are not displayed.** In the Docker image `display_errors` is off and
errors go to the log. A detailed error message gives away paths, queries and
versions.

**No secrets in the repository.** Only `.env.example` is committed, with
placeholder values. The real `.env` is in `.gitignore`.

## What this project does not do

It is worth being explicit about the limits:

- There is no second factor. For an internal tool behind a corporate single
  sign-on it would make sense; here it would be noise.
- There is no password recovery by email. An administrator resets it from the
  panel, which is the reasonable arrangement in a small organisation.
- There is no rate limiting beyond sign-in and the assistant. The application is
  meant for tens of people, not for open traffic.
- The assistant's answers are not verified. The figures it quotes come from the
  database, but the sentence wrapped around them is generated, and generated
  text can be wrong. The panel says so under the input box.
