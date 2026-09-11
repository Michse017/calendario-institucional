<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Usuario actual y reglas de permiso: admin todo; usuario solo los eventos de su área. */
final class Auth
{
    private static ?array $usuario = null;

    /** Construye el usuario actual a partir del SSO + rol y área en accesos. Llamar tras SsoBridge::boot(). */
    public static function iniciar(): void
    {
        $u = SsoBridge::usuario();
        if ($u === null) {
            Response::error(403, 'No hay sesión del SSO.');
        }
        // 'empleado' es el rol con el que el panel SSO otorga acceso (contrato del wizard): equivale a
        // 'usuario' sin área, es decir, solo consulta hasta que un admin le asigne área desde Admin › Accesos.
        $rol = 'usuario';
        if (($u['rol_global'] === 'admin' && $u['id'] === 1) || $u['rol_local'] === 'admin') {
            $rol = 'admin';
        } elseif ($u['id'] !== 1 && !in_array($u['rol_local'], ['admin', 'usuario', 'empleado'], true)) {
            Response::error(403, 'No tienes acceso a esta plataforma. Pide al administrador que te otorgue acceso.');
        }
        $area = self::cargarArea((int) $u['id']);
        self::$usuario = [
            'id'          => $u['id'],
            'nombre'      => $u['nombre'],
            'rol'         => $rol,
            'area_id'     => (int) ($area['id'] ?? 0),
            'area_nombre' => (string) ($area['valor'] ?? ''),
        ];

        // Mantener el nombre al día en accesos para mostrar "creado por" sin consultar el SSO.
        try {
            Database::pdo()->prepare('UPDATE accesos SET nombre = ? WHERE id = ? AND nombre <> ?')
                ->execute([$u['nombre'], $u['id'], $u['nombre']]);
        } catch (Throwable $e) {
            // Sin BD no bloqueamos la entrada, pero dejamos rastro en storage/logs/php-errors.log.
            error_log('cal: no se pudo sincronizar accesos.nombre para el usuario ' . $u['id'] . ': ' . $e->getMessage());
        }
    }

    /** Para pruebas y scripts CLI. Completa las claves del área si faltan. */
    public static function fijar(array $usuario): void
    {
        self::$usuario = $usuario + ['area_id' => 0, 'area_nombre' => ''];
    }

    /** @return array{id:int,nombre:string,rol:string,area_id:int,area_nombre:string} */
    public static function usuario(): array
    {
        return self::$usuario ?? ['id' => 0, 'nombre' => '', 'rol' => 'usuario', 'area_id' => 0, 'area_nombre' => ''];
    }

    public static function esAdmin(): bool
    {
        return self::usuario()['rol'] === 'admin';
    }

    /** Regla única: admin edita todo; un usuario solo los eventos cuya área es la suya. */
    public static function puedeEditar(array $usuario, array $evento): bool
    {
        if (($usuario['rol'] ?? '') === 'admin') {
            return true;
        }
        $areaUsuario = (int) ($usuario['area_id'] ?? 0);
        return $areaUsuario > 0 && (int) ($evento['area_id'] ?? 0) === $areaUsuario;
    }

    /** Crear exige ser admin o tener área (el evento nace en el área del creador). */
    public static function puedeCrear(array $usuario): bool
    {
        return ($usuario['rol'] ?? '') === 'admin' || (int) ($usuario['area_id'] ?? 0) > 0;
    }

    public static function exigirAdmin(): void
    {
        if (!self::esAdmin()) {
            Response::error(403, 'Solo los administradores pueden entrar aquí.');
        }
    }

    public static function exigirEdicion(array $evento): void
    {
        if (!self::puedeEditar(self::usuario(), $evento)) {
            Response::error(403, 'Solo el área responsable del evento (o un administrador) puede modificarlo.');
        }
    }

    public static function exigirCreacion(): void
    {
        if (!self::puedeCrear(self::usuario())) {
            Response::error(403, 'No tienes un área asignada. Pide al administrador que te asigne una para poder crear eventos.');
        }
    }

    /**
     * Área del acceso (id, valor) desde accesos.area_id; sin BD, sin área o con el área desactivada
     * devuelve null (equivale a "sin área", no bloquea la entrada).
     */
    private static function cargarArea(int $id): ?array
    {
        try {
            $st = Database::pdo()->prepare(
                "SELECT c.id, c.valor FROM accesos a
                 JOIN catalogo_valores c ON c.id = a.area_id AND c.campo = 'area' AND c.activo = 1
                 WHERE a.id = ?"
            );
            $st->execute([$id]);
            $f = $st->fetch();
            return $f ?: null;
        } catch (Throwable $e) {
            error_log('cal: no se pudo cargar el área del usuario ' . $id . ': ' . $e->getMessage());
            return null;
        }
    }
}
