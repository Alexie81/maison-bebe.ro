<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;

final class LegalContentService
{
    public function render(string $html): string
    {
        $pdo = Database::connection();
        $company = $pdo->query("SELECT * FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1")->fetch() ?: [];
        $address = json_decode((string) ($company['address_json'] ?? '{}'), true) ?: [];
        $shipping = $pdo->query("SELECT name,config_json FROM shipping_providers WHERE is_default=1 ORDER BY id LIMIT 1")->fetch() ?: [];
        $shippingConfig = json_decode((string) ($shipping['config_json'] ?? '{}'), true) ?: [];

        $emailStatement = $pdo->query("SELECT purpose,from_email,reply_to_email FROM email_senders WHERE is_active=1 ORDER BY FIELD(purpose,'general','orders','recovery','marketing')");
        $emails = [];
        foreach ($emailStatement->fetchAll() as $sender) {
            $emails[(string) $sender['purpose']] = trim((string) ($sender['reply_to_email'] ?: $sender['from_email']));
        }

        $contactEmail = $emails['general'] ?? $emails['orders'] ?? (string) ($company['billing_email'] ?? '');
        $privacyEmail = $contactEmail;
        $returnsEmail = $contactEmail;
        $legalName = trim((string) ($company['legal_name'] ?? '')) ?: 'Operatorul magazinului';
        $tradeName = trim((string) ($company['trade_name'] ?? '')) ?: 'Maison Bébé';
        $vatStatus = strtolower(trim((string) ($company['vat_status'] ?? '')));
        $vatText = in_array($vatStatus, ['registered','platitoare','plătitoare','vat_registered'], true)
            ? 'includ TVA, conform regimului fiscal aplicabil'
            : 'sunt afișate conform regimului fiscal aplicabil comerciantului';

        $tokens = [
            '{{company.legal_name}}' => $legalName,
            '{{company.trade_name}}' => $tradeName,
            '{{company.tax_id}}' => trim((string) ($company['tax_id'] ?? 'Nespecificat')),
            '{{company.registration_number}}' => trim((string) ($company['registration_number'] ?? '')) ?: 'Nespecificat',
            '{{company.address}}' => $this->address($address),
            '{{company.return_address}}' => $this->address($address),
            '{{company.email}}' => $contactEmail,
            '{{company.returns_email}}' => $returnsEmail,
            '{{company.privacy_email}}' => $privacyEmail,
            '{{company.phone}}' => trim((string) ($company['phone'] ?? 'Nespecificat')),
            '{{company.vat_text}}' => $vatText,
            '{{shipping.courier}}' => trim((string) ($shipping['name'] ?? 'curierul selectat la finalizarea comenzii')),
            '{{shipping.standard_price}}' => $this->money((int) ($shippingConfig['base_price_minor'] ?? 0)),
            '{{shipping.free_threshold}}' => $this->money((int) ($shippingConfig['free_threshold_minor'] ?? 0)),
            '{{legal.updated_at}}' => date('d.m.Y'),
        ];

        return strtr($html, array_map(static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $tokens));
    }

    private function address(array $address): string
    {
        $parts = array_filter([
            trim((string) ($address['line1'] ?? '')),
            trim((string) ($address['city'] ?? '')),
            trim((string) ($address['county'] ?? '')) !== '' ? 'jud. ' . trim((string) $address['county']) : '',
            trim((string) ($address['postal_code'] ?? '')) !== '' ? 'cod poștal ' . trim((string) $address['postal_code']) : '',
            trim((string) ($address['country'] ?? 'RO')),
        ]);
        return $parts ? implode(', ', $parts) : 'Adresă neconfigurată';
    }

    private function money(int $minor): string
    {
        return number_format($minor / 100, 2, ',', '.') . ' lei';
    }
}