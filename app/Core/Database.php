<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    /** Almacenes de autoridades certificadoras habituales, por distribución. */
    private const ALMACENES_CA = [
        '/etc/ssl/certs/ca-certificates.crt', // Debian, Ubuntu, Alpine
        '/etc/pki/tls/certs/ca-bundle.crt',   // RedHat, Fedora
        '/etc/ssl/cert.pem',                  // Alpine antiguo, BSD
    ];

    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_NAME', 'calendario_demo')
            );
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            if (Env::bool('DB_SSL')) {
                $opciones += self::opcionesTls();
            }

            self::$pdo = new PDO($dsn, (string) Env::get('DB_USER', 'root'), (string) Env::get('DB_PASS', ''), $opciones);
            // Modo estricto siempre, tambien en desarrollo. Sin esto MariaDB acepta
            // en silencio un NULL en una columna NOT NULL y lo convierte al valor
            // por defecto: el error aparece solo al desplegar, donde el servidor si
            // es estricto. Mejor que reviente en la maquina de quien programa.
            self::$pdo->exec(
                "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,"
                . "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
            );
            // UTC por defecto: la zona es una decision de despliegue, no del codigo.
            $zona = (string) Env::get('DB_TIMEZONE', '+00:00');
            self::$pdo->prepare('SET time_zone = ?')->execute([$zona]);
        }
        return self::$pdo;
    }

    /**
     * Opciones para que la conexión viaje cifrada.
     *
     * Ojo con el detalle que cuesta una tarde: PDO solo negocia TLS si se le
     * pasa alguna de las opciones SSL_KEY, SSL_CERT, SSL_CA, SSL_CAPATH o
     * SSL_CIPHER. Poner únicamente SSL_VERIFY_SERVER_CERT no cifra nada: la
     * conexión sale en claro igual que sin opciones, sin ningún aviso. Por eso,
     * si no se declara una autoridad concreta, se apunta al almacén del
     * sistema, que es lo que enciende el cifrado.
     */
    private static function opcionesTls(): array
    {
        $ca = (string) Env::get('DB_SSL_CA', '');
        if ($ca === '') {
            foreach (self::ALMACENES_CA as $ruta) {
                if (is_readable($ruta)) {
                    $ca = $ruta;
                    break;
                }
            }
        }
        if ($ca === '') {
            throw new RuntimeException(
                'DB_SSL está activo pero no hay autoridad certificadora: no se encontró '
                . 'el almacén del sistema. Indica la ruta en DB_SSL_CA.'
            );
        }

        // Verificar que el nombre del servidor coincida con el certificado queda
        // desactivado por defecto a propósito: las bases gestionadas se firman
        // con la autoridad interna del proveedor, que este contenedor no conoce,
        // y la comprobación fallaría siempre. El tráfico va cifrado igualmente.
        // Con DB_SSL_VERIFY=true y una DB_SSL_CA válida se puede exigir.
        return [
            PDO::MYSQL_ATTR_SSL_CA                 => $ca,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => Env::bool('DB_SSL_VERIFY', false),
        ];
    }

    /** Ejecuta $fn(PDO) en una transacción. Si ya hay una activa (pruebas), la reutiliza. */
    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }
        $pdo->beginTransaction();
        try {
            $r = $fn($pdo);
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
