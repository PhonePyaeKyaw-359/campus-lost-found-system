<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($itemId === false || $itemId === null) { http_response_code(404); exit('Found item not found.'); }
$item = FoundItem::findFullById($itemId, $user);
if ($item === null) { http_response_code(404); exit('Found item not found.'); }
$values = ['item_name' => (string) $item['item_name'], 'category' => (string) $item['category'], 'colour' => (string) ($item['colour'] ?? ''), 'brand' => (string) ($item['brand'] ?? ''), 'public_description' => (string) $item['public_description'], 'private_details' => (string) $item['private_details'], 'found_location' => (string) $item['found_location'], 'location_detail' => (string) ($item['location_detail'] ?? ''), 'found_datetime' => $item['found_datetime'] ? str_replace(' ', 'T', substr((string) $item['found_datetime'], 0, 16)) : '', 'storage_location' => (string) $item['storage_location']];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    foreach ($values as $key => $_) { $values[$key] = (string) ($_POST[$key] ?? ''); }
    try {
        FoundItem::update($itemId, $_POST, $user);
        set_flash('success', 'Found-item record updated.');
        redirect('staff/item_view.php?id=' . $itemId);
    } catch (DomainException $exception) { $error = $exception->getMessage(); }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit <?= e((string) $item['reference_number']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="item_view.php?id=<?= (int) $itemId ?>">Back to item</a></header><main class="form-card"><h1>Edit <?= e((string) $item['reference_number']) ?></h1><p>Keep private ownership-verification details out of the public description.</p><?php if ($error): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="item_name">Item name *</label><input id="item_name" name="item_name" maxlength="100" value="<?= e($values['item_name']) ?>" required><label for="category">Category *</label><select id="category" name="category" required><?php foreach (CATEGORIES as $option): ?><option value="<?= e($option) ?>" <?= $values['category'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><label for="colour">Colour</label><select id="colour" name="colour"><option value="">Not specified</option><?php foreach (COLOURS as $option): ?><option value="<?= e($option) ?>" <?= $values['colour'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><label for="brand">Brand</label><input id="brand" name="brand" maxlength="50" value="<?= e($values['brand']) ?>"><label for="public_description">Public description *</label><textarea id="public_description" name="public_description" maxlength="5000" required><?= e($values['public_description']) ?></textarea><label for="private_details">Private identifying details *</label><textarea id="private_details" name="private_details" maxlength="5000" required><?= e($values['private_details']) ?></textarea><label for="found_location">Campus location *</label><select id="found_location" name="found_location" required><?php foreach (LOCATIONS as $option): ?><option value="<?= e($option) ?>" <?= $values['found_location'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><label for="location_detail">Location detail</label><input id="location_detail" name="location_detail" maxlength="120" value="<?= e($values['location_detail']) ?>"><label for="found_datetime">Approximate date and time found</label><input id="found_datetime" name="found_datetime" type="datetime-local" value="<?= e($values['found_datetime']) ?>"><label for="storage_location">Storage location *</label><input id="storage_location" name="storage_location" maxlength="100" value="<?= e($values['storage_location']) ?>" required><button type="submit">Save item changes</button></form></main></body></html>
