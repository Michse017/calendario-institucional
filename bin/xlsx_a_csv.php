<?php
declare(strict_types=1);

// Convierte una hoja de un .xlsx YA DESCOMPRIMIDO a CSV (UTF-8, coma). No necesita la extensión zip.
//   unzip -o -q "CRONOGRAMA GENERAL CORPOTURISMO 2026.xlsx" -d storage/tmp_xlsx
//   php bin/xlsx_a_csv.php storage/tmp_xlsx "Base de Datos" sql/datos/base_de_datos_2026.csv
// Las celdas numéricas de columnas cuya cabecera contiene "fecha" se convierten de serial Excel a AAAA-MM-DD.
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Normalizador;

[$dir, $hoja, $salida] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if ($dir === '' || $hoja === '' || $salida === '') {
    fwrite(STDERR, "Uso: php bin/xlsx_a_csv.php <carpeta_descomprimida> <nombre_hoja> <salida.csv>\n");
    exit(1);
}
$wb = simplexml_load_file("$dir/xl/workbook.xml");
$rels = simplexml_load_file("$dir/xl/_rels/workbook.xml.rels");
if (!$wb || !$rels) {
    fwrite(STDERR, "No parece una carpeta de .xlsx descomprimido: $dir\n");
    exit(1);
}
$wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$rid = null;
foreach ($wb->xpath('//m:sheets/m:sheet') ?: [] as $s) {
    if ((string) $s['name'] === $hoja) {
        $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    }
}
if ($rid === null) {
    fwrite(STDERR, "Hoja no encontrada: $hoja\n");
    exit(1);
}
$archivoHoja = null;
foreach ($rels->Relationship as $r) {
    if ((string) $r['Id'] === $rid) {
        $target = (string) $r['Target'];
        $archivoHoja = str_starts_with($target, '/') ? $dir . $target : "$dir/xl/$target";
    }
}
if ($archivoHoja === null || !is_file($archivoHoja)) {
    fwrite(STDERR, "No se encontró el archivo de la hoja ($rid).\n");
    exit(1);
}

$ss = [];
if (is_file("$dir/xl/sharedStrings.xml")) {
    foreach (simplexml_load_file("$dir/xl/sharedStrings.xml")->si as $si) {
        $t = '';
        foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $tt) {
            $t .= (string) $tt;
        }
        $ss[] = $t;
    }
}

$colIndice = static function (string $col): int {
    $n = 0;
    foreach (str_split($col) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return $n;
};

$filas = [];
foreach (simplexml_load_file($archivoHoja)->sheetData->row as $row) {
    $celdas = [];
    foreach ($row->c as $c) {
        $col = (string) preg_replace('/\d+/', '', (string) $c['r']);
        $t = (string) $c['t'];
        $v = (string) $c->v;
        if ($t === 's') {
            $v = $ss[(int) $v] ?? '';
        } elseif ($t === 'inlineStr') {
            $v = (string) $c->is->t;
        }
        $celdas[$col] = $v;
    }
    $filas[] = $celdas;
}
if (!$filas) {
    fwrite(STDERR, "Hoja vacía.\n");
    exit(1);
}

// Columnas = las de la fila de cabecera, en orden A, B, …, AA
$cabecera = $filas[0];
uksort($cabecera, static fn(string $a, string $b): int => $colIndice($a) <=> $colIndice($b));
$columnas = array_keys($cabecera);
$esFecha = [];
foreach ($cabecera as $col => $titulo) {
    $esFecha[$col] = str_contains(Normalizador::normalizar($titulo), 'fecha');
}

@mkdir(dirname($salida), 0777, true);
$out = fopen($salida, 'w');
fputcsv($out, array_map(static fn(string $t): string => trim((string) preg_replace('/\s+/u', ' ', $t)), array_values($cabecera)));
$n = 0;
foreach (array_slice($filas, 1) as $fila) {
    $linea = [];
    $llenas = 0;
    foreach ($columnas as $col) {
        $v = trim((string) ($fila[$col] ?? ''));
        if ($esFecha[$col] && $v !== '' && is_numeric($v)) {
            $v = (new DateTime('1899-12-30'))->modify('+' . (int) $v . ' days')->format('Y-m-d');
        }
        if ($v !== '') {
            $llenas++;
        }
        $linea[] = $v;
    }
    if ($llenas < 3) {
        continue;   // fila vacía o solo fórmulas de N°/Mes
    }
    fputcsv($out, $linea);
    $n++;
}
fclose($out);
echo "Filas exportadas: $n → $salida\n";
