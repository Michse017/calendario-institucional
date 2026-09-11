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
