<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

// Autoloader PSR-4 para App\ → app/
spl_autoload_register(static function (string $clase): void {
    if (strncmp($clase, 'App\\', 4) !== 0) {
        return;
    }
    $ruta = APP_PATH . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});

App\Core\Env::cargar(BASE_PATH . '/.env');
require_once APP_PATH . '/helpers.php';

date_default_timezone_set('America/Bogota');
mb_internal_encoding('UTF-8');

$debug = App\Core\Env::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
// Si el servidor ya dice a donde van los errores, se respeta. En un contenedor
// apunta a la salida de error, que es de donde la plataforma recoge los
// registros; escribir en un archivo interno los volveria invisibles justo
// cuando mas falta hacen. Solo se elige archivo cuando no hay nada configurado.
if (trim((string) ini_get('error_log')) === '') {
    ini_set('error_log', STORAGE_PATH . '/logs/php-errors.log');
}

// Log propio en JSON por línea (storage/logs/app.log)
$croLog = static function (string $nivel, string $msg, ?string $archivo = null, ?int $linea = null): void {
    $fila = ['t' => date('c'), 'nivel' => $nivel, 'msg' => $msg, 'archivo' => $archivo, 'linea' => $linea,
        'url' => ($_SERVER['REQUEST_METHOD'] ?? 'cli') . ' ' . ($_SERVER['REQUEST_URI'] ?? '')];
    $json = json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents(STORAGE_PATH . '/logs/app.log', $json . PHP_EOL, FILE_APPEND);
    // Lo grave va ademas al registro del servidor. El archivo de arriba vive
    // dentro del contenedor y nadie lo lee; esto si llega a la plataforma.
    error_log('[calendario] ' . $json);
};

set_exception_handler(static function (Throwable $e) use ($debug, $croLog): void {
    $croLog('excepcion', get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre style="background:#1a1a1a;color:#f88;padding:20px;font:13px/1.5 monospace">'
            . h(get_class($e) . ': ' . $e->getMessage()) . "\n" . h($e->getFile() . ':' . $e->getLine()) . "\n\n"
            . h($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Error interno</h1><p>Hubo un problema procesando la solicitud. Ya quedó registrado.</p>';
    }
});

register_shutdown_function(static function () use ($croLog): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $croLog('fatal', $err['message'], $err['file'] ?? null, $err['line'] ?? null);
    }
});

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('cro_sess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() !== '' ? base_path() : '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
    }
}
