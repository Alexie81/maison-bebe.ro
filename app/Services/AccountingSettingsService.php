<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;

final class AccountingSettingsService
{
    private const DEFAULTS = [
        'valuation_method' => 'WeightedAverage',
        'nir_series' => 'NIR-MB',
        'retention_years' => 10,
    ];

    public function get(): array
    {
        $statement = Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=?');
        $statement->execute(['accounting_stock']);
        $stored = json_decode((string) ($statement->fetchColumn() ?: ''), true);
        return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public function save(array $input): array
    {
        $current = $this->get();
        $method = (string) ($input['valuation_method'] ?? $current['valuation_method']);
        if (!in_array($method, ['WeightedAverage', 'FIFO'], true)) {
            throw new HttpException(422, 'Metoda de evaluare selectată nu este validă.');
        }
        $pdo = Database::connection();
        $hasMovements = (bool) $pdo->query('SELECT EXISTS(SELECT 1 FROM accounting_stock_movements)')->fetchColumn();
        if ($hasMovements && $method !== $current['valuation_method']) {
            throw new HttpException(422, 'Metoda de evaluare este blocată după prima mișcare contabilă. Schimbarea necesită o migrare contabilă aprobată.');
        }
        $series = strtoupper(trim((string) ($input['nir_series'] ?? $current['nir_series'])));
        if (!preg_match('/^[A-Z0-9-]{2,40}$/', $series)) {
            throw new HttpException(422, 'Seria NIR poate conține numai litere, cifre și cratimă.');
        }
        $settings = [
            'valuation_method' => $method,
            'nir_series' => $series,
            'retention_years' => max(10, min(50, (int) ($input['retention_years'] ?? 10))),
        ];
        $pdo->prepare(
            'INSERT INTO settings (setting_key,value_json,updated_by) VALUES (?,?,?) '
            . 'ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_by=VALUES(updated_by)'
        )->execute(['accounting_stock', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), Auth::id()]);
        (new AccountingAuditService())->record('accounting.settings.updated', 'setting', null, $current, $settings, null, null, $pdo);
        return $settings;
    }
}
