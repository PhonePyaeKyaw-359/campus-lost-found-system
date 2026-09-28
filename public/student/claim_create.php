<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['student']);
$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$item = $itemId === false || $itemId === null ? null : FoundItem::findPublicById($itemId, $user);
if ($item === null) { http_response_code(404); exit('Found item not found.'); }
$values = ['private_details_given' => '', 'lost_location' => '', 'lost_datetime' => '', 'additional_info' => ''];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    foreach ($values as $key => $_) { $values[$key] = (string) ($_POST[$key] ?? ''); }
    try {
        $claimId = Claim::submit((int) $item['id'], $_POST, $user);
        set_flash('success', 'Your claim was submitted for staff review.');
        redirect('student/claim_view.php?id=' . $claimId);
    } catch (DomainException $exception) { $error = $exception->getMessage(); }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Claim <?= e((string) $item['reference_number']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="item_view.php?id=<?= (int) $item['id'] ?>">Back to item</a></header><main class="form-card"><h1>Claim <?= e((string) $item['reference_number']) ?></h1><p>Provide details that are not shown on the public item page. Staff will compare them with the recorded item details.</p><?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="private_details_given">Private identifying details *</label><textarea id="private_details_given" name="private_details_given" maxlength="5000" required><?= e($values['private_details_given']) ?></textarea><label for="lost_location">Where did you lose it? *</label><select id="lost_location" name="lost_location" required><option value="">Select a location</option><?php foreach (LOCATIONS as $option): ?><option value="<?= e($option) ?>" <?= $values['lost_location'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><label for="lost_datetime">Approximate date and time lost *</label><input id="lost_datetime" name="lost_datetime" type="datetime-local" value="<?= e($values['lost_datetime']) ?>" required><label for="additional_info">Additional information</label><textarea id="additional_info" name="additional_info" maxlength="5000"><?= e($values['additional_info']) ?></textarea><button type="submit">Submit claim</button></form></main></body></html>
