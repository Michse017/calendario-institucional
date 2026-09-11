<?php
declare(strict_types=1);

/**
 * Devuelve la demostración a su estado inicial.
 *
 * La demo es pública y cualquiera puede crear, editar y borrar. Sin esto, en una
 * semana estaría llena de pruebas ajenas o vacía. Se ejecuta cada noche desde una
 * tarea programada, y también desde el botón del panel de administración.
 *
 * Es deliberadamente una envoltura muy fina sobre el sembrador: un solo camino
 * para generar los datos significa que la demo recién desplegada y la demo
 * recién reiniciada son exactamente iguales.
 *
 * Uso:
 *   php bin/reiniciar_demo.php                 vacía y vuelve a sembrar
 *   php bin/reiniciar_demo.php --con-usuarios  además recrea las cuentas de ejemplo
 */

$conUsuarios = in_array('--con-usuarios', $argv, true);

$argumentos = [escapeshellarg(__DIR__ . '/sembrar_demo.php'), '--forzar'];
if ($conUsuarios) {
    $argumentos[] = '--con-usuarios';
}

$comando = escapeshellarg(PHP_BINARY) . ' ' . implode(' ', $argumentos);

echo "Reiniciando la demostración...\n";
passthru($comando, $codigo);

if ($codigo !== 0) {
    fwrite(STDERR, "El reinicio falló con código $codigo.\n");
    exit($codigo);
}

echo "Demostración reiniciada.\n";
