#!/bin/sh
# Prepara la base la primera vez y arranca Apache.
#
# Es idempotente: si el esquema ya está aplicado y hay eventos, no toca nada,
# de modo que reiniciar el contenedor no borra lo que haya hecho la gente.
set -e

: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_NAME:=calendario_demo}"
: "${DB_USER:=calendario}"
: "${DB_PASS:=cambiame}"

echo "Esperando a la base de datos en ${DB_HOST}:${DB_PORT}..."
intentos=0
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASS}');" 2>/dev/null; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 60 ]; then
        echo "La base no respondió tras 60 intentos. Abortando." >&2
        exit 1
    fi
    sleep 1
done
echo "Base disponible."

echo "Aplicando el esquema (es idempotente)..."
php -r '
$h = getenv("DB_HOST"); $p = getenv("DB_PORT"); $u = getenv("DB_USER"); $c = getenv("DB_PASS");
$pdo = new PDO("mysql:host=$h;port=$p", $u, $c, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents("/var/www/html/sql/001_schema.sql"));
echo "  esquema aplicado\n";
'

echo "Sembrando datos de ejemplo si la base está vacía..."
php /var/www/html/bin/sembrar_demo.php --con-usuarios || true

exec "$@"
