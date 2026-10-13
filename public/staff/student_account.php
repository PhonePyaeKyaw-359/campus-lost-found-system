<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$actor = require_role(['staff', 'admin']);
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$account = $userId === false || $userId === null ? null : User::findManagedAccount($userId, $actor);
if ($userId !== false && $userId !== null && ($account === null || $account['role'] !== 'student')) { http_response_code(404); exit('Student account not found.'); }
$values = ['full_name' => $account['full_name'] ?? '', 'id_number' => $account['id_number'] ?? '', 'role' => 'student'];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? '');
    $values['full_name'] = (string) ($_POST['full_name'] ?? ''); $values['id_number'] = (string) ($_POST['id_number'] ?? '');
    try {
        if ($action === 'create') { $newId = User::createAccount(array_merge($_POST, ['role' => 'student']), $actor); set_flash('success', 'Student account created.'); redirect('staff/student_account.php?id=' . $newId); }
        if ($account === null) { throw new DomainException('Student account not found.'); }
        if ($action === 'update') { User::updateAccount((int) $account['id'], $_POST, $actor); set_flash('success', 'Student account updated.'); }
        elseif ($action === 'password') { User::setPassword((int) $account['id'], $_POST, $actor); set_flash('success', 'Student password updated. Communicate it securely in person.'); }
        elseif ($action === 'disable') { User::disableUser((int) $account['id'], $actor); set_flash('success', 'Student account disabled.'); }
        else { if ($action !== 'update' && $action !== 'password' && $action !== 'disable') { throw new DomainException('Invalid account action.'); } }
        redirect('staff/student_account.php?id=' . (int) $account['id']);
    } catch (DomainException $exception) { $error = $exception->getMessage(); }
}
if ($account !== null) { $account = User::findManagedAccount((int) $account['id'], $actor); }
$flash = consume_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $account ? 'Edit' : 'Create' ?> Student account | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="../assets/app.css"></head>
<body><header class="site-header"><a href="../dashboard.php"><?= e(APP_NAME) ?></a><a href="students.php">Student accounts</a></header><main class="form-card"><h1><?= $account ? 'Edit Student account' : 'Create Student account' ?></h1><?php if ($flash): ?><p class="message <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p><?php endif; ?><?php if ($error): ?><p class="message error"><?= e($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $account ? 'update' : 'create' ?>"><label for="full_name">Full name *</label><input id="full_name" name="full_name" maxlength="100" value="<?= e($values['full_name']) ?>" required><label for="id_number">University ID number *</label><input id="id_number" name="id_number" maxlength="20" value="<?= e($values['id_number']) ?>" required><?php if (!$account): ?><label for="new_password">Initial password *</label><input id="new_password" name="new_password" type="password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required><?php endif; ?><button type="submit"><?= $account ? 'Save account details' : 'Create account' ?></button></form>
<?php if ($account): ?><section class="action-panel"><h2>Set new password</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="password"><label for="reset_password">New password *</label><input id="reset_password" name="new_password" type="password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required><button type="submit">Set new password</button></form><?php if ($account['is_active']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="disable"><button type="submit" class="danger-button">Disable account</button></form><?php endif; ?></section><?php endif; ?></main></body></html>
