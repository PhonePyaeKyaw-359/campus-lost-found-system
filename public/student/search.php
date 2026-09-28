<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['student']);
$filters = [
    'keyword' => trim((string) ($_GET['keyword'] ?? '')),
    'category' => (string) ($_GET['category'] ?? ''),
    'colour' => (string) ($_GET['colour'] ?? ''),
    'location' => (string) ($_GET['location'] ?? ''),
];
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$error = null;
$search = ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
try {
    $search = FoundItem::searchAvailable($filters, $page === false || $page === null ? 1 : $page, $user);
} catch (DomainException $exception) {
    $error = $exception->getMessage();
}

function search_page_url(array $filters, int $page): string
{
    return 'search.php?' . http_build_query(array_merge($filters, ['page' => $page]));
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Search found items | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="lost_create.php">Report a lost item</a></header>
    <main class="form-card"><h1>Search found items</h1><p>Only items currently in storage are shown. Do not use public information alone to prove ownership.</p>
        <form method="get" class="filter-form"><label for="keyword">Keyword</label><input id="keyword" name="keyword" maxlength="100" value="<?= e($filters['keyword']) ?>" placeholder="Name, brand, or public description">
            <label for="category">Category</label><select id="category" name="category"><option value="">All categories</option><?php foreach (CATEGORIES as $option): ?><option value="<?= e($option) ?>" <?= $filters['category'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="colour">Colour</label><select id="colour" name="colour"><option value="">All colours</option><?php foreach (COLOURS as $option): ?><option value="<?= e($option) ?>" <?= $filters['colour'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="location">Campus location</label><select id="location" name="location"><option value="">All locations</option><?php foreach (LOCATIONS as $option): ?><option value="<?= e($option) ?>" <?= $filters['location'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <button type="submit">Search</button>
        </form>
        <?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php else: ?><p><?= (int) $search['total'] ?> item<?= $search['total'] === 1 ? '' : 's' ?> found.</p>
            <?php if ($search['items'] === []): ?><p>No available found items match your search.</p><?php else: ?><ul class="search-results"><?php foreach ($search['items'] as $item): ?><li><h2><a href="item_view.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['reference_number']) ?> — <?= e((string) $item['item_name']) ?></a></h2><p><?= e((string) $item['category']) ?><?= $item['colour'] ? ' · ' . e((string) $item['colour']) : '' ?> · <?= e((string) $item['found_location']) ?></p><p><?= e(mb_strimwidth((string) $item['public_description'], 0, 180, '…')) ?></p></li><?php endforeach; ?></ul>
                <?php if ($search['total_pages'] > 1): ?><nav class="pagination" aria-label="Search pages"><?php if ($search['page'] > 1): ?><a href="<?= e(search_page_url($filters, $search['page'] - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= (int) $search['page'] ?> of <?= (int) $search['total_pages'] ?></span><?php if ($search['page'] < $search['total_pages']): ?><a href="<?= e(search_page_url($filters, $search['page'] + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
