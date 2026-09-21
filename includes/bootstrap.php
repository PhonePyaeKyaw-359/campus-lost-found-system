<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/../classes/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

set_exception_handler(static function (Throwable $exception): void {
    error_log(sprintf('[%s] %s', APP_NAME, $exception->getMessage()));
    http_response_code(500);
    echo 'An unexpected error occurred. Please try again later.';
});
