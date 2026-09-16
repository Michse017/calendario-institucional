# Deployment

The demo runs in a container built from this repository's image, against a
managed MySQL database. These notes are enough to reproduce it on any platform
that accepts a `Dockerfile`.

## What the application needs

| | |
|---|---|
| Image | The one from the `Dockerfile` at the root: PHP 8.3 on Apache |
| Port | Whatever `PORT` says; 80 by default |
| Database | MySQL 8 or MariaDB 10.5+. `JSON` columns are required, so **MySQL 5.6 will not do** |
| Storage | None. The container keeps no state: everything lives in the database |
| Rewrites | None. The document root points at `public/` and routes travel in the query string, so no rewrite rules or `.htaccess` files are needed |

Start-up is automatic. The entrypoint waits for the database to answer, applies
`sql/001_schema.sql` (which is idempotent) and seeds the sample data if the
database is empty. Restarting the container deletes nothing.

## Environment variables

| Variable | Value in the demo | What for |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | With `true` it would show detailed errors and relax the session cookie |
| `APP_BASE_PATH` | empty | Only filled in when the app hangs off a subfolder |
| `APP_DEMO` | `true` | Shows the sample accounts and enables the reset |
| `PORT` | whatever the platform assigns | |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | the database's own | |
| `DB_TIMEZONE` | `+00:00` | Time zone of the database session |
| `DB_SSL` | `true` | The managed database requires an encrypted connection |
| `DB_SSL_VERIFY` | `false` | The certificate is signed by the provider's internal authority, which the container does not know. Traffic is still encrypted |
| `DB_SSL_CA` | empty | Path to the certificate authority. Empty uses the system store, which is what switches encryption on |
| `TRUST_PROXY` | `true` | There is a load balancer in front, so the real address arrives in a header |
| `DEMO_RESET_TOKEN` | a long random string | Authorises the nightly reset |
| `GEMINI_API_KEY` | a free key from [AI Studio](https://aistudio.google.com/apikey) | Turns on the help assistant. Leave it out and the button never appears |

To generate the token:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

**`TRUST_PROXY` should only be `true` if there really is a trusted proxy in
front.** If there is not, the `X-Forwarded-For` header is forgeable and anyone
could sidestep the sign-in attempt limit by sending a different address each
time.

### Managed databases and encryption

Almost every managed cloud database requires an encrypted connection and flatly
rejects a connection in the clear. If start-up hangs waiting for the database,
the first thing to try is `DB_SSL=true`.

The trap behind it is worth knowing, because it is silent: PHP's PDO **only**
negotiates TLS if it is given one of the `SSL_KEY`, `SSL_CERT`, `SSL_CA`,
`SSL_CAPATH` or `SSL_CIPHER` options. Setting `SSL_VERIFY_SERVER_CERT` alone
encrypts nothing and gives no warning: the connection goes out in the clear as
if nothing had been configured. That is why `DB_SSL=true` always declares a
certificate authority, either the one in `DB_SSL_CA` or the system store, and
if it finds neither it would rather fail than connect unencrypted.

To check it on a live connection:

```sql
SHOW SESSION STATUS LIKE 'Ssl_cipher';
```

An empty value means there is no encryption.

## Nightly reset

The demo is public and anyone can create, edit and delete. Every night it
returns to its initial state on its own.

It is triggered by the scheduled action `.github/workflows/reiniciar-demo.yml`,
which needs two repository secrets under *Settings › Secrets and variables ›
Actions*:

- `DEMO_URL`: the demo's address, with no trailing slash.
- `DEMO_TOKEN`: the same value as `DEMO_RESET_TOKEN` in the environment.

It is scheduled from GitHub rather than from the platform's own cron on
purpose: that way it works the same even if the demo changes provider.

It can also be called by hand:

```bash
curl -X POST -H "X-Demo-Token: $TOKEN" https://THE-DEMO/?r=demo/reiniciar
```

It answers `{"ok":true,...}`. With no token, or the wrong one, it answers 403
and touches nothing. Outside demo mode the route returns 404.

## Checks after deploying

1. Open the address in a private window: it should redirect to the sign-in
   screen and show the three sample accounts.
2. Sign in as `ana.torres@meridiano.demo` and check that the calendar has
   events spread across the whole year.
3. Sign in as `carlos.mena@meridiano.demo` and check that they **cannot** edit
   an event belonging to another department. That is the rule that gives the
   application its point.
4. Run the reset action by hand from the *Actions* tab and confirm it comes back
   green.
5. Check that the security headers arrive:

```bash
curl -sI https://THE-DEMO/?r=acceso | grep -iE 'content-security|x-frame|strict-transport'
```
