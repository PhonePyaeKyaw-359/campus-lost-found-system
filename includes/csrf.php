<?php
declare(strict_types=1);

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_or_abort(): void
{
    start_secure_session();
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($sessionToken)
        || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        exit('Invalid request. Please return to the previous page and try again.');
    }
}
