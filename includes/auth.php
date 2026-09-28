<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function current_user(): ?array
{
    start_secure_session();
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $lastActivity = $_SESSION['last_activity'] ?? 0;
    if (!is_int($lastActivity) || (time() - $lastActivity) > SESSION_IDLE_MINUTES * 60) {
        destroy_session();
        return null;
    }

    $user = User::getCurrentUser((int) $userId);
    if ($user === null || !(bool) $user['is_active']) {
        destroy_session();
        return null;
    }

    $_SESSION['last_activity'] = time();
    return $user;
}

/** @return array<string, mixed> */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }

    return $user;
}

/** @param list<string> $roles
 *  @return array<string, mixed>
 */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        ActivityLog::recordAction($user, 'ACCESS_DENIED', null, null, 'Attempted to access a restricted page.', client_ip());
        http_response_code(403);
        exit('Access denied.');
    }

    return $user;
}
