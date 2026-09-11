<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Puente con el SSO: incluye el validador del panel (local o producción), que redirige
 * si no hay sesión o acceso, y deja en variables globales $usuario_sso, $rol_local, etc.
 */
final class SsoBridge
{
    private static ?array $usuario = null;
    private static ?PDO $ssoPdo = null;

    public static function boot(): void
    {
        $ruta = (string) Env::get('SSO_VALIDATOR_PATH', '');
        if ($ruta === '' || !is_file($ruta)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Configuración SSO faltante: SSO_VALIDATOR_PATH en .env no apunta a un validador existente.');
        }

        // El validador asigna estas variables en el ámbito donde se incluye; las exponemos como globales.
        global $usuario_sso, $rol_local, $estado_local, $id_plataforma, $codigo_plataforma, $nombre_plataforma, $db_nombre, $sso_pdo;
        require_once $ruta;

        // El validador apaga los errores hacia el cliente; volvemos a nuestra política.
        error_reporting(E_ALL);
        ini_set('display_errors', Env::bool('APP_DEBUG') ? '1' : '0');

        if (is_array($usuario_sso ?? null) && !empty($usuario_sso['id'])) {
            self::$usuario = [
                'id'           => (int) $usuario_sso['id'],
                'nombre'       => trim((string) ($usuario_sso['nombre'] ?? '')),
                'rol_global'   => (string) ($usuario_sso['rol'] ?? ''),
                'rol_local'    => (string) ($rol_local ?? 'No asignado'),
                'estado_local' => (string) ($estado_local ?? 'Desconocido'),
            ];
        }
        self::$ssoPdo = ($sso_pdo ?? null) instanceof PDO ? $sso_pdo : null;
    }

    public static function usuario(): ?array
    {
        return self::$usuario;
    }

    /** Conexión a la BD del SSO (la abre el validador). Sirve para listar usuarios al otorgar accesos. */
    public static function ssoPdo(): ?PDO
    {
        return self::$ssoPdo;
    }

    public static function panelUrl(): string
    {
        return (string) Env::get('SSO_PANEL_URL', '/panel/index.php');
    }
}
