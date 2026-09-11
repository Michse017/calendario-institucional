<?php
declare(strict_types=1);

use App\Core\Campos;
use App\Core\Env;

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    return rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
}

/** Traduce un texto de interfaz. La clave es el propio texto en español. */
function t(string $texto, array $params = []): string
{
    return \App\Core\Idioma::t($texto, $params);
}

/**
 * La dirección actual pero en otro idioma, para el conmutador de la barra.
 *
 * Conserva la ruta y los filtros que haya puestos: cambiar de idioma no debe
 * devolver a nadie al principio de lo que estaba mirando.
 */
function url_idioma(string $codigo): string
{
    $params = array_filter($_GET, 'is_string');
    $params['lang'] = $codigo;

    return base_path() . '/?' . http_build_query($params);
}

/** Nonce de la política de contenido, para marcar un script en línea autorizado. */
function nonce(): string
{
    return \App\Core\Seguridad::nonce();
}

/** url('eventos/nuevo', ['fecha' => '2026-09-16']) → /agenda/?r=eventos%2Fnuevo&fecha=2026-09-16 */
function url(string $ruta = '', array $params = []): string
{
    $u = base_path() . '/';
    if ($ruta !== '') {
        $params = ['r' => $ruta] + $params;
    }
    if ($params) {
        $u .= '?' . http_build_query($params);
    }
    return $u;
}

/** asset('css/app.css') → /agenda/assets/css/app.css?v=<mtime> */
function asset(string $ruta): string
{
    $archivo = PUBLIC_PATH . '/assets/' . $ruta;
    $v = is_file($archivo) ? (string) filemtime($archivo) : '0';
    return base_path() . '/assets/' . $ruta . '?v=' . $v;
}

/** '2026-09-16' → '16 sep 2026' */
function fecha_humana(string $ymd, bool $conAnio = true): string
{
    $t = strtotime($ymd);
    if ($t === false) {
        return $ymd;
    }
    $s = date('j', $t) . ' ' . Campos::MESES_CORTO[(int) date('n', $t)];
    return $conAnio ? $s . ' ' . date('Y', $t) : $s;
}

/** Rango compacto: '15–17 sep 2026', '26 sep – 3 oct 2026', '28 dic 2026 – 2 ene 2027' */
function rango_fechas(string $ini, string $fin): string
{
    if ($ini === $fin) {
        return fecha_humana($ini);
    }
    $ti = strtotime($ini);
    $tf = strtotime($fin);
    if ($ti === false || $tf === false) {
        return $ini . ' – ' . $fin;
    }
    if (date('Y-m', $ti) === date('Y-m', $tf)) {
        return date('j', $ti) . '–' . fecha_humana($fin);
    }
    if (date('Y', $ti) === date('Y', $tf)) {
        return fecha_humana($ini, false) . ' – ' . fecha_humana($fin);
    }
    return fecha_humana($ini) . ' – ' . fecha_humana($fin);
}

function dia_semana_corto(string $ymd): string
{
    $t = strtotime($ymd);
    return $t === false ? '' : Campos::DIAS_CORTO[(int) date('w', $t)];
}

function estado_etiqueta(string $k): string
{
    return Campos::ESTADOS[$k] ?? $k;
}

function estado_color(string $k): string
{
    return Campos::ESTADO_COLOR[$k] ?? Campos::COLOR_NEUTRO;
}

/**
 * Valor de un catálogo tal como se muestra: si el campo quedó en "Otros" y hay detalle, devuelve
 * "Otros: lo que escribieron"; si no, el valor tal cual.
 */
function catalogo_mostrar(array $evento, string $campo): string
{
    $valor = (string) ($evento[$campo] ?? '');
    $col = Campos::COLUMNA_OTRO[$campo] ?? null;
    $detalle = $col !== null ? trim((string) ($evento[$col] ?? '')) : '';
    return ($valor === Campos::OTROS && $detalle !== '') ? $valor . ': ' . $detalle : $valor;
}

function usuario_actual(): array
{
    return App\Core\Auth::usuario();
}

function es_admin(): bool
{
    return App\Core\Auth::esAdmin();
}

function csrf_campo(): string
{
    return App\Core\Csrf::campo();
}

/** flash('ok', 'Guardado') para dejar un mensaje; flash() para leerlo (y borrarlo). */
function flash(?string $tipo = null, ?string $mensaje = null): ?array
{
    if ($tipo !== null) {
        $_SESSION['_flash'] = ['tipo' => $tipo, 'mensaje' => (string) $mensaje];
        return null;
    }
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($f) ? $f : null;
}

function ruta_actual(): string
{
    return trim((string) ($_GET['r'] ?? 'calendario'), '/');
}
