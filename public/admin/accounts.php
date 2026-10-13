<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['admin']);
$accounts = User::listManagedAccounts($user);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff and Admin accounts | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="../dashboard.php">Dashboard</a></header><main class="dashboard"><h1>Staff and Admin accounts</h1><p><a class="primary-link" href="account_form.php">Create account</a></p><ul class="record-list"><?php foreach ($accounts as $account): ?><?php if ($account['role'] !== 'student'): ?><li><a href="account_form.php?id=<?= (int) $account['id'] ?>"><?= e((string) $account['full_name']) ?> — <?= e((string) $account['id_number']) ?></a><span><?= e((string) $account['role']) ?> · <?= $account['is_active'] ? 'active' : 'disabled' ?></span></li><?php endif; ?><?php endforeach; ?></ul></main></body></html>
