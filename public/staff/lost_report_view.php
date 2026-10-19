<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($reportId === false || $reportId === null) { http_response_code(404); exit('Lost report not found.'); }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'match') { $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); if ($itemId === false || $itemId === null) { throw new DomainException('Select a possible matching item.'); } LostReport::setPossibleMatch($reportId, $itemId, $user); set_flash('success', 'Possible match recorded. The student still must submit a claim.'); }
        elseif ($action === 'resolve') { LostReport::markResolved($reportId, $user); set_flash('success', 'Lost report marked resolved.'); }
        else { throw new DomainException('Invalid report action.'); }
        redirect('staff/lost_report_view.php?id=' . $reportId);
    } catch (DomainException $exception) { $error = $exception->getMessage(); }
}
$report = LostReport::findFullById($reportId, $user);
if ($report === null) { http_response_code(404); exit('Lost report not found.'); }
$items = $report['status'] === 'resolved' ? [] : FoundItem::listAvailableForPossibleMatch($user);
$flash = consume_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $report['item_name']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="lost_reports.php">Lost reports</a></header><main class="dashboard"><h1><?= e((string) $report['item_name']) ?></h1><?php if ($flash): ?><p class="message <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p><?php endif; ?><?php if ($error): ?><p class="message error"><?= e($error) ?></p><?php endif; ?><dl class="details"><dt>Status</dt><dd><?= e((string) $report['status']) ?></dd><dt>Student</dt><dd><?= e((string) $report['reporter_name']) ?> (<?= e((string) $report['reporter_id_number']) ?>)</dd><dt>Category</dt><dd><?= e((string) $report['category']) ?></dd><dt>Location</dt><dd><?= e((string) $report['lost_location']) ?><?= $report['location_detail'] ? ', ' . e((string) $report['location_detail']) : '' ?></dd><dt>Lost time</dt><dd><?= e((string) $report['lost_datetime']) ?></dd><dt>Description</dt><dd><?= nl2br(e((string) $report['description'])) ?></dd><dt>Private details</dt><dd><?= nl2br(e((string) ($report['private_details'] ?? ''))) ?></dd><?php if ($report['possible_match_reference']): ?><dt>Possible match</dt><dd><?= e((string) $report['possible_match_reference']) ?> — <?= e((string) $report['possible_match_name']) ?></dd><?php endif; ?></dl><?php if ($report['photo_path']): ?><img class="report-photo" src="../view_photo.php?type=lost&amp;id=<?= (int) $report['id'] ?>" alt="Lost item photo for <?= e((string) $report['item_name']) ?>"><?php endif; ?>
<?php if ($report['status'] !== 'resolved'): ?><section class="action-panel"><h2>Set possible match</h2><p>This is a staff note only. It does not prove ownership or bypass the claim process.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="match"><label for="item_id">Available found item</label><select id="item_id" name="item_id" required><option value="">Select item</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['reference_number']) ?> — <?= e((string) $item['item_name']) ?> (<?= e((string) $item['found_location']) ?>)</option><?php endforeach; ?></select><button type="submit">Record possible match</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resolve"><button type="submit" class="danger-button">Mark lost report resolved</button></form></section><?php endif; ?></main></body></html>
