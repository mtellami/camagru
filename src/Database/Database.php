<?php

declare(strict_types=1);

namespace Camagru\Database;

use Camagru\Config;
use PDO;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    /** Release the cached PDO connection. */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /** Lazily open one PostgreSQL connection with native prepared statements. */
    public static function connection(): PDO
    {
        return self::$connection ??= new PDO(
            'pgsql:host=' .
                Config::get('DB_HOST', 'db') .
                ';port=' .
                Config::get('DB_PORT', '5432') .
                ';dbname=' .
                Config::get('DB_NAME', 'camagru'),
            Config::get('DB_USER', 'camagru'),
            Config::get('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    /** Execute a parameterized statement and return its result cursor. */
    public static function query(string $sql, array $parameters = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    /** Commit the callback result or roll back and rethrow its failure. */
    public static function transaction(callable $callback): mixed
    {
        $db = self::connection();
        $db->beginTransaction();
        try {
            $result = $callback();
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
