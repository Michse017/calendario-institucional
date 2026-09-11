<?php
declare(strict_types=1);

/**
 * Devuelve la demostración a su estado inicial.
 *
 * La demo es pública y cualquiera puede crear, editar y borrar. Sin esto, en una
 * semana estaría llena de pruebas ajenas o vacía. Lo llama la tarea programada
 * nocturna; el botón del panel usa la misma clase directamente.
 *
 * Uso:
 *   php bin/reiniciar_demo.php                 vacía y vuelve a sembrar
 *   php bin/reiniciar_demo.php --con-usuarios  además recrea las cuentas de ejemplo
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Demo\Sembrador;

$conUsuarios = in_array('--con-usuarios', $argv, true);

echo "Reiniciando la demostración...\n";

try {
    $r = Sembrador::sembrar(true, $conUsuarios);
} catch (Throwable $e) {
    fwrite(STDERR, '  Falló: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "  {$r['eventos']} eventos y {$r['catalogos']} valores de catálogo.\n";
echo "Demostración reiniciada.\n";
