<?php
declare(strict_types=1);

/** @return PDO */
function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $localConfigPath = __DIR__ . '/local.php';
    if (!is_file($localConfigPath)) {
        throw new RuntimeException(
            'Missing config/local.php. Copy config/local.example.php and enter local credentials.'
        );
    }

    $settings = require $localConfigPath;
    $db = $settings['database'] ?? null;
    if (!is_array($db)) {
        throw new RuntimeException('Database configuration is invalid.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $connection = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
