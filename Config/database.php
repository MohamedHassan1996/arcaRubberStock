<?php

require_once __DIR__ . '/../vendor/autoload.php'; // adjust path as needed
use Dotenv\Dotenv;

// Load environment variables (only once ideally)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

return [

    'default' => $_ENV['DB_CONNECTION_NAME'] ?? 'mysql',

    'connections' => [
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'name' => $_ENV['DB_NAME'] ?? 'test',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'pass' => $_ENV['DB_PASS'] ?? '',
        ],
        // You can add more connections like pgsql, sqlite, etc.
    ]

];
