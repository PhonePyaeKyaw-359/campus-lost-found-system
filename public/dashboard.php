<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$flash = consume_flash();
$dashboardDescriptions = [
    'student' => 'Search found items, report a lost item, or view the status of your reports and claims.',
    'staff' => 'Review found reports, claims awaiting review, approved collections, and items in storage.',
    'admin' => 'Manage campus lost-and-found work, accounts, and recent activity.',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <header class="site-header">
        <a href="dashboard.php"><?= e(APP_NAME) ?></a>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="link-button">Log out</button>
        </form>
    </header>
    <main class="dashboard">
        <h1>Welcome, <?= e((string) $user['full_name']) ?></h1>
        <p class="role-label"><?= e(ucfirst((string) $user['role'])) ?> account</p>
        <?php if ($flash !== null): ?>
            <p class="message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>
        <p><?= e($dashboardDescriptions[(string) $user['role']] ?? '') ?></p>
        <?php if ($user['role'] === 'student'): ?>
            <p><a class="primary-link" href="student/lost_create.php">Report a lost item</a> <a href="student/lost_list.php">My lost reports</a></p>
            <p><a class="primary-link" href="student/found_create.php">Report a found item</a> <a href="student/found_list.php">My found reports</a></p>
        <?php elseif ($user['role'] === 'staff' || $user['role'] === 'admin'): ?>
            <p><a class="primary-link" href="staff/found_reports.php">Review found-item reports</a></p>
        <?php else: ?>
            <p>The next development feature will add the relevant actions to this dashboard.</p>
        <?php endif; ?>
    </main>
</body>
</html>
