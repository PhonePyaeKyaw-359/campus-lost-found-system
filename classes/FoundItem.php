<?php
declare(strict_types=1);

final class FoundItem
{
    /** @param array<string, mixed> $data
     *  @param array<string, mixed>|null $photo
     *  @param array<string, mixed> $actor
     */
    public static function create(array $data, ?array $photo, array $actor): int
    {
        return self::createItem($data, $photo, $actor, null, null);
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed>|null $photo
     *  @param array<string, mixed> $actor
     */
    public static function createFromReport(int $reportId, array $data, ?array $photo, array $actor): int
    {
        self::requireStaff($actor);
        $pdo = database();
        $statement = $pdo->prepare('SELECT user_id, status FROM found_reports WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $reportId]);
        $report = $statement->fetch();
        if (!is_array($report) || $report['status'] !== 'submitted') {
            throw new DomainException('This found report is no longer available for registration.');
        }

        return self::createItem($data, $photo, $actor, $reportId, (int) $report['user_id']);
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listForStaff(array $actor): array
    {
        self::requireStaff($actor);
        return database()->query(
            'SELECT id, reference_number, item_name, category, found_location, received_datetime, status, storage_location
             FROM found_items ORDER BY created_at DESC'
        )->fetchAll();
    }

    /** @param array<string, mixed> $actor
     *  @return list<array<string, mixed>>
     */
    public static function listAvailableForPossibleMatch(array $actor): array
    {
        self::requireStaff($actor);
        return database()->query(
            "SELECT id, reference_number, item_name, category, found_location
             FROM found_items WHERE status = 'in_storage' ORDER BY received_datetime DESC"
        )->fetchAll();
    }

    /** @param array<string, mixed> $filters
     *  @param array<string, mixed> $actor
     *  @return array{items: list<array<string, mixed>>, total: int, page: int, total_pages: int}
     */
    public static function searchAvailable(array $filters, int $requestedPage, array $actor): array
    {
        self::requireStudent($actor);
        [$where, $parameters] = self::publicSearchConditions($filters);
        $whereSql = implode(' AND ', $where);
        $countStatement = database()->prepare("SELECT COUNT(*) FROM found_items WHERE {$whereSql}");
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / RESULTS_PER_PAGE));
        $page = min(max(1, $requestedPage), $totalPages);
        $offset = ($page - 1) * RESULTS_PER_PAGE;

        $statement = database()->prepare(
            "SELECT id, reference_number, item_name, category, colour, brand, public_description,
                    found_location, found_datetime, show_photo_publicly, created_at
             FROM found_items
             WHERE {$whereSql}
             ORDER BY received_datetime DESC
             LIMIT " . RESULTS_PER_PAGE . " OFFSET " . $offset
        );
        $statement->execute($parameters);

        return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'total_pages' => $totalPages];
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findPublicById(int $itemId, array $actor): ?array
    {
        self::requireStudent($actor);
        $statement = database()->prepare(
            "SELECT id, reference_number, item_name, category, colour, brand, public_description,
                    found_location, found_datetime, show_photo_publicly, created_at
             FROM found_items
             WHERE id = :id AND status = 'in_storage'
             LIMIT 1"
        );
        $statement->execute(['id' => $itemId]);
        $item = $statement->fetch();
        return is_array($item) ? $item : null;
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>|null
     */
    public static function findFullById(int $itemId, array $actor): ?array
    {
        self::requireStaff($actor);
        $statement = database()->prepare(
            'SELECT fi.*, receiver.full_name AS received_by_name, finder.full_name AS finder_name
             FROM found_items fi
             INNER JOIN users receiver ON receiver.id = fi.received_by
             LEFT JOIN users finder ON finder.id = fi.finder_user_id
             WHERE fi.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $itemId]);
        $item = $statement->fetch();
        return is_array($item) ? $item : null;
    }

    /** @param array<string, mixed> $actor */
    public static function canViewPhoto(int $itemId, array $actor): ?string
    {
        if (in_array($actor['role'] ?? '', ['staff', 'admin'], true)) {
            $statement = database()->prepare('SELECT photo_path FROM found_items WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $itemId]);
        } else {
            $statement = database()->prepare(
                "SELECT photo_path FROM found_items
                 WHERE id = :id AND show_photo_publicly = 1 AND status = 'in_storage' LIMIT 1"
            );
            $statement->execute(['id' => $itemId]);
        }
        $path = $statement->fetchColumn();
        return is_string($path) && $path !== '' ? $path : null;
    }

    /** @param array<string, mixed> $actor */
    public static function setPhotoVisibility(int $itemId, bool $visible, array $actor): void
    {
        self::requireStaff($actor);
        $statement = database()->prepare('SELECT photo_path FROM found_items WHERE id = :id FOR UPDATE');
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $statement->execute(['id' => $itemId]);
            $path = $statement->fetchColumn();
            if ($path === false) {
                throw new OutOfBoundsException('Found item not found.');
            }
            if ($visible && (!is_string($path) || $path === '')) {
                throw new DomainException('A photo must be uploaded before it can be published.');
            }
            $pdo->prepare('UPDATE found_items SET show_photo_publicly = :visible WHERE id = :id')
                ->execute(['visible' => $visible ? 1 : 0, 'id' => $itemId]);
            ActivityLog::recordAction($actor, 'ITEM_EDITED', 'found_item', $itemId, 'Found-item photo visibility changed.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $actor
     */
    public static function update(int $itemId, array $data, array $actor): void
    {
        self::requireStaff($actor);
        $item = self::validate($data);
        unset($item['show_photo_publicly']);
        $pdo = database();
        $pdo->beginTransaction();
        try {
            self::lockItemForEdit($pdo, $itemId);
            $pdo->prepare(
                'UPDATE found_items
                 SET item_name = :item_name, category = :category, colour = :colour, brand = :brand,
                     public_description = :public_description, private_details = :private_details,
                     found_location = :found_location, location_detail = :location_detail,
                     found_datetime = :found_datetime, storage_location = :storage_location
                 WHERE id = :id'
            )->execute(array_merge($item, ['id' => $itemId]));
            ActivityLog::recordAction($actor, 'ITEM_EDITED', 'found_item', $itemId, 'Found-item record updated.', client_ip());
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed>|null $photo
     *  @param array<string, mixed> $actor
     */
    private static function createItem(array $data, ?array $photo, array $actor, ?int $reportId, ?int $finderUserId): int
    {
        self::requireStaff($actor);
        $item = self::validate($data);
        $photoPath = null;
        $pdo = database();
        try {
            $photoPath = PhotoStorage::storeFoundPhoto($photo);
            if ($photoPath === null) {
                $item['show_photo_publicly'] = 0;
            }
            $pdo->beginTransaction();
            if ($reportId !== null) {
                $lock = $pdo->prepare('SELECT status FROM found_reports WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $reportId]);
                if ($lock->fetchColumn() !== 'submitted') {
                    throw new DomainException('This found report is no longer available for registration.');
                }
            }

            $statement = $pdo->prepare(
                'INSERT INTO found_items
                    (found_report_id, finder_user_id, item_name, category, colour, brand, public_description,
                     private_details, found_location, location_detail, found_datetime, received_datetime,
                     received_by, photo_path, show_photo_publicly, storage_location)
                 VALUES
                    (:found_report_id, :finder_user_id, :item_name, :category, :colour, :brand, :public_description,
                     :private_details, :found_location, :location_detail, :found_datetime, NOW(),
                     :received_by, :photo_path, :show_photo_publicly, :storage_location)'
            );
            $statement->execute(array_merge($item, [
                'found_report_id' => $reportId,
                'finder_user_id' => $finderUserId,
                'received_by' => (int) $actor['id'],
                'photo_path' => $photoPath,
            ]));
            $itemId = (int) $pdo->lastInsertId();
            $referenceNumber = sprintf('FI-%06d', $itemId);
            $pdo->prepare('UPDATE found_items SET reference_number = :reference_number WHERE id = :id')
                ->execute(['reference_number' => $referenceNumber, 'id' => $itemId]);
            if ($reportId !== null) {
                $pdo->prepare("UPDATE found_reports SET status = 'received', handled_by = :handled_by WHERE id = :id")
                    ->execute(['handled_by' => $actor['id'], 'id' => $reportId]);
            }
            ActivityLog::recordAction($actor, 'ITEM_CREATED', 'found_item', $itemId, 'Official found-item record created.', client_ip());
            $pdo->commit();
            return $itemId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            PhotoStorage::delete($photoPath);
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private static function lockItemForEdit(PDO $pdo, int $itemId): array
    {
        $statement = $pdo->prepare('SELECT id FROM found_items WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $itemId]);
        $item = $statement->fetch();
        if (!is_array($item)) { throw new OutOfBoundsException('Found item not found.'); }
        return $item;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, string|int|null>
     */
    private static function validate(array $data): array
    {
        $foundDateTime = trim((string) ($data['found_datetime'] ?? ''));
        if ($foundDateTime !== '') {
            $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $foundDateTime);
            $errors = DateTimeImmutable::getLastErrors();
            if ($dateTime === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $dateTime->format('Y-m-d\\TH:i') !== $foundDateTime || $dateTime > new DateTimeImmutable('now')) {
                throw new DomainException('Found date and time must be valid and not in the future.');
            }
            $foundDateTime = $dateTime->format('Y-m-d H:i:s');
        } else {
            $foundDateTime = null;
        }

        return [
            'item_name' => self::requiredText($data, 'item_name', 100, 'Item name'),
            'category' => self::controlled($data, 'category', CATEGORIES, 'category'),
            'colour' => self::optionalControlled($data, 'colour', COLOURS, 'colour'),
            'brand' => self::optionalText($data, 'brand', 50, 'Brand'),
            'public_description' => self::requiredText($data, 'public_description', 5000, 'Public description'),
            'private_details' => self::requiredText($data, 'private_details', 5000, 'Private identifying details'),
            'found_location' => self::controlled($data, 'found_location', LOCATIONS, 'found location'),
            'location_detail' => self::optionalText($data, 'location_detail', 120, 'Location detail'),
            'found_datetime' => $foundDateTime,
            'show_photo_publicly' => isset($data['show_photo_publicly']) ? 1 : 0,
            'storage_location' => self::requiredText($data, 'storage_location', 100, 'Storage location'),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array{0: list<string>, 1: array<string, string>}
     */
    private static function publicSearchConditions(array $filters): array
    {
        $where = ["status = 'in_storage'"];
        $parameters = [];
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if (mb_strlen($keyword) > 100) {
            throw new DomainException('The search keyword is too long.');
        }
        if ($keyword !== '') {
            $escapedKeyword = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword) . '%';
            $where[] = "(item_name LIKE :keyword_name ESCAPE '!' OR public_description LIKE :keyword_description ESCAPE '!' OR COALESCE(brand, '') LIKE :keyword_brand ESCAPE '!')";
            $parameters['keyword_name'] = $escapedKeyword;
            $parameters['keyword_description'] = $escapedKeyword;
            $parameters['keyword_brand'] = $escapedKeyword;
        }
        foreach ([
            'category' => ['values' => CATEGORIES, 'column' => 'category'],
            'colour' => ['values' => COLOURS, 'column' => 'colour'],
            'location' => ['values' => LOCATIONS, 'column' => 'found_location'],
        ] as $key => $definition) {
            $value = (string) ($filters[$key] ?? '');
            if ($value === '') {
                continue;
            }
            if (!is_allowed_value($value, $definition['values'])) {
                throw new DomainException('One of the selected filters is invalid.');
            }
            $where[] = $definition['column'] . ' = :' . $key;
            $parameters[$key] = $value;
        }

        return [$where, $parameters];
    }

    /** @param array<string, mixed> $data */
    private static function requiredText(array $data, string $key, int $max, string $label): string { $value = trim((string) ($data[$key] ?? '')); if ($value === '' || mb_strlen($value) > $max) { throw new DomainException($label . ' is required and must be within the allowed length.'); } return $value; }
    /** @param array<string, mixed> $data */
    private static function optionalText(array $data, string $key, int $max, string $label): ?string { $value = trim((string) ($data[$key] ?? '')); if ($value === '') { return null; } if (mb_strlen($value) > $max) { throw new DomainException($label . ' is too long.'); } return $value; }
    /** @param array<string, mixed> $data @param list<string> $values */
    private static function controlled(array $data, string $key, array $values, string $label): string { $value = (string) ($data[$key] ?? ''); if (!is_allowed_value($value, $values)) { throw new DomainException('Select a valid ' . $label . '.'); } return $value; }
    /** @param array<string, mixed> $data @param list<string> $values */
    private static function optionalControlled(array $data, string $key, array $values, string $label): ?string { $value = (string) ($data[$key] ?? ''); if ($value === '') { return null; } if (!is_allowed_value($value, $values)) { throw new DomainException('Select a valid ' . $label . '.'); } return $value; }
    /** @param array<string, mixed> $actor */
    private static function requireStaff(array $actor): void { if (!in_array($actor['role'] ?? '', ['staff', 'admin'], true)) { throw new LogicException('Only staff or admins can use this action.'); } }
}
