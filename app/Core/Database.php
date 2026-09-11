<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Database
{
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
            self::$pdo = new PDO($dsn, (string) Env::get('DB_USER', 'root'), (string) Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
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
