<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$flash = consume_flash();
$metrics = DashboardMetrics::forUser($user);
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
            <p class="metric-summary">Open lost reports: <?= (int) $metrics['open_lost_reports'] ?> · Pending claims: <?= (int) $metrics['pending_claims'] ?></p>
            <p><a class="primary-link" href="student/lost_create.php">Report a lost item</a> <a href="student/lost_list.php">My lost reports</a></p>
            <p><a class="primary-link" href="student/found_create.php">Report a found item</a> <a href="student/found_list.php">My found reports</a></p>
            <p><a class="primary-link" href="student/search.php">Search found items</a></p>
            <p><a href="student/claims.php">View my claims</a></p>
        <?php elseif ($user['role'] === 'staff' || $user['role'] === 'admin'): ?>
            <ul class="metric-list"><li>Found reports awaiting receipt: <?= (int) $metrics['found_reports_awaiting_receipt'] ?></li><li>Claims awaiting review: <?= (int) $metrics['claims_awaiting_review'] ?></li><li>Approved claims awaiting collection: <?= (int) $metrics['approved_claims_awaiting_collection'] ?></li><li>Items in storage: <?= (int) $metrics['items_in_storage'] ?></li></ul>
            <p><a class="primary-link" href="staff/found_reports.php">Review found-item reports</a></p>
            <p><a class="primary-link" href="staff/item_create.php">Register found item</a> <a href="staff/items.php">View official found items</a></p>
            <p><a class="primary-link" href="staff/claims.php">Review claims</a></p>
            <p><a href="staff/students.php">Manage Student accounts</a></p>
            <p><a href="staff/history.php">View activity history</a></p>
            <?php if ($user['role'] === 'admin'): ?><p><a href="admin/accounts.php">Manage Staff and Admin accounts</a></p><?php endif; ?>
        <?php else: ?>
            <p>The next development feature will add the relevant actions to this dashboard.</p>
        <?php endif; ?>
    </main>
</body>
</html>
