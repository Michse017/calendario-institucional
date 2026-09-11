<?php
declare(strict_types=1);

// Importa el CSV de la hoja "Base de Datos" como eventos (usa el mismo Validator y los catálogos que aprenden).
//   php bin/importar_csv.php sql/datos/base_de_datos_2026.csv [--usuario=1] [--dry-run] [--limpiar] [--anexar]
//   --dry-run  valida y muestra el resumen sin escribir nada
//   --limpiar  (solo APP_ENV=local) vacía eventos e historial antes de importar y recalcula usos
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Normalizador;
use App\Core\Validator;
use App\Models\Catalogo;
use App\Models\Evento;

$csv = null;
$usuario = 1;
$dry = false;
$limpiar = false;
$anexar = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run') {
        $dry = true;
    } elseif ($a === '--limpiar') {
        $limpiar = true;
    } elseif ($a === '--anexar') {
        $anexar = true;
    } elseif (str_starts_with($a, '--usuario=')) {
        $usuario = (int) substr($a, 10);
    } else {
        $csv = $a;
    }
}
if ($csv === null || !is_file($csv)) {
    fwrite(STDERR, "Uso: php bin/importar_csv.php <archivo.csv> [--usuario=1] [--dry-run] [--limpiar] [--anexar]\n");
    exit(1);
}

// cabecera normalizada (prefijo) → campo del formulario
$mapa = [
    'fecha inicio' => 'fecha_inicio', 'fecha fin' => 'fecha_fin', 'ciudad' => 'ciudad', 'pais' => 'pais', 'tipo de accion' => 'tipo_accion',
    'segmento' => 'segmento', 'nombre del evento' => 'nombre', 'mercado' => 'mercado', 'organizador' => 'organizador', 'area responsable' => 'area',
    'objetivo' => 'objetivo', 'linea estrategica' => 'linea_estrategica', 'resultados' => 'resultados', 'contactos' => 'contactos_url',
    'reuniones' => 'reuniones', 'alianzas' => 'alianzas', 'estado' => 'estado', 'observaciones' => 'observaciones', 'evidencia' => 'evidencia_url',
];
$estados = ['no realizado' => 'no_realizado', 'en ejecucion' => 'en_ejecucion', 'realizado' => 'realizado', 'pendiente' => 'no_realizado'];  // "Pendiente" en el Excel = aún no realizado

$f = fopen($csv, 'r');
$cab = fgetcsv($f);
if (!$cab) {
    fwrite(STDERR, "CSV vacío.\n");
    exit(1);
}
$cab[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $cab[0]);
$indice = [];
foreach ($cab as $i => $titulo) {
    $n = Normalizador::normalizar((string) $titulo);
    foreach ($mapa as $pref => $campo) {
        if (str_starts_with($n, $pref) && !isset($indice[$campo])) {
            $indice[$campo] = $i;
        }
    }
}
$faltan = array_diff(array_values($mapa), array_keys($indice));
if ($faltan) {
    fwrite(STDERR, 'Faltan columnas en el CSV: ' . implode(', ', $faltan) . "\n");
    exit(1);
}

if ($limpiar && !$dry) {
    if (Env::get('APP_ENV') !== 'local') {
        fwrite(STDERR, "--limpiar solo está permitido con APP_ENV=local.\n");
        exit(1);
    }
    $pdo = Database::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE eventos_historial');
    $pdo->exec('TRUNCATE eventos');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    Catalogo::recalcularUsos();
    echo "Eventos e historial vaciados (entorno local).\n";
}

// Protección contra cargas duplicadas: si ya hay eventos vivos hay que decirlo explícitamente.
if (!$dry && !$limpiar && !$anexar) {
    $vivos = (int) Database::pdo()->query('SELECT COUNT(*) FROM eventos WHERE eliminado_en IS NULL')->fetchColumn();
    if ($vivos > 0) {
        fwrite(STDERR, "Ya hay $vivos eventos en la base; para no duplicarlos usa --anexar (añadir igualmente) o, solo en local, --limpiar.
");
        exit(1);
    }
}

$ok = 0;
$saltadas = 0;
$avisos = [];
$linea = 1;
while (($fila = fgetcsv($f)) !== false) {
    $linea++;
    if (count(array_filter($fila, static fn($v): bool => trim((string) $v) !== '')) < 3) {
        continue;
    }
    $in = [];
    foreach ($indice as $campo => $i) {
        $in[$campo] = trim((string) ($fila[$i] ?? ''));
    }
    foreach ($in as $campo => $v) {                      // vacíos → N/A (todo es obligatorio)
        if ($v === '' && !in_array($campo, ['fecha_inicio', 'fecha_fin', 'nombre', 'estado'], true)) {
            $in[$campo] = 'N/A';
        }
    }
    $in['estado'] = $estados[Normalizador::normalizar($in['estado'])] ?? ($in['estado'] === '' ? 'no_realizado' : $in['estado']);
    if (!preg_match('/^\d+$/', $in['reuniones']) && !Normalizador::esNA($in['reuniones'])) {
        $orig = $in['reuniones'];
        $in['reuniones'] = str_contains(Normalizador::normalizar($orig), 'pendiente') ? 'Pendiente' : 'N/A';
        if ($in['reuniones'] === 'N/A') {
            $avisos[] = "Línea $linea: reuniones «" . mb_strimwidth($orig, 0, 50, '…') . "» → N/A";
        }
    }
    foreach (['contactos_url', 'evidencia_url'] as $l) {
        $v = $in[$l];
        if (!Normalizador::esNA($v) && !preg_match('~^https?://~i', $v)) {
            $in[$l] = str_contains(Normalizador::normalizar($v), 'pendiente') ? 'Pendiente' : 'N/A';
            if ($in[$l] === 'N/A') {
                $avisos[] = "Línea $linea: $l «" . mb_strimwidth($v, 0, 50, '…') . "» → N/A";
            }
        }
    }
    $r = Validator::evento($in, false, false);   // las cargas del Excel traían N/A en campos que hoy no lo admiten
    if (!$r['ok']) {
        $saltadas++;
        $avisos[] = "Línea $linea SALTADA («{$in['nombre']}»): " . json_encode($r['errores'], JSON_UNESCAPED_UNICODE);
        continue;
    }
    if (!$dry) {
        Evento::crear($r['datos'], $usuario);
    }
    $ok++;
}
fclose($f);
echo ($dry ? '[DRY-RUN] ' : ''), "Importadas: $ok · Saltadas: $saltadas\n";
foreach ($avisos as $a) {
    echo "  · $a\n";
}
exit($saltadas > 0 ? 2 : 0);
