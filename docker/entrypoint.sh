#!/bin/sh
# Prepara la aplicación y arranca Apache.
#
# Es idempotente: si el esquema ya está aplicado y hay eventos, no toca nada. De
# modo que reiniciar el contenedor no borra lo que haya hecho la gente.
#
# Toda la lógica que necesita hablar con la base vive en bin/*.php y usa las
# mismas clases que la aplicación. Aquí solo se encadenan los pasos.
set -e

# --- Puerto -----------------------------------------------------------------
# Las plataformas de contenedores asignan el puerto por variable de entorno.
: "${PORT:=80}"
sed -i "s/\${PUERTO}/${PORT}/g" /etc/apache2/sites-available/000-default.conf
# Apache trae su propio Listen 80 en ports.conf: se quita para no chocar.
sed -i 's/^Listen 80$//' /etc/apache2/ports.conf
echo "Apache escuchará en el puerto ${PORT}."

# --- Base de datos ----------------------------------------------------------
# Si la base nunca responde se arranca Apache igualmente. Un contenedor que
# muere en bucle solo produce "conexión rechazada", que no dice nada; vivo, la
# aplicación muestra su propia página de error y los registros quedan visibles.
if php /var/www/html/bin/esperar_bd.php 30 2; then
    php /var/www/html/bin/aplicar_esquema.php
    echo "Sembrando datos de ejemplo si la base está vacía..."
    php /var/www/html/bin/sembrar_demo.php --con-usuarios || true
else
    echo "Se arranca Apache de todos modos para que el error sea visible." >&2
fi

exec "$@"
