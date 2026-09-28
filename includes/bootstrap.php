<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self'; style-src 'self'; script-src 'self'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

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
