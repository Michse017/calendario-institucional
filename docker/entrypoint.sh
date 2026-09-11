#!/bin/sh
# Prepara la aplicación y arranca Apache.
#
# Es idempotente: si el esquema ya está aplicado y hay eventos no toca nada, de
# modo que reiniciar el contenedor no borra lo que haya hecho la gente.
set -e

# --- Puerto -----------------------------------------------------------------
# Las plataformas de contenedores asignan el puerto por variable de entorno.
: "${PORT:=80}"
sed -i "s/\${PUERTO}/${PORT}/g" /etc/apache2/sites-available/000-default.conf
# Apache trae su propio Listen 80 en ports.conf: se quita para no chocar.
sed -i 's/^Listen 80$//' /etc/apache2/ports.conf
echo "Apache escuchará en el puerto ${PORT}."

# --- Base de datos ----------------------------------------------------------
: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_NAME:=calendario_demo}"

# Un intento de conexión que IMPRIME el motivo si falla. Sin esto, el arranque
# se queda en "no respondió" y no hay forma de saber si es la contraseña, el
# cortafuegos o que la base exige TLS.
intentar_conexion() {
    php -r '
        $h = getenv("DB_HOST"); $p = getenv("DB_PORT");
        $o = [];
        if (filter_var(getenv("DB_SSL"), FILTER_VALIDATE_BOOLEAN)) {
            $ca = (string) getenv("DB_SSL_CA");
            if ($ca !== "") { $o[PDO::MYSQL_ATTR_SSL_CA] = $ca; }
            $o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] =
                filter_var(getenv("DB_SSL_VERIFY"), FILTER_VALIDATE_BOOLEAN);
        }
        try {
            new PDO("mysql:host=$h;port=$p", getenv("DB_USER"), getenv("DB_PASS"), $o);
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, "  motivo: " . $e->getMessage() . "\n");
            exit(1);
        }
    ' 2>"$1"
}

echo "Esperando a la base de datos en ${DB_HOST}:${DB_PORT}..."
intentos=0
maximo=30
until intentar_conexion /tmp/db_error.txt; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge "$maximo" ]; then
        echo "" >&2
        echo "No se pudo conectar a la base tras ${maximo} intentos." >&2
        cat /tmp/db_error.txt >&2
        echo "" >&2
        echo "Revisa DB_HOST, DB_PORT, DB_USER y DB_PASS." >&2
        echo "Si la base exige conexión cifrada, pon DB_SSL=true." >&2
        echo "" >&2
        echo "Se arranca Apache igualmente para que la aplicación pueda" >&2
        echo "mostrar el error en pantalla en lugar de rechazar conexiones." >&2
        exec "$@"
    fi
    sleep 2
done
echo "Base disponible."

echo "Aplicando el esquema (es idempotente)..."
php -r '
$h = getenv("DB_HOST"); $p = getenv("DB_PORT"); $n = getenv("DB_NAME");
$o = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
if (filter_var(getenv("DB_SSL"), FILTER_VALIDATE_BOOLEAN)) {
    $ca = (string) getenv("DB_SSL_CA");
    if ($ca !== "") { $o[PDO::MYSQL_ATTR_SSL_CA] = $ca; }
    $o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = filter_var(getenv("DB_SSL_VERIFY"), FILTER_VALIDATE_BOOLEAN);
}
$pdo = new PDO("mysql:host=$h;port=$p", getenv("DB_USER"), getenv("DB_PASS"), $o);
$sql = file_get_contents("/var/www/html/sql/001_schema.sql");
// La plataforma ya crea la base y puede no dar permiso para CREATE DATABASE:
// se apunta a la que nos dieron y se quitan esas dos sentencias.
$sql = preg_replace("/^CREATE DATABASE.*?;\s*/ms", "", $sql);
$sql = preg_replace("/^USE\s+`[^`]+`;\s*/m", "", $sql);
$pdo->exec("USE `$n`");
$pdo->exec($sql);
echo "  esquema aplicado\n";
'

echo "Sembrando datos de ejemplo si la base está vacía..."
php /var/www/html/bin/sembrar_demo.php --con-usuarios || true

exec "$@"
