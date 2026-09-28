<?php
declare(strict_types=1);

final class Claim
{
    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     */
    public static function submit(int $itemId, array $data, array $actor): int
    {
        self::requireStudent($actor);
        $claim = self::validate($data);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $item = self::lockItem($pdo, $itemId);
            if ($item['status'] !== 'in_storage') {
                throw new DomainException('This item is no longer available for a claim.');
            }
            $rejected = self::countByStatus($pdo, $itemId, (int) $actor['id'], 'rejected');
            if ($rejected >= MAX_REJECTED_CLAIMS) {
                throw new DomainException('You have reached the maximum number of rejected claims for this item.');
            }
            if (self::countByStatus($pdo, $itemId, (int) $actor['id'], 'pending') > 0) {
                throw new DomainException('You already have a pending claim for this item.');
            }
            $statement = $pdo->prepare(
                'INSERT INTO claims (found_item_id, user_id, private_details_given, lost_location, lost_datetime, additional_info)
                 VALUES (:found_item_id, :user_id, :private_details_given, :lost_location, :lost_datetime, :additional_info)'
            );
            $statement->execute(array_merge($claim, ['found_item_id' => $itemId, 'user_id' => (int) $actor['id']]));
            $claimId = (int) $pdo->lastInsertId();
            ActivityLog::recordAction($actor, 'CLAIM_SUBMITTED', 'claim', $claimId, 'Claim submitted.', client_ip());
            $pdo->commit();
            return $claimId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    public static function approve(int $claimId, array $actor): void
    {
        self::requireStaff($actor);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $claim = self::findClaim($pdo, $claimId);
            $item = self::lockItem($pdo, (int) $claim['found_item_id']);
            $claim = self::lockClaim($pdo, $claimId);
            if ($item['status'] !== 'in_storage' || $claim['status'] !== 'pending') {
                throw new DomainException('This claim can no longer be approved.');
            }
            $others = $pdo->prepare("SELECT id FROM claims WHERE found_item_id = :item_id AND status = 'pending' AND id <> :claim_id");
            $others->execute(['item_id' => $item['id'], 'claim_id' => $claimId]);
            $otherClaimIds = array_map(static fn(array $row): int => (int) $row['id'], $others->fetchAll());
            $pdo->prepare("UPDATE claims SET status = 'approved', reviewed_by = :staff_id, reviewed_at = NOW() WHERE id = :id")
                ->execute(['staff_id' => $actor['id'], 'id' => $claimId]);
            $pdo->prepare("UPDATE found_items SET status = 'claimed' WHERE id = :id")->execute(['id' => $item['id']]);
            if ($otherClaimIds !== []) {
                $pdo->prepare("UPDATE claims SET status = 'closed', staff_reason = 'This item is no longer available.', reviewed_by = :staff_id, reviewed_at = NOW() WHERE found_item_id = :item_id AND status = 'pending' AND id <> :claim_id")
                    ->execute(['staff_id' => $actor['id'], 'item_id' => $item['id'], 'claim_id' => $claimId]);
            }
            ActivityLog::recordAction($actor, 'CLAIM_APPROVED', 'claim', $claimId, 'Claim approved.', client_ip());
            foreach ($otherClaimIds as $otherClaimId) {
                ActivityLog::recordAction($actor, 'CLAIM_CLOSED', 'claim', $otherClaimId, 'Closed because another claim was approved.', client_ip());
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    public static function reject(int $claimId, string $reason, array $actor): void
    {
        self::completePendingClaim($claimId, $reason, 'rejected', 'CLAIM_REJECTED', $actor);
    }

    /** @param array<string, mixed> $actor */
    public static function cancelPending(int $claimId, string $reason, array $actor): void
    {
        self::completePendingClaim($claimId, $reason, 'cancelled', 'CLAIM_CANCELLED', $actor);
    }

    /** @param array<string, mixed> $actor */
    public static function cancelApproval(int $claimId, string $reason, array $actor): void
    {
        self::requireStaff($actor);
        $reason = self::requiredReason($reason);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $claim = self::findClaim($pdo, $claimId);
            $item = self::lockItem($pdo, (int) $claim['found_item_id']);
            $claim = self::lockClaim($pdo, $claimId);
            if ($claim['status'] !== 'approved' || $item['status'] !== 'claimed') {
                throw new DomainException('Only an approved claim for a claimed item can be cancelled.');
            }
            $pdo->prepare("UPDATE claims SET status = 'cancelled', staff_reason = :reason, reviewed_by = :staff_id, reviewed_at = NOW() WHERE id = :id")
                ->execute(['reason' => $reason, 'staff_id' => $actor['id'], 'id' => $claimId]);
            $pdo->prepare("UPDATE found_items SET status = 'in_storage' WHERE id = :id")->execute(['id' => $item['id']]);
            ActivityLog::recordAction($actor, 'CLAIM_APPROVAL_CANCELLED', 'claim', $claimId, 'Approved claim cancelled.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    public static function markIdChecked(int $claimId, array $actor): void
    {
        self::requireStaff($actor);
        self::updateApprovedClaim($claimId, $actor, 'id_check');
    }

    /** @param array<string, mixed> $actor */
    public static function markCollected(int $claimId, array $actor): void
    {
        self::requireStaff($actor);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $claim = self::findClaim($pdo, $claimId);
            $item = self::lockItem($pdo, (int) $claim['found_item_id']);
            $claim = self::lockClaim($pdo, $claimId);
            if ($claim['status'] !== 'approved' || !(bool) $claim['id_checked'] || $item['status'] !== 'claimed') {
                throw new DomainException('The identity check must be recorded before collection.');
            }
            $pdo->prepare("UPDATE claims SET status = 'collected', collected_at = NOW(), released_by = :staff_id WHERE id = :id")
                ->execute(['staff_id' => $actor['id'], 'id' => $claimId]);
            $pdo->prepare("UPDATE found_items SET status = 'returned' WHERE id = :id")->execute(['id' => $item['id']]);
            ActivityLog::recordAction($actor, 'ITEM_COLLECTED', 'claim', $claimId, 'Item collection recorded.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listForStudent(array $actor): array
    {
        self::requireStudent($actor);
        $statement = database()->prepare(
            'SELECT c.id, c.status, c.staff_reason, c.created_at, c.id_checked, c.collected_at,
                    fi.reference_number, fi.item_name
             FROM claims c INNER JOIN found_items fi ON fi.id = c.found_item_id
             WHERE c.user_id = :user_id ORDER BY c.created_at DESC'
        );
        $statement->execute(['user_id' => $actor['id']]);
        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findByIdForStudent(int $claimId, array $actor): ?array
    {
        self::requireStudent($actor);
        $statement = database()->prepare(
            'SELECT c.id, c.status, c.staff_reason, c.private_details_given, c.lost_location, c.lost_datetime,
                    c.additional_info, c.id_checked, c.collected_at, c.created_at, fi.reference_number, fi.item_name
             FROM claims c INNER JOIN found_items fi ON fi.id = c.found_item_id
             WHERE c.id = :id AND c.user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $claimId, 'user_id' => $actor['id']]);
        $claim = $statement->fetch();
        return is_array($claim) ? $claim : null;
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listForStaff(array $actor): array
    {
        self::requireStaff($actor);
        return database()->query(
            "SELECT c.id, c.status, c.created_at, fi.reference_number, fi.item_name, u.full_name AS claimant_name, u.id_number AS claimant_id_number
             FROM claims c
             INNER JOIN found_items fi ON fi.id = c.found_item_id
             INNER JOIN users u ON u.id = c.user_id
             ORDER BY FIELD(c.status, 'pending', 'approved', 'rejected', 'cancelled', 'closed', 'collected'), c.created_at DESC"
        )->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findForStaff(int $claimId, array $actor): ?array
    {
        self::requireStaff($actor);
        $statement = database()->prepare(
            'SELECT c.*, fi.reference_number, fi.item_name, fi.private_details AS item_private_details,
                    fi.storage_location, fi.status AS item_status, u.full_name AS claimant_name, u.id_number AS claimant_id_number
             FROM claims c
             INNER JOIN found_items fi ON fi.id = c.found_item_id
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $claimId]);
        $claim = $statement->fetch();
        return is_array($claim) ? $claim : null;
    }

    /** @param array<string, mixed> $actor */
    private static function completePendingClaim(int $claimId, string $reason, string $status, string $action, array $actor): void
    {
        self::requireStaff($actor);
        $reason = self::requiredReason($reason);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $claim = self::lockClaim($pdo, $claimId);
            if ($claim['status'] !== 'pending') { throw new DomainException('Only a pending claim can be changed this way.'); }
            $pdo->prepare("UPDATE claims SET status = :status, staff_reason = :reason, reviewed_by = :staff_id, reviewed_at = NOW() WHERE id = :id")
                ->execute(['status' => $status, 'reason' => $reason, 'staff_id' => $actor['id'], 'id' => $claimId]);
            ActivityLog::recordAction($actor, $action, 'claim', $claimId, $status === 'rejected' ? 'Claim rejected.' : 'Pending claim cancelled.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    private static function updateApprovedClaim(int $claimId, array $actor, string $operation): void
    {
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $claim = self::lockClaim($pdo, $claimId);
            if ($claim['status'] !== 'approved') { throw new DomainException('Only an approved claim can be updated.'); }
            if ($operation === 'id_check') {
                $pdo->prepare('UPDATE claims SET id_checked = 1, id_checked_by = :staff_id, id_checked_at = NOW() WHERE id = :id')
                    ->execute(['staff_id' => $actor['id'], 'id' => $claimId]);
                ActivityLog::recordAction($actor, 'ID_CHECKED', 'claim', $claimId, 'Claimant identification checked.', client_ip());
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private static function lockItem(PDO $pdo, int $itemId): array
    {
        $statement = $pdo->prepare('SELECT id, status FROM found_items WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $itemId]);
        $item = $statement->fetch();
        if (!is_array($item)) { throw new OutOfBoundsException('Found item not found.'); }
        return $item;
    }

    /** @return array<string, mixed> */
    private static function lockClaim(PDO $pdo, int $claimId): array
    {
        $statement = $pdo->prepare('SELECT id, found_item_id, status, id_checked FROM claims WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $claimId]);
        $claim = $statement->fetch();
        if (!is_array($claim)) { throw new OutOfBoundsException('Claim not found.'); }
        return $claim;
    }

    /** @return array<string, mixed> */
    private static function findClaim(PDO $pdo, int $claimId): array
    {
        $statement = $pdo->prepare('SELECT id, found_item_id FROM claims WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $claimId]);
        $claim = $statement->fetch();
        if (!is_array($claim)) { throw new OutOfBoundsException('Claim not found.'); }
        return $claim;
    }

    private static function countByStatus(PDO $pdo, int $itemId, int $userId, string $status): int
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE found_item_id = :item_id AND user_id = :user_id AND status = :status');
        $statement->execute(['item_id' => $itemId, 'user_id' => $userId, 'status' => $status]);
        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $data
     *  @return array<string, string|null>
     */
    private static function validate(array $data): array
    {
        $inputDateTime = (string) ($data['lost_datetime'] ?? '');
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $inputDateTime);
        $errors = DateTimeImmutable::getLastErrors();
        if ($dateTime === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $dateTime->format('Y-m-d\\TH:i') !== $inputDateTime || $dateTime > new DateTimeImmutable('now')) {
            throw new DomainException('Lost date and time must be valid and not in the future.');
        }
        $privateDetails = trim((string) ($data['private_details_given'] ?? ''));
        if ($privateDetails === '' || mb_strlen($privateDetails) > 5000) { throw new DomainException('Private identifying details are required.'); }
        $location = (string) ($data['lost_location'] ?? '');
        if (!is_allowed_value($location, LOCATIONS)) { throw new DomainException('Select a valid lost location.'); }
        $additionalInfo = trim((string) ($data['additional_info'] ?? ''));
        if (mb_strlen($additionalInfo) > 5000) { throw new DomainException('Additional information is too long.'); }
        return ['private_details_given' => $privateDetails, 'lost_location' => $location, 'lost_datetime' => $dateTime->format('Y-m-d H:i:s'), 'additional_info' => $additionalInfo === '' ? null : $additionalInfo];
    }

    private static function requiredReason(string $reason): string { $reason = trim($reason); if ($reason === '' || mb_strlen($reason) > 255) { throw new DomainException('A reason of up to 255 characters is required.'); } return $reason; }
    /** @param array<string, mixed> $actor */
    private static function requireStudent(array $actor): void { if (($actor['role'] ?? null) !== 'student') { throw new LogicException('Only students can use this action.'); } }
    /** @param array<string, mixed> $actor */
    private static function requireStaff(array $actor): void { if (!in_array($actor['role'] ?? '', ['staff', 'admin'], true)) { throw new LogicException('Only staff or admins can use this action.'); } }
}
