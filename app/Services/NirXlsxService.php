<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;

final class NirXlsxService
{
    public function generate(int $nirId): array
    {
        $service = new NirService();
        $document = $service->document($nirId);
        $currency = (string) $document['currency'];
        $exchangeRate = Decimal::normalize($document['exchange_rate'] ?? '1', 6);
        $rows = array_map(static function (array $line) use ($currency, $exchangeRate): array {
            $netRon = Decimal::round(Decimal::mul((string) $line['value_without_vat'], $exchangeRate, 8), 2);
            $vatRon = Decimal::round(Decimal::mul((string) $line['vat_value'], $exchangeRate, 8), 2);
            $totalRon = Decimal::round(Decimal::mul((string) $line['total_with_vat'], $exchangeRate, 8), 2);
            return [
                $line['sku_snapshot'],
                $line['product_name_snapshot'],
                $line['supplier_product_name'],
                $line['variant_name_snapshot'],
                $line['unit_of_measure_snapshot'],
                $line['invoiced_quantity'],
                $line['received_quantity'],
                $line['accepted_quantity'],
                $line['damaged_quantity'],
                $line['difference_type'],
                $line['difference_reason'],
                $currency,
                $exchangeRate,
                $line['unit_purchase_price_without_vat'],
                $line['discount_value'],
                $line['allocated_acquisition_cost'],
                $line['vat_rate'],
                $line['value_without_vat'],
                $line['vat_value'],
                $line['total_with_vat'],
                $netRon,
                $vatRon,
                $totalRon,
            ];
        }, $service->lines($nirId));

        $isReversal = ($document['document_kind'] ?? '') === 'reversal';
        $statusLabels = [
            'Draft' => 'Ciornă',
            'RequiresProductMapping' => 'Necesită asociere produse',
            'InReception' => 'Recepție în lucru',
            'ReadyForConfirmation' => 'Gata de confirmare',
            'Confirmed' => 'Confirmat',
            'PartiallyReceived' => 'Recepționat parțial',
            'Reversed' => 'Inversat',
        ];
        $metadata = [
            'Tip document' => $isReversal ? 'Inversare NIR' : 'Notă de recepție și constatare de diferențe',
            'Status' => $statusLabels[$document['status']] ?? $document['status'],
            'Document' => $document['formatted_number'] ?: '#' . $nirId,
            'Data recepției' => $document['receipt_date'],
            'Gestiune' => $document['warehouse_name'],
            'Furnizor' => $document['supplier_name_snapshot'],
            'CUI furnizor' => $document['supplier_tax_id_snapshot'],
            'Factura furnizorului' => trim((string) ($document['invoice_series'] . ' ' . $document['invoice_number'])),
            'Data facturii' => $document['invoice_date'],
            'Monedă' => $currency,
            'Curs în RON' => $exchangeRate,
            'Data cursului' => $document['exchange_rate_date'] ?: '—',
            'Sursa cursului' => $document['exchange_rate_source'] ?: '—',
            'Total fără TVA' => $document['total_without_vat'] . ' ' . $currency,
            'TVA total' => $document['vat_total'] . ' ' . $currency,
            'Total document' => $document['grand_total'] . ' ' . $currency,
            'Total document în RON' => Decimal::round(Decimal::mul((string) $document['grand_total'], $exchangeRate, 8), 2) . ' RON',
            'Recepționat de' => trim((string) $document['creator_name']) ?: 'Sistem',
            'Confirmat de' => trim((string) $document['confirmer_name']) ?: trim((string) $document['creator_name']),
            'Generat la' => date('d.m.Y H:i'),
        ];
        if ($isReversal) {
            $metadata['NIR inițial'] = $document['original_formatted_number'] ?: '#' . (int) $document['original_nir_id'];
            $metadata['Motivul inversării'] = $document['reversal_reason'];
        } elseif ($document['status'] === 'Reversed') {
            $metadata['Document de inversare'] = $document['reversal_formatted_number'] ?: '—';
            $metadata['Motivul inversării'] = $document['reversal_reason'];
        }

        $binary = (new XlsxService())->export($isReversal ? 'Inversare NIR' : 'NIR', [
            'SKU website',
            'Denumire în website',
            'Denumire pe factura furnizorului',
            'Variantă',
            'UM',
            'Cantitate facturată',
            'Cantitate recepționată',
            'Cantitate acceptată',
            'Deteriorată',
            'Tip diferență',
            'Motiv diferență',
            'Monedă',
            'Curs RON',
            'Preț unitar fără TVA',
            'Discount',
            'Cost repartizat',
            'Cotă TVA',
            'Valoare fără TVA',
            'TVA',
            'Total cu TVA',
            'Valoare fără TVA (RON)',
            'TVA (RON)',
            'Total cu TVA (RON)',
        ], $rows, $metadata);

        $relative = null;
        if (in_array($document['status'], ['Confirmed', 'PartiallyReceived', 'Reversed'], true)) {
            $pdo = Database::connection();
            $stored = $pdo->prepare("SELECT * FROM nir_artifacts WHERE nir_document_id=? AND artifact_type='xlsx' ORDER BY id DESC LIMIT 1");
            $stored->execute([$nirId]);
            $artifact = $stored->fetch();
            $relative = $artifact ? (string) $artifact['path'] : '/nir/' . date('Y/m', strtotime((string) ($document['confirmed_at'] ?: 'now'))) . '/nir-' . $nirId . '.xlsx';
            $path = BASE_PATH . '/storage' . $relative;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true) && !is_dir(dirname($path))) {
                throw new HttpException(500, 'Directorul exporturilor NIR nu a putut fi creat.');
            }
            if (file_put_contents($path, $binary, LOCK_EX) === false) {
                throw new HttpException(500, 'Exportul XLSX nu a putut fi arhivat.');
            }
            $hash = hash('sha256', $binary);
            if ($artifact) {
                $pdo->prepare('UPDATE nir_artifacts SET sha256=?,size_bytes=?,document_version=document_version+1,generated_at=NOW(),generated_by=? WHERE id=?')
                    ->execute([$hash, strlen($binary), Auth::id(), $artifact['id']]);
                $artifactId = (int) $artifact['id'];
            } else {
                $pdo->prepare("INSERT INTO nir_artifacts (nir_document_id,artifact_type,path,mime_type,sha256,size_bytes,generated_by) VALUES (?,'xlsx',?,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',?,?,?)")
                    ->execute([$nirId, $relative, $hash, strlen($binary), Auth::id()]);
                $artifactId = (int) $pdo->lastInsertId();
            }
            (new AccountingAuditService())->record('nir.xlsx.generated', 'nir_document', $nirId, [], ['artifact_id' => $artifactId, 'sha256' => $hash]);
        }

        return [
            'binary' => $binary,
            'filename' => ($document['formatted_number'] ?: 'nir-' . $nirId) . '.xlsx',
            'path' => $relative,
        ];
    }
}
