<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Bitácora de cambios por evento (JSON antes/después por campo). */
final class Historial
{
    public static function registrar(int $eventoId, int $usuarioId, string $accion, ?array $cambios): void
    {
        Database::pdo()->prepare('INSERT INTO eventos_historial (evento_id, usuario_id, accion, cambios) VALUES (?, ?, ?, ?)')
            ->execute([$eventoId, $usuarioId, $accion, $cambios === null ? null : json_encode($cambios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** Últimos movimientos (todos o de un evento), con `cambios` ya decodificado. */
    public static function listar(int $limite = 200, ?int $eventoId = null): array
    {
        $sql = 'SELECT h.*, e.nombre AS evento_nombre, e.eliminado_en,
                       COALESCE(a.nombre, CONCAT(\'Usuario #\', h.usuario_id)) AS usuario_nombre
                FROM eventos_historial h
                JOIN eventos e ON e.id = h.evento_id
                LEFT JOIN usuarios a ON a.id = h.usuario_id';
        $p = [];
        if ($eventoId !== null) {
            $sql .= ' WHERE h.evento_id = ?';
            $p[] = $eventoId;
        }
        $sql .= ' ORDER BY h.id DESC LIMIT ' . max(1, min(1000, $limite));
        $st = Database::pdo()->prepare($sql);
        $st->execute($p);
        return array_map(static function (array $f): array {
            $f['cambios'] = $f['cambios'] ? (json_decode((string) $f['cambios'], true) ?: []) : [];
            return $f;
        }, $st->fetchAll());
    }
}
