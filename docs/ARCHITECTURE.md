# Architecture

The diagrams are written in Mermaid, which GitHub draws on its own. They are
edited as text, so they age alongside the code instead of drifting out of date
in an image nobody ever exports again.

- [Infrastructure](#infrastructure)
- [Continuous integration and delivery](#continuous-integration-and-delivery)
- [Layers of the code](#layers-of-the-code)
- [The path of a request](#the-path-of-a-request)
- [How signing in works](#how-signing-in-works)
- [Data model](#data-model)
- [Container start-up](#container-start-up)

## Infrastructure

Three pieces: the repository, a service running the image, and a managed
database. Nothing else. The container keeps no state, so restarting it loses
nothing and it can be replaced while running.

```mermaid
flowchart LR
    subgraph dev["Development machine"]
        A["XAMPP · php -S · MariaDB"]
    end

    subgraph github["GitHub"]
        B["Repository"]
        C["Actions · CI"]
        D["Actions · nightly cron"]
    end

    subgraph host["Hosting platform"]
        E["Build from the Dockerfile"]
        F["Service calendario-app<br/>PHP 8.3 · Apache"]
        G[("Addon calendario-db<br/>MySQL 9.7")]
    end

    V(["Visitor"])

    A -->|git push| B
    B --> C
    B -->|continuous delivery| E
    E -->|image| F
    F <-->|TLS · private network| G
    V -->|HTTPS| F
    D -->|nightly reset| F
```

Details the drawing does not show:

| | |
|---|---|
| The database speaks **only** over the private network | It has no public address. Only the service in the same project can reach it |
| The connection is **encrypted** | The server has `require_secure_transport=ON` and flatly rejects any connection in the clear |
| The platform decides the port | It arrives in the `PORT` variable and start-up reconfigures Apache with it |
| The container writes nothing that matters | All state lives in the database. There are no volumes |

## Continuous integration and delivery

Every push to `main` and every pull request fire three jobs in parallel. Only
when the repository is green does the platform build and publish.

```mermaid
flowchart TD
    P(["push to main · pull request"]) --> J1
    P --> J2
    P --> J3

    subgraph J1["Syntax and tests"]
        direction TB
        A1["MariaDB 11 service container"] --> A2["php -l across the tree"]
        A2 --> A3["Apply sql/001_schema.sql"]
        A3 --> A4["Seed the sample data"]
        A4 --> A5["tests/run.php --sin-omitidos"]
    end

    subgraph J2["Compiled CSS is up to date"]
        direction TB
        B1["Keep the committed CSS"] --> B2["npm run build"]
        B2 --> B3["verificar_css.mjs<br/>compare the set of classes"]
    end

    subgraph J3["The Docker image builds"]
        direction TB
        C1["docker build ."]
    end

    J1 --> OK{"All three green?"}
    J2 --> OK
    J3 --> OK
    OK -->|yes| DEPLOY["Platform builds and deploys"]
    OK -->|no| STOP["Nothing changes"]
    DEPLOY --> LIVE(["Demo updated"])
```

### What each job guards, and why it exists

None of the three is decoration. Each one was born from a failure that actually
happened.

| Job | Guards | The real failure that justifies it |
|---|---|---|
| **Syntax and tests** | 71 tests against a real database | The suite passed green with 35 tests silently **skipped**, because without a database they skip themselves. `--sin-omitidos` turns skipping into failure |
| | Strict SQL mode | The development MariaDB is permissive and was turning a `NULL` into the default value. The error only surfaced on deploy. The connection now forces `STRICT_TRANS_TABLES` so it breaks on the developer's own machine |
| | Seeding happens **before** testing | Some tests check that the catalogues are complete. It also proves the seeder works against a clean database |
| **Compiled CSS is up to date** | That no custom class goes missing | `npm run build` regenerates the whole sheet. Any rule hand-written inside the compiled file would disappear on the next build, without warning |
| **The Docker image builds** | That the `Dockerfile` is still valid | A build failure otherwise only shows up at deploy time, which is the worst possible moment |

The CSS check **does not compare byte for byte**. Tailwind 4 scans the project
on its own on top of the declared `@source` entries, and the result depends on
the file system: Windows and the Linux runner produce different sheets. So what
is compared is the **set of custom classes**, which is what actually matters.
Tested in both directions: it catches the missing class and does not trip on a
harmless change.

### Nightly reset

The demo is public and anyone can create, edit and delete. Every night it
returns to its initial state on its own.

```mermaid
sequenceDiagram
    autonumber
    participant Cron as GitHub Actions
    participant App as Service
    participant DB as Database

    Cron->>Cron: Are DEMO_URL and DEMO_TOKEN set?
    Note over Cron: If missing, it says so and ends green:<br/>there is no demo to reset yet
    Cron->>App: POST with the X-Demo-Token header
    App->>App: hash_equals against DEMO_RESET_TOKEN
    alt token correct
        App->>DB: wipe and seed again
        DB-->>App: 70 events, 3 accounts
        App-->>Cron: ok
    else token missing or different
        App-->>Cron: 403, nothing touched
    end
    Note over Cron: A 4xx or 5xx turns the job red,<br/>so a demo that went down is noticed
```

It is scheduled on GitHub rather than in the platform's own cron on purpose:
that way it works the same even if the demo changes provider.

### Secrets required

| Where | Name | What for |
|---|---|---|
| GitHub › Settings › Secrets › Actions | `DEMO_URL` | The demo's address, with no trailing slash |
| GitHub › Settings › Secrets › Actions | `DEMO_TOKEN` | The same value as `DEMO_RESET_TOKEN` in the environment |
| Platform › Environment variables | `DEMO_RESET_TOKEN` | Authorises the reset |
| Platform › Environment variables | `DB_SSL=true` | The managed database requires an encrypted connection |

## Layers of the code

PHP with no framework, with a PSR-4 autoloader of its own. The rule is that each
layer only knows the one below it.

```mermaid
flowchart TD
    W["public/index.php<br/>single entry point"]
    W --> N["Core · app/Core"]
    N --> R["Router"]
    R --> C["Controllers · app/Controllers"]
    C --> M["Models · app/Models"]
    C --> V["Views · app/Views"]
    M --> D["Database · PDO"]
    D --> BD[("MySQL / MariaDB")]

    subgraph core["What the core provides"]
        direction LR
        N1["Sesion · session"]
        N2["Seguridad<br/>headers and nonce"]
        N3["Auth<br/>who you are, what you may do"]
        N4["Csrf"]
        N5["Request · Response"]
        N6["Validator · Normalizador"]
        N7["Env"]
    end

    N -.-> core
```

Views do not query the database. Models do not write HTML. Controllers do not
build SQL. When that separation breaks it shows immediately: the model's test
stops being runnable without standing up half the application.

## The path of a request

```mermaid
sequenceDiagram
    autonumber
    participant N as Browser
    participant I as index.php
    participant S as Session and Security
    participant A as Auth
    participant R as Router
    participant C as Controller
    participant Mo as Model
    participant DB as Database

    N->>I: GET the calendar
    I->>S: start the session and set the headers
    S-->>I: CSP with a nonce, HSTS, Secure and HttpOnly cookie
    I->>A: rebuild the user from the session
    A->>DB: load the account and its department
    DB-->>A: a row, or nothing
    alt no session and a private route
        I-->>N: 302 to the sign-in screen
    else
        I->>R: dispatch
        Note over R: On a POST the CSRF token is required<br/>before the controller is ever reached
        R->>C: controller method
        C->>Mo: query with bound parameters
        Mo->>DB: prepared statement
        DB-->>Mo: rows
        Mo-->>C: data
        C-->>N: view with everything escaped
    end
```

## How signing in works

```mermaid
sequenceDiagram
    autonumber
    participant N as Browser
    participant A as AuthController
    participant L as IntentoAcceso
    participant U as Usuario
    participant S as Sesion

    N->>A: POST address, password and CSRF
    A->>L: too many failed attempts?
    alt 5 per address or 20 per IP in 15 minutes
        L-->>A: blocked
        A-->>N: the same generic message
    else
        A->>U: authenticate
        Note over U: If the address does not exist it is still compared<br/>against a decoy hash, so response time does not<br/>reveal which accounts are registered
        U->>U: password_verify in constant time
        alt credentials correct
            U-->>A: the user, never the password column
            A->>S: regenerate the session identifier
            Note over S: Cuts off session fixation: the identifier<br/>from before signing in stops being valid
            A-->>N: 302 to the calendar
        else
            A->>L: record the attempt
            A-->>N: the same generic message
        end
    end
```

The error message is deliberately identical in all three cases. Saying "that
address does not exist" hands whoever is probing a list of valid accounts.

## Data model

Five tables. The decision that explains the whole thing is the first one: every
catalogue lives in **a single table**, told apart by its `tipo` column.

```mermaid
erDiagram
    CATALOGO_VALORES ||--o{ USUARIOS : "area_id"
    CATALOGO_VALORES ||--o{ EVENTOS : "eight different columns"
    USUARIOS ||--o{ EVENTOS_HISTORIAL : "who did it"
    EVENTOS ||--o{ EVENTOS_HISTORIAL : "on which event"

    CATALOGO_VALORES {
        int id PK
        string tipo "department, country, market, type..."
        string valor "display value"
        bool activo "soft delete"
    }
    USUARIOS {
        int id PK
        string nombre
        string correo UK
        string contrasena "password_hash digest"
        enum rol "admin or user"
        int area_id FK
        bool activo "soft delete"
    }
    INTENTOS_ACCESO {
        int id PK
        string correo
        string ip
        datetime creado_en
    }
    EVENTOS {
        int id PK
        string nombre
        date fecha_inicio
        date fecha_fin
        enum estado "not_started, running, done, cancelled"
        string cancelacion_motivo
        int area_id FK
        int tipo_accion_id FK
        int segmento_id FK
    }
    EVENTOS_HISTORIAL {
        int id PK
        int evento_id
        int usuario_id
        enum accion "create, edit, delete, restore, cancel, resume"
        json cambios "only the fields that changed"
        datetime fecha
    }
```

Why it looks like this:

- **A single catalogue.** Eight different lists (departments, countries,
  markets, types, audiences, strategic lines, cities, organisers) with the same
  shape: a value, an order and a soft delete. Eight identical tables would have
  meant eight models, eight admin screens and eight migrations every time
  anything changed.
- **Soft deletion everywhere.** Nothing is ever removed. A retired department
  has to keep existing so that old events can still name their department.
- **`eventos_historial` has no foreign key, on purpose.** The record of who did
  what must outlive the event itself. A foreign key with cascading delete would
  wipe out exactly the evidence worth keeping.
- **`cambios` is a JSON column.** It stores only the fields that changed. It is
  the reason MySQL 8 or MariaDB 10.5 upwards is required.

## Container start-up

```mermaid
flowchart TD
    S(["Container starts"]) --> P["Set Apache's port from PORT"]
    P --> W["bin/esperar_bd.php<br/>30 attempts, 2 seconds apart"]
    W --> Q{"Does the database answer?"}
    Q -->|yes| E["bin/aplicar_esquema.php<br/>idempotent"]
    E --> D["bin/sembrar_demo.php<br/>only if empty"]
    D --> AP["Start Apache"]
    Q -->|no| L["Print the reason the driver returned"]
    L --> AP2["Start Apache anyway"]
    AP --> OK(["Serving"])
    AP2 --> ERR(["Serving the application's error page"])
```

Two decisions that took some finding:

**The wait uses the same `Database` class as the application.** When the
start-up check keeps its own copy of the connection logic, it lies as soon as
that logic changes. That is exactly what happened here: the database required
TLS, the check did not account for it, and the log only said "no answer".

**If the database never answers, Apache starts anyway.** A container that dies
in a loop only produces "connection refused", which tells nobody anything.
Alive, the application shows its own error page and the logs stay visible.
