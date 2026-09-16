<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Campos;
use App\Core\Database;
use App\Core\Normalizador;
use DateTime;
use PDO;
use RuntimeException;

/** Eventos del cronograma: CRUD con borrado lógico, catálogos resueltos y bitácora. */
final class Evento
{
    public const COLS_SIMPLES = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'objetivo', 'resultados', 'alianzas', 'observaciones', 'contactos_url', 'evidencia_url', 'reuniones', 'tipo_accion_otro', 'segmento_otro'];

    private const SELECT = 'SELECT e.*,
            ta.valor AS tipo_accion, se.valor AS segmento, ar.valor AS area, ar.color AS area_color,
            li.valor AS linea_estrategica, pa.valor AS pais, ci.valor AS ciudad, me.valor AS mercado, org.valor AS organizador,
            COALESCE(ac.nombre, CONCAT(\'Usuario #\', e.creado_por)) AS creado_por_nombre
        FROM eventos e
        JOIN catalogo_valores ta  ON ta.id  = e.tipo_accion_id
        JOIN catalogo_valores se  ON se.id  = e.segmento_id
        JOIN catalogo_valores ar  ON ar.id  = e.area_id
        JOIN catalogo_valores li  ON li.id  = e.linea_id
        JOIN catalogo_valores pa  ON pa.id  = e.pais_id
        JOIN catalogo_valores ci  ON ci.id  = e.ciudad_id
        JOIN catalogo_valores me  ON me.id  = e.mercado_id
        JOIN catalogo_valores org ON org.id = e.organizador_id
        LEFT JOIN usuarios ac ON ac.id = e.creado_por';

    /** Joins mínimos para COUNT/GROUP con los mismos filtros (q usa ci y org). */
    private const FROM_CORTO = 'FROM eventos e
        JOIN catalogo_valores ar  ON ar.id  = e.area_id
        JOIN catalogo_valores ci  ON ci.id  = e.ciudad_id
        JOIN catalogo_valores org ON org.id = e.organizador_id';

    /** @param array<string,string> $datos salida de Validator::evento()['datos'] */
    public static function crear(array $datos, int $usuarioId): int
    {
        return Database::transaccion(function (PDO $pdo) use ($datos, $usuarioId): int {
            $ids = self::resolverCatalogos($datos, $usuarioId);
            $cols = array_merge(self::COLS_SIMPLES, array_values(Campos::COLUMNA), ['creado_por', 'actualizado_por']);
            $vals = [];
            foreach (self::COLS_SIMPLES as $c) {
                // Las columnas de detalle de "Otros" solo llegan cuando aplican; el resto
                // de quien llama (formulario o guion) puede omitirlas sin romper el INSERT.
                $vals[] = $datos[$c] ?? '';
            }
            foreach (Campos::COLUMNA as $col) {
                $vals[] = $ids[$col];
            }
            $vals[] = $usuarioId;
            $vals[] = $usuarioId;
            $pdo->prepare('INSERT INTO eventos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
                ->execute($vals);
            $id = (int) $pdo->lastInsertId();
            Historial::registrar($id, $usuarioId, 'crear', ['despues' => self::resumen((array) self::porId($id))]);
            return $id;
        });
    }

    public static function actualizar(int $id, array $datos, int $usuarioId): void
    {
        Database::transaccion(function (PDO $pdo) use ($id, $datos, $usuarioId): void {
            $antes = self::porId($id);
            if (!$antes) {
                throw new RuntimeException('Evento no encontrado.');
            }
            $ids = self::resolverCatalogos($datos, $usuarioId);   // +1 a los valores nuevos
            foreach (Campos::COLUMNA as $col) {                    // -1 a los anteriores (neto 0 si no cambió)
                Catalogo::decrementar((int) $antes[$col]);
            }
            $set = [];
            $vals = [];
            foreach (self::COLS_SIMPLES as $c) {
                $set[] = "$c = ?";
                $vals[] = $datos[$c] ?? '';
            }
            foreach (Campos::COLUMNA as $col) {
                $set[] = "$col = ?";
                $vals[] = $ids[$col];
            }
            $set[] = 'actualizado_por = ?';
            $vals[] = $usuarioId;
            $vals[] = $id;
            $pdo->prepare('UPDATE eventos SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
            $cambios = self::diferencias($antes, (array) self::porId($id));
            if ($cambios) {
                Historial::registrar($id, $usuarioId, 'editar', $cambios);
            }
        });
    }

    /** Borrado lógico (papelera) con motivo obligatorio (3–500). Un admin puede restaurarlo desde Admin › Eliminados. */
    public static function eliminar(int $id, int $usuarioId, string $motivo = ''): void
    {
        $motivo = Normalizador::limpiar($motivo);
        $len = mb_strlen($motivo);
        if ($len < 3 || $len > 500) {
            throw new RuntimeException('Escribe el motivo de la eliminación (entre 3 y 500 caracteres).');
        }
        Database::transaccion(function (PDO $pdo) use ($id, $usuarioId, $motivo): void {
            $ev = self::porId($id);
            if (!$ev) {
                throw new RuntimeException('Evento no encontrado.');
            }
            $pdo->prepare('UPDATE eventos SET eliminado_en = NOW(), eliminado_por = ?, eliminacion_motivo = ?, actualizado_por = ? WHERE id = ?')
                ->execute([$usuarioId, $motivo, $usuarioId, $id]);
            foreach (Campos::COLUMNA as $col) {
                Catalogo::decrementar((int) $ev[$col]);
            }
            Historial::registrar($id, $usuarioId, 'eliminar', ['motivo' => $motivo, 'antes' => self::resumen($ev)]);
        });
    }

    /** Papelera: eventos con eliminado_en, del más reciente al más antiguo, con quién los eliminó y por qué. */
    public static function eliminados(int $limite = 200): array
    {
        $lim = max(1, min(1000, $limite));
        $st = Database::pdo()->prepare('SELECT e.id, e.nombre, e.fecha_inicio, e.fecha_fin, e.estado, e.eliminado_en, e.eliminacion_motivo, e.eliminado_por,
                ar.valor AS area, ar.color AS area_color, ci.valor AS ciudad,
                COALESCE(ac.nombre, CONCAT(\'Usuario #\', e.creado_por)) AS creado_por_nombre,
                COALESCE(el.nombre, CONCAT(\'Usuario #\', e.eliminado_por)) AS eliminado_por_nombre
            FROM eventos e
            JOIN catalogo_valores ar ON ar.id = e.area_id
            JOIN catalogo_valores ci ON ci.id = e.ciudad_id
            LEFT JOIN usuarios ac ON ac.id = e.creado_por
            LEFT JOIN usuarios el ON el.id = e.eliminado_por
            WHERE e.eliminado_en IS NOT NULL
            ORDER BY e.eliminado_en DESC, e.id DESC LIMIT ' . $lim);
        $st->execute();
        return $st->fetchAll();
    }

    public static function restaurar(int $id, int $usuarioId): void
    {
        Database::transaccion(function (PDO $pdo) use ($id, $usuarioId): void {
            $ev = self::porId($id, true);
            if (!$ev || $ev['eliminado_en'] === null) {
                throw new RuntimeException('El evento no está eliminado.');
            }
            $pdo->prepare('UPDATE eventos SET eliminado_en = NULL, eliminado_por = NULL, eliminacion_motivo = NULL, actualizado_por = ? WHERE id = ?')->execute([$usuarioId, $id]);
            foreach (Campos::COLUMNA as $col) {
                Catalogo::incrementar((int) $ev[$col]);
            }
            Historial::registrar($id, $usuarioId, 'restaurar', ['motivo_eliminacion' => (string) ($ev['eliminacion_motivo'] ?? '')]);
        });
    }

    /** Cancela (estado manual reversible) con motivo obligatorio; el evento sigue vivo y sus usos no cambian. */
    public static function cancelar(int $id, string $motivo, int $usuarioId): void
    {
        $motivo = Normalizador::limpiar($motivo);
        $len = mb_strlen($motivo);
        if ($len < 3 || $len > 500) {
            throw new RuntimeException('Escribe el motivo de la cancelación (entre 3 y 500 caracteres).');
        }
        Database::transaccion(function (PDO $pdo) use ($id, $motivo, $usuarioId): void {
            $ev = self::porId($id);
            if (!$ev) {
                throw new RuntimeException('Evento no encontrado.');
            }
            if ($ev['estado'] === 'cancelado') {
                throw new RuntimeException('El evento ya está cancelado.');
            }
            $pdo->prepare("UPDATE eventos SET estado = 'cancelado', cancelacion_motivo = ?, actualizado_por = ? WHERE id = ?")->execute([$motivo, $usuarioId, $id]);
            Historial::registrar($id, $usuarioId, 'cancelar', ['estado_previo' => $ev['estado'], 'motivo' => $motivo]);
        });
    }

    /** Deshace la cancelación: vuelve al estado que tenía (guardado en el historial) y borra el motivo. */
    public static function reanudar(int $id, int $usuarioId): void
    {
        Database::transaccion(function (PDO $pdo) use ($id, $usuarioId): void {
            $ev = self::porId($id);
            if (!$ev) {
                throw new RuntimeException('Evento no encontrado.');
            }
            if ($ev['estado'] !== 'cancelado') {
                throw new RuntimeException('El evento no está cancelado.');
            }
            $previo = 'no_realizado';
            foreach (Historial::listar(50, $id) as $h) {
                if ($h['accion'] === 'cancelar') {
                    $previo = (string) ($h['cambios']['estado_previo'] ?? 'no_realizado');
                    break;
                }
            }
            if (!isset(Campos::ESTADOS_FORMULARIO[$previo])) {
                $previo = 'no_realizado';
            }
            $pdo->prepare('UPDATE eventos SET estado = ?, cancelacion_motivo = NULL, actualizado_por = ? WHERE id = ?')->execute([$previo, $usuarioId, $id]);
            Historial::registrar($id, $usuarioId, 'reanudar', ['estado' => $previo]);
        });
    }

    /** Cambio rápido entre los tres estados manuales. No aplica a cancelados (primero "Reanudar"); sin cambio si es el mismo. */
    public static function cambiarEstado(int $id, string $estado, int $usuarioId): void
    {
        if (!isset(Campos::ESTADOS_FORMULARIO[$estado])) {
            throw new RuntimeException('Estado inválido.');
        }
        Database::transaccion(function (PDO $pdo) use ($id, $estado, $usuarioId): void {
            $ev = self::porId($id);
            if (!$ev) {
                throw new RuntimeException('Evento no encontrado.');
            }
            if ($ev['estado'] === 'cancelado') {
                throw new RuntimeException('El evento está cancelado: usa "Reanudar" antes de cambiar su estado.');
            }
            if ($ev['estado'] === $estado) {
                return;
            }
            $pdo->prepare('UPDATE eventos SET estado = ?, actualizado_por = ? WHERE id = ?')->execute([$estado, $usuarioId, $id]);
            Historial::registrar($id, $usuarioId, 'editar', ['estado' => ['antes' => $ev['estado'], 'despues' => $estado]]);
        });
    }

    public static function porId(int $id, bool $conEliminados = false): ?array
    {
        $st = Database::pdo()->prepare(self::SELECT . ' WHERE e.id = ?' . ($conEliminados ? '' : ' AND e.eliminado_en IS NULL'));
        $st->execute([$id]);
        $f = $st->fetch();
        return $f ?: null;
    }

    /** Eventos que tocan el rango [inicio, fin] (Y-m-d, inclusive). */
    public static function enRango(string $inicio, string $fin, array $filtros = []): array
    {
        $p = [$fin, $inicio];
        $where = self::where($filtros, $p);
        $st = Database::pdo()->prepare(self::SELECT . " WHERE e.fecha_inicio <= ? AND e.fecha_fin >= ? AND $where ORDER BY e.fecha_inicio, e.fecha_fin, e.id");
        $st->execute($p);
        return $st->fetchAll();
    }

    /** @return array{filas:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $p = [];
        $where = self::where($filtros, $p);
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT COUNT(*) ' . self::FROM_CORTO . " WHERE $where");
        $st->execute($p);
        $total = (int) $st->fetchColumn();
        $porPagina = max(1, min(500, $porPagina));
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = max(1, min($pagina, $paginas));
        $off = ($pagina - 1) * $porPagina;
        $dir = self::direccion($filtros);
        $st = $pdo->prepare(self::SELECT . " WHERE $where ORDER BY e.fecha_inicio $dir, e.id $dir LIMIT $porPagina OFFSET $off");
        $st->execute($p);
        return ['filas' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /** Sin paginar (exportación CSV), en la misma dirección que la lista (`orden`). */
    public static function todos(array $filtros = []): array
    {
        $p = [];
        $where = self::where($filtros, $p);
        $dir = self::direccion($filtros);
        $st = Database::pdo()->prepare(self::SELECT . " WHERE $where ORDER BY e.fecha_inicio $dir, e.id $dir");
        $st->execute($p);
        return $st->fetchAll();
    }

    /** Próximos N días, incluyendo los que están en curso hoy. */
    public static function proximos(int $dias = 30, int $limite = 8, array $filtros = []): array
    {
        $p = [$dias];
        $where = self::where($filtros, $p);
        $lim = max(1, min(100, $limite));
        $st = Database::pdo()->prepare(self::SELECT . " WHERE e.fecha_fin >= CURDATE() AND e.fecha_inicio <= DATE_ADD(CURDATE(), INTERVAL ? DAY) AND $where ORDER BY e.fecha_inicio, e.id LIMIT $lim");
        $st->execute($p);
        return $st->fetchAll();
    }

    public static function moverFechas(int $id, string $inicio, string $fin, int $usuarioId): void
    {
        foreach ([$inicio, $fin] as $f) {
            $dt = DateTime::createFromFormat('!Y-m-d', $f);
            if (!$dt || $dt->format('Y-m-d') !== $f) {
                throw new RuntimeException('Fecha inválida.');
            }
        }
        if ($fin < $inicio) {
            throw new RuntimeException('La fecha fin no puede ser anterior al inicio.');
        }
        Database::transaccion(function (PDO $pdo) use ($id, $inicio, $fin, $usuarioId): void {
            $antes = self::porId($id);
            if (!$antes) {
                throw new RuntimeException('Evento no encontrado.');
            }
            $pdo->prepare('UPDATE eventos SET fecha_inicio = ?, fecha_fin = ?, actualizado_por = ? WHERE id = ?')->execute([$inicio, $fin, $usuarioId, $id]);
            $cambios = self::diferencias($antes, (array) self::porId($id));
            if ($cambios) {
                Historial::registrar($id, $usuarioId, 'editar', $cambios);
            }
        });
    }

    /** @return int[] años con eventos (desc), siempre incluye el actual */
    public static function anios(): array
    {
        $r = Database::pdo()->query('SELECT DISTINCT YEAR(fecha_inicio) a FROM eventos WHERE eliminado_en IS NULL ORDER BY a DESC')->fetchAll(PDO::FETCH_COLUMN);
        $r = array_map('intval', $r);
        if (!in_array((int) date('Y'), $r, true)) {
            $r[] = (int) date('Y');
            rsort($r);
        }
        return $r;
    }

    /** Búsqueda rápida (paleta Ctrl+K). */
    public static function buscar(string $q, int $limite = 10): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = '%' . Normalizador::escaparLike($q) . '%';
        $st = Database::pdo()->prepare('SELECT e.id, e.nombre, e.fecha_inicio, e.fecha_fin, e.estado, ar.valor AS area, ar.color AS area_color, ci.valor AS ciudad '
            . self::FROM_CORTO . ' WHERE e.eliminado_en IS NULL AND (e.nombre LIKE ? OR ci.valor LIKE ? OR org.valor LIKE ? OR e.objetivo LIKE ?)
              ORDER BY e.fecha_inicio DESC LIMIT ' . max(1, min(30, $limite)));
        $st->execute([$like, $like, $like, $like]);
        return $st->fetchAll();
    }

    /**
     * Eventos vivos que se llaman EXACTAMENTE igual que $nombre.
     *
     * Sirve para avisar de un posible duplicado, nunca para impedirlo: hay
     * eventos que se repiten a propósito (un comité mensual, un taller
     * semanal). La comparación la resuelve el cotejamiento utf8mb4_unicode_ci
     * de la columna, que ya ignora mayúsculas y tildes; aquí solo se limpian
     * los espacios sobrantes.
     */
    public static function mismoNombre(string $nombre, ?int $excluir = null): array
    {
        $nombre = Normalizador::limpiar($nombre);
        if ($nombre === '') {
            return [];
        }
        $sql = 'SELECT e.id, e.nombre, e.fecha_inicio, e.fecha_fin, e.estado, ar.valor AS area '
            . self::FROM_CORTO . ' WHERE e.eliminado_en IS NULL AND e.nombre = ?';
        $p = [$nombre];
        if ($excluir) {
            $sql .= ' AND e.id <> ?';
            $p[] = $excluir;
        }
        $st = Database::pdo()->prepare($sql . ' ORDER BY e.fecha_inicio LIMIT 10');
        $st->execute($p);
        return $st->fetchAll();
    }

    /**
     * Mapa de calor por día de un año, con los mismos filtros que el calendario.
     * Cuenta los eventos ACTIVOS cada día (los que empiezan antes y terminan después también cuentan),
     * que es lo que muestra la carga real del equipo; con $modo='inicio' cuenta solo el día de arranque.
     * Devuelve los 12 meses ya maquetados en celdas de 7 columnas (null = relleno antes del día 1).
     */
    public static function mapaCalor(int $anio, array $filtros = [], string $modo = 'activos', bool $conCancelados = false): array
    {
        $ini = sprintf('%04d-01-01', $anio);
        $fin = sprintf('%04d-12-31', $anio);
        // El año lo fija el rango; desde/hasta no aplican a esta vista.
        $f = array_diff_key($filtros, ['anio' => 1, 'desde' => 1, 'hasta' => 1]);

        $porDia = [];
        $usados = [];
        // Los cancelados se ocultan salvo que se pidan a propósito con el filtro de estado.
        $verCancelados = $conCancelados || str_contains((string) ($f['estado'] ?? ''), 'cancelado');
        foreach (self::enRango($ini, $fin, $f) as $e) {
            if (!$verCancelados && $e['estado'] === 'cancelado') {
                continue;
            }
            $desde = max($e['fecha_inicio'], $ini);
            $hasta = min($e['fecha_fin'], $fin);
            if ($modo === 'inicio') {
                if ($e['fecha_inicio'] < $ini || $e['fecha_inicio'] > $fin) {
                    continue;
                }
                $desde = $hasta = $e['fecha_inicio'];
            }
            $usados[(int) $e['id']] = true;
            $d = new DateTime($desde);
            $tope = new DateTime($hasta);
            while ($d <= $tope) {
                $k = $d->format('Y-m-d');
                $porDia[$k]['n'] = ($porDia[$k]['n'] ?? 0) + 1;
                $areaEv = (int) $e['area_id'];
                $porDia[$k]['areas'][$areaEv] = ($porDia[$k]['areas'][$areaEv] ?? 0) + 1;
                if (count($porDia[$k]['nombres'] ?? []) < 8) {
                    $porDia[$k]['nombres'][] = $e['nombre'];
                }
                $d->modify('+1 day');
            }
        }

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $primero = new DateTime(sprintf('%04d-%02d-01', $anio, $m));
            $celdas = array_fill(0, ((int) $primero->format('N')) - 1, null);   // lunes = primera columna
            $ultimo = (int) $primero->format('t');
            for ($dia = 1; $dia <= $ultimo; $dia++) {
                $k = sprintf('%04d-%02d-%02d', $anio, $m, $dia);
                $celdas[] = [
                    'f'       => $k,
                    'd'       => $dia,
                    'n'       => (int) ($porDia[$k]['n'] ?? 0),
                    'areas'   => $porDia[$k]['areas'] ?? [],
                    'nombres' => $porDia[$k]['nombres'] ?? [],
                ];
            }
            $meses[] = ['mes' => $m, 'nombre' => Campos::MESES[$m], 'celdas' => $celdas];
        }

        $conteos = array_map(static fn(array $x): int => (int) $x['n'], $porDia);
        arsort($conteos);
        $diaPico = $conteos ? array_key_first($conteos) : null;
        $semanas = [];
        foreach ($conteos as $fecha => $n) {
            $sem = (new DateTime($fecha))->format('o-W');
            $semanas[$sem] = ($semanas[$sem] ?? 0) + $n;
        }
        arsort($semanas);
        // Los 7 días de la semana más cargada, para poder resaltarla entera en el mapa.
        $semanaPico = $semanas ? array_key_first($semanas) : null;
        $semanaDias = [];
        if ($semanaPico !== null) {
            [$sAnio, $sSemana] = array_map('intval', explode('-', $semanaPico));
            $lunes = (new DateTime())->setISODate($sAnio, $sSemana);
            for ($i = 0; $i < 7; $i++) {
                $semanaDias[] = $lunes->format('Y-m-d');
                $lunes->modify('+1 day');
            }
        }
        $diasActivos = array_keys($porDia);
        sort($diasActivos);

        return [
            'anio'  => $anio,
            'modo'  => $modo,
            'max'   => $conteos ? max($conteos) : 0,
            'meses' => $meses,
            'resumen' => [
                'acciones'       => count($usados),
                'dias_con_algo'  => count($porDia),
                'dia_pico'       => $diaPico,
                'dia_pico_n'     => $diaPico !== null ? $conteos[$diaPico] : 0,
                'semana_pico'      => $semanaPico,
                'semana_pico_n'    => $semanas ? reset($semanas) : 0,
                'semana_pico_dias' => $semanaDias,
                'dias_activos'     => $diasActivos,
            ],
        ];
    }

    /** Conteos por área y por estado con los filtros dados (barra lateral). */
    public static function conteos(array $filtros = []): array
    {
        $p = [];
        $where = self::where($filtros, $p);
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT e.area_id AS id, ar.valor, ar.color, COUNT(*) AS n ' . self::FROM_CORTO . " WHERE $where GROUP BY e.area_id, ar.valor, ar.color ORDER BY n DESC, ar.valor");
        $st->execute($p);
        $areas = array_map(static fn(array $f): array => ['id' => (int) $f['id'], 'valor' => $f['valor'], 'color' => $f['color'], 'n' => (int) $f['n']], $st->fetchAll());
        $st = $pdo->prepare('SELECT e.estado, COUNT(*) AS n ' . self::FROM_CORTO . " WHERE $where GROUP BY e.estado ORDER BY e.estado");
        $st->execute($p);
        $estados = [];
        foreach ($st->fetchAll() as $f) {
            $estados[$f['estado']] = (int) $f['n'];
        }
        return ['areas' => $areas, 'estados' => $estados];
    }

    // ---------- internos ----------

    private static function where(array $f, array &$p): string
    {
        $w = ['e.eliminado_en IS NULL'];
        if (!empty($f['anio'])) {
            $w[] = 'YEAR(e.fecha_inicio) = ?';
            $p[] = (int) $f['anio'];
        }
        // Estos filtros aceptan una o varias opciones ("18" o "18,19,21").
        foreach (['area_id', 'tipo_accion_id', 'segmento_id'] as $c) {
            if (empty($f[$c])) {
                continue;
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $f[$c])))));
            if (!$ids) {
                continue;
            }
            if (count($ids) === 1) {
                $w[] = "e.$c = ?";
                $p[] = $ids[0];
            } else {
                $w[] = "e.$c IN (" . implode(', ', array_fill(0, count($ids), '?')) . ')';
                array_push($p, ...$ids);
            }
        }
        if (!empty($f['estado'])) {
            $estadosF = array_values(array_unique(array_filter(
                array_map('trim', explode(',', (string) $f['estado'])),
                static fn(string $e): bool => isset(Campos::ESTADOS[$e])
            )));
            if (count($estadosF) === 1) {
                $w[] = 'e.estado = ?';
                $p[] = $estadosF[0];
            } elseif ($estadosF) {
                $w[] = 'e.estado IN (' . implode(', ', array_fill(0, count($estadosF), '?')) . ')';
                array_push($p, ...$estadosF);
            }
        }
        if (!empty($f['creado_por'])) {
            $w[] = 'e.creado_por = ?';
            $p[] = (int) $f['creado_por'];
        }
        if (!empty($f['q'])) {
            $w[] = '(e.nombre LIKE ? OR ci.valor LIKE ? OR org.valor LIKE ? OR e.objetivo LIKE ?)';
            $like = '%' . Normalizador::escaparLike((string) $f['q']) . '%';
            array_push($p, $like, $like, $like, $like);
        }
        if (!empty($f['desde'])) {
            $w[] = 'e.fecha_fin >= ?';
            $p[] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $w[] = 'e.fecha_inicio <= ?';
            $p[] = $f['hasta'];
        }
        return implode(' AND ', $w);
    }

    /** Dirección de orden por fecha: `asc` (del más cercano al más lejano, por defecto) o `desc`. */
    private static function direccion(array $filtros): string
    {
        return (($filtros['orden'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
    }

    /** @return array<string,int> columna → id de catálogo (suma un uso a cada valor) */
    private static function resolverCatalogos(array $datos, int $usuarioId): array
    {
        $ids = [];
        foreach (Campos::COLUMNA as $campo => $col) {
            $ids[$col] = Catalogo::resolver($campo, (string) $datos[$campo], $usuarioId);
        }
        return $ids;
    }

    private static function resumen(array $ev): array
    {
        $r = [];
        foreach (array_merge(self::COLS_SIMPLES, Campos::CATALOGOS) as $c) {
            $r[$c] = $ev[$c] ?? null;
        }
        return $r;
    }

    private static function diferencias(array $antes, array $despues): array
    {
        $d = [];
        foreach (array_merge(self::COLS_SIMPLES, Campos::CATALOGOS) as $c) {
            if ((string) ($antes[$c] ?? '') !== (string) ($despues[$c] ?? '')) {
                $d[$c] = ['antes' => $antes[$c] ?? null, 'despues' => $despues[$c] ?? null];
            }
        }
        return $d;
    }
}
