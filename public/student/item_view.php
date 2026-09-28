<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['student']);
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$item = $itemId === false || $itemId === null ? null : FoundItem::findPublicById($itemId, $user);
if ($item === null) {
    http_response_code(404);
    exit('Found item not found.');
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e((string) $item['reference_number']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="search.php">Search found items</a></header>
    <main class="dashboard"><h1><?= e((string) $item['reference_number']) ?> — <?= e((string) $item['item_name']) ?></h1>
        <dl class="details"><dt>Category</dt><dd><?= e((string) $item['category']) ?></dd><?php if ($item['colour']): ?><dt>Colour</dt><dd><?= e((string) $item['colour']) ?></dd><?php endif; ?><?php if ($item['brand']): ?><dt>Brand</dt><dd><?= e((string) $item['brand']) ?></dd><?php endif; ?><dt>Found location</dt><dd><?= e((string) $item['found_location']) ?></dd><?php if ($item['found_datetime']): ?><dt>Found date and time</dt><dd><?= e((string) $item['found_datetime']) ?></dd><?php endif; ?><dt>Description</dt><dd><?= nl2br(e((string) $item['public_description'])) ?></dd></dl>
        <?php if ($item['show_photo_publicly']): ?><img class="report-photo" src="../view_photo.php?type=found&amp;id=<?= (int) $item['id'] ?>" alt="Photo of <?= e((string) $item['item_name']) ?>"><?php endif; ?>
        <p class="action-panel"><a class="primary-link" href="claim_create.php?item_id=<?= (int) $item['id'] ?>">Claim this item</a></p>
    </main>
</body>
</html>
