<?php
declare(strict_types=1);

namespace App\Core;

use DateTime;

/**
 * Validación de servidor de un evento. Todos los campos son obligatorios y solo los de la sección
 * "3 · Seguimiento" (Campos::ADMITEN_NA) aceptan N/A o Pendiente; en el resto hay que elegir o escribir
 * un valor real. La comprobación de que un valor de lista cerrada existe de verdad la hace
 * Catalogo::erroresCerrados(), porque necesita consultar la base.
 */
final class Validator
{
    private const MSG_OBLIG   = 'Obligatorio: escribe un valor o N/A si no aplica.';
    private const MSG_ELIGE   = 'Obligatorio: elige una opción de la lista.';
    private const MSG_ESCRIBE = 'Obligatorio: escribe un valor.';
    private const MSG_SIN_NA  = 'Este campo no admite N/A ni Pendiente: elige un valor real.';

    /**
     * @param array<string,mixed> $in                Entrada cruda ($_POST o fila del CSV)
     * @param bool                $permitirCancelado Solo lo usa el controlador al editar un evento ya cancelado
     * @param bool                $exigirSinNA       false para cargas antiguas (bin/importar_csv.php), que sí traían N/A
     * @return array{ok:bool, errores:array<string,string>, datos:array<string,string>}
     */
    public static function evento(array $in, bool $permitirCancelado = false, bool $exigirSinNA = true): array
    {
        $e = [];
        $d = [];

        $nombre = Normalizador::limpiar((string) ($in['nombre'] ?? ''));
        if ($nombre === '') {
            $e['nombre'] = 'Escribe el nombre del evento.';
        } elseif (mb_strlen($nombre) > 200) {
            $e['nombre'] = 'Máximo 200 caracteres.';
        } else {
            $d['nombre'] = $nombre;
        }

        foreach (['fecha_inicio', 'fecha_fin'] as $f) {
            $v = trim((string) ($in[$f] ?? ''));
            $dt = DateTime::createFromFormat('!Y-m-d', $v);
            if ($v === '' || !$dt || $dt->format('Y-m-d') !== $v) {
                $e[$f] = 'Fecha inválida (usa AAAA-MM-DD).';
            } else {
                $d[$f] = $v;
            }
        }
        if (isset($d['fecha_inicio'], $d['fecha_fin']) && $d['fecha_fin'] < $d['fecha_inicio']) {
            $e['fecha_fin'] = 'La fecha fin debe ser igual o posterior a la fecha inicio.';
        }

        $estado = (string) ($in['estado'] ?? '');
        $permitidos = $permitirCancelado ? Campos::ESTADOS : Campos::ESTADOS_FORMULARIO;
        if (!isset($permitidos[$estado])) {
            $e['estado'] = 'Selecciona un estado.';
        } else {
            $d['estado'] = $estado;
        }

        foreach (Campos::CATALOGOS as $c) {
            $cerrado = in_array($c, Campos::CERRADOS, true);
            $v = Normalizador::limpiar((string) ($in[$c] ?? ''));
            if ($v === '') {
                $e[$c] = $cerrado ? self::MSG_ELIGE : self::MSG_ESCRIBE;
            } elseif ($exigirSinNA && ($v === 'N/A' || self::esPendiente($v))) {
                $e[$c] = self::MSG_SIN_NA;
            } elseif (mb_strlen($v) > 255) {
                $e[$c] = 'Máximo 255 caracteres.';
            } else {
                $d[$c] = $v;
            }
        }

        // Detalle de "Otros": obligatorio cuando el campo quedó en ese valor, vacío en cualquier otro caso.
        foreach (Campos::CON_OTROS as $c) {
            $col = Campos::COLUMNA_OTRO[$c];
            $d[$col] = '';
            if (($d[$c] ?? '') !== Campos::OTROS) {
                continue;
            }
            $detalle = Normalizador::limpiar((string) ($in[$col] ?? ''));
            if ($detalle === '' || $detalle === 'N/A' || self::esPendiente($detalle) || mb_strlen($detalle) < 3) {
                $e[$col] = 'Escribe cuál: mínimo 3 caracteres.';
            } elseif (mb_strlen($detalle) > 200) {
                $e[$col] = 'Máximo 200 caracteres.';
            } else {
                $d[$col] = $detalle;
            }
        }

        foreach (Campos::TEXTOS as $t) {
            $v = trim((string) ($in[$t] ?? ''));
            $v = Normalizador::esNA($v) ? 'N/A' : $v;
            if ($v === '' && in_array($t, Campos::AUTO_NA, true)) {
                $v = 'N/A';   // se puede dejar en blanco: lo normalizamos a N/A en vez de rechazarlo
            }
            if ($v === '') {
                $e[$t] = self::MSG_OBLIG;
            } elseif (mb_strlen($v) > 5000) {
                $e[$t] = 'Máximo 5000 caracteres.';
            } else {
                $d[$t] = $v;
            }
        }

        foreach (Campos::ENLACES as $l) {
            $v = Normalizador::limpiar((string) ($in[$l] ?? ''));
            if ($v === '') {
                $e[$l] = 'Obligatorio: pega el enlace, o escribe N/A o Pendiente.';
            } elseif ($v === 'N/A') {
                $d[$l] = 'N/A';
            } elseif (self::esPendiente($v)) {
                $d[$l] = 'Pendiente';
            } elseif (mb_strlen($v) > 500 || !preg_match('~^https?://[^\s]+$~i', $v) || filter_var($v, FILTER_VALIDATE_URL) === false) {
                $e[$l] = 'Debe ser un enlace http(s) válido, N/A o Pendiente.';
            } else {
                $d[$l] = $v;
            }
        }

        $r = Normalizador::limpiar((string) ($in['reuniones'] ?? ''));
        if ($r === '') {
            $e['reuniones'] = 'Obligatorio: número de reuniones, N/A o Pendiente.';
        } elseif ($r === 'N/A') {
            $d['reuniones'] = 'N/A';
        } elseif (self::esPendiente($r)) {
            $d['reuniones'] = 'Pendiente';
        } elseif (preg_match('/^\d{1,6}$/', $r)) {
            $d['reuniones'] = (string) (int) $r;
        } else {
            $e['reuniones'] = 'Debe ser un número entero (0 o más), N/A o Pendiente.';
        }

        return ['ok' => $e === [], 'errores' => $e, 'datos' => $d];
    }

    public static function esPendiente(string $v): bool
    {
        return mb_strtolower(Normalizador::quitarTildes(trim($v)), 'UTF-8') === 'pendiente';
    }
}
