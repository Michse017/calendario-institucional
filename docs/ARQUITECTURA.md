# Arquitectura

Los diagramas están escritos en Mermaid, que GitHub dibuja solo. Se editan como
texto, así que envejecen con el código en lugar de quedarse desfasados en una
imagen que nadie vuelve a exportar.

- [Infraestructura](#infraestructura)
- [Integración y entrega continuas](#integración-y-entrega-continuas)
- [Capas del código](#capas-del-código)
- [Recorrido de una petición](#recorrido-de-una-petición)
- [Cómo se entra](#cómo-se-entra)
- [Modelo de datos](#modelo-de-datos)
- [Arranque del contenedor](#arranque-del-contenedor)

## Infraestructura

Tres piezas: el repositorio, un servicio que corre la imagen y una base
gestionada. Nada más. El contenedor no guarda estado, así que reiniciarlo no
pierde nada y se puede sustituir en caliente.

```mermaid
flowchart LR
    subgraph maquina["Máquina de desarrollo"]
        A["XAMPP · php -S · MariaDB"]
    end

    subgraph github["GitHub"]
        B["Repositorio"]
        C["Actions · CI"]
        D["Actions · cron diario"]
    end

    subgraph northflank["Northflank"]
        E["Construcción desde el Dockerfile"]
        F["Servicio calendario-app<br/>PHP 8.3 · Apache"]
        G[("Addon calendario-db<br/>MySQL 9.7")]
    end

    V(["Visitante"])

    A -->|git push| B
    B --> C
    B -->|entrega continua| E
    E -->|imagen| F
    F <-->|TLS · red privada| G
    V -->|HTTPS| F
    D -->|reinicio nocturno| F
```

Detalles que no se ven en el dibujo:

| | |
|---|---|
| La base **solo** habla por red privada | No tiene dirección pública. Únicamente el servicio del mismo proyecto la alcanza |
| La conexión va **cifrada** | El servidor tiene `require_secure_transport=ON` y rechaza de plano cualquier conexión en claro |
| El puerto lo decide la plataforma | Llega en la variable `PORT` y el arranque reconfigura Apache con ella |
| El contenedor no escribe nada que importe | Todo el estado vive en la base. No hay volúmenes |

## Integración y entrega continuas

Cada empujón a `main` y cada propuesta de cambio disparan tres trabajos en
paralelo. Solo cuando el repositorio queda verde, Northflank construye y publica.

```mermaid
flowchart TD
    P(["push a main · pull request"]) --> J1
    P --> J2
    P --> J3

    subgraph J1["Sintaxis y pruebas"]
        direction TB
        A1["MariaDB 11 de servicio"] --> A2["php -l sobre todo el árbol"]
        A2 --> A3["Aplicar sql/001_schema.sql"]
        A3 --> A4["Sembrar los datos de ejemplo"]
        A4 --> A5["tests/run.php --sin-omitidos"]
    end

    subgraph J2["El CSS compilado está al día"]
        direction TB
        B1["Guardar el CSS versionado"] --> B2["npm run build"]
        B2 --> B3["verificar_css.mjs<br/>comparar el conjunto de clases"]
    end

    subgraph J3["La imagen de Docker se construye"]
        direction TB
        C1["docker build ."]
    end

    J1 --> OK{"¿Los tres en verde?"}
    J2 --> OK
    J3 --> OK
    OK -->|sí| DEPLOY["Northflank construye y despliega"]
    OK -->|no| STOP["Se queda como está"]
    DEPLOY --> LIVE(["Demostración actualizada"])
```

### Qué vigila cada trabajo, y por qué existe

Ninguno de los tres está por adorno. Cada uno nació de un fallo que llegó a
producirse.

| Trabajo | Vigila | El fallo real que lo justifica |
|---|---|---|
| **Sintaxis y pruebas** | 71 pruebas contra una base de datos de verdad | La batería pasaba en verde con 35 pruebas **omitidas** en silencio, porque sin base se saltan solas. `--sin-omitidos` convierte omitir en fallo |
| | Modo estricto de SQL | El MariaDB de desarrollo es permisivo y convertía un `NULL` en el valor por defecto. El error solo aparecía al desplegar. Ahora la conexión fuerza `STRICT_TRANS_TABLES` y revienta en la máquina de quien programa |
| | Se siembra **antes** de probar | Hay pruebas que comprueban que los catálogos estén completos. Además así se verifica que el sembrador funciona contra una base limpia |
| **El CSS compilado está al día** | Que no falte ninguna clase propia | `npm run build` regenera la hoja entera. Cualquier regla escrita a mano dentro del compilado desaparecería en la siguiente compilación, sin avisar |
| **La imagen de Docker se construye** | Que el `Dockerfile` siga siendo válido | Un fallo de construcción solo se nota al desplegar, que es el peor momento |

La comprobación del CSS **no compara byte a byte**. Tailwind 4 recorre el
proyecto por su cuenta además de los `@source` declarados, y el resultado
depende del sistema de archivos: Windows y el ejecutor de Linux generan hojas
distintas. Por eso se compara el **conjunto de clases propias**, que es lo que
de verdad importa. Probado en ambos sentidos: detecta la clase que falta y no
salta con un cambio inocuo.

### Reinicio nocturno

La demostración es pública y cualquiera puede crear, editar y borrar. Cada
madrugada vuelve sola a su estado inicial.

```mermaid
sequenceDiagram
    autonumber
    participant Cron as GitHub Actions
    participant App as Servicio
    participant BD as Base de datos

    Cron->>Cron: ¿Están DEMO_URL y DEMO_TOKEN?
    Note over Cron: Si faltan, avisa y termina en verde:<br/>no hay demo que reiniciar todavía
    Cron->>App: POST con la cabecera X-Demo-Token
    App->>App: hash_equals contra DEMO_RESET_TOKEN
    alt token correcto
        App->>BD: vaciar y volver a sembrar
        BD-->>App: 70 eventos, 3 cuentas
        App-->>Cron: ok
    else token ausente o distinto
        App-->>Cron: 403, sin tocar nada
    end
    Note over Cron: Un 4xx o 5xx deja el trabajo en rojo,<br/>para enterarse si la demo se cayó
```

Se programa en GitHub y no en el cron de la plataforma a propósito: así funciona
igual aunque la demostración cambie de proveedor.

### Secretos que hacen falta

| Dónde | Nombre | Para qué |
|---|---|---|
| GitHub › Settings › Secrets › Actions | `DEMO_URL` | La dirección de la demo, sin barra final |
| GitHub › Settings › Secrets › Actions | `DEMO_TOKEN` | El mismo valor que `DEMO_RESET_TOKEN` del entorno |
| Plataforma › Variables de entorno | `DEMO_RESET_TOKEN` | Autoriza el reinicio |
| Plataforma › Variables de entorno | `DB_SSL=true` | La base gestionada exige conexión cifrada |

## Capas del código

PHP sin framework, con un autocargador PSR-4 propio. La regla es que cada capa
solo conozca a la de abajo.

```mermaid
flowchart TD
    W["public/index.php<br/>punto de entrada único"]
    W --> N["Núcleo · app/Core"]
    N --> R["Router"]
    R --> C["Controladores · app/Controllers"]
    C --> M["Modelos · app/Models"]
    C --> V["Vistas · app/Views"]
    M --> D["Database · PDO"]
    D --> BD[("MySQL / MariaDB")]

    subgraph nucleo["Lo que aporta el núcleo"]
        direction LR
        N1["Sesion"]
        N2["Seguridad<br/>cabeceras y nonce"]
        N3["Auth<br/>quién eres y qué puedes"]
        N4["Csrf"]
        N5["Request · Response"]
        N6["Validator · Normalizador"]
        N7["Env"]
    end

    N -.-> nucleo
```

Las vistas no consultan la base. Los modelos no escriben HTML. Los controladores
no construyen SQL. Cuando esa separación se rompe se nota enseguida: la prueba
del modelo deja de poder ejecutarse sin levantar media aplicación.

## Recorrido de una petición

```mermaid
sequenceDiagram
    autonumber
    participant N as Navegador
    participant I as index.php
    participant S as Sesion y Seguridad
    participant A as Auth
    participant R as Router
    participant C as Controlador
    participant Mo as Modelo
    participant BD as Base de datos

    N->>I: GET del calendario
    I->>S: iniciar sesión y poner cabeceras
    S-->>I: CSP con nonce, HSTS, cookie Secure y HttpOnly
    I->>A: reconstruir el usuario de la sesión
    A->>BD: cargar la cuenta y su área
    BD-->>A: fila, o nada
    alt sin sesión y ruta privada
        I-->>N: 302 a la pantalla de acceso
    else
        I->>R: despachar
        Note over R: En un POST se exige el testigo CSRF<br/>antes de llegar al controlador
        R->>C: método del controlador
        C->>Mo: consulta con parámetros
        Mo->>BD: sentencia preparada
        BD-->>Mo: filas
        Mo-->>C: datos
        C-->>N: vista con todo escapado
    end
```

## Cómo se entra

```mermaid
sequenceDiagram
    autonumber
    participant N as Navegador
    participant A as AuthController
    participant L as IntentoAcceso
    participant U as Usuario
    participant S as Sesion

    N->>A: POST correo, contraseña y CSRF
    A->>L: ¿demasiados intentos fallidos?
    alt 5 por correo o 20 por dirección en 15 minutos
        L-->>A: bloqueado
        A-->>N: mismo mensaje genérico
    else
        A->>U: autenticar
        Note over U: Si el correo no existe se compara igualmente<br/>contra un hash señuelo, para que el tiempo de<br/>respuesta no revele qué cuentas existen
        U->>U: password_verify en tiempo constante
        alt credenciales correctas
            U-->>A: usuario, nunca la columna de la contraseña
            A->>S: regenerar el identificador de sesión
            Note over S: Corta la fijación de sesión: el identificador<br/>de antes de entrar deja de valer
            A-->>N: 302 al calendario
        else
            A->>L: registrar el intento
            A-->>N: mismo mensaje genérico
        end
    end
```

El mensaje de error es idéntico en los tres casos a propósito. Decir «ese correo
no existe» le regala a quien prueba una lista de cuentas válidas.

## Modelo de datos

Cinco tablas. La decisión que lo explica entero es la primera: todos los
catálogos viven en **una sola tabla**, distinguidos por su columna `tipo`.

```mermaid
erDiagram
    CATALOGO_VALORES ||--o{ USUARIOS : "area_id"
    CATALOGO_VALORES ||--o{ EVENTOS : "ocho columnas distintas"
    USUARIOS ||--o{ EVENTOS_HISTORIAL : "quien lo hizo"
    EVENTOS ||--o{ EVENTOS_HISTORIAL : "sobre que evento"

    CATALOGO_VALORES {
        int id PK
        string tipo "area, pais, mercado, tipo_accion..."
        string valor
        bool activo "baja logica"
    }
    USUARIOS {
        int id PK
        string nombre
        string correo UK
        string contrasena "hash de password_hash"
        enum rol "admin o usuario"
        int area_id FK
        bool activo "baja logica"
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
        enum estado "no_realizado, en_ejecucion, realizado, cancelado"
        string cancelacion_motivo
        int area_id FK
        int tipo_accion_id FK
        int segmento_id FK
    }
    EVENTOS_HISTORIAL {
        int id PK
        int evento_id
        int usuario_id
        enum accion "crear, editar, eliminar, restaurar, cancelar, reanudar"
        json cambios
        datetime fecha
    }
```

Por qué está así:

- **Un solo catálogo.** Ocho listas distintas (áreas, países, mercados, tipos,
  segmentos, líneas, ciudades, organizadores) con la misma forma: un valor, un
  orden y una baja lógica. Ocho tablas idénticas habrían sido ocho modelos,
  ocho pantallas de administración y ocho migraciones cada vez que cambia algo.
- **Baja lógica en todas partes.** Nada se borra. Un área retirada tiene que
  seguir existiendo para que los eventos antiguos sigan nombrando su área.
- **`eventos_historial` no tiene clave foránea, a propósito.** El registro de
  quién hizo qué debe sobrevivir aunque el evento desaparezca. Una clave foránea
  con borrado en cascada se llevaría por delante justo la prueba que interesaba
  conservar.
- **`cambios` es una columna JSON.** Guarda solo los campos que cambiaron. Es el
  motivo de que haga falta MySQL 8 o MariaDB 10.5 en adelante.

## Arranque del contenedor

```mermaid
flowchart TD
    S(["Arranca el contenedor"]) --> P["Fijar el puerto de Apache desde PORT"]
    P --> W["bin/esperar_bd.php<br/>30 intentos, 2 segundos"]
    W --> Q{"¿Responde la base?"}
    Q -->|sí| E["bin/aplicar_esquema.php<br/>idempotente"]
    E --> D["bin/sembrar_demo.php<br/>solo si está vacía"]
    D --> AP["Arrancar Apache"]
    Q -->|no| L["Imprimir el motivo que devuelve el driver"]
    L --> AP2["Arrancar Apache igualmente"]
    AP --> OK(["Sirviendo"])
    AP2 --> ERR(["Sirviendo la página de error de la aplicación"])
```

Dos decisiones que costó encontrar:

**La espera usa la misma clase `Database` que la aplicación.** Cuando la
comprobación de arranque tiene su propia copia de la lógica de conexión, miente
en cuanto la lógica cambia. Aquí pasó: la base exigía TLS, la comprobación no lo
contemplaba, y el registro solo decía «no respondió».

**Si la base nunca responde, Apache arranca igual.** Un contenedor que muere en
bucle solo produce «conexión rechazada», que no dice nada a nadie. Vivo, la
aplicación muestra su propia página de error y los registros quedan a la vista.
