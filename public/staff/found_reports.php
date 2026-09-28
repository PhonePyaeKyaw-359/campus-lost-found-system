<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['staff', 'admin']);
$reports = FoundReport::listForStaff($user);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Found-item reports | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="../dashboard.php">Dashboard</a></header>
    <main class="dashboard"><h1>Found-item reports</h1>
        <?php if ($reports === []): ?><p>No found-item reports exist yet.</p><?php else: ?><ul class="record-list"><?php foreach ($reports as $report): ?><li><a href="found_report_view.php?id=<?= (int) $report['id'] ?>"><?= e((string) $report['item_name']) ?></a><span><?= e((string) $report['status']) ?> · <?= e((string) $report['finder_name']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    </main>
</body>
</html>
