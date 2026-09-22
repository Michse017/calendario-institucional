<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private ?array $json = null;

    public function metodo(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** Ruta lógica: ?r=eventos/nuevo → 'eventos/nuevo'. Por defecto 'calendario'. */
    public function ruta(): string
    {
        $r = trim((string) ($_GET['r'] ?? 'calendario'), '/');
        return preg_match('~^[a-z0-9_/-]{1,80}$~', $r) ? $r : '__invalida__';
    }

    public function get(string $k, ?string $d = null): ?string
    {
        $v = $_GET[$k] ?? null;
        return is_string($v) ? $v : $d;
    }

    public function post(string $k, ?string $d = null): ?string
    {
        $v = $_POST[$k] ?? null;
        return is_string($v) ? $v : $d;
    }

    /** @return array<string,mixed> */
    public function posts(): array
    {
        return $_POST;
    }

    public function int(string $k, int $d = 0): int
    {
        $v = $this->get($k) ?? $this->post($k) ?? ($this->json()[$k] ?? null);
        return is_numeric($v) ? (int) $v : $d;
    }

    /** Cuerpo JSON (fetch con application/json). */
    public function json(): array
    {
        if ($this->json === null) {
            $j = json_decode((string) file_get_contents('php://input'), true);
            $this->json = is_array($j) ? $j : [];
        }
        return $this->json;
    }

    /** Filtros de calendario/lista permitidos por la URL (saneados; los ids se validan en el modelo). */
    public function filtros(): array
    {
        $f = [];
        foreach (['anio', 'area_id', 'tipo_accion_id', 'segmento_id', 'estado', 'mios', 'q', 'desde', 'hasta', 'orden', 'persona'] as $k) {
            $v = $this->get($k);
            if ($v === null || $v === '') {
                continue;
            }
            if (in_array($k, ['desde', 'hasta'], true) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                continue;
            }
            if ($k === 'orden' && !in_array($v, ['asc', 'desc'], true)) {
                continue;
            }
            $f[$k] = $v;
        }
        return $f;
    }
}
