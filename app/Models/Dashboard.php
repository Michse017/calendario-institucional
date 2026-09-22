<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Indicadores de la hoja "Dashboard" del Excel sobre eventos vivos de un año.
 * Los cancelados cuentan en el total y en la serie mensual, pero no en KPIs ni conteos.
 */
final class Dashboard
{
    public static function indicadores(int $anio): array
    {
        $pdo = Database::pdo();
        $q = static function (string $sql) use ($pdo, $anio): array {
            $st = $pdo->prepare($sql);
            $st->execute([$anio]);
            return $st->fetchAll();
        };
        $vivos = 'e.eliminado_en IS NULL AND YEAR(e.fecha_inicio) = ?';
        $activos = $vivos . " AND e.estado <> 'cancelado'";

        $totales = $q("SELECT COUNT(*) AS total,
                COALESCE(SUM(e.estado = 'realizado'), 0) AS realizados,
                COALESCE(SUM(e.estado = 'en_ejecucion'), 0) AS en_ejecucion,
                COALESCE(SUM(e.estado = 'no_realizado'), 0) AS no_realizados,
                COALESCE(SUM(e.estado = 'cancelado'), 0) AS cancelados,
                COUNT(DISTINCT CASE WHEN e.estado <> 'cancelado' AND pa.valor_norm <> 'n/a' THEN e.pais_id END) AS paises,
                COUNT(DISTINCT CASE WHEN e.estado <> 'cancelado' AND ci.valor_norm <> 'n/a' THEN e.ciudad_id END) AS ciudades,
                COALESCE(SUM(e.estado <> 'cancelado' AND e.contactos_url NOT IN ('N/A', 'Pendiente')), 0) AS contactos,
                COALESCE(SUM(CASE WHEN e.estado <> 'cancelado' AND e.reuniones REGEXP '^[0-9]+$' THEN CAST(e.reuniones AS UNSIGNED) ELSE 0 END), 0) AS reuniones,
                COALESCE(SUM(e.estado <> 'cancelado' AND e.alianzas <> 'N/A'), 0) AS alianzas
            FROM eventos e JOIN catalogo_valores pa ON pa.id = e.pais_id JOIN catalogo_valores ci ON ci.id = e.ciudad_id
            WHERE $vivos")[0];

        $porTipo = $q("SELECT ta.valor AS tipo, ta.valor_norm AS norm, COUNT(*) AS total, COALESCE(SUM(e.estado = 'realizado'), 0) AS realizados
            FROM eventos e JOIN catalogo_valores ta ON ta.id = e.tipo_accion_id WHERE $activos GROUP BY ta.id, ta.valor, ta.valor_norm ORDER BY total DESC");
        $porSegmento = $q("SELECT se.valor AS segmento, se.valor_norm AS norm, COUNT(*) AS total, COALESCE(SUM(e.estado = 'realizado'), 0) AS realizados
            FROM eventos e JOIN catalogo_valores se ON se.id = e.segmento_id WHERE $activos GROUP BY se.id, se.valor, se.valor_norm ORDER BY total DESC");
        $porArea = $q("SELECT ar.valor AS area, ar.color, COUNT(*) AS total
            FROM eventos e JOIN catalogo_valores ar ON ar.id = e.area_id WHERE $activos GROUP BY ar.id, ar.valor, ar.color ORDER BY total DESC");
        $porMesRaw = $q("SELECT MONTH(e.fecha_inicio) AS mes, e.estado, COUNT(*) AS n FROM eventos e WHERE $vivos GROUP BY MONTH(e.fecha_inicio), e.estado");
        // Una fila por área y mes de arranque. Se excluyen las canceladas para que los totales de fila
        // cuadren exactamente con el gráfico "Por área" que está más abajo.
        $matrizRaw = $q("SELECT ar.id AS area_id, ar.valor AS area, ar.color, MONTH(e.fecha_inicio) AS mes, COUNT(*) AS n
            FROM eventos e JOIN catalogo_valores ar ON ar.id = e.area_id WHERE $activos
            GROUP BY ar.id, ar.valor, ar.color, MONTH(e.fecha_inicio)");

        $familia = static function (array $filas, string $patron): array {
            $t = 0;
            $r = 0;
            foreach ($filas as $f) {
                if (preg_match($patron, (string) $f['norm'])) {
                    $t += (int) $f['total'];
                    $r += (int) $f['realizados'];
                }
            }
            return ['total' => $t, 'realizados' => $r];
        };
        // Indicadores por familia de actividad. Se agrupan por el valor normalizado
        // del catálogo, de modo que siguen funcionando aunque alguien añada un tipo
        // nuevo parecido ("Concierto didáctico" cuenta como concierto).
        $kpis = [
            'conciertos'  => $familia($porTipo, '/concierto/'),
            'exposiciones' => $familia($porTipo, '/exposicion/'),
            'talleres'    => $familia($porTipo, '/taller/'),
            'escenicas'   => $familia($porTipo, '/teatro|danza|funcion/'),
            'festivales'  => $familia($porTipo, '/festival/'),
            'escolar'     => $familia($porSegmento, '/comunidad educativa|infantil/'),
        ];

        $porMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $porMes[$m] = ['no_realizado' => 0, 'en_ejecucion' => 0, 'realizado' => 0, 'cancelado' => 0];
        }
        foreach ($porMesRaw as $f) {
            $porMes[(int) $f['mes']][$f['estado']] = (int) $f['n'];
        }

        return [
            'anio' => $anio,
            'matriz' => self::matriz($matrizRaw, $anio),
            'totales' => array_map('intval', $totales),
            'kpis' => $kpis,
            'por_tipo' => array_map(static fn(array $f): array => ['tipo' => $f['tipo'], 'total' => (int) $f['total'], 'realizados' => (int) $f['realizados']], $porTipo),
            'por_segmento' => array_map(static fn(array $f): array => ['segmento' => $f['segmento'], 'total' => (int) $f['total']], $porSegmento),
            'por_area' => array_map(static fn(array $f): array => ['area' => $f['area'], 'color' => $f['color'], 'total' => (int) $f['total']], $porArea),
            'por_mes' => $porMes,
        ];
    }

    /**
     * Informe de cubrimiento del año. Solo cuentan los eventos que piden cubrimiento, con su
     * responsable (no hay equipos asignados):
     *  - carga: responsables de eventos que piden cubrimiento, con cuántos y cuántos días
     *  - responsables: quién lidera más eventos del año y cuántos de ellos piden cubrimiento
     *  - por_area: eventos por área, y cuántos piden cubrimiento
     *  - semanas: personas-día por semana ISO
     *
     * @return array{carga:list<array<string,mixed>>, responsables:list<array<string,mixed>>, por_area:list<array<string,mixed>>, semanas:list<array{semana:int,personas_dia:int}>}
     */
    public static function cubrimiento(int $anio): array
    {
        $pdo = Database::pdo();
        $vivos = "e.eliminado_en IS NULL AND e.estado <> 'cancelado' AND YEAR(e.fecha_inicio) = ?";

        $carga = $pdo->prepare(
            "SELECT u.id, u.nombre, c.valor AS area, COUNT(*) AS eventos,
                    COALESCE(SUM(DATEDIFF(e.fecha_fin, e.fecha_inicio) + 1), 0) AS dias
               FROM eventos e
               JOIN usuarios u ON u.id = e.dueno_id
               LEFT JOIN catalogo_valores c ON c.id = u.area_id
              WHERE $vivos AND e.requiere_cubrimiento = 1
           GROUP BY u.id, u.nombre, c.valor
           ORDER BY eventos DESC, dias DESC, u.nombre"
        );
        $carga->execute([$anio]);

        $responsables = $pdo->prepare(
            "SELECT u.id, u.nombre, c.valor AS area, COUNT(*) AS eventos,
                    COALESCE(SUM(e.requiere_cubrimiento = 1), 0) AS con_cubrimiento
               FROM eventos e
               JOIN usuarios u ON u.id = e.dueno_id
               LEFT JOIN catalogo_valores c ON c.id = u.area_id
              WHERE $vivos
           GROUP BY u.id, u.nombre, c.valor
           ORDER BY eventos DESC, con_cubrimiento DESC, u.nombre"
        );
        $responsables->execute([$anio]);

        $porArea = $pdo->prepare(
            "SELECT ar.valor AS area, ar.color, COUNT(*) AS total, COALESCE(SUM(e.requiere_cubrimiento = 1), 0) AS piden
               FROM eventos e
               JOIN catalogo_valores ar ON ar.id = e.area_id
              WHERE $vivos
           GROUP BY ar.id, ar.valor, ar.color
           ORDER BY total DESC, ar.valor"
        );
        $porArea->execute([$anio]);

        // Personas-día por semana ISO: cada responsable cuenta una vez por día aunque lleve dos eventos.
        $semanas = array_fill(1, 53, 0);
        $ini = sprintf('%04d-01-01', $anio);
        $fin = sprintf('%04d-12-31', $anio);
        foreach (self::agendasEnRango($ini, $fin) as $eventos) {
            $vistos = [];
            foreach ($eventos as $e) {
                $d = new \DateTime(max($e['fecha_inicio'], $ini));
                $tope = new \DateTime(min($e['fecha_fin'], $fin));
                while ($d <= $tope) {
                    $k = $d->format('Y-m-d');
                    if (!isset($vistos[$k])) {
                        $vistos[$k] = true;
                        $semanas[(int) $d->format('W')]++;
                    }
                    $d->modify('+1 day');
                }
            }
        }
        if ($semanas[53] === 0) {
            unset($semanas[53]);
        }
        $listaSemanas = [];
        foreach ($semanas as $n => $v) {
            $listaSemanas[] = ['semana' => $n, 'personas_dia' => $v];
        }

        $entero = static fn(array $f, array $claves): array => array_merge($f, array_map('intval', array_intersect_key($f, array_flip($claves))));
        return [
            'carga'        => array_map(static fn(array $f): array => $entero($f, ['id', 'eventos', 'dias']), $carga->fetchAll()),
            'responsables' => array_map(static fn(array $f): array => $entero($f, ['id', 'eventos', 'con_cubrimiento']), $responsables->fetchAll()),
            'por_area'     => array_map(static fn(array $f): array => $entero($f, ['total', 'piden']), $porArea->fetchAll()),
            'semanas'      => $listaSemanas,
        ];
    }

    /**
     * Quién está comprometido y quién disponible en un rango. "Disponible" significa solo que
     * no es responsable de ningún evento con cubrimiento en esas fechas: no sabe de vacaciones
     * ni de bajas. Cada persona trae su LISTA de eventos del rango, que es lo que la vista
     * enseña al señalarla.
     *
     * @return list<array{id:int,nombre:string,area:?string,eventos:int,detalle:string,lista:list<array<string,mixed>>}>
     */
    public static function disponibilidad(string $inicio, string $fin): array
    {
        $gente = Database::pdo()->query(
            "SELECT u.id, u.nombre, c.valor AS area
               FROM usuarios u
               LEFT JOIN catalogo_valores c ON c.id = u.area_id
              WHERE u.activo = 1"
        )->fetchAll();
        $agendas = self::agendasEnRango($inicio, $fin);
        $out = [];
        foreach ($gente as $p) {
            $lista = $agendas[(int) $p['id']] ?? [];
            $out[] = [
                'id'      => (int) $p['id'],
                'nombre'  => (string) $p['nombre'],
                'area'    => $p['area'],
                'eventos' => count($lista),
                'detalle' => implode(' | ', array_column($lista, 'nombre')),
                'lista'   => $lista,
            ];
        }
        // Los más comprometidos primero; a igualdad, por área y nombre.
        usort($out, static fn(array $x, array $y): int =>
            [$y['eventos'], (string) $x['area'], $x['nombre']] <=> [$x['eventos'], (string) $y['area'], $y['nombre']]);
        return $out;
    }

    /**
     * Agenda de UNA persona en un rango: cada evento con cubrimiento del que es responsable, con
     * sus fechas. Con esto se sabe qué días tiene comprometidos y, por descarte, cuáles libres.
     * @return list<array{id:int,nombre:string,fecha_inicio:string,fecha_fin:string,area:string,estado:string}>
     */
    public static function agendaDe(int $usuarioId, string $inicio, string $fin): array
    {
        return self::agendasEnRango($inicio, $fin)[$usuarioId] ?? [];
    }

    /**
     * usuario_id => eventos vivos del rango de los que es RESPONSABLE y que piden cubrimiento.
     * Es la misma regla que el mapa: sin equipos asignados, estar comprometido es liderar un
     * evento que pide cubrimiento.
     *
     * @return array<int, list<array{id:int,nombre:string,fecha_inicio:string,fecha_fin:string,area:string,estado:string}>>
     */
    public static function agendasEnRango(string $inicio, string $fin): array
    {
        $st = Database::pdo()->prepare(
            "SELECT e.dueno_id AS usuario_id, e.id, e.nombre, e.fecha_inicio, e.fecha_fin, e.estado, ar.valor AS area
               FROM eventos e
               JOIN catalogo_valores ar ON ar.id = e.area_id
              WHERE e.requiere_cubrimiento = 1
                AND e.eliminado_en IS NULL
                AND e.estado <> 'cancelado'
                AND e.fecha_fin >= ?
                AND e.fecha_inicio <= ?
           ORDER BY e.fecha_inicio, e.id"
        );
        $st->execute([$inicio, $fin]);
        $out = [];
        foreach ($st->fetchAll() as $f) {
            $out[(int) $f['usuario_id']][] = [
                'id'           => (int) $f['id'],
                'nombre'       => (string) $f['nombre'],
                'fecha_inicio' => (string) $f['fecha_inicio'],
                'fecha_fin'    => (string) $f['fecha_fin'],
                'area'         => (string) $f['area'],
                'estado'       => (string) $f['estado'],
            ];
        }
        return $out;
    }

    /**
     * Matriz de carga: una fila por área con acciones y doce columnas (los meses).
     * Deja fuera las áreas sin nada en el año, pero las devuelve aparte en 'sin_actividad'
     * para poder decirlo al pie de la tabla en vez de dibujar filas vacías.
     */
    private static function matriz(array $filasSql, int $anio): array
    {
        $filas = [];
        foreach ($filasSql as $f) {
            $id = (int) $f['area_id'];
            if (!isset($filas[$id])) {
                $filas[$id] = [
                    'id' => $id,
                    'area' => (string) $f['area'],
                    'color' => $f['color'] ?: '#B3B7BF',
                    'meses' => array_fill(1, 12, 0),
                    'total' => 0,
                    'pico' => 0,
                    'meses_activos' => 0,
                ];
            }
            $filas[$id]['meses'][(int) $f['mes']] = (int) $f['n'];
        }

        $totalMes = array_fill(1, 12, 0);
        $max = 0;
        foreach ($filas as &$fila) {
            foreach ($fila['meses'] as $m => $n) {
                $fila['total'] += $n;
                $totalMes[$m] += $n;
                if ($n > 0) {
                    $fila['meses_activos']++;
                }
                $fila['pico'] = max($fila['pico'], $n);
                $max = max($max, $n);
            }
            $fila['meses'] = array_values($fila['meses']);
        }
        unset($fila);

        // De más cargada a menos, que es como se lee una tabla así.
        usort($filas, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['area'], $b['area']));

        $conActividad = array_column($filas, 'id');
        $sinActividad = [];
        $st = Database::pdo()->query("SELECT id, valor FROM catalogo_valores WHERE campo = 'area' AND activo = 1 ORDER BY valor");
        foreach ($st->fetchAll() as $a) {
            if (!in_array((int) $a['id'], $conActividad, true)) {
                $sinActividad[] = (string) $a['valor'];
            }
        }

        return [
            'anio' => $anio,
            'areas' => array_values($filas),
            'meses' => array_values($totalMes),
            'max' => $max,
            'total' => array_sum($totalMes),
            'sin_actividad' => $sinActividad,
        ];
    }
}
