<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$type = $_GET['type'] ?? '';
$recordId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($type !== 'lost' || $recordId === false || $recordId === null) {
    http_response_code(404);
    exit('Photo not found.');
}

$relativePath = LostReport::canViewPhoto($recordId, $user);
$path = $relativePath === null ? null : PhotoStorage::absoluteLostPath($relativePath);
if ($path === null) {
    http_response_code(404);
    exit('Photo not found.');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
    http_response_code(404);
    exit('Photo not found.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
