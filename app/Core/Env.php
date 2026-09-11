<?php
declare(strict_types=1);

namespace App\Core;

/** Lector mínimo de .env: CLAVE=valor, comillas opcionales, # comentarios. */
final class Env
{
    /** @var array<string,string> */
    private static array $valores = [];

    public static function cargar(string $archivo): void
    {
        if (!is_file($archivo)) {
            return;
        }
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);
            // Comentario al final de la línea (solo si el valor no va entre comillas)
            if ($valor !== '' && $valor[0] !== '"' && $valor[0] !== "'" && str_contains($valor, ' #')) {
                $valor = trim(substr($valor, 0, (int) strpos($valor, ' #')));
            }
            if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && str_ends_with($valor, $valor[0])) {
                $valor = substr($valor, 1, -1);
            }
            self::$valores[$clave] = $valor;
        }
    }

    public static function get(string $clave, ?string $default = null): ?string
    {
        return self::$valores[$clave] ?? $default;
    }

    public static function bool(string $clave, bool $default = false): bool
    {
        $v = self::get($clave);
        return $v === null ? $default : filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $clave, string $valor): void
    {
        self::$valores[$clave] = $valor;
    }
}
