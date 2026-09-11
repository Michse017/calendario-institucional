<?php
declare(strict_types=1);

namespace App\Core;

/** Reglas de limpieza y comparación de textos de catálogo (spec §4). */
final class Normalizador
{
    private const NA = ['n/a', 'na', 'n.a.', 'n.a', 'n-a', 'n / a', 'no aplica'];

    private const TILDES = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ];

    /** Colapsa espacios en blanco consecutivos a uno solo y recorta bordes. */
    private static function colapsarEspacios(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** Recorta y colapsa espacios; cualquier forma de "no aplica" se vuelve N/A. */
    public static function limpiar(string $s): string
    {
        $s = self::colapsarEspacios($s);
        return self::esNA($s) ? 'N/A' : $s;
    }

    public static function quitarTildes(string $s): string
    {
        return strtr($s, self::TILDES);
    }

    /** Clave de comparación: limpio, sin tildes, minúsculas (conserva ñ). */
    public static function normalizar(string $s): string
    {
        $s = self::colapsarEspacios($s);
        return mb_strtolower(self::quitarTildes($s), 'UTF-8');
    }

    public static function esNA(string $s): bool
    {
        $n = mb_strtolower(self::colapsarEspacios($s), 'UTF-8');
        return in_array($n, self::NA, true);
    }

    /** Escapa \, % y _ para usar el texto como literal dentro de un patrón LIKE. */
    public static function escaparLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    /**
     * Distancia de Levenshtein por caracteres UTF-8 (la nativa levenshtein()
     * cuenta bytes y sobreestima con ñ, que ocupa dos bytes).
     */
    private static function distancia(string $a, string $b): int
    {
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $la = count($x);
        $lb = count($y);
        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }
        $previa = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $actual = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $costo = $x[$i - 1] === $y[$j - 1] ? 0 : 1;
                $actual[$j] = min($previa[$j] + 1, $actual[$j - 1] + 1, $previa[$j - 1] + $costo);
            }
            $previa = $actual;
        }
        return $previa[$lb];
    }

    /**
     * Candidatos "parecidos" para preguntar "¿Quisiste decir…?".
     * @param array<int,array{id:int,valor:string,valor_norm:string}> $candidatos
     * @return array<int,array{id:int,valor:string,valor_norm:string}>
     */
    public static function similares(string $valor, array $candidatos): array
    {
        $n = self::normalizar($valor);
        if (mb_strlen($n) < 5) {
            return [];
        }
        $out = [];
        foreach ($candidatos as $c) {
            $cn = (string) $c['valor_norm'];
            if ($cn === $n || abs(mb_strlen($cn) - mb_strlen($n)) > 2) {
                continue;
            }
            if (self::distancia($n, $cn) <= 2) {
                $out[] = $c;
            }
        }
        return $out;
    }
}
