<?php
declare(strict_types=1);

final class FoundReport
{
    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     */
    public static function create(array $data, array $actor): int
    {
        self::requireStudent($actor);
        $report = self::validate($data);

        $pdo = database();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO found_reports
                    (user_id, item_name, category, description, found_location, location_detail, found_datetime)
                 VALUES
                    (:user_id, :item_name, :category, :description, :found_location, :location_detail, :found_datetime)'
            );
            $statement->execute(array_merge(['user_id' => (int) $actor['id']], $report));
            $reportId = (int) $pdo->lastInsertId();
            ActivityLog::recordAction($actor, 'FOUND_REPORT_CREATED', 'found_report', $reportId, 'Found report created.', client_ip());
            $pdo->commit();

            return $reportId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findByIdForStudent(int $reportId, array $actor): ?array
    {
        self::requireStudent($actor);
        $statement = database()->prepare(
            'SELECT id, item_name, category, description, found_location, location_detail, found_datetime, status, created_at
             FROM found_reports WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $reportId, 'user_id' => $actor['id']]);
        $report = $statement->fetch();

        return is_array($report) ? $report : null;
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listForStudent(array $actor): array
    {
        self::requireStudent($actor);
        $statement = database()->prepare(
            'SELECT id, item_name, category, found_location, found_datetime, status, created_at
             FROM found_reports WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $actor['id']]);
        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listForStaff(array $actor): array
    {
        self::requireStaff($actor);
        $statement = database()->query(
            "SELECT fr.id, fr.item_name, fr.category, fr.found_location, fr.found_datetime, fr.status, fr.created_at,
                    u.full_name AS finder_name, u.id_number AS finder_id_number
             FROM found_reports fr
             INNER JOIN users u ON u.id = fr.user_id
             ORDER BY FIELD(fr.status, 'submitted', 'received', 'closed'), fr.created_at DESC"
        );
        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findForStaff(int $reportId, array $actor): ?array
    {
        self::requireStaff($actor);
        $statement = database()->prepare(
            'SELECT fr.id, fr.user_id, fr.item_name, fr.category, fr.description, fr.found_location,
                    fr.location_detail, fr.found_datetime, fr.status, fr.staff_reason, fr.handled_by, fr.created_at,
                    u.full_name AS finder_name, u.id_number AS finder_id_number,
                    handler.full_name AS handler_name
             FROM found_reports fr
             INNER JOIN users u ON u.id = fr.user_id
             LEFT JOIN users handler ON handler.id = fr.handled_by
             WHERE fr.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $reportId]);
        $report = $statement->fetch();
        return is_array($report) ? $report : null;
    }

    /** @param array<string, mixed> $actor */
    public static function close(int $reportId, string $reason, array $actor): void
    {
        self::requireStaff($actor);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new DomainException('A closing reason of up to 255 characters is required.');
        }

        $pdo = database();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT status FROM found_reports WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $reportId]);
            $status = $statement->fetchColumn();
            if ($status === false) {
                throw new OutOfBoundsException('Found report not found.');
            }
            if ($status !== 'submitted') {
                throw new DomainException('Only a submitted found report can be closed.');
            }

            $pdo->prepare(
                "UPDATE found_reports
                 SET status = 'closed', staff_reason = :reason, handled_by = :handled_by
                 WHERE id = :id"
            )->execute(['reason' => $reason, 'handled_by' => $actor['id'], 'id' => $reportId]);
            ActivityLog::recordAction($actor, 'FOUND_REPORT_CLOSED', 'found_report', $reportId, 'Found report closed.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, string|null>
     */
    private static function validate(array $data): array
    {
        $itemName = self::requiredText($data, 'item_name', 100, 'Item name');
        $category = self::controlledValue($data, 'category', CATEGORIES, 'category');
        $description = self::requiredText($data, 'description', 5000, 'Description');
        $location = self::controlledValue($data, 'found_location', LOCATIONS, 'found location');
        $locationDetail = self::optionalText($data, 'location_detail', 120, 'Location detail');
        $foundDateTime = self::pastDateTime($data, 'found_datetime', 'Found date and time');

        return [
            'item_name' => $itemName,
            'category' => $category,
            'description' => $description,
            'found_location' => $location,
            'location_detail' => $locationDetail,
            'found_datetime' => $foundDateTime,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function requiredText(array $data, string $key, int $maximumLength, string $label): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new DomainException($label . ' is required.');
        }
        if (mb_strlen($value) > $maximumLength) {
            throw new DomainException($label . ' is too long.');
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function optionalText(array $data, string $key, int $maximumLength, string $label): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximumLength) {
            throw new DomainException($label . ' is too long.');
        }
        return $value;
    }

    /** @param array<string, mixed> $data
     *  @param list<string> $values
     */
    private static function controlledValue(array $data, string $key, array $values, string $label): string
    {
        $value = (string) ($data[$key] ?? '');
        if (!is_allowed_value($value, $values)) {
            throw new DomainException('Select a valid ' . $label . '.');
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function pastDateTime(array $data, string $key, string $label): string
    {
        $value = (string) ($data[$key] ?? '');
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasParsingErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if ($dateTime === false || $hasParsingErrors || $dateTime->format('Y-m-d\\TH:i') !== $value
            || $dateTime > new DateTimeImmutable('now')) {
            throw new DomainException($label . ' must be a valid time in the past.');
        }
        return $dateTime->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $actor */
    private static function requireStudent(array $actor): void
    {
        if (($actor['role'] ?? null) !== 'student') {
            throw new LogicException('Only students can use this action.');
        }
    }

    /** @param array<string, mixed> $actor */
    private static function requireStaff(array $actor): void
    {
        if (!in_array($actor['role'] ?? '', ['staff', 'admin'], true)) {
            throw new LogicException('Only staff or admins can use this action.');
        }
    }
}
