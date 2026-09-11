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
: "${DB_USER:=calendario}"
: "${DB_PASS:=cambiame}"

echo "Esperando a la base de datos en ${DB_HOST}:${DB_PORT}..."
intentos=0
until php -r '$h=getenv("DB_HOST"); $p=getenv("DB_PORT");
    new PDO("mysql:host=$h;port=$p", getenv("DB_USER"), getenv("DB_PASS"));' 2>/dev/null; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 60 ]; then
        echo "La base no respondió tras 60 intentos. Abortando." >&2
        exit 1
    fi
    sleep 2
done
echo "Base disponible."

echo "Aplicando el esquema (es idempotente)..."
php -r '
$h = getenv("DB_HOST"); $p = getenv("DB_PORT");
$u = getenv("DB_USER"); $c = getenv("DB_PASS"); $n = getenv("DB_NAME");
$pdo = new PDO("mysql:host=$h;port=$p", $u, $c, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
