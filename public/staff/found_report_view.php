<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['staff', 'admin']);
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($reportId === false || $reportId === null) {
    http_response_code(404);
    exit('Report not found.');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        FoundReport::close($reportId, (string) ($_POST['staff_reason'] ?? ''), $user);
        set_flash('success', 'The found-item report was closed.');
        redirect('staff/found_report_view.php?id=' . $reportId);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}

$report = FoundReport::findForStaff($reportId, $user);
if ($report === null) {
    http_response_code(404);
    exit('Report not found.');
}
$flash = consume_flash();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $report['item_name']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="found_reports.php">Found reports</a></header>
    <main class="dashboard"><h1><?= e((string) $report['item_name']) ?></h1>
        <?php if ($flash !== null): ?><p class="message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p><?php endif; ?>
        <?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <dl class="details"><dt>Status</dt><dd><?= e((string) $report['status']) ?></dd><dt>Finder</dt><dd><?= e((string) $report['finder_name']) ?> (<?= e((string) $report['finder_id_number']) ?>)</dd><dt>Category</dt><dd><?= e((string) $report['category']) ?></dd><dt>Location</dt><dd><?= e((string) $report['found_location']) ?><?= $report['location_detail'] ? ', ' . e((string) $report['location_detail']) : '' ?></dd><dt>Date and time found</dt><dd><?= e((string) $report['found_datetime']) ?></dd><dt>Description</dt><dd><?= nl2br(e((string) $report['description'])) ?></dd><?php if ($report['staff_reason']): ?><dt>Staff reason</dt><dd><?= e((string) $report['staff_reason']) ?></dd><?php endif; ?></dl>
        <?php if ($report['status'] === 'submitted'): ?>
            <section class="action-panel"><h2>Close this report</h2><p>Close only if the item was not handed in or this is a duplicate. Registering the physical item will be added in the next feature.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="staff_reason">Reason *</label><textarea id="staff_reason" name="staff_reason" maxlength="255" required></textarea><button type="submit">Close report</button></form></section>
        <?php endif; ?>
    </main>
</body>
</html>
