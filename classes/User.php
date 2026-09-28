<?php
declare(strict_types=1);

final class User
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    /** @return array<string, mixed>|null */
    public static function login(string $idNumber, string $password, ?string $ipAddress): ?array
    {
        $pdo = database();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'SELECT id, id_number, full_name, role, password_hash, is_active, failed_attempts, locked_until
                 FROM users WHERE id_number = :id_number FOR UPDATE'
            );
            $statement->execute(['id_number' => $idNumber]);
            $user = $statement->fetch();

            if (!is_array($user)) {
                password_verify($password, self::DUMMY_PASSWORD_HASH);
                ActivityLog::recordAction(null, 'FAILED_LOGIN', 'user', null, 'Unknown university ID.', $ipAddress);
                $pdo->commit();
                return null;
            }

            $isLocked = $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time();
            $passwordIsValid = password_verify($password, (string) $user['password_hash']);
            if (!(bool) $user['is_active'] || $isLocked || !$passwordIsValid) {
                if (!$isLocked && (bool) $user['is_active']) {
                    $failedAttempts = (int) $user['failed_attempts'] + 1;
                    $lockedUntil = $failedAttempts >= LOGIN_MAX_ATTEMPTS
                        ? (new DateTimeImmutable('now'))->modify('+' . LOGIN_LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s')
                        : null;
                    $update = $pdo->prepare(
                        'UPDATE users SET failed_attempts = :failed_attempts, locked_until = :locked_until WHERE id = :id'
                    );
                    $update->execute([
                        'failed_attempts' => $failedAttempts,
                        'locked_until' => $lockedUntil,
                        'id' => $user['id'],
                    ]);
                }

                ActivityLog::recordAction($user, 'FAILED_LOGIN', 'user', (int) $user['id'], 'Invalid login attempt.', $ipAddress);
                $pdo->commit();
                return null;
            }

            $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id')
                ->execute(['id' => $user['id']]);
            $user['failed_attempts'] = 0;
            $user['locked_until'] = null;
            ActivityLog::recordAction($user, 'LOGIN', 'user', (int) $user['id'], 'User logged in.', $ipAddress);
            $pdo->commit();

            return $user;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public static function getCurrentUser(int $userId): ?array
    {
        $statement = database()->prepare(
            'SELECT id, id_number, full_name, role, is_active FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }
}
