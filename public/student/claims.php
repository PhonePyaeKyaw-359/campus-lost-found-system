<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['student']);
$claims = Claim::listForStudent($user);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My claims | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="search.php">Search found items</a></header><main class="dashboard"><h1>My claims</h1><?php if ($claims === []): ?><p>You have not submitted a claim yet.</p><?php else: ?><ul class="record-list"><?php foreach ($claims as $claim): ?><li><a href="claim_view.php?id=<?= (int) $claim['id'] ?>"><?= e((string) $claim['reference_number']) ?> — <?= e((string) $claim['item_name']) ?></a><span><?= e((string) $claim['status']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></main></body></html>
