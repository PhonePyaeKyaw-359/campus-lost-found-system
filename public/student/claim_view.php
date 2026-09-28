<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['student']);
$claimId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$claim = $claimId === false || $claimId === null ? null : Claim::findByIdForStudent($claimId, $user);
if ($claim === null) { http_response_code(404); exit('Claim not found.'); }
$flash = consume_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $claim['reference_number']) ?> claim | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="claims.php">My claims</a></header><main class="dashboard"><h1><?= e((string) $claim['reference_number']) ?> — <?= e((string) $claim['item_name']) ?></h1><?php if ($flash !== null): ?><p class="message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p><?php endif; ?><dl class="details"><dt>Status</dt><dd><?= e((string) $claim['status']) ?></dd><?php if ($claim['staff_reason']): ?><dt>Staff message</dt><dd><?= e((string) $claim['staff_reason']) ?></dd><?php endif; ?><dt>Lost location</dt><dd><?= e((string) $claim['lost_location']) ?></dd><dt>Lost date and time</dt><dd><?= e((string) $claim['lost_datetime']) ?></dd></dl><?php if ($claim['status'] === 'approved'): ?><p class="message success">Bring your university ID to <?= e(PICKUP_LOCATION) ?>. Staff must check your ID before releasing the item.</p><?php endif; ?></main></body></html>
