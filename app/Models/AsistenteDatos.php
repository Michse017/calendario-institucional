<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Normalizador;

/**
 * Las consultas que el asistente puede pedir.
 *
 * Esto es un MENÚ CERRADO, y esa es la idea entera. El modelo no escribe SQL:
 * elige una de estas funciones y pasa argumentos. Todo lo que llega se coteja
 * contra el catálogo antes de tocar la base, y las consultas van con
 * parámetros. Así una pregunta retorcida, o un intento de inyección metido en
 * la conversación, como mucho consigue un filtro que no existe y una respuesta
 * vacía.
 *
 * Dos decisiones que conviene no deshacer:
 *
 * 1. **Los porcentajes se calculan aquí, en PHP.** Los modelos de lenguaje
 *    hacen aritmética mal: si se les dan dos números y se les pide la tasa, a
 *    veces sale torcida. Se les da ya calculada y solo la leen.
 * 2. **Se consulta `eventos` directamente, no las vistas `bi_`.** El despliegue
 *    solo aplica `001_schema.sql`, así que esas vistas no existen en la demo.
 *    Depender de ellas sería un fallo que solo aparece en producción.
 */
final class AsistenteDatos
{
    /** Tope de filas que vuelven al modelo. Más no ayuda y engorda la petición. */
    private const MAX_FILAS = 25;

    /** Campo del catálogo que respalda cada filtro y cada agrupación. */
    private const DIMENSION = [
        'area'        => ['campo' => 'area',        'col' => 'e.area_id'],
        'tipo'        => ['campo' => 'tipo_accion', 'col' => 'e.tipo_accion_id'],
        'publico'     => ['campo' => 'segmento',    'col' => 'e.segmento_id'],
        'procedencia' => ['campo' => 'mercado',     'col' => 'e.mercado_id'],
    ];

    private const ESTADOS = ['no_realizado', 'en_ejecucion', 'realizado', 'cancelado'];

    /**
     * El menú tal como lo ve el modelo. Las descripciones son la documentación
     * que él lee para decidir: si están flojas, elige mal la herramienta.
     */
    public static function declaraciones(): array
    {
        $filtros = [
            'area'        => ['type' => 'STRING', 'description' => 'Área responsable exacta, por ejemplo "Programación".'],
            'tipo'        => ['type' => 'STRING', 'description' => 'Tipo de evento exacto, por ejemplo "Taller".'],
            'publico'     => ['type' => 'STRING', 'description' => 'Público al que se dirige.'],
            'procedencia' => ['type' => 'STRING', 'description' => 'Procedencia del público.'],
            'estado'      => ['type' => 'STRING', 'description' => 'Uno de: no_realizado, en_ejecucion, realizado, cancelado.'],
            'mes'         => ['type' => 'INTEGER', 'description' => 'Mes del 1 al 12. Solo si preguntan por un mes concreto.'],
        ];

        return [[
            'functionDeclarations' => [
                [
                    'name' => 'contar_eventos',
                    'description' => 'Cifras de los eventos que cumplen los filtros: cuántos hay, cuántos realizados y cancelados, '
                        . 'la tasa de cumplimiento, el público total, los días de agenda que ocupan y qué parte tiene el aforo reportado. '
                        . 'Sin filtros devuelve el total de todo el calendario. Úsala para "cuántos", "qué porcentaje" y "qué tasa".',
                    'parameters' => ['type' => 'OBJECT', 'properties' => $filtros],
                ],
                [
                    'name' => 'resumen_por',
                    'description' => 'Las mismas cifras que contar_eventos pero desglosadas por una dimensión, de mayor a menor. '
                        . 'Úsala para "cuál área...", "compara por tipo", "cómo se reparte", "cuál es el que más...".',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'dimension' => ['type' => 'STRING', 'description' => 'Una de: area, tipo, publico, procedencia, mes, estado.'],
                        ] + $filtros,
                        'required' => ['dimension'],
                    ],
                ],
                [
                    'name' => 'buscar_eventos',
                    'description' => 'Lista eventos concretos con su nombre, fechas, área y estado. '
                        . 'Úsala cuando pregunten CUÁLES son, no cuántos.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => ['texto' => ['type' => 'STRING', 'description' => 'Parte del nombre del evento.']] + $filtros,
                    ],
                ],
            ],
        ]];
    }

    /**
     * Ejecuta lo que el modelo pidió.
     *
     * Nunca lanza: un error aquí volvería al modelo como excepción y cortaría la
     * conversación. Se devuelve el problema como dato para que él lo explique.
     */
    public static function ejecutar(string $nombre, array $args): array
    {
        try {
            return match ($nombre) {
                'contar_eventos' => self::contar($args),
                'resumen_por'    => self::resumen($args),
                'buscar_eventos' => self::buscar($args),
                default          => ['error' => 'Esa consulta no existe.'],
            };
        } catch (\Throwable $e) {
            error_log('asistente/datos: ' . $nombre . ': ' . $e->getMessage());
            return ['error' => 'No se pudo consultar ese dato.'];
        }
    }

    // ------------------------------------------------------------ Consultas

    private static function contar(array $args): array
    {
        [$where, $p, $usados, $ignorados] = self::filtros($args);
        $sql = 'SELECT COUNT(*) total,
                       SUM(e.estado = "realizado")  realizados,
                       SUM(e.estado = "cancelado")  cancelados,
                       SUM(e.estado <> "cancelado") vigentes,
                       SUM(DATEDIFF(e.fecha_fin, e.fecha_inicio) + 1) dias,
                       SUM(e.reuniones REGEXP "^[0-9]+$") con_aforo,
                       SUM(CASE WHEN e.reuniones REGEXP "^[0-9]+$" THEN CAST(e.reuniones AS UNSIGNED) ELSE 0 END) aforo
                FROM eventos e WHERE ' . $where;
        $st = Database::pdo()->prepare($sql);
        $st->execute($p);
        return self::cifras((array) $st->fetch()) + self::nota($usados, $ignorados);
    }

    private static function resumen(array $args): array
    {
        $dim = strtolower(trim((string) ($args['dimension'] ?? '')));
        unset($args['dimension']);
        [$where, $p, $usados, $ignorados] = self::filtros($args);

        // La etiqueta de cada grupo. Van aparte porque mes y estado no salen del
        // catálogo, y meterlos en el mismo mapa sería forzar la analogía.
        if (isset(self::DIMENSION[$dim])) {
            $etiqueta = 'c.valor';
            $join = 'JOIN catalogo_valores c ON c.id = ' . self::DIMENSION[$dim]['col'];
        } elseif ($dim === 'mes') {
            $etiqueta = 'MONTH(e.fecha_inicio)';
            $join = '';
        } elseif ($dim === 'estado') {
            $etiqueta = 'e.estado';
            $join = '';
        } else {
            return ['error' => 'Dimensión no válida. Usa: ' . implode(', ', array_keys(self::DIMENSION)) . ', mes o estado.'];
        }

        $sql = "SELECT $etiqueta etiqueta, COUNT(*) total,
                       SUM(e.estado = \"realizado\")  realizados,
                       SUM(e.estado = \"cancelado\")  cancelados,
                       SUM(e.estado <> \"cancelado\") vigentes,
                       SUM(DATEDIFF(e.fecha_fin, e.fecha_inicio) + 1) dias,
                       SUM(e.reuniones REGEXP \"^[0-9]+$\") con_aforo,
                       SUM(CASE WHEN e.reuniones REGEXP \"^[0-9]+$\" THEN CAST(e.reuniones AS UNSIGNED) ELSE 0 END) aforo
                FROM eventos e $join WHERE $where
                GROUP BY etiqueta ORDER BY total DESC, etiqueta LIMIT " . self::MAX_FILAS;
        $st = Database::pdo()->prepare($sql);
        $st->execute($p);

        $grupos = [];
        foreach ($st->fetchAll() as $f) {
            $grupos[] = ['nombre' => (string) $f['etiqueta']] + self::cifras($f);
        }
        return ['desglose_por' => $dim, 'grupos' => $grupos] + self::nota($usados, $ignorados);
    }

    private static function buscar(array $args): array
    {
        $texto = trim((string) ($args['texto'] ?? ''));
        unset($args['texto']);
        [$where, $p, $usados, $ignorados] = self::filtros($args);
        if ($texto !== '') {
            $where .= ' AND e.nombre LIKE ?';
            $p[] = '%' . Normalizador::escaparLike($texto) . '%';
            $usados['texto'] = $texto;
        }
        $st = Database::pdo()->prepare(
            'SELECT e.nombre, e.fecha_inicio, e.fecha_fin, e.estado, ar.valor area, ta.valor tipo
             FROM eventos e
             JOIN catalogo_valores ar ON ar.id = e.area_id
             JOIN catalogo_valores ta ON ta.id = e.tipo_accion_id
             WHERE ' . $where . ' ORDER BY e.fecha_inicio LIMIT ' . self::MAX_FILAS
        );
        $st->execute($p);
        $filas = $st->fetchAll();
        return [
            'eventos' => array_map(static fn(array $f): array => [
                'nombre' => $f['nombre'],
                'fechas' => $f['fecha_inicio'] === $f['fecha_fin'] ? $f['fecha_inicio'] : $f['fecha_inicio'] . ' a ' . $f['fecha_fin'],
                'area' => $f['area'], 'tipo' => $f['tipo'], 'estado' => $f['estado'],
            ], $filas),
            'mostrados' => count($filas),
            'hay_mas' => count($filas) === self::MAX_FILAS,
        ] + self::nota($usados, $ignorados);
    }

    // ------------------------------------------------------------- Internos

    /**
     * Traduce los argumentos del modelo a un WHERE con parámetros.
     *
     * Un valor que no está en el catálogo NO se busca: se ignora y se avisa. Es
     * mejor responder "no existe el área Marketing" que devolver cero eventos y
     * dejar creer que el área existe pero está vacía.
     *
     * @return array{0:string, 1:array, 2:array, 3:array}  where, parámetros, filtros aplicados, filtros ignorados
     */
    private static function filtros(array $args): array
    {
        $w = ['e.eliminado_en IS NULL'];
        $p = [];
        $usados = [];
        $ignorados = [];

        foreach (self::DIMENSION as $clave => $d) {
            $valor = trim((string) ($args[$clave] ?? ''));
            if ($valor === '') {
                continue;
            }
            $fila = Catalogo::buscarPorNorm($d['campo'], Normalizador::normalizar($valor));
            if (!$fila) {
                $ignorados[$clave] = $valor;
                continue;
            }
            $w[] = $d['col'] . ' = ?';
            $p[] = (int) $fila['id'];
            $usados[$clave] = (string) $fila['valor'];
        }

        $estado = trim((string) ($args['estado'] ?? ''));
        if ($estado !== '') {
            if (in_array($estado, self::ESTADOS, true)) {
                $w[] = 'e.estado = ?';
                $p[] = $estado;
                $usados['estado'] = $estado;
            } else {
                $ignorados['estado'] = $estado;
            }
        }

        $mes = (int) ($args['mes'] ?? 0);
        if ($mes >= 1 && $mes <= 12) {
            $w[] = 'MONTH(e.fecha_inicio) = ?';
            $p[] = $mes;
            $usados['mes'] = $mes;
        } elseif (($args['mes'] ?? null) !== null && $mes !== 0) {
            $ignorados['mes'] = $args['mes'];
        }

        return [implode(' AND ', $w), $p, $usados, $ignorados];
    }

    /** Las cifras de un grupo, con los porcentajes YA calculados. */
    private static function cifras(array $f): array
    {
        $total = (int) ($f['total'] ?? 0);
        $vigentes = (int) ($f['vigentes'] ?? 0);
        $conAforo = (int) ($f['con_aforo'] ?? 0);
        $pct = static fn(int $parte, int $de): ?float => $de > 0 ? round(100 * $parte / $de, 1) : null;

        return [
            'eventos' => $total,
            'realizados' => (int) ($f['realizados'] ?? 0),
            'cancelados' => (int) ($f['cancelados'] ?? 0),
            'tasa_cumplimiento_pct' => $pct((int) ($f['realizados'] ?? 0), $vigentes),
            'tasa_cancelacion_pct' => $pct((int) ($f['cancelados'] ?? 0), $total),
            'dias_de_agenda_ocupados' => (int) ($f['dias'] ?? 0),
            'duracion_media_dias' => $total > 0 ? round(((int) ($f['dias'] ?? 0)) / $total, 1) : null,
            'aforo_total' => (int) ($f['aforo'] ?? 0),
            // La media se saca solo sobre los que SÍ reportaron: contar los que no
            // como cero diría que no fue nadie, que es distinto de no medirlo.
            'aforo_medio_de_los_reportados' => $conAforo > 0 ? (int) round(((int) ($f['aforo'] ?? 0)) / $conAforo) : null,
            'aforo_reportado_pct' => $pct($conAforo, $total),
        ];
    }

    /** Qué filtros se aplicaron de verdad, para que el modelo no dé por hecho lo que pidió. */
    private static function nota(array $usados, array $ignorados): array
    {
        $n = ['filtros_aplicados' => $usados ?: 'ninguno, son las cifras de todo el calendario'];
        if ($ignorados) {
            $n['filtros_ignorados_por_no_existir'] = $ignorados;
            $n['aviso'] = 'Díselo: esos valores no existen en el catálogo, así que NO se filtró por ellos.';
        }
        return $n;
    }
}
