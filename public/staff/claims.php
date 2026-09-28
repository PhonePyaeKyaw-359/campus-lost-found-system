<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$claims = Claim::listForStaff($user);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Claims | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="../dashboard.php">Dashboard</a></header><main class="dashboard"><h1>Claims</h1><?php if ($claims === []): ?><p>There are no claims yet.</p><?php else: ?><ul class="record-list"><?php foreach ($claims as $claim): ?><li><a href="claim_view.php?id=<?= (int) $claim['id'] ?>"><?= e((string) $claim['reference_number']) ?> — <?= e((string) $claim['item_name']) ?></a><span><?= e((string) $claim['status']) ?> · <?= e((string) $claim['claimant_name']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></main></body></html>
