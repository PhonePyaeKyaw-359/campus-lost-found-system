<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf_or_abort();
destroy_session();
start_secure_session();
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');
redirect('login.php');
