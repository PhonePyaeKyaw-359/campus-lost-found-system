<?php
declare(strict_types=1);

final class DashboardMetrics
{
    /** @param array<string, mixed> $actor
     *  @return array<string, int>
     */
    public static function forUser(array $actor): array
    {
        $role = (string) ($actor['role'] ?? '');
        $pdo = database();
        if ($role === 'student') {
            $lost = $pdo->prepare("SELECT COUNT(*) FROM lost_reports WHERE user_id = :user_id AND status <> 'resolved'");
            $lost->execute(['user_id' => $actor['id']]);
            $claims = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE user_id = :user_id AND status = 'pending'");
            $claims->execute(['user_id' => $actor['id']]);
            return ['open_lost_reports' => (int) $lost->fetchColumn(), 'pending_claims' => (int) $claims->fetchColumn()];
        }
        if (in_array($role, ['staff', 'admin'], true)) {
            return [
                'found_reports_awaiting_receipt' => (int) $pdo->query("SELECT COUNT(*) FROM found_reports WHERE status = 'submitted'")->fetchColumn(),
                'claims_awaiting_review' => (int) $pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'pending'")->fetchColumn(),
                'approved_claims_awaiting_collection' => (int) $pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'approved'")->fetchColumn(),
                'items_in_storage' => (int) $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'in_storage'")->fetchColumn(),
            ];
        }
        throw new LogicException('Invalid account role.');
    }
}
