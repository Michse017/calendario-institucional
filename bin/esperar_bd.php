<?php
declare(strict_types=1);

/**
 * Espera a que la base de datos acepte conexiones.
 *
 * Lo usa el arranque del contenedor: la base y la aplicación se levantan a la
 * vez y la primera suele tardar más. Usa la misma clase Database que el resto
 * de la aplicación, a propósito: si la conexión necesita cifrado o una zona
 * horaria concreta, esta comprobación pasa por ahí igual que el código real, y
 * no por una copia que puede quedarse atrás.
 *
 * Si agota los intentos imprime el motivo que devuelve el driver. Sin eso, un
 * arranque fallido solo dice "no respondió", que no distingue entre una
 * contraseña mala, un nombre de base equivocado y una base que exige TLS.
 *
 * Uso: php bin/esperar_bd.php [intentos] [segundos entre intentos]
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$intentos = max(1, (int) ($argv[1] ?? 30));
$espera   = max(1, (int) ($argv[2] ?? 2));

printf(
    "Esperando a la base en %s:%s (base %s, cifrado %s)...\n",
    Env::get('DB_HOST', '127.0.0.1'),
    Env::get('DB_PORT', '3306'),
    Env::get('DB_NAME', 'calendario_demo'),
    Env::bool('DB_SSL') ? 'sí' : 'no'
);

$ultimo = null;
for ($i = 1; $i <= $intentos; $i++) {
    try {
        Database::pdo()->query('SELECT 1');
        echo "Base disponible en el intento {$i}.\n";
        exit(0);
    } catch (Throwable $e) {
        $ultimo = $e;
        if ($i < $intentos) {
            sleep($espera);
        }
    }
}

fwrite(STDERR, "\nLa base no respondió tras {$intentos} intentos.\n");
fwrite(STDERR, 'Motivo: ' . ($ultimo?->getMessage() ?? 'desconocido') . "\n\n");
fwrite(STDERR, "Comprueba DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS.\n");
if (!Env::bool('DB_SSL')) {
    fwrite(STDERR, "Si la base exige conexión cifrada, pon DB_SSL=true.\n");
}
exit(1);
