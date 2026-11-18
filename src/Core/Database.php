<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $instance = null;
    private static int $savepointDepth = 0;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = (string) ($_ENV['DB_HOST'] ?? '127.0.0.1');
        $port = (string) ($_ENV['DB_PORT'] ?? '3306');
        $name = (string) ($_ENV['DB_NAME'] ?? 'huit_db');
        $user = (string) ($_ENV['DB_USER'] ?? 'root');
        $pass = (string) ($_ENV['DB_PASS'] ?? '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException('Không thể kết nối cơ sở dữ liệu MySQL: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return self::$instance;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
        self::$savepointDepth = 0;
    }

    /**
     * Execute callback inside an atomic MySQL transaction.
     * Supports nested transactions via SAVEPOINT (essential for transactional tests).
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $isNested = $pdo->inTransaction();

        if (!$isNested) {
            $pdo->beginTransaction();
        } else {
            self::$savepointDepth++;
            $savepoint = 'sp_huit_' . self::$savepointDepth;
            $pdo->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $result = $callback($pdo);
            if (!$isNested) {
                $pdo->commit();
            } else {
                $savepoint = 'sp_huit_' . self::$savepointDepth;
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                self::$savepointDepth--;
            }
            return $result;
        } catch (Throwable $e) {
            if (!$isNested) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $savepoint = 'sp_huit_' . self::$savepointDepth;
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                self::$savepointDepth--;
            }
            throw $e;
        }
    }
}
