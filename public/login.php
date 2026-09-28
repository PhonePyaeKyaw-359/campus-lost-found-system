<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $idNumber = trim((string) ($_POST['id_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = $idNumber !== '' && $password !== '' ? User::login($idNumber, $password, client_ip()) : null;

    if ($user === null) {
        set_flash('error', 'The university ID number or password is incorrect.');
        redirect('login.php');
    }

    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    redirect('dashboard.php');
}

$flash = consume_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <main class="auth-card">
        <h1><?= e(APP_NAME) ?></h1>
        <p>Log in with your university account.</p>
        <?php if ($flash !== null): ?>
            <p class="message <?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></p>
        <?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="id_number">University ID number</label>
            <input id="id_number" name="id_number" type="text" maxlength="20" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="submit">Log in</button>
        </form>
    </main>
</body>
</html>
