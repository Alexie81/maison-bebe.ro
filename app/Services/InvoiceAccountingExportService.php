<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use MaisonBebe\Core\Database;
use RuntimeException;

final class InvoiceAccountingExportService
{
    private const MAX_INVOICES = 2000;

    public function exportPeriod(string $from, string $to): array
    {
        [$from, $to] = $this->validatedPeriod($from, $to);
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            "SELECT i.*, s.prefix AS series_prefix, o.order_number, o.email AS order_email, o.phone AS order_phone,
                    o.payment_method, o.payment_status, o.shipping_method,
                    ba.iban, ba.bank_name
             FROM invoices i
             LEFT JOIN invoice_series s ON s.id=i.series_id
             LEFT JOIN orders o ON o.id=i.order_id
             LEFT JOIN company_bank_accounts ba ON ba.id=(
                 SELECT bank.id FROM company_bank_accounts bank
                 WHERE bank.company_profile_id=i.company_profile_id
                 ORDER BY bank.is_default DESC,bank.id LIMIT 1
             )
             WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?
             ORDER BY i.issue_date,i.id
             LIMIT " . (self::MAX_INVOICES + 1)
        );
        $statement->execute([$from, $to]);
        $invoices = $statement->fetchAll();
        if ($invoices === []) {
            throw new RuntimeException('Nu există facturi emise în perioada selectată.');
        }
        if (count($invoices) > self::MAX_INVOICES) {
            throw new RuntimeException('Perioada conține peste ' . self::MAX_INVOICES . ' de facturi. Selectează un interval mai scurt.');
        }

        $invoiceIds = array_map(static fn(array $invoice): int => (int) $invoice['id'], $invoices);
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $itemsStatement = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id IN ($placeholders) ORDER BY invoice_id,sort_order,id");
        $itemsStatement->execute($invoiceIds);
        $itemsByInvoice = [];
        foreach ($itemsStatement->fetchAll() as $item) {
            $itemsByInvoice[(int) $item['invoice_id']][] = $item;
        }

        $headers = [
            'ID factură', 'Tip document', 'Serie', 'Număr factură', 'Data emiterii', 'Data livrării', 'Data scadenței',
            'Moneda', 'Număr comandă', 'Status document', 'Furnizor', 'CUI furnizor', 'Cod TVA furnizor',
            'Reg. Com. furnizor', 'Adresă furnizor', 'IBAN furnizor', 'Bancă furnizor', 'Tip client', 'Client',
            'CUI/CNP client', 'Cod TVA client', 'Adresă client', 'Localitate client', 'Județ client', 'Țară client',
            'Email client', 'Telefon client', 'Metodă plată', 'Status plată', 'Metodă livrare', 'Nr. linie', 'SKU',
            'Denumire produs / serviciu', 'UM', 'Cantitate', 'Preț unitar fără TVA', 'Discount linie fără TVA',
            'Valoare netă linie', 'Cotă TVA %', 'TVA linie', 'Total linie cu TVA', 'Subtotal factură fără TVA',
            'Discount factură fără TVA', 'TVA factură', 'Total factură cu TVA', 'Data emiterii în sistem',
            'Hash document', 'Fișier RO e-Factura', 'Mențiuni',
        ];

        $rows = [];
        $files = [];
        $ublService = new EInvoiceUblService();
        foreach ($invoices as $invoice) {
            $invoiceId = (int) $invoice['id'];
            $items = $itemsByInvoice[$invoiceId] ?? [];
            if ($items === []) {
                throw new RuntimeException('Factura ' . ($invoice['number'] ?: '#' . $invoiceId) . ' nu conține poziții exportabile.');
            }

            try {
                $ubl = $ublService->generate($invoiceId);
            } catch (\Throwable $exception) {
                throw new RuntimeException('RO e-Factura nu a putut fi generată pentru factura ' . ($invoice['number'] ?: '#' . $invoiceId) . ': ' . $exception->getMessage(), 0, $exception);
            }
            $ublFilename = $this->safeFilename((string) $ubl['filename'], 'RO-eFactura-' . $invoiceId . '.xml');
            $files['e-factura/' . $ublFilename] = (string) $ubl['xml'];

            $issuer = json_decode((string) $invoice['issuer_snapshot_json'], true) ?: [];
            $customer = json_decode((string) $invoice['customer_snapshot_json'], true) ?: [];
            $issuerAddress = (array) ($issuer['address'] ?? []);
            $customerAddress = (array) ($customer['address'] ?? []);
            $lineNumber = 0;
            foreach ($items as $item) {
                $lineNumber++;
                $rows[] = [
                    $invoiceId,
                    $this->documentType((string) $invoice['document_type']),
                    (string) ($invoice['series_prefix'] ?? ''),
                    (string) ($invoice['number'] ?? ''),
                    (string) ($invoice['issue_date'] ?? ''),
                    (string) ($invoice['delivery_date'] ?? ''),
                    (string) ($invoice['due_date'] ?? ''),
                    (string) ($invoice['currency'] ?: 'RON'),
                    (string) ($invoice['order_number'] ?? ''),
                    'Emisă',
                    $this->partyName($issuer, true),
                    (string) ($issuer['tax_id'] ?? ''),
                    (string) ($issuer['vat_code'] ?? ''),
                    (string) ($issuer['registration_number'] ?? ''),
                    $this->address($issuerAddress),
                    (string) ($invoice['iban'] ?? ''),
                    (string) ($invoice['bank_name'] ?? ''),
                    ($invoice['customer_type'] ?? '') === 'company' ? 'Persoană juridică' : 'Persoană fizică',
                    $this->partyName($customer, false),
                    (string) ($customer['tax_id'] ?? $customer['cnp'] ?? ''),
                    (string) ($customer['vat_code'] ?? ''),
                    $this->address($customerAddress),
                    (string) ($customerAddress['city'] ?? ''),
                    (string) ($customerAddress['county'] ?? ''),
                    strtoupper((string) ($customerAddress['country_code'] ?? $customerAddress['country'] ?? 'RO')),
                    (string) ($customer['email'] ?? $invoice['order_email'] ?? ''),
                    (string) ($customer['phone'] ?? $invoice['order_phone'] ?? ''),
                    $this->paymentMethod((string) ($invoice['payment_method'] ?? '')),
                    $this->paymentStatus((string) ($invoice['payment_status'] ?? '')),
                    $this->shippingMethod((string) ($invoice['shipping_method'] ?? '')),
                    $lineNumber,
                    (string) ($item['sku'] ?? ''),
                    (string) $item['name'],
                    'buc',
                    (float) $item['quantity'],
                    $this->money((int) $item['unit_price_minor']),
                    $this->money((int) $item['discount_minor']),
                    $this->money((int) $item['total_minor']),
                    (float) $item['vat_rate'],
                    $this->money((int) $item['vat_minor']),
                    $this->money((int) $item['total_minor'] + (int) $item['vat_minor']),
                    $this->money((int) $invoice['subtotal_minor']),
                    $this->money((int) $invoice['discount_minor']),
                    $this->money((int) $invoice['vat_minor']),
                    $this->money((int) $invoice['grand_total_minor']),
                    (string) ($invoice['issued_at'] ?? ''),
                    (string) ($invoice['document_hash'] ?? ''),
                    $ublFilename,
                    (string) ($invoice['notes'] ?? ''),
                ];
            }
        }

        $xlsxFilename = 'registru-facturi-' . $from . '_' . $to . '.xlsx';
        $files[$xlsxFilename] = (new XlsxService())->export(
            'Facturi emise',
            $headers,
            $rows,
            [
                'Perioada exportată' => $from . ' - ' . $to,
                'Facturi emise' => count($invoices),
                'Poziții contabile' => count($rows),
                'Monedă valori' => 'Moneda este indicată pe fiecare rând; valorile sunt numerice.',
                'Generat la' => date('Y-m-d H:i:s'),
            ],
            [
                0 => 'integer',
                1 => 'text', 2 => 'text', 3 => 'text', 4 => 'text', 5 => 'text', 6 => 'text', 7 => 'text',
                8 => 'text', 9 => 'text', 10 => 'text', 11 => 'text', 12 => 'text', 13 => 'text', 14 => 'text',
                15 => 'text', 16 => 'text', 17 => 'text', 18 => 'text', 19 => 'text', 20 => 'text', 21 => 'text',
                22 => 'text', 23 => 'text', 24 => 'text', 25 => 'text', 26 => 'text', 27 => 'text', 28 => 'text',
                29 => 'text', 30 => 'integer', 31 => 'text', 32 => 'text', 33 => 'text',
                45 => 'text', 46 => 'text', 47 => 'text', 48 => 'text',
            ]
        );

        $filename = 'pachet-contabil-facturi-' . $from . '_' . $to . '.zip';
        return [
            'filename' => $filename,
            'binary' => (new ZipService())->create($files),
            'xlsx_filename' => $xlsxFilename,
            'invoice_count' => count($invoices),
            'line_count' => count($rows),
        ];
    }

    private function validatedPeriod(string $from, string $to): array
    {
        $from = trim($from);
        $to = trim($to);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$start || $start->format('Y-m-d') !== $from || !$end || $end->format('Y-m-d') !== $to) {
            throw new RuntimeException('Selectează o dată de început și o dată finală valide.');
        }
        if ($start > $end) {
            throw new RuntimeException('Data de început trebuie să fie înaintea datei finale.');
        }
        return [$from, $to];
    }

    private function money(int $minor): float
    {
        return round($minor / 100, 2);
    }

    private function documentType(string $type): string
    {
        return ['invoice' => 'Factură', 'credit_note' => 'Notă de credit', 'storno' => 'Factură storno'][$type] ?? $type;
    }

    private function partyName(array $party, bool $seller): string
    {
        if ($seller) {
            return trim((string) ($party['legal_name'] ?? $party['company_name'] ?? $party['name'] ?? ''));
        }
        $company = trim((string) ($party['company_name'] ?? ''));
        if ($company !== '') return $company;
        $person = trim((string) ($party['first_name'] ?? '') . ' ' . (string) ($party['last_name'] ?? ''));
        return $person !== '' ? $person : trim((string) ($party['name'] ?? $party['email'] ?? ''));
    }

    private function address(array $address): string
    {
        return implode(', ', array_filter([
            trim((string) ($address['line1'] ?? $address['address'] ?? '')),
            trim((string) ($address['line2'] ?? '')),
            trim((string) ($address['city'] ?? '')),
            trim((string) ($address['county'] ?? '')),
            trim((string) ($address['postal_code'] ?? '')),
            trim((string) ($address['country'] ?? $address['country_code'] ?? '')),
        ], static fn(string $part): bool => $part !== ''));
    }

    private function paymentMethod(string $method): string
    {
        return match ($method) {
            'cod' => 'Ramburs la curier',
            'stripe', 'card' => 'Card online',
            'bank' => 'Transfer bancar',
            default => $method,
        };
    }

    private function paymentStatus(string $status): string
    {
        return ['paid' => 'Plătită', 'unpaid' => 'Neplătită', 'pending' => 'În așteptare', 'failed' => 'Eșuată', 'refunded' => 'Rambursată'][$status] ?? $status;
    }

    private function shippingMethod(string $method): string
    {
        return ['courier' => 'Curier', 'manual' => 'Curier', 'pickup' => 'Ridicare personală'][$method] ?? $method;
    }

    private function safeFilename(string $filename, string $fallback): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/u', '-', basename($filename)) ?: '';
        $filename = trim($filename, '.-');
        return $filename !== '' ? $filename : $fallback;
    }
}
