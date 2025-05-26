<?php 

namespace Core;

use Config\DbConnection;
use Exception;
use PDO;
use PDOException; 
class DB
{
    protected static ?PDO $conn = null;

    protected static function connect(): PDO
    {
        if (self::$conn === null) {
            self::$conn = DbConnection::connection();

            if (self::$conn === null) {
                throw new Exception('Database connection failed');
            }
        }

        return self::$conn;
    }

    public static function raw(string $sql, array $bindings = [], bool $fetch = true)
    {
        $conn = self::connect();

        $stmt = $conn->prepare($sql);

        try {
            $stmt->execute($bindings);
        } catch (PDOException $e) {
            throw new Exception('Raw SQL execution failed: ' . $e->getMessage());
        }

        // If fetch = true → SELECT
        if ($fetch) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // If INSERT → return lastInsertId
        if (preg_match('/^\s*insert\s+/i', $sql)) {
            return (int) $conn->lastInsertId();
        }

        // Otherwise (UPDATE, DELETE) → return affected rows
        return $stmt->rowCount();
    }

    public static function select(string $sql, array $bindings = [])
    {
        return self::raw($sql, $bindings, true);
    }

    public static function statement(string $sql, array $bindings = [])
    {
        return self::raw($sql, $bindings, false);
    }

        // ✅ Begin transaction
    public static function beginTransaction(): void
    {
        self::connect()->beginTransaction();
    }

    // ✅ Commit transaction
    public static function commit(): void
    {
        self::connect()->commit();
    }

    // ✅ Rollback transaction
    public static function rollBack(): void
    {
        self::connect()->rollBack();
    }

    // Optional: check if inside transaction
    public static function inTransaction(): bool
    {
        return self::connect()->inTransaction();
    }

}
