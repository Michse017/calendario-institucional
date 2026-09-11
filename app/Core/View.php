<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $vista, array $datos = [], ?string $layout = 'layout/base'): void
    {
        $extra = '';
        $contenido = self::parcial($vista, $datos, $extra);
        if ($layout === null) {
            echo $contenido;
            return;
        }
        echo self::parcial($layout, $datos + ['contenido' => $contenido, 'scripts' => $extra]);
    }

    /** $scriptsSalida recoge la variable $scripts que una vista deje definida (p. ej. CDNs propios). */
    public static function parcial(string $vista, array $datos = [], string &$scriptsSalida = ''): string
    {
        $archivo = APP_PATH . '/Views/' . $vista . '.php';
        if (!is_file($archivo)) {
            throw new RuntimeException("Vista no encontrada: $vista");
        }
        extract($datos, EXTR_SKIP);
        ob_start();
        include $archivo;
        $html = (string) ob_get_clean();
        if (isset($scripts) && is_string($scripts)) {
            $scriptsSalida = $scripts;
        }
        return $html;
    }
}
