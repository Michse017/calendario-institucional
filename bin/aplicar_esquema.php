<?php
declare(strict_types=1);

/**
 * Aplica sql/001_schema.sql sobre la base ya existente.
 *
 * El fichero es idempotente (todo va con IF NOT EXISTS), así que se puede
 * ejecutar en cada arranque sin miedo a perder datos.
 *
 * Se quitan las sentencias CREATE DATABASE y USE porque en una base gestionada
 * la base ya viene creada y el usuario que nos dan casi nunca tiene permiso
 * para crear otras.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$ruta = dirname(__DIR__) . '/sql/001_schema.sql';
$sql  = file_get_contents($ruta);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer {$ruta}\n");
    exit(1);
}

$sql = preg_replace('/^CREATE DATABASE.*?;\s*/ms', '', $sql);
$sql = preg_replace('/^USE\s+`[^`]+`;\s*/m', '', $sql);

try {
    Database::pdo()->exec($sql);
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo aplicar el esquema: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Esquema aplicado.\n";
