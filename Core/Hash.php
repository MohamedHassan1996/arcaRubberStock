<?php 

namespace Core;

use Config\DbConnection;
use Exception;
use PDO;
use PDOException; 
class Hash
{
    /**
     * Hash the given value.
     */
    public static function make(string $value, array $options = []): string
    {
        $algo = $options['algo'] ?? PASSWORD_DEFAULT;
        $cost = $options['cost'] ?? 10;

        return password_hash($value, $algo, ['cost' => $cost]);
    }

    /**
     * Verify that a plain-text value matches a given hash.
     */
    public static function check(string $value, string $hashed): bool
    {
        return password_verify($value, $hashed);
    }

    /**
     * Check if a given hash needs rehashing (e.g., cost changes).
     */
    public static function needsRehash(string $hashed, array $options = []): bool
    {
        $algo = $options['algo'] ?? PASSWORD_DEFAULT;
        $cost = $options['cost'] ?? 10;

        return password_needs_rehash($hashed, $algo, ['cost' => $cost]);
    }
}
