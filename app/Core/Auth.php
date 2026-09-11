<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;
use Throwable;

/**
 * Usuario actual y reglas de permiso.
 *
 * La regla de fondo es una sola y se lee en `puedeEditar()`: un administrador
 * puede con todo, y cualquier otra persona solo con los eventos de su área.
 * Todo lo demás de esta clase existe para sostener esa frase.
 */
final class Auth
{
    private static ?array $usuario = null;

    /**
     * Reconstruye el usuario actual a partir de la sesión.
     *
     * No decide si la ruta es pública ni redirige: de eso se encarga el punto de
     * entrada. Aquí, si no hay sesión válida, simplemente no hay usuario.
     */
    public static function iniciar(): void
    {
        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        try {
            $u = Usuario::porId($id);
        } catch (Throwable $e) {
            error_log('calendario: no se pudo cargar el usuario ' . $id . ': ' . $e->getMessage());
            return;
        }

        // La cuenta pudo darse de baja con la sesión ya abierta: se cierra en el acto.
        if ($u === null || (int) $u['activo'] !== 1) {
            Sesion::cerrar();
            return;
        }

        $area = self::cargarArea((int) ($u['area_id'] ?? 0));

        self::$usuario = [
            'id'          => (int) $u['id'],
            'nombre'      => (string) $u['nombre'],
            'rol'         => (string) $u['rol'],
            'area_id'     => (int) ($area['id'] ?? 0),
            'area_nombre' => (string) ($area['valor'] ?? ''),
        ];
    }

    /** Para pruebas y guiones de consola. Completa las claves del área si faltan. */
    public static function fijar(array $usuario): void
    {
        self::$usuario = $usuario + ['area_id' => 0, 'area_nombre' => ''];
    }

    public static function hayUsuario(): bool
    {
        return self::$usuario !== null;
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

    /** Crear exige ser admin o tener área: el evento nace en el área de quien lo crea. */
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
     * Área del usuario a partir de su `area_id`.
     *
     * Devuelve null si no tiene área o si el área fue desactivada, lo que equivale
     * a "solo consulta" y no impide entrar.
     */
    private static function cargarArea(int $areaId): ?array
    {
        if ($areaId <= 0) {
            return null;
        }
        try {
            $st = Database::pdo()->prepare(
                "SELECT id, valor FROM catalogo_valores
                 WHERE id = ? AND campo = 'area' AND activo = 1"
            );
            $st->execute([$areaId]);
            return $st->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('calendario: no se pudo cargar el área ' . $areaId . ': ' . $e->getMessage());
            return null;
        }
    }
}
