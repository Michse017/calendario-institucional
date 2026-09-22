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

// --- Columnas que llegaron después de la primera versión ------------------------
// CREATE TABLE IF NOT EXISTS no añade columnas a una tabla que ya existe, y MySQL no
// admite ADD COLUMN IF NOT EXISTS. Se mira information_schema y se altera solo lo que
// falte, así el guion sigue siendo idempotente sobre una base ya desplegada.
$pdo = Database::pdo();
$existe = static function (string $tabla, string $columna) use ($pdo): bool {
    $st = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $st->execute([$tabla, $columna]);
    return (bool) $st->fetchColumn();
};
try {
    if (!$existe('eventos', 'dueno_id')) {
        $pdo->exec("ALTER TABLE eventos ADD COLUMN dueno_id INT NULL COMMENT 'usuarios.id: quien responde por el evento' AFTER creado_por");
        // El responsable arranca siendo quien creó el evento; si ese usuario ya no existe, el primer administrador.
        $pdo->exec('UPDATE eventos e JOIN usuarios u ON u.id = e.creado_por SET e.dueno_id = u.id WHERE e.dueno_id IS NULL');
        $pdo->exec("UPDATE eventos SET dueno_id = (SELECT MIN(id) FROM usuarios WHERE rol = 'admin') WHERE dueno_id IS NULL");
        $pdo->exec('ALTER TABLE eventos MODIFY dueno_id INT NOT NULL, ADD KEY idx_dueno (dueno_id), ADD CONSTRAINT fk_ev_dueno FOREIGN KEY (dueno_id) REFERENCES usuarios (id)');
        echo "Columna eventos.dueno_id añadida.\n";
    }
    if (!$existe('eventos', 'requiere_cubrimiento')) {
        $pdo->exec('ALTER TABLE eventos ADD COLUMN requiere_cubrimiento TINYINT(1) NOT NULL DEFAULT 0 AFTER dueno_id');
        echo "Columna eventos.requiere_cubrimiento añadida.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudieron aplicar los cambios de columnas: ' . $e->getMessage() . "\n");
    exit(1);
}
