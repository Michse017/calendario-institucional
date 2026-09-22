<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Password;

/**
 * Usuarios de la aplicación.
 *
 * El rol y el área viven aquí: `admin` lo puede todo, y un `usuario` con área
 * crea y edita solo los eventos de esa área. Un usuario sin área solo consulta.
 *
 * La columna `contrasena` guarda un hash y nunca sale de esta clase: todos los
 * métodos públicos devuelven la fila ya sin ella.
 */
final class Usuario
{
    /** Columnas que sí pueden viajar fuera de esta clase. */
    private const CAMPOS_PUBLICOS = 'id, nombre, correo, rol, area_id, activo, creado_en';

    public static function porId(int $id): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT ' . self::CAMPOS_PUBLICOS . ' FROM usuarios WHERE id = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Uso interno de autenticar(): es el único lugar donde se lee el hash. */
    private static function conHashPorCorreo(string $correo): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT ' . self::CAMPOS_PUBLICOS . ', contrasena FROM usuarios WHERE correo = ?'
        );
        $st->execute([mb_strtolower(trim($correo))]);
        return $st->fetch() ?: null;
    }

    /**
     * Comprueba las credenciales y devuelve el usuario, o null si no cuadran.
     *
     * Dos detalles deliberados:
     *
     * 1. Siempre se ejecuta una verificación, incluso cuando el correo no existe.
     *    Si no, responder "no existe" sería instantáneo y responder "contraseña
     *    incorrecta" tardaría lo que tarda el hash, y midiendo esa diferencia
     *    cualquiera podría averiguar qué correos están registrados.
     *
     * 2. La causa concreta del fallo no se devuelve. Quien llama solo sabe que
     *    no entró, y así el mensaje al usuario es siempre el mismo.
     */
    public static function autenticar(string $correo, string $plano): ?array
    {
        $fila = self::conHashPorCorreo($correo);

        // Hash señuelo con el mismo coste que uno real, para gastar el mismo tiempo.
        $hash = $fila['contrasena']
            ?? '$2y$12$C9Qe1Yk0oQx7t6bWc2dSPeE1kHnJ0mLrV5uYtA3sD8fG7hJ9kL0mO';

        $acierta = Password::verificar($plano, $hash);

        if (!$acierta || $fila === null || (int) $fila['activo'] !== 1) {
            return null;
        }

        // Aprovechamos que aquí sí tenemos el texto plano para subir el coste
        // del hash si PHP ya recomienda uno mayor que el usado al registrarlo.
        if (Password::necesitaRecifrado($hash)) {
            self::cambiarContrasena((int) $fila['id'], $plano);
        }

        unset($fila['contrasena']);
        return $fila;
    }

    /** @return array<int,array<string,mixed>> Todos, incluidos los dados de baja. */
    public static function listar(): array
    {
        return Database::pdo()->query(
            'SELECT u.' . str_replace(', ', ', u.', self::CAMPOS_PUBLICOS) . ", c.valor AS area_nombre
             FROM usuarios u
             LEFT JOIN catalogo_valores c ON c.id = u.area_id AND c.campo = 'area'
             ORDER BY u.activo DESC, u.nombre"
        )->fetchAll();
    }

    public static function crear(string $nombre, string $correo, string $plano, string $rol, ?int $areaId): int
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO usuarios (nombre, correo, contrasena, rol, area_id) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            trim($nombre),
            mb_strtolower(trim($correo)),
            Password::cifrar($plano),
            $rol === 'admin' ? 'admin' : 'usuario',
            $areaId ?: null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(int $id, string $nombre, string $rol, ?int $areaId): void
    {
        Database::pdo()->prepare(
            'UPDATE usuarios SET nombre = ?, rol = ?, area_id = ? WHERE id = ?'
        )->execute([trim($nombre), $rol === 'admin' ? 'admin' : 'usuario', $areaId ?: null, $id]);
    }

    public static function cambiarContrasena(int $id, string $plano): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET contrasena = ? WHERE id = ?')
            ->execute([Password::cifrar($plano), $id]);
    }

    /** Baja y alta lógicas. Nunca se borra la fila: el historial apunta a ella. */
    public static function activar(int $id, bool $activo): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')
            ->execute([$activo ? 1 : 0, $id]);
    }

    public static function correoExiste(string $correo, int $excepto = 0): bool
    {
        $st = Database::pdo()->prepare('SELECT 1 FROM usuarios WHERE correo = ? AND id <> ?');
        $st->execute([mb_strtolower(trim($correo)), $excepto]);
        return (bool) $st->fetchColumn();
    }

    /** Cuántos administradores activos quedan. Evita dejar la app sin nadie que la gobierne. */
    public static function administradoresActivos(): int
    {
        return (int) Database::pdo()
            ->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1")
            ->fetchColumn();
    }

    /**
     * Quién puede ser RESPONSABLE de un evento de esa área: la gente activa del área, más los
     * administradores, que no tienen área y responden por cualquier evento.
     *
     * Sin área conocida (un admin creando un evento y todavía sin elegirla) la lista son TODOS
     * los activos: si se limitara a los del área, el admin no vería a nadie y no podría asignar
     * responsable a la primera. El área real se valida igual al guardar.
     *
     * @return list<array{id:int,nombre:string,rol:string,area_nombre:?string}>
     */
    public static function duenosPosibles(?int $areaId): array
    {
        if ($areaId === null) {
            return Database::pdo()->query(
                "SELECT u.id, u.nombre, u.rol, c.valor AS area_nombre FROM usuarios u
                   LEFT JOIN catalogo_valores c ON c.id = u.area_id AND c.campo = 'area'
                  WHERE u.activo = 1
               ORDER BY u.rol = 'admin' DESC, c.valor, u.nombre"
            )->fetchAll();
        }
        $st = Database::pdo()->prepare(
            "SELECT u.id, u.nombre, u.rol, c.valor AS area_nombre FROM usuarios u
               LEFT JOIN catalogo_valores c ON c.id = u.area_id AND c.campo = 'area'
              WHERE u.activo = 1 AND (u.rol = 'admin' OR u.area_id = ?)
           ORDER BY u.rol = 'admin', u.nombre"
        );
        $st->execute([$areaId]);
        return $st->fetchAll();
    }

    /**
     * Segunda barrera, contra la base: el responsable existe, está activo y pertenece al área del
     * evento. Salta sobre todo cuando un ADMIN mueve un evento de un área a otra y el responsable
     * se queda fuera; un usuario normal no puede cambiar el área del evento.
     *
     * @return array<string,string> vacío si está bien
     */
    public static function errorDueno(int $duenoId, ?int $areaId): array
    {
        foreach (self::duenosPosibles($areaId) as $d) {
            if ((int) $d['id'] === $duenoId) {
                return [];
            }
        }
        return ['dueno_id' => 'Esa persona no pertenece al área del evento. Elige a otra como responsable.'];
    }

    /**
     * Toda la gente activa, para los filtros de persona (calendario, lista, disponibilidad) y para
     * la línea de tiempo. Los administradores van primero, que son los que no tienen área.
     *
     * @return list<array{id:int,nombre:string,rol:string,area_id:?int,area_nombre:?string}>
     */
    public static function activos(): array
    {
        return Database::pdo()->query(
            "SELECT u.id, u.nombre, u.rol, u.area_id, c.valor AS area_nombre FROM usuarios u
               LEFT JOIN catalogo_valores c ON c.id = u.area_id AND c.campo = 'area'
              WHERE u.activo = 1
           ORDER BY c.valor IS NULL DESC, c.valor, u.nombre"
        )->fetchAll();
    }
}
