<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;

final class AccountingEmailRecipientService
{
    public function suggestions(): array
    {
        $emails = [];
        $audit = Database::connection()->query(
            "SELECT metadata_json FROM audit_logs WHERE action IN ('accounting.archive.emailed','invoices.accounting_bundle.emailed') ORDER BY id DESC LIMIT 50"
        )->fetchAll();
        foreach ($audit as $row) {
            $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
            $this->append($emails, (string) ($metadata['recipient'] ?? ''));
        }

        $internal = Database::connection()->query(
            'SELECT email FROM order_email_recipients WHERE is_active=1 ORDER BY email LIMIT 50'
        )->fetchAll();
        foreach ($internal as $row) {
            $this->append($emails, (string) ($row['email'] ?? ''));
        }

        return array_values($emails);
    }

    private function append(array &$emails, string $email): void
    {
        $email = mb_strtolower(trim($email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = $email;
        }
    }
}
