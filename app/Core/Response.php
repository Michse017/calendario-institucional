<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(mixed $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirigir(string $url, int $codigo = 302): never
    {
        header('Location: ' . $url, true, $codigo);
        exit;
    }

    public static function quiereJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public static function error(int $codigo, string $mensaje): never
    {
        if (self::quiereJson()) {
            self::json(['ok' => false, 'error' => $mensaje], $codigo);
        }
        http_response_code($codigo);
        View::render('errores/error', ['titulo' => "Error $codigo", 'codigo' => $codigo, 'mensaje' => $mensaje]);
        exit;
    }

    /** CSV UTF-8 con BOM y separador ; (Excel en español lo abre en columnas). */
    public static function csv(string $nombre, array $cabecera, array $filas): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $cabecera, ';');
        foreach ($filas as $f) {
            fputcsv($out, $f, ';');
        }
        fclose($out);
        exit;
    }
}
