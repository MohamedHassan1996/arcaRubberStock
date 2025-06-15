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

   /* public static function raw(string $sql, array $bindings = [], bool $fetch = true)
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
    }*/

    public static function raw(string $sql, array $bindings = [], bool $fetch = true)
    {
        $conn = self::connect();
        $now = date('Y-m-d H:i:s');

        $isNamed = self::isNamedBindings($bindings);

        // INSERT logic
        if (preg_match('/^\s*INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)/i', $sql, $matches)) {
            $columns = array_map('trim', explode(',', $matches[2]));
            $table = $matches[1];

            if ($isNamed) {
                if (!in_array('created_at', $columns)) {
                    $columns[] = 'created_at';
                    $bindings['created_at'] = $now;
                }
                if (!in_array('updated_at', $columns)) {
                    $columns[] = 'updated_at';
                    $bindings['updated_at'] = $now;
                }

                $placeholders = array_map(fn($col) => ':' . $col, $columns);
            } else {
                if (!in_array('created_at', $columns)) {
                    $columns[] = 'created_at';
                    $bindings[] = $now;
                }
                if (!in_array('updated_at', $columns)) {
                    $columns[] = 'updated_at';
                    $bindings[] = $now;
                }

                $placeholders = array_fill(0, count($columns), '?');
            }

            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        }


        // UPDATE logic
        if (preg_match('/^\s*UPDATE\s+`?(\w+)`?\s+SET\s+/i', $sql, $matches)) {
            if ($isNamed) {
                if (!str_contains($sql, 'updated_at')) {
                    $sql = preg_replace(
                        '/\bset\b\s+/i',
                        'SET updated_at = :updated_at, ',
                        $sql,
                        1
                    );
                    $bindings['updated_at'] = $now;
                }
            } else {
                if (!preg_match('/updated_at\s*=\s*\?/i', $sql)) {
                    $sql = preg_replace(
                        '/set\s+(.*?)(\s+where\s+|$)/i',
                        'SET updated_at = ?, $1$2',
                        $sql,
                        1
                    );
                    array_unshift($bindings, $now); // prepend updated_at
                }
            }
        }

        $stmt = $conn->prepare($sql);

        try {
            $stmt->execute($bindings);
        } catch (PDOException $e) {
            throw new Exception('Raw SQL execution failed: ' . $e->getMessage());
        }

        if ($fetch) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (preg_match('/^\s*insert\s+/i', $sql)) {
            return (int) $conn->lastInsertId();
        }

        return $stmt->rowCount();
    }
    protected static function isNamedBindings(array $bindings): bool
    {
        foreach (array_keys($bindings) as $key) {
            if (is_string($key)) return true;
        }
        return false;
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
