<?php
declare(strict_types=1);

/**
 * Vuelca la capa de análisis a CSV, una vista por archivo.
 *
 * Para cuando no se puede conectar la herramienta a la base: Power BI, Excel o
 * Tableau leen la carpeta y ya. También sirve para versionar una foto de los
 * datos junto a un informe, o para compartirlos sin dar acceso al servidor.
 *
 * Uso:  php bin/exportar_bi.php [carpeta]        (por defecto storage/bi)
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$destino = rtrim($argv[1] ?? (BASE_PATH . '/storage/bi'), '/\\');
if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
    fwrite(STDERR, "No se pudo crear la carpeta: $destino\n");
    exit(1);
}

// El orden importa poco, pero se listan las dimensiones primero para que quien
// abra la carpeta entienda el modelo leyendo los nombres de arriba abajo.
$vistas = [
    'bi_dim_fecha', 'bi_dim_area', 'bi_dim_tipo', 'bi_dim_publico',
    'bi_dim_procedencia', 'bi_dim_linea', 'bi_dim_lugar', 'bi_dim_organizador',
    'bi_dim_estado', 'bi_dim_usuario',
    'bi_hechos_eventos', 'bi_hechos_dias', 'bi_hechos_actividad',
    'bi_puente_procedencia', 'bi_plano_eventos',
];

$pdo = Database::pdo();
$total = 0;

foreach ($vistas as $vista) {
    $st = $pdo->query("SELECT * FROM `$vista`");
    $f = fopen("$destino/$vista.csv", 'w');
    if ($f === false) {
        fwrite(STDERR, "No se pudo escribir $vista.csv\n");
        exit(1);
    }
    // BOM de UTF-8: sin él, Excel en Windows abre los acentos rotos. Power BI
    // no lo necesita pero tampoco le molesta.
    fwrite($f, "\xEF\xBB\xBF");

    $filas = 0;
    while ($fila = $st->fetch(PDO::FETCH_ASSOC)) {
        if ($filas === 0) {
            fputcsv($f, array_keys($fila));
        }
        // Los booleanos de MySQL llegan como "0"/"1"; se dejan así porque es lo
        // que cualquier herramienta interpreta sin configurar nada.
        fputcsv($f, array_map(static fn($v) => $v ?? '', $fila));
        $filas++;
    }
    if ($filas === 0) {
        // Una vista vacía sigue necesitando su cabecera, o la herramienta no
        // sabe qué columnas tiene y rompe el modelo al actualizar.
        $cols = [];
        for ($i = 0; $i < $st->columnCount(); $i++) {
            $cols[] = $st->getColumnMeta($i)['name'] ?? "col$i";
        }
        fputcsv($f, $cols);
    }
    fclose($f);
    printf("  %-24s %6d filas\n", $vista, $filas);
    $total += $filas;
}

echo "\nListo: " . count($vistas) . " archivos y $total filas en $destino\n";
