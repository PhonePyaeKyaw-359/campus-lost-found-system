<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['staff', 'admin']);
$reportId = filter_input(INPUT_GET, 'report_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$values = ['item_name' => '', 'category' => '', 'colour' => '', 'brand' => '', 'public_description' => '', 'private_details' => '', 'found_location' => '', 'location_detail' => '', 'found_datetime' => '', 'storage_location' => ''];
$sourceReport = null;
$error = null;

if ($reportId !== false && $reportId !== null) {
    $sourceReport = FoundReport::findForStaff($reportId, $user);
    if ($sourceReport === null || $sourceReport['status'] !== 'submitted') {
        http_response_code(404);
        exit('Found report not available for registration.');
    }
    $values = array_merge($values, [
        'item_name' => (string) $sourceReport['item_name'],
        'category' => (string) $sourceReport['category'],
        'public_description' => (string) $sourceReport['description'],
        'found_location' => (string) $sourceReport['found_location'],
        'location_detail' => (string) ($sourceReport['location_detail'] ?? ''),
        'found_datetime' => str_replace(' ', 'T', substr((string) $sourceReport['found_datetime'], 0, 16)),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    foreach ($values as $key => $_) {
        $values[$key] = (string) ($_POST[$key] ?? '');
    }
    $postedReportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        $itemId = $postedReportId !== false && $postedReportId !== null
            ? FoundItem::createFromReport($postedReportId, $_POST, $_FILES['photo'] ?? null, $user)
            : FoundItem::create($_POST, $_FILES['photo'] ?? null, $user);
        set_flash('success', 'The official found-item record was created.');
        redirect('staff/item_view.php?id=' . $itemId);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Register found item | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="items.php">Found items</a></header>
    <main class="form-card"><h1>Register an official found item</h1>
        <p>This creates the staff-controlled item record. Do not put ownership-verification details in the public description or a published photo.</p>
        <?php if ($sourceReport !== null): ?><p class="message success">Creating a record from found report #<?= (int) $sourceReport['id'] ?>. The report will be marked received when this record is saved.</p><?php endif; ?>
        <?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><?php if ($sourceReport !== null): ?><input type="hidden" name="report_id" value="<?= (int) $sourceReport['id'] ?>"><?php endif; ?>
            <label for="item_name">Item name *</label><input id="item_name" name="item_name" maxlength="100" value="<?= e($values['item_name']) ?>" required>
            <label for="category">Category *</label><select id="category" name="category" required><option value="">Select a category</option><?php foreach (CATEGORIES as $option): ?><option value="<?= e($option) ?>" <?= $values['category'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="colour">Colour</label><select id="colour" name="colour"><option value="">Not specified</option><?php foreach (COLOURS as $option): ?><option value="<?= e($option) ?>" <?= $values['colour'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="brand">Brand</label><input id="brand" name="brand" maxlength="50" value="<?= e($values['brand']) ?>">
            <label for="public_description">Public description *</label><textarea id="public_description" name="public_description" maxlength="5000" required><?= e($values['public_description']) ?></textarea>
            <label for="private_details">Private identifying details *</label><textarea id="private_details" name="private_details" maxlength="5000" required></textarea><p class="help-text">Never shown to students. Record details used to verify a claimant.</p>
            <label for="found_location">Campus location *</label><select id="found_location" name="found_location" required><option value="">Select a location</option><?php foreach (LOCATIONS as $option): ?><option value="<?= e($option) ?>" <?= $values['found_location'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="location_detail">Location detail</label><input id="location_detail" name="location_detail" maxlength="120" value="<?= e($values['location_detail']) ?>">
            <label for="found_datetime">Approximate date and time found</label><input id="found_datetime" name="found_datetime" type="datetime-local" value="<?= e($values['found_datetime']) ?>">
            <label for="storage_location">Storage location *</label><input id="storage_location" name="storage_location" maxlength="100" value="<?= e($values['storage_location']) ?>" required>
            <label for="photo">One photo (JPG or PNG, maximum 2 MB)</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png"><label class="checkbox"><input type="checkbox" name="show_photo_publicly" value="1"> Publish this photo to students after you have reviewed it</label>
            <button type="submit">Create official item record</button>
        </form>
    </main>
</body>
</html>
