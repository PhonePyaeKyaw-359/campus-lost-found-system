<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['student']);
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$report = $reportId === false || $reportId === null ? null : FoundReport::findByIdForStudent($reportId, $user);
if ($report === null) {
    http_response_code(404);
    exit('Report not found.');
}
$flash = consume_flash();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $report['item_name']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="found_list.php">My reports</a></header>
    <main class="dashboard"><h1><?= e((string) $report['item_name']) ?></h1>
        <?php if ($flash !== null): ?><p class="message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p><?php endif; ?>
        <dl class="details"><dt>Status</dt><dd><?= e((string) $report['status']) ?></dd><dt>Category</dt><dd><?= e((string) $report['category']) ?></dd><dt>Location</dt><dd><?= e((string) $report['found_location']) ?><?= $report['location_detail'] ? ', ' . e((string) $report['location_detail']) : '' ?></dd><dt>Date and time found</dt><dd><?= e((string) $report['found_datetime']) ?></dd><dt>Description</dt><dd><?= nl2br(e((string) $report['description'])) ?></dd></dl>
    </main>
</body>
</html>
