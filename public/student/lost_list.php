<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['student']);
$reports = LostReport::listForStudent($user);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My lost-item reports | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="lost_create.php">Report a lost item</a></header>
    <main class="dashboard"><h1>My lost-item reports</h1>
        <?php if ($reports === []): ?><p>You have not submitted a lost-item report yet.</p><?php else: ?><ul class="record-list"><?php foreach ($reports as $report): ?><li><a href="lost_view.php?id=<?= (int) $report['id'] ?>"><?= e((string) $report['item_name']) ?></a><span><?= e((string) $report['status']) ?> · <?= e((string) $report['lost_location']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    </main>
</body>
</html>
