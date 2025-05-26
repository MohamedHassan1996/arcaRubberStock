<?php

namespace Config;

use PDO;
use PDOException;

class DbConnection
{
    private static $conn = null;

    private function __construct() {}

    public static function connection()
    {
        $config = require __DIR__ . '/database.php';
        $dbConfig = $config['connections'][$config['default']];

        if (self::$conn === null) {
            try {
                self::$conn = new PDO(
                    "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}",
                    $dbConfig['user'],
                    $dbConfig['pass']
                );
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }

        return self::$conn;
    }
}
