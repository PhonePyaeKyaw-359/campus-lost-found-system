<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$claimId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($claimId === false || $claimId === null) { http_response_code(404); exit('Claim not found.'); }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        $action = (string) ($_POST['action'] ?? '');
        match ($action) {
            'approve' => Claim::approve($claimId, $user),
            'reject' => Claim::reject($claimId, (string) ($_POST['reason'] ?? ''), $user),
            'cancel_pending' => Claim::cancelPending($claimId, (string) ($_POST['reason'] ?? ''), $user),
            'cancel_approval' => Claim::cancelApproval($claimId, (string) ($_POST['reason'] ?? ''), $user),
            'id_check' => Claim::markIdChecked($claimId, $user),
            'collect' => Claim::markCollected($claimId, $user),
            default => throw new DomainException('The requested action is not valid.'),
        };
        set_flash('success', 'Claim updated.');
        redirect('staff/claim_view.php?id=' . $claimId);
    } catch (DomainException $exception) { $error = $exception->getMessage(); }
}
$claim = Claim::findForStaff($claimId, $user);
if ($claim === null) { http_response_code(404); exit('Claim not found.'); }
$flash = consume_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Claim <?= (int) $claim['id'] ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="claims.php">Claims</a></header><main class="dashboard"><h1><?= e((string) $claim['reference_number']) ?> — <?= e((string) $claim['item_name']) ?></h1><?php if ($flash !== null): ?><p class="message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p><?php endif; ?><?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?><dl class="details"><dt>Claim status</dt><dd><?= e((string) $claim['status']) ?></dd><dt>Item status</dt><dd><?= e((string) $claim['item_status']) ?></dd><dt>Claimant</dt><dd><?= e((string) $claim['claimant_name']) ?> (<?= e((string) $claim['claimant_id_number']) ?>)</dd><dt>Item private details</dt><dd><?= nl2br(e((string) $claim['item_private_details'])) ?></dd><dt>Claimant details</dt><dd><?= nl2br(e((string) $claim['private_details_given'])) ?></dd><dt>Lost location and time</dt><dd><?= e((string) $claim['lost_location']) ?> · <?= e((string) $claim['lost_datetime']) ?></dd><dt>Storage location</dt><dd><?= e((string) $claim['storage_location']) ?></dd><?php if ($claim['staff_reason']): ?><dt>Staff reason</dt><dd><?= e((string) $claim['staff_reason']) ?></dd><?php endif; ?></dl>
<?php if ($claim['status'] === 'pending'): ?><section class="action-panel"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approve"><button type="submit">Approve claim</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reject"><label for="reject_reason">Rejection reason *</label><textarea id="reject_reason" name="reason" maxlength="255" required></textarea><button type="submit">Reject claim</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel_pending"><label for="cancel_pending_reason">Cancellation reason *</label><textarea id="cancel_pending_reason" name="reason" maxlength="255" required></textarea><button type="submit">Cancel pending claim</button></form></section><?php elseif ($claim['status'] === 'approved'): ?><section class="action-panel"><?php if (!$claim['id_checked']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="id_check"><button type="submit">Record university ID check</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="collect"><button type="submit">Record item collection</button></form><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel_approval"><label for="cancel_approval_reason">Cancellation reason *</label><textarea id="cancel_approval_reason" name="reason" maxlength="255" required></textarea><button type="submit">Cancel approval</button></form></section><?php endif; ?></main></body></html>
