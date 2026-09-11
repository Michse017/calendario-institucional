# Seguridad

Qué se protege, cómo, y dónde está el código. Sirve de guía para revisar el
proyecto y de recordatorio de por qué cada decisión está tomada así.

## Autenticación

**Las contraseñas se guardan cifradas.** `App\Core\Password` usa `password_hash()`
con el algoritmo por defecto de PHP, que ya incorpora una sal aleatoria distinta
por contraseña. No hay ningún camino por el que una contraseña llegue a la base
en claro, ni al registro de errores.

**El hash se recifra solo cuando envejece.** Al entrar correctamente se comprueba
`password_needs_rehash()`. Si PHP ya recomienda un coste mayor, se vuelve a cifrar
en ese momento, que es el único en que se tiene el texto plano a mano.

**El acceso no revela qué correos existen.** `Usuario::autenticar()` ejecuta
siempre una verificación, incluso cuando el correo no está registrado, usando un
hash señuelo del mismo coste. Sin eso, responder "no existe" sería instantáneo y
responder "contraseña incorrecta" tardaría lo que tarda el hash: midiendo esa
diferencia cualquiera podría hacer inventario de cuentas. El mensaje que ve el
usuario es el mismo en ambos casos.

**El hash nunca sale del modelo.** `Usuario` solo devuelve la fila tras hacerle
`unset()` a la columna. La única consulta que la lee es privada.

## Sesión

`App\Core\Sesion` fija las marcas de cookie **antes** de `session_start()`, que es
la única ocasión en que se pueden fijar:

- `HttpOnly`: el JavaScript no puede leer la cookie, así que un XSS no basta para
  robar la sesión.
- `SameSite=Lax`: la cookie no viaja en peticiones que nacen en otro sitio.
- `Secure` fuera de desarrollo: solo se envía por HTTPS.

**Fijación de sesión.** Al autenticar se llama a `session_regenerate_id(true)`
antes de escribir el identificador del usuario. Si no se hiciera, alguien podría
fijar de antemano un identificador en el navegador de la víctima y quedarse dentro
de su sesión en cuanto esta entrara.

**Cuenta dada de baja con sesión abierta.** `Auth::iniciar()` recarga el usuario en
cada petición y cierra la sesión si la cuenta ya no está activa. La baja tiene
efecto en el acto, no al caducar la cookie.

## Fuerza bruta

`App\Models\IntentoAcceso` cuenta los fallos recientes por correo y por dirección:

| Ámbito | Umbral | Ventana |
|---|---|---|
| Correo | 5 fallos | 15 minutos |
| Dirección IP | 20 fallos | 15 minutos |

El umbral por dirección es más alto a propósito: detrás de un cortafuegos
corporativo muchas personas legítimas comparten IP y no deben estorbarse.

Solo cuentan los fallos posteriores al último acierto, así que entrar bien deja
el contador a cero sin tener que borrar nada.

**Las cabeceras de proxy se ignoran salvo que se declaren.** `ipCliente()` solo
hace caso a `X-Forwarded-For` si `TRUST_PROXY` está activo. Sin esa precaución
cualquiera burlaría el límite enviando una dirección distinta en cada intento.

## Entrada y salida de datos

**Inyección SQL.** Todas las consultas usan sentencias preparadas con parámetros.
Ningún valor de la petición se concatena. Los nombres de tabla y columna son
constantes del código, nunca vienen de fuera.

**XSS.** Toda salida a plantilla pasa por el ayudante `h()`, que es
`htmlspecialchars` con `ENT_QUOTES`. La política de contenido añade una segunda
barrera limitando desde dónde puede cargarse código.

**CSRF.** `App\Core\Csrf` genera un testigo por sesión y `Csrf::exigir()` lo
comprueba en toda petición que modifica algo. La comparación es con `hash_equals`.

**Validación en el servidor.** `App\Core\Validator` valida todo el evento del lado
del servidor. Lo que el navegador comprueba es comodidad, no seguridad.

## Cabeceras

`App\Core\Seguridad::cabeceras()` se aplica en el punto de entrada, antes de
cualquier salida:

| Cabecera | Para qué |
|---|---|
| `Content-Security-Policy` | Limita de dónde se carga código, estilos y tipografías |
| `X-Frame-Options: DENY` | Impide incrustar la app en un marco para engañar al usuario |
| `X-Content-Type-Options: nosniff` | Evita que el navegador adivine el tipo de un archivo |
| `Referrer-Policy: same-origin` | La dirección completa no sale del sitio |
| `Permissions-Policy` | Renuncia a cámara, micrófono, ubicación y pagos |
| `Strict-Transport-Security` | Exige HTTPS durante un año, solo fuera de desarrollo |

La política permite `unsafe-eval` en los scripts porque Alpine.js evalúa las
expresiones de sus atributos. Es una concesión consciente y acotada.

## Permisos

La regla vive en un solo sitio, `Auth::puedeEditar()`: un administrador puede con
todo, y cualquier otra persona solo con los eventos cuya área es la suya.

El área del evento **nunca se toma del formulario** para un usuario normal: se
impone la suya en el servidor. Así, manipular el campo oculto no sirve de nada.
Hay una prueba dedicada a esto en `tests/PermisosAreaTest.php`.

## Despliegue

**El documento raíz es `public/`.** El código, la configuración y los guiones
quedan fuera del alcance del servidor web aunque alguien adivine la ruta.

**Los errores no se muestran.** En la imagen de Docker, `display_errors` está
apagado y los errores van al registro. Un mensaje de error detallado revela rutas,
consultas y versiones.

**Sin secretos en el repositorio.** Solo se versiona `.env.example` con valores de
ejemplo. El `.env` real está en `.gitignore`.

## Lo que este proyecto no hace

Conviene ser explícito sobre los límites:

- No hay segundo factor. Para una herramienta interna detrás de un acceso único
  corporativo tendría sentido; aquí sería ruido.
- No hay recuperación de contraseña por correo. Un administrador la restablece
  desde el panel, que es lo razonable en una organización pequeña.
- No hay limitación de peticiones más allá del acceso. La aplicación está pensada
  para decenas de personas, no para tráfico abierto.
