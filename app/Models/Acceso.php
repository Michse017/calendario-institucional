<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\SsoBridge;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Accesos locales (id = id del usuario en el SSO) con su única área. Revocar = baja lógica, nunca DELETE. */
final class Acceso
{
    public const ROLES = ['admin', 'usuario'];

    private const SELECT = 'SELECT a.*, c.valor AS area_nombre, c.color AS area_color FROM accesos a LEFT JOIN catalogo_valores c ON c.id = a.area_id';

    public static function listar(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY a.estado, a.rol, a.nombre')->fetchAll();
    }

    public static function porId(int $id): ?array
    {
        $st = Database::pdo()->prepare(self::SELECT . ' WHERE a.id = ?');
        $st->execute([$id]);
        $f = $st->fetch();
        return $f ?: null;
    }

    /** Crea o actualiza el acceso. Si viene $areaId (> 0) la valida y la guarda; si no viene, conserva la existente. */
    public static function otorgar(int $id, string $nombre, string $rol, ?int $areaId = null): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Selecciona un usuario del SSO.');
        }
        self::validarRol($rol);
        if ($areaId !== null && $areaId > 0) {
            self::validarArea($areaId);
        } else {
            $areaId = null;
        }
        Database::pdo()->prepare('INSERT INTO accesos (id, nombre, rol, estado, area_id) VALUES (?, ?, ?, \'activo\', ?)
            ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), rol = VALUES(rol), estado = \'activo\', area_id = COALESCE(VALUES(area_id), area_id)')
            ->execute([$id, trim($nombre), $rol, $areaId]);
    }

    /** Cambia (o quita con NULL) el área del acceso. */
    public static function cambiarArea(int $id, ?int $areaId): void
    {
        if ($id <= 0 || !self::porId($id)) {
            throw new RuntimeException('Acceso no encontrado.');
        }
        if ($areaId !== null) {
            self::validarArea($areaId);
        }
        Database::pdo()->prepare('UPDATE accesos SET area_id = ? WHERE id = ?')->execute([$areaId, $id]);
    }

    public static function cambiarRol(int $id, string $rol): void
    {
        self::validarRol($rol);
        Database::pdo()->prepare('UPDATE accesos SET rol = ? WHERE id = ?')->execute([$rol, $id]);
    }

    public static function revocar(int $id): void
    {
        if ($id === 1) {
            throw new RuntimeException('El super-administrador del SSO no se puede revocar.');
        }
        Database::pdo()->prepare("UPDATE accesos SET estado = 'inactivo' WHERE id = ?")->execute([$id]);
    }

    public static function reactivar(int $id): void
    {
        Database::pdo()->prepare("UPDATE accesos SET estado = 'activo' WHERE id = ?")->execute([$id]);
    }

    /** Usuarios activos del SSO que aún no tienen acceso activo aquí (para el formulario "Otorgar"). */
    public static function candidatosSso(): array
    {
        $sso = SsoBridge::ssoPdo();
        if ($sso === null) {
            return [];
        }
        try {
            $todos = $sso->query("SELECT id, TRIM(CONCAT(nombre, ' ', COALESCE(apellido, ''))) AS nombre, usuario FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
        } catch (Throwable) {
            return [];
        }
        $activos = [];
        foreach (self::listar() as $a) {
            if ($a['estado'] === 'activo') {
                $activos[(int) $a['id']] = true;
            }
        }
        return array_values(array_filter($todos, static fn(array $u): bool => !isset($activos[(int) $u['id']])));
    }

    /** Solo valores del catálogo `area`, activos y distintos de N/A. */
    private static function validarArea(int $areaId): void
    {
        $area = Catalogo::valor($areaId);
        if (!$area || $area['campo'] !== 'area' || $area['valor_norm'] === 'n/a' || (int) $area['activo'] !== 1) {
            throw new InvalidArgumentException('Selecciona un área válida.');
        }
    }

    private static function validarRol(string $rol): void
    {
        if (!in_array($rol, self::ROLES, true)) {
            throw new InvalidArgumentException('Rol inválido.');
        }
    }
}
