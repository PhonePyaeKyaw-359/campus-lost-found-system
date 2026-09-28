<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['staff', 'admin']);
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($itemId === false || $itemId === null) { http_response_code(404); exit('Found item not found.'); }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        FoundItem::setPhotoVisibility($itemId, isset($_POST['show_photo_publicly']), $user);
        set_flash('success', 'Photo visibility was updated.');
        redirect('staff/item_view.php?id=' . $itemId);
    } catch (DomainException $exception) { $error = $exception->getMessage(); }
}
$item = FoundItem::findFullById($itemId, $user);
if ($item === null) { http_response_code(404); exit('Found item not found.'); }
$flash = consume_flash();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $item['reference_number']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="items.php">Found items</a></header>
<main class="dashboard"><h1><?= e((string) $item['reference_number']) ?> — <?= e((string) $item['item_name']) ?></h1><?php if ($flash !== null): ?><p class="message <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p><?php endif; ?><?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>
<dl class="details"><dt>Status</dt><dd><?= e((string) $item['status']) ?></dd><dt>Storage location</dt><dd><?= e((string) $item['storage_location']) ?></dd><dt>Found location</dt><dd><?= e((string) $item['found_location']) ?><?= $item['location_detail'] ? ', ' . e((string) $item['location_detail']) : '' ?></dd><dt>Public description</dt><dd><?= nl2br(e((string) $item['public_description'])) ?></dd><dt>Private identifying details</dt><dd><?= nl2br(e((string) $item['private_details'])) ?></dd><dt>Received by</dt><dd><?= e((string) $item['received_by_name']) ?></dd></dl>
<?php if ($item['photo_path']): ?><img class="report-photo" src="../view_photo.php?type=found&amp;id=<?= (int) $item['id'] ?>" alt="Found item photo for <?= e((string) $item['item_name']) ?>"><form method="post" class="action-panel"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="checkbox"><input type="checkbox" name="show_photo_publicly" value="1" <?= $item['show_photo_publicly'] ? 'checked' : '' ?>> Publish photo to students</label><button type="submit">Save photo visibility</button></form><?php endif; ?>
</main></body></html>
