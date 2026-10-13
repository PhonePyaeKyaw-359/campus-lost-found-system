<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$students = User::listManagedAccounts($user, 'student');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Student accounts | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="../dashboard.php">Dashboard</a></header><main class="dashboard"><h1>Student accounts</h1><p><a class="primary-link" href="student_account.php">Create Student account</a></p><ul class="record-list"><?php foreach ($students as $student): ?><li><a href="student_account.php?id=<?= (int) $student['id'] ?>"><?= e((string) $student['full_name']) ?> — <?= e((string) $student['id_number']) ?></a><span><?= $student['is_active'] ? 'active' : 'disabled' ?></span></li><?php endforeach; ?></ul></main></body></html>
