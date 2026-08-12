<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use PDO;

final class AccountingAuditService
{
    public function record(
        string $action,
        ?string $targetType,
        ?int $targetId,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?string $correlationId = null,
        ?PDO $pdo = null
    ): void {
        $pdo ??= Database::connection();
        $user = Auth::user() ?? [];
        $metadata = [
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'reason' => $reason,
            'user_name_snapshot' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))),
        ];
        $pdo->prepare(
            'INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address,correlation_id,metadata_json) '
            . 'VALUES (?,?,?,?,?,?,?)'
        )->execute([
            Auth::id(),
            $action,
            $targetType,
            $targetId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $correlationId,
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }
}
