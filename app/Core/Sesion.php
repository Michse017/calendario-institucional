<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Arranque y cierre de la sesión con las marcas de cookie puestas.
 *
 * Las marcas hay que fijarlas ANTES de session_start(), o PHP ya habrá emitido
 * la cookie con los valores por defecto y no habrá forma de corregirla.
 */
final class Sesion
{
    private const NOMBRE = 'calendario_sid';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $base = (string) Env::get('APP_BASE_PATH', '');

        session_set_cookie_params([
            'lifetime' => 0,                        // la cookie muere al cerrar el navegador
            'path'     => $base !== '' ? $base : '/',
            'httponly' => true,                     // el JavaScript no puede leerla: corta el robo por XSS
            'samesite' => 'Lax',                    // no viaja en peticiones desde otros sitios: corta el CSRF
            'secure'   => !Env::bool('APP_DEBUG'),  // solo por HTTPS fuera de desarrollo
        ]);

        session_name(self::NOMBRE);
        session_start();
    }

    /**
     * Cambia el identificador de sesión conservando su contenido.
     *
     * Se llama justo al autenticar. Si no se hiciera, alguien podría fijar de
     * antemano un identificador en el navegador de la víctima y quedarse dentro
     * de su sesión una vez que esta entrara. Es la fijación de sesión.
     */
    public static function regenerar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Vacía la sesión, borra su cookie y la destruye. En ese orden. */
    public static function cerrar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
