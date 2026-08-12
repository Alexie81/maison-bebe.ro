<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class AccountingPeriodService
{
    public function lockedPeriod(string $date, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare('SELECT * FROM accounting_periods WHERE is_locked=1 AND ? BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1');
        $statement->execute([$date]);
        return $statement->fetch() ?: null;
    }

    public function assertPostingAllowed(string $date, bool $override = false, ?string $reason = null, ?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $period = $this->lockedPeriod($date, $pdo);
        if (!$period) {
            return;
        }
        if (!$override || !Auth::hasPermission('accounting_periods.manage') || trim((string) $reason) === '') {
            throw new HttpException(422, 'Data contabilă aparține unei perioade blocate. Confirmarea necesită autorizare și motiv explicit.');
        }
        (new AccountingAuditService())->record(
            'accounting.period.override_used',
            'accounting_period',
            (int) $period['id'],
            [],
            ['posting_date' => $date],
            $reason,
            null,
            $pdo
        );
    }

    public function save(string $start, string $end, bool $locked, ?string $reason = null): void
    {
        if (!$this->validDate($start) || !$this->validDate($end) || $start > $end) {
            throw new HttpException(422, 'Perioada contabilă nu este validă.');
        }
        if (!$locked && trim((string) $reason) === '') {
            throw new HttpException(422, 'Deblocarea unei perioade necesită un motiv.');
        }
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO accounting_periods (start_date,end_date,is_locked,locked_at,locked_by,unlock_reason) '
            . 'VALUES (?,?,?,IF(?=1,NOW(),NULL),?,?) '
            . 'ON DUPLICATE KEY UPDATE is_locked=VALUES(is_locked),locked_at=VALUES(locked_at),locked_by=VALUES(locked_by),unlock_reason=VALUES(unlock_reason)'
        );
        $statement->execute([$start, $end, $locked ? 1 : 0, $locked ? 1 : 0, Auth::id(), $locked ? null : trim((string) $reason)]);
        $idStatement = $pdo->prepare('SELECT id FROM accounting_periods WHERE start_date=? AND end_date=?');
        $idStatement->execute([$start, $end]);
        (new AccountingAuditService())->record(
            $locked ? 'accounting.period.locked' : 'accounting.period.unlocked',
            'accounting_period',
            (int) $idStatement->fetchColumn(),
            [],
            ['start_date' => $start, 'end_date' => $end, 'is_locked' => $locked],
            $reason,
            null,
            $pdo
        );
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }
}
