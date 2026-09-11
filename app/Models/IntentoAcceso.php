<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Límite de intentos de acceso.
 *
 * Sin esto, una contraseña débil cae por fuerza bruta en minutos. Se cuenta por
 * correo y por dirección IP a la vez: lo primero frena el ataque dirigido a una
 * cuenta concreta, lo segundo el que prueba muchas cuentas desde el mismo sitio.
 */
final class IntentoAcceso
{
    /** Fallos permitidos dentro de la ventana antes de bloquear. */
    public const MAXIMO = 5;

    /** Minutos que se miran hacia atrás, y que dura el bloqueo. */
    public const VENTANA_MINUTOS = 15;

    public static function registrar(string $correo, string $ip, bool $exito): void
    {
        Database::pdo()->prepare(
            'INSERT INTO intentos_acceso (correo, ip, exito) VALUES (?, ?, ?)'
        )->execute([mb_strtolower(trim($correo)), $ip, $exito ? 1 : 0]);
    }

    /**
     * Verdadero cuando el correo o la IP acumulan demasiados fallos recientes.
     *
     * Solo cuentan los fallos posteriores al último acierto: quien entra bien
     * deja el contador a cero sin necesidad de borrar nada.
     */
    public static function bloqueado(string $correo, string $ip): bool
    {
        $correo = mb_strtolower(trim($correo));
        $st = Database::pdo()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM intentos_acceso
                   WHERE correo = ? AND exito = 0 AND creado_en > NOW() - INTERVAL ? MINUTE
                     AND creado_en > COALESCE((SELECT MAX(creado_en) FROM intentos_acceso
                                               WHERE correo = ? AND exito = 1), "1970-01-01")) AS por_correo,
                (SELECT COUNT(*) FROM intentos_acceso
                   WHERE ip = ? AND exito = 0 AND creado_en > NOW() - INTERVAL ? MINUTE) AS por_ip'
        );
        $st->execute([$correo, self::VENTANA_MINUTOS, $correo, $ip, self::VENTANA_MINUTOS]);
        $f = $st->fetch() ?: ['por_correo' => 0, 'por_ip' => 0];

        // La IP tiene más margen: tras un cortafuegos corporativo comparten IP
        // muchas personas legítimas y no deben estorbarse entre ellas.
        return (int) $f['por_correo'] >= self::MAXIMO
            || (int) $f['por_ip'] >= self::MAXIMO * 4;
    }

    /** Borra el rastro de un correo. Lo usan las pruebas y el restablecimiento. */
    public static function limpiar(string $correo): void
    {
        Database::pdo()->prepare('DELETE FROM intentos_acceso WHERE correo = ?')
            ->execute([mb_strtolower(trim($correo))]);
    }

    /** Purga lo que ya no influye en ninguna decisión. Para la tarea programada. */
    public static function purgar(int $dias = 7): int
    {
        $st = Database::pdo()->prepare('DELETE FROM intentos_acceso WHERE creado_en < NOW() - INTERVAL ? DAY');
        $st->execute([$dias]);
        return $st->rowCount();
    }

    /**
     * Dirección del cliente.
     *
     * Solo se hace caso a las cabeceras de proxy cuando la configuración declara
     * que hay uno delante. Si no, cualquiera podría falsificarlas y saltarse el
     * límite enviando una IP distinta en cada intento.
     */
    public static function ipCliente(): string
    {
        $remota = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!\App\Core\Env::bool('TRUST_PROXY')) {
            return $remota;
        }

        $reenviada = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($reenviada === '') {
            return $remota;
        }

        $primera = trim(explode(',', $reenviada)[0]);
        return filter_var($primera, FILTER_VALIDATE_IP) ? $primera : $remota;
    }
}
