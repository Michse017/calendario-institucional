<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="' . h(self::token()) . '">';
    }

    public static function verificar(?string $t): bool
    {
        return is_string($t) && isset($_SESSION['_csrf']) && is_string($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
    }

    /** Lee el token de $_POST['_csrf'] o de la cabecera X-CSRF-Token; corta con 403 si no cuadra. */
    public static function exigir(): void
    {
        $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::verificar(is_string($t) ? $t : null)) {
            Response::error(403, 'La sesión caducó o el formulario es inválido. Recarga la página e inténtalo de nuevo.');
        }
    }
}
