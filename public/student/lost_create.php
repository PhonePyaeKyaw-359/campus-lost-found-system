<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['student']);
$values = [
    'item_name' => '', 'category' => '', 'colour' => '', 'brand' => '', 'description' => '',
    'private_details' => '', 'lost_location' => '', 'location_detail' => '', 'lost_datetime' => '',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    foreach ($values as $key => $_) {
        $values[$key] = (string) ($_POST[$key] ?? '');
    }

    try {
        $reportId = LostReport::create($_POST, $user, $_FILES['photo'] ?? null);
        set_flash('success', 'Your lost-item report has been created.');
        redirect('student/lost_view.php?id=' . $reportId);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report a lost item | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="lost_list.php">My reports</a></header>
    <main class="form-card">
        <h1>Report a lost item</h1>
        <p>Provide enough detail for staff to identify possible matches. Private details are not shown to other students.</p>
        <?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="item_name">Item name *</label><input id="item_name" name="item_name" maxlength="100" value="<?= e($values['item_name']) ?>" required>
            <label for="category">Category *</label><select id="category" name="category" required><option value="">Select a category</option><?php foreach (CATEGORIES as $option): ?><option value="<?= e($option) ?>" <?= $values['category'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="colour">Colour</label><select id="colour" name="colour"><option value="">Not specified</option><?php foreach (COLOURS as $option): ?><option value="<?= e($option) ?>" <?= $values['colour'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="brand">Brand</label><input id="brand" name="brand" maxlength="50" value="<?= e($values['brand']) ?>">
            <label for="description">Description *</label><textarea id="description" name="description" maxlength="5000" required><?= e($values['description']) ?></textarea>
            <label for="private_details">Private identifying detail</label><textarea id="private_details" name="private_details" maxlength="5000" aria-describedby="private-help"><?= e($values['private_details']) ?></textarea><p id="private-help" class="help-text">For example, a name written inside or a unique mark. Staff and Admins only.</p>
            <label for="lost_location">Campus location *</label><select id="lost_location" name="lost_location" required><option value="">Select a location</option><?php foreach (LOCATIONS as $option): ?><option value="<?= e($option) ?>" <?= $values['lost_location'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="location_detail">Location detail</label><input id="location_detail" name="location_detail" maxlength="120" value="<?= e($values['location_detail']) ?>" placeholder="For example, Room 204">
            <label for="lost_datetime">Approximate date and time lost *</label><input id="lost_datetime" name="lost_datetime" type="datetime-local" value="<?= e($values['lost_datetime']) ?>" required>
            <label for="photo">One photo (JPG or PNG, maximum 2 MB)</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png">
            <button type="submit">Submit lost-item report</button>
        </form>
    </main>
</body>
</html>
