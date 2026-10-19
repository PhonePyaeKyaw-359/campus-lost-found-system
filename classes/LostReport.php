<?php
declare(strict_types=1);

final class LostReport
{
    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     *  @param array<string, mixed>|null $photo
     */
    public static function create(array $data, array $actor, ?array $photo): int
    {
        self::requireStudent($actor);
        $report = self::validate($data);
        $storedPhotoPath = null;
        $pdo = database();

        try {
            $storedPhotoPath = PhotoStorage::storeLostPhoto($photo);
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'INSERT INTO lost_reports
                    (user_id, item_name, category, colour, brand, description, private_details,
                     lost_location, location_detail, lost_datetime, photo_path)
                 VALUES
                    (:user_id, :item_name, :category, :colour, :brand, :description, :private_details,
                     :lost_location, :location_detail, :lost_datetime, :photo_path)'
            );
            $statement->execute(array_merge(
                ['user_id' => (int) $actor['id']],
                $report,
                ['photo_path' => $storedPhotoPath]
            ));
            $reportId = (int) $pdo->lastInsertId();
            ActivityLog::recordAction($actor, 'LOST_REPORT_CREATED', 'lost_report', $reportId, 'Lost report created.', client_ip());
            $pdo->commit();

            return $reportId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            PhotoStorage::delete($storedPhotoPath);
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
            'SELECT id, item_name, category, colour, brand, description, lost_location, location_detail,
                    lost_datetime, photo_path, status, created_at
             FROM lost_reports WHERE id = :id AND user_id = :user_id LIMIT 1'
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
            'SELECT id, item_name, category, lost_location, lost_datetime, photo_path, status, created_at
             FROM lost_reports WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $actor['id']]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $filters
     *  @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listForStaff(array $filters, array $actor): array
    {
        self::requireStaff($actor);
        $where = [];
        $parameters = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            if (!in_array($status, ['lost', 'possible_match', 'resolved'], true)) { throw new DomainException('Invalid status filter.'); }
            $where[] = 'lr.status = :status'; $parameters['status'] = $status;
        }
        $category = (string) ($filters['category'] ?? '');
        if ($category !== '') {
            if (!is_allowed_value($category, CATEGORIES)) { throw new DomainException('Invalid category filter.'); }
            $where[] = 'lr.category = :category'; $parameters['category'] = $category;
        }
        $sql = 'SELECT lr.id, lr.item_name, lr.category, lr.lost_location, lr.lost_datetime, lr.status, lr.created_at,
                       u.full_name AS reporter_name
                FROM lost_reports lr INNER JOIN users u ON u.id = lr.user_id';
        if ($where !== []) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY FIELD(lr.status, \'lost\', \'possible_match\', \'resolved\'), lr.created_at DESC';
        $statement = database()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findFullById(int $reportId, array $actor): ?array
    {
        self::requireStaff($actor);
        $statement = database()->prepare(
            'SELECT lr.id, lr.user_id, lr.item_name, lr.category, lr.colour, lr.brand, lr.description,
                    lr.private_details, lr.lost_location, lr.location_detail, lr.lost_datetime, lr.photo_path,
                    lr.possible_match_item_id, lr.status, lr.created_at, u.full_name AS reporter_name,
                    u.id_number AS reporter_id_number, fi.reference_number AS possible_match_reference,
                    fi.item_name AS possible_match_name
             FROM lost_reports lr
             INNER JOIN users u ON u.id = lr.user_id
             LEFT JOIN found_items fi ON fi.id = lr.possible_match_item_id
             WHERE lr.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $reportId]);
        $report = $statement->fetch();
        return is_array($report) ? $report : null;
    }

    /** @param array<string, mixed> $actor */
    public static function setPossibleMatch(int $reportId, int $itemId, array $actor): void
    {
        self::requireStaff($actor);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $report = self::lockReport($pdo, $reportId);
            if ($report['status'] === 'resolved') { throw new DomainException('A resolved lost report cannot be changed.'); }
            $item = $pdo->prepare("SELECT id FROM found_items WHERE id = :id AND status = 'in_storage' FOR UPDATE");
            $item->execute(['id' => $itemId]);
            if ($item->fetchColumn() === false) { throw new DomainException('Select an available found item as the possible match.'); }
            $pdo->prepare("UPDATE lost_reports SET possible_match_item_id = :item_id, status = 'possible_match' WHERE id = :id")
                ->execute(['item_id' => $itemId, 'id' => $reportId]);
            ActivityLog::recordAction($actor, 'POSSIBLE_MATCH_SET', 'lost_report', $reportId, 'Possible match recorded.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    public static function markResolved(int $reportId, array $actor): void
    {
        self::requireStaff($actor);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $report = self::lockReport($pdo, $reportId);
            if (!in_array($report['status'], ['lost', 'possible_match'], true)) { throw new DomainException('This lost report is already resolved.'); }
            $pdo->prepare("UPDATE lost_reports SET status = 'resolved' WHERE id = :id")->execute(['id' => $reportId]);
            ActivityLog::recordAction($actor, 'LOST_REPORT_RESOLVED', 'lost_report', $reportId, 'Lost report marked resolved.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $actor */
    public static function canViewPhoto(int $reportId, array $actor): ?string
    {
        $isStaff = in_array($actor['role'], ['staff', 'admin'], true);
        $sql = 'SELECT photo_path FROM lost_reports WHERE id = :id';
        $parameters = ['id' => $reportId];
        if (!$isStaff) {
            $sql .= ' AND user_id = :user_id';
            $parameters['user_id'] = $actor['id'];
        }
        $sql .= ' LIMIT 1';

        $statement = database()->prepare($sql);
        $statement->execute($parameters);
        $path = $statement->fetchColumn();

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, string|null>
     */
    private static function validate(array $data): array
    {
        $itemName = self::requiredText($data, 'item_name', 100, 'Item name');
        $category = self::controlledValue($data, 'category', CATEGORIES, 'category');
        $colour = self::optionalControlledValue($data, 'colour', COLOURS, 'colour');
        $brand = self::optionalText($data, 'brand', 50, 'Brand');
        $description = self::requiredText($data, 'description', 5000, 'Description');
        $privateDetails = self::optionalText($data, 'private_details', 5000, 'Private identifying detail');
        $location = self::controlledValue($data, 'lost_location', LOCATIONS, 'lost location');
        $locationDetail = self::optionalText($data, 'location_detail', 120, 'Location detail');
        $lostDateTime = self::pastDateTime($data, 'lost_datetime', 'Lost date and time');

        return [
            'item_name' => $itemName,
            'category' => $category,
            'colour' => $colour,
            'brand' => $brand,
            'description' => $description,
            'private_details' => $privateDetails,
            'lost_location' => $location,
            'location_detail' => $locationDetail,
            'lost_datetime' => $lostDateTime,
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

    /** @param array<string, mixed> $data
     *  @param list<string> $values
     */
    private static function optionalControlledValue(array $data, string $key, array $values, string $label): ?string
    {
        $value = (string) ($data[$key] ?? '');
        if ($value === '') {
            return null;
        }
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
        if (!in_array($actor['role'] ?? '', ['staff', 'admin'], true)) { throw new LogicException('Only staff or admins can use this action.'); }
    }

    /** @return array<string, mixed> */
    private static function lockReport(PDO $pdo, int $reportId): array
    {
        $statement = $pdo->prepare('SELECT id, status FROM lost_reports WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $reportId]);
        $report = $statement->fetch();
        if (!is_array($report)) { throw new OutOfBoundsException('Lost report not found.'); }
        return $report;
    }
}
