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

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     */
    public static function createAccount(array $data, array $actor): int
    {
        $role = (string) ($data['role'] ?? '');
        self::assertCanManageRole($actor, $role);
        $idNumber = self::idNumber($data);
        $fullName = self::fullName($data);
        $password = self::newPassword($data, true);

        $pdo = database();
        $pdo->beginTransaction();
        try {
            self::assertIdNumberAvailable($pdo, $idNumber);
            $statement = $pdo->prepare(
                'INSERT INTO users (id_number, full_name, role, password_hash, created_by)
                 VALUES (:id_number, :full_name, :role, :password_hash, :created_by)'
            );
            $statement->execute([
                'id_number' => $idNumber,
                'full_name' => $fullName,
                'role' => $role,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_by' => $actor['id'],
            ]);
            $userId = (int) $pdo->lastInsertId();
            ActivityLog::recordAction($actor, 'ACCOUNT_CREATED', 'user', $userId, 'Account created.', client_ip());
            $pdo->commit();
            return $userId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     */
    public static function updateAccount(int $userId, array $data, array $actor): void
    {
        $idNumber = self::idNumber($data);
        $fullName = self::fullName($data);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $target = self::lockUser($pdo, $userId);
            self::assertCanManageRole($actor, (string) $target['role']);
            self::assertIdNumberAvailable($pdo, $idNumber, $userId);
            $pdo->prepare('UPDATE users SET id_number = :id_number, full_name = :full_name WHERE id = :id')
                ->execute(['id_number' => $idNumber, 'full_name' => $fullName, 'id' => $userId]);
            ActivityLog::recordAction($actor, 'ACCOUNT_EDITED', 'user', $userId, 'Account details updated.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     */
    public static function setPassword(int $userId, array $data, array $actor): void
    {
        $password = self::newPassword($data, true);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $target = self::lockUser($pdo, $userId);
            self::assertCanManageRole($actor, (string) $target['role']);
            $pdo->prepare('UPDATE users SET password_hash = :password_hash, failed_attempts = 0, locked_until = NULL WHERE id = :id')
                ->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
            ActivityLog::recordAction($actor, 'PASSWORD_RESET', 'user', $userId, 'Password set by authorized user.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    public static function disableUser(int $userId, array $actor): void
    {
        if ((int) $actor['id'] === $userId) {
            throw new DomainException('You cannot disable your own account.');
        }
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $target = self::lockUser($pdo, $userId);
            self::assertCanManageRole($actor, (string) $target['role']);
            if (!(bool) $target['is_active']) {
                throw new DomainException('This account is already disabled.');
            }
            if ($target['role'] === 'admin') {
                $activeAdminRows = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE")->fetchAll();
                $activeAdmins = count($activeAdminRows);
                if ($activeAdmins <= 1) {
                    throw new DomainException('The last active Admin account cannot be disabled.');
                }
            }
            $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute(['id' => $userId]);
            ActivityLog::recordAction($actor, 'ACCOUNT_DISABLED', 'user', $userId, 'Account disabled.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listManagedAccounts(array $actor, ?string $role = null): array
    {
        self::assertActorCanManage($actor);
        if ($actor['role'] === 'staff') { $role = 'student'; }
        if ($role !== null && !in_array($role, ['student', 'staff', 'admin'], true)) {
            throw new DomainException('Invalid account role.');
        }
        $sql = 'SELECT id, id_number, full_name, role, is_active, locked_until, created_at FROM users';
        $parameters = [];
        if ($role !== null) { $sql .= ' WHERE role = :role'; $parameters['role'] = $role; }
        $sql .= ' ORDER BY role, full_name';
        $statement = database()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findManagedAccount(int $userId, array $actor): ?array
    {
        self::assertActorCanManage($actor);
        $statement = database()->prepare('SELECT id, id_number, full_name, role, is_active, locked_until, created_at FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user)) { return null; }
        self::assertCanManageRole($actor, (string) $user['role']);
        return $user;
    }

    /** @param array<string, mixed> $data */
    private static function idNumber(array $data): string
    {
        $value = trim((string) ($data['id_number'] ?? ''));
        if ($value === '' || mb_strlen($value) > 20) { throw new DomainException('A university ID number of up to 20 characters is required.'); }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function fullName(array $data): string
    {
        $value = trim((string) ($data['full_name'] ?? ''));
        if ($value === '' || mb_strlen($value) > 100) { throw new DomainException('A full name of up to 100 characters is required.'); }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function newPassword(array $data, bool $required): string
    {
        $password = (string) ($data['new_password'] ?? '');
        if (($required && $password === '') || mb_strlen($password) < MIN_PASSWORD_LENGTH) {
            throw new DomainException('The new password must contain at least ' . MIN_PASSWORD_LENGTH . ' characters.');
        }
        return $password;
    }

    /** @param array<string, mixed> $actor */
    private static function assertActorCanManage(array $actor): void
    {
        if (!in_array($actor['role'] ?? '', ['staff', 'admin'], true)) { throw new LogicException('Only staff or admins can manage accounts.'); }
    }

    /** @param array<string, mixed> $actor */
    private static function assertCanManageRole(array $actor, string $targetRole): void
    {
        self::assertActorCanManage($actor);
        if ($targetRole === 'student') { return; }
        if ($actor['role'] !== 'admin' || !in_array($targetRole, ['staff', 'admin'], true)) {
            throw new LogicException('Only an Admin can manage Staff or Admin accounts.');
        }
    }

    /** @return array<string, mixed> */
    private static function lockUser(PDO $pdo, int $userId): array
    {
        $statement = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user)) { throw new OutOfBoundsException('Account not found.'); }
        return $user;
    }

    private static function assertIdNumberAvailable(PDO $pdo, string $idNumber, ?int $excludeUserId = null): void
    {
        $sql = 'SELECT id FROM users WHERE id_number = :id_number';
        $parameters = ['id_number' => $idNumber];
        if ($excludeUserId !== null) { $sql .= ' AND id <> :id'; $parameters['id'] = $excludeUserId; }
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        if ($statement->fetchColumn() !== false) { throw new DomainException('That university ID number is already in use.'); }
    }
}
