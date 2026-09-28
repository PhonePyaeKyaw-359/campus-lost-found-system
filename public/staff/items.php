<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$items = FoundItem::listForStaff($user);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Found items | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="item_create.php">Register found item</a></header>
<main class="dashboard"><h1>Official found items</h1><?php if ($items === []): ?><p>No official found items exist yet.</p><?php else: ?><ul class="record-list"><?php foreach ($items as $item): ?><li><a href="item_view.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['reference_number']) ?> — <?= e((string) $item['item_name']) ?></a><span><?= e((string) $item['status']) ?> · <?= e((string) $item['storage_location']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></main>
</body></html>
