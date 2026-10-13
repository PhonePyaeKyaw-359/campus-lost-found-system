<?php
declare(strict_types=1);

final class ActivityLog
{
    private const ACTIONS = [
        'LOGIN', 'FAILED_LOGIN', 'ACCESS_DENIED', 'LOST_REPORT_CREATED', 'FOUND_REPORT_CREATED',
        'FOUND_REPORT_CLOSED', 'ITEM_CREATED', 'ITEM_EDITED', 'POSSIBLE_MATCH_SET',
        'LOST_REPORT_RESOLVED', 'CLAIM_SUBMITTED', 'CLAIM_APPROVED', 'CLAIM_REJECTED',
        'CLAIM_CANCELLED', 'CLAIM_CLOSED', 'CLAIM_APPROVAL_CANCELLED', 'ID_CHECKED',
        'ITEM_COLLECTED', 'ACCOUNT_CREATED', 'ACCOUNT_EDITED', 'ACCOUNT_DISABLED', 'PASSWORD_RESET',
    ];
    /** @param array<string, mixed>|null $actor */
    public static function recordAction(
        ?array $actor,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $details,
        ?string $ipAddress
    ): void {
        $statement = database()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)'
        );
        $statement->execute([
            'user_id' => $actor['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $ipAddress,
        ]);
    }

    /** @param array<string, mixed> $filters
     *  @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function findRecent(array $filters, array $actor): array
    {
        if (!in_array($actor['role'] ?? '', ['staff', 'admin'], true)) {
            throw new LogicException('Only staff or admins can view activity history.');
        }
        $where = [];
        $parameters = [];
        $action = (string) ($filters['action'] ?? '');
        if ($action !== '') {
            if (!in_array($action, self::ACTIONS, true)) { throw new DomainException('Invalid activity action filter.'); }
            $where[] = 'al.action = :action';
            $parameters['action'] = $action;
        }
        $entityType = (string) ($filters['entity_type'] ?? '');
        if ($entityType !== '') {
            if (!in_array($entityType, ['user', 'lost_report', 'found_report', 'found_item', 'claim'], true)) { throw new DomainException('Invalid entity filter.'); }
            $where[] = 'al.entity_type = :entity_type';
            $parameters['entity_type'] = $entityType;
        }
        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $value = (string) ($filters[$filter] ?? '');
            if ($value === '') { continue; }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
                throw new DomainException('Use a valid date filter.');
            }
            $where[] = $operator === '>=' ? 'al.created_at >= :from_date' : 'al.created_at < :to_date';
            $parameters[$filter] = $operator === '>=' ? $value . ' 00:00:00' : $date->modify('+1 day')->format('Y-m-d 00:00:00');
        }
        $sql = 'SELECT al.id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.created_at, u.full_name AS actor_name
                FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id';
        if ($where !== []) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY al.created_at DESC LIMIT 100';
        $statement = database()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @return list<string> */
    public static function actions(): array
    {
        return self::ACTIONS;
    }
}
