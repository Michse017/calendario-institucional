<?php
declare(strict_types=1);

/**
 * Siembra los datos de demostración. Envoltura de línea de comandos sobre
 * App\Demo\Sembrador, que es donde vive la lógica.
 *
 * Uso:
 *   php bin/sembrar_demo.php                 solo si la base está vacía
 *   php bin/sembrar_demo.php --forzar        vacía el contenido y vuelve a sembrar
 *   php bin/sembrar_demo.php --con-usuarios  además recrea las cuentas de ejemplo
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Demo\Sembrador;

$forzar      = in_array('--forzar', $argv, true);
$conUsuarios = in_array('--con-usuarios', $argv, true);

echo "Sembrando datos de demostración del Centro Cultural Meridiano\n";

try {
    $r = Sembrador::sembrar($forzar, $conUsuarios);
} catch (Throwable $e) {
    fwrite(STDERR, '  Falló: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($r['omitido']) {
    echo "  Ya hay eventos. Usa --forzar para vaciar y volver a sembrar.\n";
    exit(0);
}

echo "  Catálogos: {$r['catalogos']} valores.\n";
if ($r['usuarios'] > 0) {
    echo "  Usuarios: {$r['usuarios']} cuentas de ejemplo (contraseña " . Sembrador::CONTRASENA_DEMO . ").\n";
}
echo "  Eventos: {$r['eventos']} repartidos por los doce meses ({$r['cancelados']} cancelados).\n";
echo "Listo.\n";
