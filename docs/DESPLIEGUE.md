# Despliegue

La demostración corre en un contenedor con la imagen de este repositorio y una
base MySQL gestionada. Estas notas sirven para reproducirlo en cualquier
plataforma que acepte un `Dockerfile`.

## Qué necesita la aplicación

| | |
|---|---|
| Imagen | La del `Dockerfile` de la raíz: PHP 8.3 sobre Apache |
| Puerto | El que indique la variable `PORT`; por defecto 80 |
| Base de datos | MySQL 8 o MariaDB 10.5+. Hacen falta columnas `JSON`, así que **MySQL 5.6 no sirve** |
| Almacenamiento | Ninguno. El contenedor no guarda estado: todo vive en la base |

El arranque es automático. El `entrypoint` espera a que la base responda, aplica
`sql/001_schema.sql` (que es idempotente) y siembra los datos de ejemplo si la
base está vacía. Reiniciar el contenedor no borra nada.

## Variables de entorno

| Variable | Valor en la demo | Para qué |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | En `true` mostraría errores detallados y relajaría la cookie de sesión |
| `APP_BASE_PATH` | vacío | Solo se rellena si la app cuelga de una subcarpeta |
| `APP_DEMO` | `true` | Muestra las cuentas de ejemplo y habilita el reinicio |
| `PORT` | el que asigne la plataforma | |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | los de la base | |
| `DB_TIMEZONE` | `+00:00` | Zona horaria de la sesión de base de datos |
| `TRUST_PROXY` | `true` | Hay un balanceador delante, así que la dirección real viene en la cabecera |
| `DEMO_RESET_TOKEN` | una cadena larga y aleatoria | Autoriza el reinicio nocturno |

Para generar el token:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

**`TRUST_PROXY` solo debe ir en `true` si de verdad hay un proxy de confianza
delante.** Si no lo hay, la cabecera `X-Forwarded-For` es falsificable y
cualquiera podría saltarse el límite de intentos de acceso enviando una
dirección distinta en cada intento.

## Reinicio nocturno

La demo es pública y cualquiera puede crear, editar y borrar. Cada madrugada
vuelve sola a su estado inicial.

Lo dispara la acción programada `.github/workflows/reiniciar-demo.yml`, que
necesita dos secretos del repositorio en *Settings › Secrets and variables ›
Actions*:

- `DEMO_URL`: la dirección de la demo, sin barra final.
- `DEMO_TOKEN`: el mismo valor que `DEMO_RESET_TOKEN` del entorno.

Se programa desde GitHub y no desde el cron de la plataforma a propósito: así
funciona igual aunque la demo cambie de proveedor.

También se puede llamar a mano:

```bash
curl -X POST -H "X-Demo-Token: $TOKEN" https://LA-DEMO/?r=demo/reiniciar
```

Responde `{"ok":true,...}`. Sin token, o con uno equivocado, responde 403 y no
toca nada. Fuera del modo demostración la ruta devuelve 404.

## Comprobaciones tras desplegar

1. Abrir la dirección en una ventana de incógnito: debe redirigir a la pantalla
   de acceso y mostrar las tres cuentas de ejemplo.
2. Entrar como `ana.torres@meridiano.demo` y comprobar que el calendario tiene
   eventos repartidos por todo el año.
3. Entrar como `carlos.mena@meridiano.demo` y comprobar que **no** puede editar
   un evento de otra área. Esa es la regla que da sentido a la aplicación.
4. Lanzar la acción de reinicio a mano desde la pestaña *Actions* y confirmar
   que responde en verde.
5. Comprobar que las cabeceras de seguridad llegan:

```bash
curl -sI https://LA-DEMO/?r=acceso | grep -iE 'content-security|x-frame|strict-transport'
```
