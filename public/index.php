<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

http_response_code(200);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
</head>
<body>
    <main>
        <h1><?= e(APP_NAME) ?></h1>
        <p>The application foundation is ready. Authentication is the next feature.</p>
    </main>
</body>
</html>
