<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$user = require_role(['student']);
$values = ['item_name' => '', 'category' => '', 'description' => '', 'found_location' => '', 'location_detail' => '', 'found_datetime' => ''];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    foreach ($values as $key => $_) {
        $values[$key] = (string) ($_POST[$key] ?? '');
    }
    try {
        $reportId = FoundReport::create($_POST, $user);
        set_flash('success', 'Your found-item report was submitted. Please hand the physical item to designated staff.');
        redirect('student/found_view.php?id=' . $reportId);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Report a found item | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body>
    <header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="found_list.php">My found reports</a></header>
    <main class="form-card">
        <h1>Report a found item</h1>
        <p>After submitting this report, hand the physical item to designated university staff. The report is not published to other students.</p>
        <?php if ($error !== null): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="item_name">Item name *</label><input id="item_name" name="item_name" maxlength="100" value="<?= e($values['item_name']) ?>" required>
            <label for="category">Category *</label><select id="category" name="category" required><option value="">Select a category</option><?php foreach (CATEGORIES as $option): ?><option value="<?= e($option) ?>" <?= $values['category'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="description">Description *</label><textarea id="description" name="description" maxlength="5000" required><?= e($values['description']) ?></textarea>
            <label for="found_location">Campus location *</label><select id="found_location" name="found_location" required><option value="">Select a location</option><?php foreach (LOCATIONS as $option): ?><option value="<?= e($option) ?>" <?= $values['found_location'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
            <label for="location_detail">Location detail</label><input id="location_detail" name="location_detail" maxlength="120" value="<?= e($values['location_detail']) ?>" placeholder="For example, Room 204">
            <label for="found_datetime">Approximate date and time found *</label><input id="found_datetime" name="found_datetime" type="datetime-local" value="<?= e($values['found_datetime']) ?>" required>
            <button type="submit">Submit found-item report</button>
        </form>
    </main>
</body>
</html>
