<?php
declare(strict_types=1);

define('CRO_TESTING', true);
require dirname(__DIR__) . '/bootstrap.php';

final class TestOmitido extends RuntimeException {}

function assertEq(mixed $esperado, mixed $real, string $msg = ''): void
{
    if ($esperado !== $real) {
        throw new RuntimeException(($msg !== '' ? "$msg: " : '') . 'esperado ' . var_export($esperado, true) . ', real ' . var_export($real, true));
    }
}
function assertTrue(bool $cond, string $msg = ''): void
{
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'se esperaba true');
    }
}
function assertLanza(callable $fn, string $msg = ''): void
{
    try {
        $fn();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($msg !== '' ? $msg : 'se esperaba una excepción');
}
function omitir(string $motivo): never
{
    throw new TestOmitido($motivo);
}
/** Ejecuta $fn(PDO) dentro de una transacción que SIEMPRE se revierte. Omite si no hay BD. */
function conDb(callable $fn): void
{
    try {
        $pdo = App\Core\Database::pdo();
    } catch (Throwable $e) {
        omitir('sin BD local: ' . $e->getMessage());
    }
    $pdo->beginTransaction();
    try {
        $fn($pdo);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// Datos de prueba compartidos entre archivos (se crea en el Task 8)
if (is_file(__DIR__ . '/_fixtures.php')) {
    require __DIR__ . '/_fixtures.php';
}

$filtro = $argv[1] ?? '';
$ok = $fallos = $omitidos = 0;
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $archivo) {
    if ($filtro !== '' && stripos(basename($archivo), $filtro) === false) {
        continue;
    }
    echo basename($archivo), "\n";
    $antes = get_defined_functions()['user'];
    require $archivo;
    foreach (array_diff(get_defined_functions()['user'], $antes) as $fn) {
        if (!str_starts_with($fn, 'test_')) {
            continue;
        }
        try {
            $fn();
            $ok++;
            echo "  ok      $fn\n";
        } catch (TestOmitido $e) {
            $omitidos++;
            echo "  omitido $fn ({$e->getMessage()})\n";
        } catch (Throwable $e) {
            $fallos++;
            echo "  FALLA   $fn: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()})\n";
        }
    }
}
echo "\n$ok ok, $fallos fallos, $omitidos omitidos\n";
exit($fallos > 0 ? 1 : 0);
