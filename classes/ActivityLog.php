<?php
declare(strict_types=1);

final class ActivityLog
{
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
}
