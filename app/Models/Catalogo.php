<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Campos;
use App\Core\Database;
use App\Core\Normalizador;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** Catálogos que aprenden: cada campo guarda lo que la gente escribe y cuántas veces se usa (spec §4). */
final class Catalogo
{
    /**
     * Hasta N sugerencias activas. Sin texto (al enfocar el campo): las opciones disponibles, más usadas primero,
     * sin el comodín N/A (tiene su propio botón). Con texto: primero las que empiezan por q, luego las que lo
     * contienen; más usadas primero.
     */
    public static function sugerencias(string $campo, string $q, int $limite = 12): array
    {
        self::validarCampo($campo);
        $n = Normalizador::normalizar($q);
        $limite = max(1, min(20, $limite));
        if ($n === '') {
            $st = Database::pdo()->prepare(
                "SELECT id, valor, usos FROM catalogo_valores
                 WHERE campo = ? AND activo = 1 AND valor_norm <> 'n/a'
                 ORDER BY usos DESC, valor ASC
                 LIMIT $limite"
            );
            $st->execute([$campo]);
        } else {
            $esc = Normalizador::escaparLike($n);
            $st = Database::pdo()->prepare(
                "SELECT id, valor, usos FROM catalogo_valores
                 WHERE campo = ? AND activo = 1 AND valor_norm <> 'n/a' AND valor_norm LIKE ?
                 ORDER BY (valor_norm LIKE ?) DESC, usos DESC, valor ASC
                 LIMIT $limite"
            );
            $st->execute([$campo, "%$esc%", "$esc%"]);
        }
        return array_map(
            static fn(array $f): array => ['id' => (int) $f['id'], 'valor' => $f['valor'], 'usos' => (int) $f['usos']],
            $st->fetchAll()
        );
    }

    public static function buscarPorNorm(string $campo, string $norm): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM catalogo_valores WHERE campo = ? AND valor_norm = ?');
        $st->execute([$campo, $norm]);
        $f = $st->fetch();
        return $f ?: null;
    }

    /** Devuelve el id del valor: si existe (misma clave normalizada) suma un uso; si no, lo crea con usos=1. */
    public static function resolver(string $campo, string $valor, ?int $usuarioId = null): int
    {
        self::validarCampo($campo);
        $valor = Normalizador::limpiar($valor);
        if ($valor === '') {
            throw new InvalidArgumentException("Valor vacío para el campo $campo.");
        }
        $norm = Normalizador::normalizar($valor);
        $pdo = Database::pdo();
        $fila = self::buscarPorNorm($campo, $norm);
        if ($fila) {
            return self::incrementarReactivando($fila);
        }
        $color = $campo === 'area' ? self::siguienteColorArea() : null;
        try {
            $pdo->prepare('INSERT INTO catalogo_valores (campo, valor, valor_norm, usos, activo, color, creado_por) VALUES (?, ?, ?, 1, 1, ?, ?)')
                ->execute([$campo, $valor, $norm, $color, $usuarioId]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            // Carrera: otro request lo insertó entre el SELECT y el INSERT.
            $fila = self::buscarPorNorm($campo, $norm);
            if ($fila) {
                return self::incrementarReactivando($fila);
            }
            throw $e;
        }
    }

    /** Suma un uso; si el valor estaba desactivado (spec §4), lo reactiva en la misma sentencia. */
    private static function incrementarReactivando(array $fila): int
    {
        $id = (int) $fila['id'];
        if ((int) $fila['activo'] === 0) {
            Database::pdo()->prepare('UPDATE catalogo_valores SET usos = usos + 1, activo = 1 WHERE id = ?')->execute([$id]);
        } else {
            self::incrementar($id);
        }
        return $id;
    }

    /** Valores activos parecidos (Levenshtein ≤ 2, longitud ≥ 5) para "¿Quisiste decir…?". */
    public static function similares(string $campo, string $valor): array
    {
        self::validarCampo($campo);
        if (mb_strlen(Normalizador::normalizar($valor)) < 5) {
            return [];
        }
        $st = Database::pdo()->prepare('SELECT id, valor, valor_norm FROM catalogo_valores WHERE campo = ? AND activo = 1');
        $st->execute([$campo]);
        return Normalizador::similares($valor, $st->fetchAll());
    }

    /**
     * Para cada catálogo cuyo valor NO existe aún y se parece a uno existente, devuelve hasta 3 candidatos.
     * @param array<string,string> $datos        salida de Validator (catálogos como texto)
     * @param array<string,mixed>  $confirmados  confirmar[campo]=1 → el usuario ya dijo "mantener el mío"
     * @return array<string,string[]>
     */
    public static function parecidosPendientes(array $datos, array $confirmados): array
    {
        $out = [];
        foreach (Campos::CATALOGOS as $c) {
            $valor = (string) ($datos[$c] ?? '');
            if ($valor === '' || $valor === 'N/A' || !empty($confirmados[$c])) {
                continue;
            }
            if (self::buscarPorNorm($c, Normalizador::normalizar($valor))) {
                continue;
            }
            $sim = self::similares($c, $valor);
            if ($sim) {
                $out[$c] = array_slice(array_column($sim, 'valor'), 0, 3);
            }
        }
        return $out;
    }

    public static function incrementar(int $id): void
    {
        Database::pdo()->prepare('UPDATE catalogo_valores SET usos = usos + 1 WHERE id = ?')->execute([$id]);
    }

    public static function decrementar(int $id): void
    {
        Database::pdo()->prepare('UPDATE catalogo_valores SET usos = GREATEST(usos - 1, 0) WHERE id = ?')->execute([$id]);
    }

    public static function valor(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM catalogo_valores WHERE id = ?');
        $st->execute([$id]);
        $f = $st->fetch();
        return $f ?: null;
    }

    public static function listar(?string $campo = null, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM catalogo_valores WHERE 1 = 1';
        $p = [];
        if ($campo !== null) {
            self::validarCampo($campo);
            $sql .= ' AND campo = ?';
            $p[] = $campo;
        }
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY campo, usos DESC, valor';
        $st = Database::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    /** Áreas activas con su color (chips, filtros, leyenda). */
    public static function areas(): array
    {
        return self::listar('area', true);
    }

    /** Opciones de un desplegable de lista cerrada: activas, alfabéticas y sin los comodines N/A y Otros. */
    public static function opciones(string $campo): array
    {
        self::validarCampo($campo);
        $st = Database::pdo()->prepare(
            "SELECT valor FROM catalogo_valores
             WHERE campo = ? AND activo = 1 AND valor_norm NOT IN ('n/a', 'otros')
             ORDER BY valor ASC"
        );
        $st->execute([$campo]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** @return array<string,string[]> opciones de cada campo de lista cerrada, para pintar el formulario */
    public static function opcionesCerradas(): array
    {
        $out = [];
        foreach (Campos::CERRADOS as $campo) {
            $out[$campo] = self::opciones($campo);
        }
        return $out;
    }

    /**
     * Comprueba que cada valor de lista cerrada exista y siga activo. Es lo que impide que alguien
     * invente un valor nuevo desde el formulario: solo un admin los crea desde Admin › Catálogos.
     *
     * @param  array<string,string> $datos  salida de Validator::evento()['datos']
     * @return array<string,string>         errores por campo; vacío si todo está bien
     */
    public static function erroresCerrados(array $datos): array
    {
        $e = [];
        foreach (Campos::CERRADOS as $campo) {
            $valor = (string) ($datos[$campo] ?? '');
            if ($valor === '') {
                continue;   // el campo vacío ya lo reporta Validator
            }
            $fila = self::buscarPorNorm($campo, Normalizador::normalizar($valor));
            if (!$fila || (int) $fila['activo'] !== 1) {
                $e[$campo] = 'Elige una opción de la lista: «' . $valor . '» no está disponible.';
            }
        }
        // Los mercados adicionales pasan por la misma barrera que el principal.
        foreach ((array) ($datos['mercados_extra'] ?? []) as $v) {
            $v = (string) $v;
            if ($v === '') {
                continue;
            }
            $fila = self::buscarPorNorm('mercado', Normalizador::normalizar($v));
            if (!$fila || (int) $fila['activo'] !== 1) {
                $e['mercado'] = 'Elige opciones de la lista: «' . $v . '» no está disponible.';
            }
        }
        return $e;
    }

    public static function renombrar(int $id, string $nuevo): void
    {
        $nuevo = Normalizador::limpiar($nuevo);
        if ($nuevo === '') {
            throw new InvalidArgumentException('El nuevo nombre no puede estar vacío.');
        }
        $fila = self::valor($id);
        if (!$fila) {
            throw new RuntimeException('Valor no encontrado.');
        }
        self::exigirNoNA($fila, 'renombrar');
        $norm = Normalizador::normalizar($nuevo);
        $otro = self::buscarPorNorm($fila['campo'], $norm);
        if ($otro && (int) $otro['id'] !== $id) {
            throw new RuntimeException('Ya existe "' . $otro['valor'] . '" en este catálogo; usa "Unir" en vez de renombrar.');
        }
        Database::pdo()->prepare('UPDATE catalogo_valores SET valor = ?, valor_norm = ? WHERE id = ?')->execute([$nuevo, $norm, $id]);
    }

    /**
     * Mueve los eventos del origen al destino, suma los usos y desactiva el origen.
     * Si el campo es 'area', también reasigna a los usuarios (usuarios.area_id) del origen al destino,
     * para que unir un área no deje a nadie apuntando a un valor desactivado.
     */
    public static function unir(int $origenId, int $destinoId): void
    {
        if ($origenId === $destinoId) {
            throw new InvalidArgumentException('Origen y destino son el mismo valor.');
        }
        $o = self::valor($origenId);
        $d = self::valor($destinoId);
        if (!$o || !$d) {
            throw new RuntimeException('Valor no encontrado.');
        }
        if ($o['campo'] !== $d['campo']) {
            throw new RuntimeException('Solo se pueden unir valores del mismo campo.');
        }
        self::exigirNoNA($o, 'unir');
        self::exigirNoNA($d, 'unir');
        $col = Campos::COLUMNA[$o['campo']];
        $usosOrigen = (int) $o['usos'];
        $esArea = $o['campo'] === 'area';
        Database::transaccion(static function (PDO $pdo) use ($col, $origenId, $destinoId, $usosOrigen, $esArea): void {
            $pdo->prepare("UPDATE eventos SET $col = ? WHERE $col = ?")->execute([$destinoId, $origenId]);
            if ($esArea) {
                $pdo->prepare('UPDATE usuarios SET area_id = ? WHERE area_id = ?')->execute([$destinoId, $origenId]);
            }
            $pdo->prepare('UPDATE catalogo_valores SET usos = usos + ? WHERE id = ?')->execute([$usosOrigen, $destinoId]);
            $pdo->prepare('UPDATE catalogo_valores SET usos = 0, activo = 0 WHERE id = ?')->execute([$origenId]);
        });
    }

    public static function activar(int $id, bool $activo): void
    {
        $fila = self::valor($id);
        if (!$fila) {
            throw new RuntimeException('Valor no encontrado.');
        }
        self::exigirNoNA($fila, $activo ? 'activar' : 'desactivar');
        if (!$activo && $fila['campo'] === 'area') {
            $st = Database::pdo()->prepare('SELECT COUNT(*) FROM usuarios WHERE area_id = ? AND activo = 1');
            $st->execute([$id]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                throw new RuntimeException("No se puede desactivar: hay $n usuarios con esta área. Reasígnalos desde Admin › Accesos o usa Unir.");
            }
        }
        Database::pdo()->prepare('UPDATE catalogo_valores SET activo = ? WHERE id = ?')->execute([$activo ? 1 : 0, $id]);
    }

    /** Color de un área (#RRGGBB). */
    public static function color(int $id, string $hex): void
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            throw new InvalidArgumentException('Color inválido.');
        }
        // MariaDB reporta 0 filas afectadas si el color no cambia; por eso se valida con un SELECT
        // previo (existe y es del campo 'area') en vez de mirar rowCount() tras el UPDATE.
        $fila = self::valor($id);
        if (!$fila || $fila['campo'] !== 'area') {
            throw new RuntimeException('Área no encontrada.');
        }
        Database::pdo()->prepare("UPDATE catalogo_valores SET color = ? WHERE id = ? AND campo = 'area'")->execute([strtoupper($hex), $id]);
    }

    /** usos = cantidad real de eventos vivos que apuntan a cada valor. */
    public static function recalcularUsos(): void
    {
        $pdo = Database::pdo();
        foreach (Campos::COLUMNA as $campo => $col) {
            $pdo->prepare("UPDATE catalogo_valores c SET c.usos = (SELECT COUNT(*) FROM eventos e WHERE e.eliminado_en IS NULL AND e.$col = c.id) WHERE c.campo = ?")
                ->execute([$campo]);
        }
    }

    private static function siguienteColorArea(): string
    {
        $n = (int) Database::pdo()->query("SELECT COUNT(*) FROM catalogo_valores WHERE campo = 'area' AND valor_norm <> 'n/a'")->fetchColumn();
        return Campos::PALETA_AREAS[$n % count(Campos::PALETA_AREAS)];
    }

    /** El valor N/A es el respaldo del que dependen los 21 campos obligatorios: nunca se toca. */
    private static function exigirNoNA(array $fila, string $accion): void
    {
        if ($fila['valor_norm'] === 'n/a') {
            throw new RuntimeException('El valor N/A no se puede ' . $accion . '.');
        }
    }

    private static function validarCampo(string $campo): void
    {
        if (!in_array($campo, Campos::CATALOGOS, true)) {
            throw new InvalidArgumentException('Campo de catálogo inválido: ' . $campo);
        }
    }
}
