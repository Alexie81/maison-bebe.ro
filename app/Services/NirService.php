<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;
use PDOException;
use Throwable;

final class NirService
{
    private const MUTABLE_STATUSES = ['Draft', 'RequiresProductMapping', 'InReception', 'ReadyForConfirmation'];
    private const FINAL_STATUSES = ['Confirmed', 'PartiallyReceived', 'Reversed'];
    private const STOCKABLE_TYPES = ['stockable', 'made_to_order', 'assembled_bundle'];
    private const LINE_TYPES = ['stockable', 'made_to_order', 'assembled_bundle', 'service', 'transport', 'tax', 'acquisition_cost', 'ignored'];
    private const DIFFERENCE_TYPES = ['none', 'shortage', 'surplus', 'damaged', 'wrong_product', 'other'];

    public function createDraft(array $input): int
    {
        $header = $this->validateHeader($input);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $supplierId = $this->supplier($pdo, $header);
            $invoice = $this->findInvoice($pdo, $supplierId, $header['invoice_series_normalized'], $header['invoice_number_normalized']);
            if ($invoice && empty($input['partial_receipt'])) {
                throw new HttpException(422, 'Există deja o factură de achiziție cu acest furnizor, această serie și acest număr. Bifează „Recepție în tranșe” numai dacă este aceeași factură.');
            }
            if (!$invoice) {
                $pdo->prepare(
                    'INSERT INTO supplier_invoices '
                    . '(supplier_id,supplier_name_snapshot,supplier_tax_id_snapshot,supplier_address_snapshot_json,invoice_series,invoice_series_normalized,invoice_number,invoice_number_normalized,invoice_date,invoice_received_date,due_date,currency,exchange_rate,exchange_rate_date,exchange_rate_source,delivery_note_number,delivery_note_date,is_late_entered,late_entry_reason,created_by,updated_by) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $supplierId, $header['supplier_name'], $header['supplier_tax_id'],
                    json_encode(['line1' => $header['supplier_address']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $header['invoice_series'], $header['invoice_series_normalized'], $header['invoice_number'],
                    $header['invoice_number_normalized'], $header['invoice_date'], $header['invoice_received_date'],
                    $header['due_date'], $header['currency'], $header['exchange_rate'], $header['exchange_rate_date'], $header['exchange_rate_source'], $header['delivery_note_number'],
                    $header['delivery_note_date'], $header['is_late_entered'], $header['late_entry_reason'], Auth::id(), Auth::id(),
                ]);
                $invoiceId = (int) $pdo->lastInsertId();
            } else {
                $invoiceId = (int) $invoice['id'];
            }
            $pdo->prepare(
                'INSERT INTO nir_documents '
                . '(receipt_date,supplier_invoice_id,supplier_id,warehouse_id,status,is_late_entered,late_entry_reason,notes,created_by,updated_by) '
                . "VALUES (?,?,?,?,'Draft',?,?,?,?,?)"
            )->execute([
                $header['receipt_date'], $invoiceId, $supplierId, $header['warehouse_id'],
                $header['is_late_entered'], $header['late_entry_reason'], $header['notes'], Auth::id(), Auth::id(),
            ]);
            $nirId = (int) $pdo->lastInsertId();
            $hasLines = array_filter((array) ($input['line_name'] ?? []), static fn(mixed $value): bool => trim((string) $value) !== '') !== [];
            $summary = ['count' => 0, 'unmapped' => 0];
            if ($invoice && !$hasLines) {
                $this->seedPartialLines($pdo, $nirId, $invoiceId);
            } elseif ($hasLines) {
                $summary = $this->saveLines($pdo, $nirId, $invoiceId, $supplierId, $input);
                $status = $summary['unmapped'] > 0 ? 'RequiresProductMapping' : 'ReadyForConfirmation';
                $pdo->prepare('UPDATE nir_documents SET status=? WHERE id=?')->execute([$status, $nirId]);
                $this->refreshInvoiceTotals($pdo, $invoiceId);
            }
            (new AccountingAuditService())->record(
                'nir.draft.created', 'nir_document', $nirId, [],
                ['supplier_invoice_id' => $invoiceId, 'receipt_date' => $header['receipt_date'], 'partial_receipt' => (bool) $invoice, 'line_count' => $summary['count']],
                $header['late_entry_reason'], null, $pdo
            );
            if ($header['is_late_entered']) {
                (new AccountingAuditService())->record(
                    'nir.late_entry.created', 'nir_document', $nirId, [],
                    ['invoice_date' => $header['invoice_date'], 'receipt_date' => $header['receipt_date']],
                    $header['late_entry_reason'], null, $pdo
                );
            }
            $pdo->commit();
            return $nirId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new HttpException(422, 'Există deja o factură de achiziție cu acest furnizor, această serie și acest număr.');
            }
            throw $exception;
        }
    }

    public function updateDraft(int $nirId, array $input): void
    {
        $header = $this->validateHeader($input);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $document = $this->lockDocument($pdo, $nirId);
            $this->assertMutable($document);
            $expectedVersion = (int) ($input['row_version'] ?? 0);
            if ($expectedVersion > 0 && $expectedVersion !== (int) $document['row_version']) {
                throw new HttpException(409, 'Documentul a fost modificat între timp. Reîncarcă pagina înainte de a salva din nou.');
            }
            $supplierId = $this->supplier($pdo, $header);
            $duplicate = $this->findInvoice($pdo, $supplierId, $header['invoice_series_normalized'], $header['invoice_number_normalized']);
            if ($duplicate && (int) $duplicate['id'] !== (int) $document['supplier_invoice_id']) {
                throw new HttpException(422, 'Există deja o factură de achiziție cu acest furnizor, această serie și acest număr.');
            }
            $old = [
                'receipt_date' => $document['receipt_date'],
                'invoice_date' => $document['invoice_date'],
                'supplier_id' => $document['supplier_id'],
            ];
            $pdo->prepare(
                'UPDATE supplier_invoices SET supplier_id=?,supplier_name_snapshot=?,supplier_tax_id_snapshot=?,supplier_address_snapshot_json=?,'
                . 'invoice_series=?,invoice_series_normalized=?,invoice_number=?,invoice_number_normalized=?,invoice_date=?,invoice_received_date=?,due_date=?,currency=?,exchange_rate=?,exchange_rate_date=?,exchange_rate_source=?,delivery_note_number=?,delivery_note_date=?,is_late_entered=?,late_entry_reason=?,updated_by=?,row_version=row_version+1 WHERE id=?'
            )->execute([
                $supplierId, $header['supplier_name'], $header['supplier_tax_id'],
                json_encode(['line1' => $header['supplier_address']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $header['invoice_series'], $header['invoice_series_normalized'], $header['invoice_number'], $header['invoice_number_normalized'],
                $header['invoice_date'], $header['invoice_received_date'], $header['due_date'], $header['currency'],
                $header['exchange_rate'], $header['exchange_rate_date'], $header['exchange_rate_source'], $header['delivery_note_number'], $header['delivery_note_date'],
                $header['is_late_entered'], $header['late_entry_reason'], Auth::id(), $document['supplier_invoice_id'],
            ]);
            $pdo->prepare(
                'UPDATE nir_documents SET receipt_date=?,supplier_id=?,warehouse_id=?,is_late_entered=?,late_entry_reason=?,notes=?,updated_by=?,row_version=row_version+1 WHERE id=?'
            )->execute([
                $header['receipt_date'], $supplierId, $header['warehouse_id'], $header['is_late_entered'],
                $header['late_entry_reason'], $header['notes'], Auth::id(), $nirId,
            ]);
            $summary = $this->saveLines($pdo, $nirId, (int) $document['supplier_invoice_id'], $supplierId, $input);
            $status = $summary['count'] === 0 ? 'Draft' : ($summary['unmapped'] > 0 ? 'RequiresProductMapping' : 'ReadyForConfirmation');
            $pdo->prepare('UPDATE nir_documents SET status=? WHERE id=?')->execute([$status, $nirId]);
            $this->refreshInvoiceTotals($pdo, (int) $document['supplier_invoice_id']);
            (new AccountingAuditService())->record(
                'nir.draft.updated', 'nir_document', $nirId, $old,
                ['receipt_date' => $header['receipt_date'], 'invoice_date' => $header['invoice_date'], 'supplier_id' => $supplierId, 'line_count' => $summary['count'], 'status' => $status],
                $header['late_entry_reason'], null, $pdo
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function confirm(int $nirId, array $options = []): array
    {
        $pdo = Database::connection();
        $correlationId = 'nir-confirm:' . $nirId . ':' . bin2hex(random_bytes(10));
        $pdo->beginTransaction();
        try {
            $document = $this->lockDocument($pdo, $nirId);
            if (in_array($document['status'], self::FINAL_STATUSES, true)) {
                $pdo->commit();
                return $document;
            }
            $this->assertMutable($document);
            $expectedVersion = (int) ($options['row_version'] ?? 0);
            if ($expectedVersion > 0 && $expectedVersion !== (int) $document['row_version']) {
                throw new HttpException(409, 'Documentul a fost modificat între timp și nu poate fi confirmat fără reîncărcare.');
            }
            (new ProductMappingService())->assertCatalogSkuIntegrity($pdo);
            (new AccountingPeriodService())->assertPostingAllowed(
                (string) $document['receipt_date'],
                !empty($options['period_override']),
                trim((string) ($options['period_override_reason'] ?? '')),
                $pdo
            );
            $lines = $this->validateForConfirmation($pdo, $document);
            $settings = (new AccountingSettingsService())->get();
            $sequence = (new AccountingDocumentSequenceService())->next(
                $pdo, 'NIR', (string) $settings['nir_series'], (int) substr((string) $document['receipt_date'], 0, 4)
            );
            $finalStatus = $this->isPartial($pdo, (int) $document['supplier_invoice_id'], $nirId) ? 'PartiallyReceived' : 'Confirmed';
            $pdo->prepare(
                'UPDATE nir_documents SET series=?,number=?,formatted_number=?,status=?,confirmed_at=NOW(),confirmed_by=?,updated_by=?,row_version=row_version+1 WHERE id=?'
            )->execute([
                $sequence['series'], $sequence['number'], $sequence['formatted'], $finalStatus,
                Auth::id(), Auth::id(), $nirId,
            ]);
            $this->refreshFinalSnapshots($pdo, $nirId, $lines);
            $this->updateSupplierInvoiceReceptionStatus($pdo, (int) $document['supplier_invoice_id']);
            (new AccountingStockPostingService())->postNir($nirId, $correlationId, $pdo);
            (new AccountingAuditService())->record(
                'nir.confirmed', 'nir_document', $nirId,
                ['status' => $document['status']],
                ['status' => $finalStatus, 'number' => $sequence['formatted'], 'line_count' => count($lines)],
                null, $correlationId, $pdo
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $pdfError = null;
        try {
            (new NirPdfService())->generate($nirId);
        } catch (Throwable $exception) {
            $pdfError = $exception->getMessage();
            (new AccountingAuditService())->record('nir.pdf.failed', 'nir_document', $nirId, [], ['error' => $pdfError], null, $correlationId);
        }
        $result = $this->document($nirId);
        $result['pdf_error'] = $pdfError;
        return $result;
    }

    public function reverse(int $nirId, string $effectiveDate, string $reason, array $options = []): int
    {
        $reason = trim($reason);
        if (!$this->validDate($effectiveDate) || $effectiveDate > date('Y-m-d') || $reason === '') {
            throw new HttpException(422, 'Completează data și motivul documentului de inversare.');
        }
        $pdo = Database::connection();
        $correlationId = 'nir-reversal:' . $nirId . ':' . bin2hex(random_bytes(10));
        $pdo->beginTransaction();
        try {
            $original = $this->lockDocument($pdo, $nirId);
            if (!in_array($original['status'], ['Confirmed', 'PartiallyReceived'], true)) {
                throw new HttpException(422, 'Numai un NIR confirmat și neinversat poate fi inversat.');
            }
            (new AccountingPeriodService())->assertPostingAllowed(
                $effectiveDate,
                !empty($options['period_override']),
                trim((string) ($options['period_override_reason'] ?? '')),
                $pdo
            );
            $settings = (new AccountingSettingsService())->get();
            $series = 'NIRR-MB';
            $sequence = (new AccountingDocumentSequenceService())->next($pdo, 'NIR_REVERSAL', $series, (int) substr($effectiveDate, 0, 4));
            $pdo->prepare(
                'INSERT INTO nir_documents '
                . '(series,number,formatted_number,document_kind,receipt_date,supplier_invoice_id,supplier_id,warehouse_id,status,is_late_entered,late_entry_reason,notes,created_by,updated_by,confirmed_at,confirmed_by,original_nir_id,reversal_reason) '
                . "VALUES (?,?,?,'reversal',?,?,?,?,'Confirmed',0,NULL,?,?,?,NOW(),?,?,?)"
            )->execute([
                $sequence['series'], $sequence['number'], $sequence['formatted'], $effectiveDate,
                $original['supplier_invoice_id'], $original['supplier_id'], $original['warehouse_id'],
                'Document de inversare pentru ' . $original['formatted_number'], Auth::id(), Auth::id(), Auth::id(), $nirId, $reason,
            ]);
            $reversalId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO nir_lines '
                . '(nir_document_id,supplier_invoice_line_id,product_id,product_variant_id,sku_snapshot,product_name_snapshot,variant_name_snapshot,unit_of_measure_snapshot,online_stock_mode_snapshot,track_accounting_stock_snapshot,invoiced_quantity,previously_received_quantity,received_quantity,accepted_quantity,damaged_quantity,difference_quantity,difference_type,difference_reason,observations,unit_purchase_price_without_vat,discount_value,allocated_acquisition_cost,vat_rate,value_without_vat,vat_value,total_with_vat,sort_order) '
                . 'SELECT ?,supplier_invoice_line_id,product_id,product_variant_id,sku_snapshot,product_name_snapshot,variant_name_snapshot,unit_of_measure_snapshot,online_stock_mode_snapshot,track_accounting_stock_snapshot,invoiced_quantity,previously_received_quantity,received_quantity,accepted_quantity,damaged_quantity,difference_quantity,difference_type,?,observations,unit_purchase_price_without_vat,discount_value,allocated_acquisition_cost,vat_rate,value_without_vat,vat_value,total_with_vat,sort_order '
                . 'FROM nir_lines WHERE nir_document_id=? ORDER BY sort_order,id'
            )->execute([$reversalId, $reason, $nirId]);
            (new AccountingStockPostingService())->postNir($reversalId, $correlationId, $pdo);
            $pdo->prepare('UPDATE nir_documents SET status=\'Reversed\',reversed_at=NOW(),reversed_by=?,reversal_reason=?,row_version=row_version+1 WHERE id=?')
                ->execute([Auth::id(), $reason, $nirId]);
            $this->updateSupplierInvoiceReceptionStatus($pdo, (int) $original['supplier_invoice_id']);
            (new AccountingAuditService())->record(
                'nir.reversed', 'nir_document', $nirId,
                ['status' => $original['status']],
                ['status' => 'Reversed', 'reversal_document_id' => $reversalId, 'number' => $sequence['formatted']],
                $reason, $correlationId, $pdo
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        try {
            (new NirPdfService())->generate($reversalId);
        } catch (Throwable) {
        }
        return $reversalId;
    }

    public function deleteDraft(int $nirId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $document = $this->lockDocument($pdo, $nirId);
            $this->assertMutable($document);
            $invoiceId = (int) $document['supplier_invoice_id'];
            (new AccountingAuditService())->record('nir.draft.deleted', 'nir_document', $nirId, ['status' => $document['status']], [], 'Ștergere ciornă', null, $pdo);
            $pdo->prepare('DELETE FROM nir_documents WHERE id=?')->execute([$nirId]);
            $count = $pdo->prepare('SELECT COUNT(*) FROM nir_documents WHERE supplier_invoice_id=?');
            $count->execute([$invoiceId]);
            if ((int) $count->fetchColumn() === 0) {
                $pdo->prepare('DELETE FROM supplier_invoices WHERE id=? AND status=\'draft\'')->execute([$invoiceId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function document(int $nirId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT n.*,si.invoice_series,si.invoice_number,si.invoice_date,si.invoice_received_date,si.due_date,si.currency,si.exchange_rate,si.exchange_rate_date,si.exchange_rate_source,'
            . 'si.delivery_note_number,si.delivery_note_date,si.total_without_vat,si.vat_total,si.grand_total,si.supplier_name_snapshot,si.supplier_tax_id_snapshot,si.supplier_address_snapshot_json,si.attachments_json,'
            . 'original.formatted_number original_formatted_number,(SELECT reversal.formatted_number FROM nir_documents reversal WHERE reversal.original_nir_id=n.id ORDER BY reversal.id DESC LIMIT 1) reversal_formatted_number,'
            . 'w.name warehouse_name,CONCAT(COALESCE(u.first_name,\'\'),\' \',COALESCE(u.last_name,\'\')) creator_name,'
            . 'CONCAT(COALESCE(cu.first_name,\'\'),\' \',COALESCE(cu.last_name,\'\')) confirmer_name '
            . 'FROM nir_documents n JOIN supplier_invoices si ON si.id=n.supplier_invoice_id '
            . 'LEFT JOIN nir_documents original ON original.id=n.original_nir_id '
            . 'JOIN accounting_warehouses w ON w.id=n.warehouse_id LEFT JOIN users u ON u.id=n.created_by LEFT JOIN users cu ON cu.id=n.confirmed_by WHERE n.id=?'
        );
        $statement->execute([$nirId]);
        $document = $statement->fetch();
        if (!$document) {
            throw new HttpException(404, 'NIR-ul nu a fost găsit.');
        }
        return $document;
    }

    public function lines(int $nirId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT nl.*,sil.supplier_product_code,sil.supplier_product_name,sil.imported_sku,sil.imported_ean,sil.line_type,sil.association_status,sil.is_ignored,sil.ignore_reason,'
            . 'COALESCE(m.path,\'/assets/images/packaging-reference.png\') image_path '
            . 'FROM nir_lines nl JOIN supplier_invoice_lines sil ON sil.id=nl.supplier_invoice_line_id '
            . 'LEFT JOIN product_images pi ON pi.product_id=nl.product_id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id '
            . 'WHERE nl.nir_document_id=? ORDER BY nl.sort_order,nl.id'
        );
        $statement->execute([$nirId]);
        return $statement->fetchAll();
    }

    private function validateHeader(array $input): array
    {
        $required = ['supplier_name', 'supplier_tax_id', 'invoice_number', 'invoice_date', 'receipt_date', 'warehouse_id', 'currency'];
        foreach ($required as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                throw new HttpException(422, 'Completează toate câmpurile obligatorii ale furnizorului și facturii.');
            }
        }
        $invoiceDate = trim((string) $input['invoice_date']);
        $receiptDate = trim((string) $input['receipt_date']);
        if (!$this->validDate($invoiceDate) || !$this->validDate($receiptDate) || $invoiceDate > date('Y-m-d') || $receiptDate > date('Y-m-d')) {
            throw new HttpException(422, 'Data facturii și data recepției trebuie să fie date calendaristice valide, care nu sunt în viitor.');
        }
        $late = !empty($input['is_late_entered']);
        $lateReason = trim((string) ($input['late_entry_reason'] ?? ''));
        if ($late && $lateReason === '') {
            throw new HttpException(422, 'Motivul introducerii ulterioare este obligatoriu.');
        }
        $deliveryNumber = trim((string) ($input['delivery_note_number'] ?? ''));
        $deliveryDate = trim((string) ($input['delivery_note_date'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        if ($deliveryDate !== '' && (!$this->validDate($deliveryDate) || $deliveryDate > date('Y-m-d'))) {
            throw new HttpException(422, 'Data avizului trebuie să fie o dată validă care nu este în viitor.');
        }
        if ($receiptDate < $invoiceDate && (($deliveryNumber === '' || !$this->validDate($deliveryDate)) && $notes === '')) {
            throw new HttpException(422, 'Recepția este anterioară facturii. Completează avizul și data lui sau o justificare în observații.');
        }
        foreach (['invoice_received_date', 'due_date'] as $dateField) {
            $date = trim((string) ($input[$dateField] ?? ''));
            if ($date !== '' && !$this->validDate($date)) {
                throw new HttpException(422, 'Una dintre datele opționale nu este validă.');
            }
            if ($dateField === 'invoice_received_date' && $date > date('Y-m-d')) {
                throw new HttpException(422, 'Data primirii facturii nu poate fi în viitor.');
            }
        }
        $currency = strtoupper(trim((string) $input['currency']));
        if ($currency === 'TL') {
            $currency = 'TRY';
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new HttpException(422, 'Moneda trebuie să fie un cod ISO din trei litere.');
        }
        $series = trim((string) $input['invoice_series']);
        $number = trim((string) $input['invoice_number']);
        $exchangeRate = Decimal::normalize($input['exchange_rate'] ?? '1', 6);
        if (Decimal::cmp($exchangeRate, '0', 6) <= 0) throw new HttpException(422, 'Cursul valutar trebuie să fie mai mare decât zero.');
        $exchangeRateDate=trim((string)($input['exchange_rate_date']??''));
        if($exchangeRateDate!==''&&(!$this->validDate($exchangeRateDate)||$exchangeRateDate>date('Y-m-d')))throw new HttpException(422,'Data cursului valutar nu este validă.');
        $exchangeRateSource=mb_substr(trim((string)($input['exchange_rate_source']??'')),0,80);
        $exchangeRateManual=$currency!=='RON'&&(string)($input['exchange_rate_manual']??'')==='1';
        if($exchangeRateManual){$exchangeRateDate=$invoiceDate;$exchangeRateSource='Manual';}
        if($currency==='RON'){$exchangeRate='1.000000';$exchangeRateDate=$exchangeRateDate?:date('Y-m-d');$exchangeRateSource='RON';}
        $warehouse=(int)$input['warehouse_id'];$warehouseCheck=Database::connection()->prepare('SELECT 1 FROM accounting_warehouses WHERE id=? AND is_active=1');$warehouseCheck->execute([$warehouse]);
        if(!$warehouseCheck->fetchColumn())throw new HttpException(422,'Gestiunea contabilă selectată nu este activă.');
        return [
            'supplier_name' => trim((string) $input['supplier_name']),
            'supplier_tax_id' => trim((string) $input['supplier_tax_id']),
            'supplier_tax_id_normalized' => $this->normalizeDocumentIdentity((string) $input['supplier_tax_id']),
            'supplier_address' => trim((string) ($input['supplier_address'] ?? '')),
            'invoice_series' => $series,
            'invoice_series_normalized' => $this->normalizeDocumentIdentity($series),
            'invoice_number' => $number,
            'invoice_number_normalized' => $this->normalizeDocumentIdentity($number),
            'invoice_date' => $invoiceDate,
            'invoice_received_date' => trim((string) ($input['invoice_received_date'] ?? '')) ?: null,
            'due_date' => trim((string) ($input['due_date'] ?? '')) ?: null,
            'receipt_date' => $receiptDate,
            'warehouse_id' => $warehouse,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'exchange_rate_date' => $exchangeRateDate ?: null,
            'exchange_rate_source' => $exchangeRateSource ?: null,
            'delivery_note_number' => $deliveryNumber ?: null,
            'delivery_note_date' => $deliveryDate ?: null,
            'is_late_entered' => $late ? 1 : 0,
            'late_entry_reason' => $lateReason ?: null,
            'notes' => $notes ?: null,
        ];
    }

    private function saveLines(PDO $pdo, int $nirId, int $invoiceId, int $supplierId, array $input): array
    {
        $names = (array) ($input['line_name'] ?? []);
        $supplierLineIds = (array) ($input['supplier_line_id'] ?? []);
        $existingNirLines = $pdo->prepare('SELECT supplier_invoice_line_id FROM nir_lines WHERE nir_document_id=?');
        $existingNirLines->execute([$nirId]);
        $oldSupplierLineIds = array_map('intval', $existingNirLines->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare('DELETE FROM nir_lines WHERE nir_document_id=?')->execute([$nirId]);
        $insertSupplierLine = $pdo->prepare(
            'INSERT INTO supplier_invoice_lines '
            . '(supplier_invoice_id,supplier_product_code,supplier_product_name,imported_sku,imported_ean,product_id,product_variant_id,maison_bebe_sku,unit_of_measure,invoiced_quantity,unit_price_without_vat,discount_value,vat_rate,value_without_vat,vat_value,total_with_vat,line_type,association_status,is_ignored,ignore_reason,original_imported_data_json,sort_order) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $insertNirLine = $pdo->prepare(
            'INSERT INTO nir_lines '
            . '(nir_document_id,supplier_invoice_line_id,product_id,product_variant_id,sku_snapshot,product_name_snapshot,variant_name_snapshot,unit_of_measure_snapshot,online_stock_mode_snapshot,track_accounting_stock_snapshot,invoiced_quantity,previously_received_quantity,received_quantity,accepted_quantity,damaged_quantity,difference_quantity,difference_type,difference_reason,observations,unit_purchase_price_without_vat,discount_value,allocated_acquisition_cost,vat_rate,value_without_vat,vat_value,total_with_vat,sort_order) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $mapping = new ProductMappingService();
        $count = 0;
        $unmapped = 0;
        $usedSupplierLineIds = [];
        foreach ($names as $index => $rawName) {
            $name = trim((string) $rawName);
            $supplierCode = trim((string) (($input['line_supplier_code'][$index] ?? '')));
            $importedSku = strtoupper(trim((string) (($input['line_imported_sku'][$index] ?? ''))));
            $importedEan = strtoupper(trim((string) (($input['line_imported_ean'][$index] ?? ''))));
            if ($name === '' && $supplierCode === '' && $importedSku === '') {
                continue;
            }
            $lineType = (string) ($input['line_type'][$index] ?? 'stockable');
            if (!in_array($lineType, self::LINE_TYPES, true)) {
                throw new HttpException(422, 'Tip de linie NIR invalid.');
            }
            $ignored = $lineType === 'ignored';
            $ignoreReason = trim((string) ($input['line_ignore_reason'][$index] ?? ''));
            if ($ignored && $ignoreReason === '') {
                throw new HttpException(422, 'O linie ignorată necesită justificare.');
            }
            $variantId = (int) ($input['line_variant_id'][$index] ?? 0);
            $associationStatus = $variantId ? 'manual' : 'unmapped';
            $variant = $variantId ? $mapping->variant($variantId, $pdo) : null;
            if (!$variant && in_array($lineType, self::STOCKABLE_TYPES, true)) {
                $automatic = $mapping->automatic($supplierId, $importedSku, $importedEan, $supplierCode, $pdo, $name);
                if ($automatic) {
                    $variantId = (int) $automatic['product_variant_id'];
                    $variant = $mapping->variant($variantId, $pdo);
                    $associationStatus = 'automatic';
                }
            }
            if (!in_array($lineType, self::STOCKABLE_TYPES, true)) {
                $variant = null;
                $variantId = 0;
                $associationStatus = 'not_required';
            }
            $invoiced = Decimal::normalize($input['line_invoiced_quantity'][$index] ?? '0', 4);
            $received = Decimal::normalize($input['line_received_quantity'][$index] ?? $invoiced, 4);
            $accepted = Decimal::normalize($input['line_accepted_quantity'][$index] ?? $received, 4);
            $damaged = Decimal::normalize($input['line_damaged_quantity'][$index] ?? '0', 4);
            foreach ([$invoiced, $received, $accepted, $damaged] as $quantity) {
                if (Decimal::cmp($quantity, '0', 4) < 0) {
                    throw new HttpException(422, 'Cantitățile din NIR nu pot fi negative.');
                }
            }
            if (Decimal::cmp($accepted, $received, 4) > 0) {
                throw new HttpException(422, 'Cantitatea acceptată nu poate depăși cantitatea recepționată fizic.');
            }
            if (Decimal::cmp(Decimal::add($accepted, $damaged, 4), $received, 4) > 0) {
                throw new HttpException(422, 'Cantitatea acceptată plus cantitatea deteriorată nu poate depăși cantitatea recepționată.');
            }
            $difference = Decimal::sub($received, $invoiced, 4);
            $differenceType = (string) ($input['line_difference_type'][$index] ?? 'none');
            if (!in_array($differenceType, self::DIFFERENCE_TYPES, true)) {
                $differenceType = 'other';
            }
            $differenceReason = trim((string) ($input['line_difference_reason'][$index] ?? ''));
            $hasDifference = Decimal::cmp($received, $invoiced, 4) !== 0 || Decimal::cmp($accepted, $received, 4) !== 0 || Decimal::cmp($damaged, '0', 4) > 0;
            if ($hasDifference && ($differenceType === 'none' || $differenceReason === '')) {
                throw new HttpException(422, 'Pentru fiecare diferență completează tipul și motivul.');
            }
            $unitPrice = Decimal::normalize($input['line_unit_price'][$index] ?? '0', 6);
            $discount = Decimal::normalize($input['line_discount'][$index] ?? '0', 2);
            $allocated = Decimal::normalize($input['line_allocated_cost'][$index] ?? '0', 2);
            $vatRate = Decimal::normalize($input['line_vat_rate'][$index] ?? '0', 2);
            if (Decimal::cmp($unitPrice, '0', 6) < 0 || Decimal::cmp($discount, '0', 2) < 0 || Decimal::cmp($vatRate, '0', 2) < 0) {
                throw new HttpException(422, 'Prețul, discountul și cota TVA trebuie să fie valori pozitive.');
            }
            $calculatedNet = Decimal::round(Decimal::sub(Decimal::mul($invoiced, $unitPrice, 10), $discount, 10), 2);
            $net = trim((string) ($input['line_value_without_vat'][$index] ?? '')) !== ''
                ? Decimal::normalize($input['line_value_without_vat'][$index], 2) : $calculatedNet;
            $calculatedVat = Decimal::round(Decimal::div(Decimal::mul($net, $vatRate, 8), '100', 8), 2);
            $vat = trim((string) ($input['line_vat_value'][$index] ?? '')) !== ''
                ? Decimal::normalize($input['line_vat_value'][$index], 2) : $calculatedVat;
            $total = trim((string) ($input['line_total'][$index] ?? '')) !== ''
                ? Decimal::normalize($input['line_total'][$index], 2) : Decimal::add($net, $vat, 2);
            if (Decimal::cmp(Decimal::abs(Decimal::sub($net, $calculatedNet, 2), 2), '0.02', 2) > 0
                || Decimal::cmp(Decimal::abs(Decimal::sub($vat, $calculatedVat, 2), 2), '0.02', 2) > 0
                || Decimal::cmp(Decimal::abs(Decimal::sub($total, Decimal::add($net, $vat, 2), 2), 2), '0.02', 2) > 0) {
                throw new HttpException(422, 'Valorile nete, TVA și totalul liniei nu corespund cantității, prețului și cotei TVA.');
            }
            $unit = trim((string) ($input['line_unit'][$index] ?? 'buc')) ?: 'buc';
            $productId = (int) ($variant['product_id'] ?? 0) ?: null;
            $sku = $variant['sku'] ?? null;
            $productName = $variant['product_name'] ?? $name;
            $variantName = $variant['variant_name'] ?? null;
            $track = $variant ? (int) $variant['track_accounting_stock'] : 0;
            $onlineMode = $variant && !(bool) $variant['track_inventory'] ? 'unlimited' : 'limited';
            $supplierLineId = (int) ($supplierLineIds[$index] ?? 0);
            if ($supplierLineId > 0) {
                $check = $pdo->prepare('SELECT id FROM supplier_invoice_lines WHERE id=? AND supplier_invoice_id=?');
                $check->execute([$supplierLineId, $invoiceId]);
                if (!$check->fetchColumn()) {
                    throw new HttpException(422, 'Linia facturii furnizorului nu aparține documentului curent.');
                }
                $usedSupplierLineIds[] = $supplierLineId;
            } else {
                $insertSupplierLine->execute([
                    $invoiceId, $supplierCode ?: null, $name, $importedSku ?: null, $importedEan ?: null,
                    $productId, $variantId ?: null, $sku, $unit, $invoiced, $unitPrice, $discount, $vatRate,
                    $net, $vat, $total, $lineType, $associationStatus, $ignored ? 1 : 0, $ignoreReason ?: null,
                    trim((string) ($input['line_original_json'][$index] ?? '')) ?: null,
                    $index * 10,
                ]);
                $supplierLineId = (int) $pdo->lastInsertId();
                $usedSupplierLineIds[] = $supplierLineId;
            }
            $previous = $pdo->prepare(
                "SELECT COALESCE(SUM(nl.accepted_quantity),0) FROM nir_lines nl JOIN nir_documents n ON n.id=nl.nir_document_id "
                . "WHERE nl.supplier_invoice_line_id=? AND n.id<>? AND n.status IN ('Confirmed','PartiallyReceived') AND n.document_kind='receipt'"
            );
            $previous->execute([$supplierLineId, $nirId]);
            $previousQuantity = Decimal::normalize($previous->fetchColumn(), 4);
            if (Decimal::cmp(Decimal::add($previousQuantity, $received, 4), $invoiced, 4) > 0 && $differenceType !== 'surplus') {
                throw new HttpException(422, 'Cantitatea recepționată depășește cantitatea rămasă. Marchează explicit diferența în plus.');
            }
            $insertNirLine->execute([
                $nirId, $supplierLineId, $productId, $variantId ?: null, $sku, $productName, $variantName,
                $unit, $onlineMode, $track, $invoiced, $previousQuantity, $received, $accepted, $damaged,
                $difference, $hasDifference ? $differenceType : 'none', $differenceReason ?: null,
                trim((string) ($input['line_observations'][$index] ?? '')) ?: null,
                $unitPrice, $discount, $allocated, $vatRate, $net, $vat, $total, $index * 10,
            ]);
            if (!empty($input['line_remember_mapping'][$index]) && $variantId) {
                $mapping->remember($supplierId, $supplierCode, $name, $importedEan, $variantId, $pdo);
            }
            if (in_array($lineType, self::STOCKABLE_TYPES, true) && !$variantId) {
                $unmapped++;
            }
            $count++;
        }
        $orphanIds = array_values(array_diff($oldSupplierLineIds, $usedSupplierLineIds));
        if ($orphanIds) {
            $placeholders = implode(',', array_fill(0, count($orphanIds), '?'));
            $pdo->prepare(
                "DELETE sil FROM supplier_invoice_lines sil WHERE sil.id IN ({$placeholders}) "
                . 'AND NOT EXISTS(SELECT 1 FROM nir_lines nl WHERE nl.supplier_invoice_line_id=sil.id)'
            )->execute($orphanIds);
        }
        return ['count' => $count, 'unmapped' => $unmapped];
    }

    private function validateForConfirmation(PDO $pdo, array $document): array
    {
        $statement = $pdo->prepare(
            'SELECT nl.*,sil.line_type,sil.is_ignored,sil.ignore_reason FROM nir_lines nl '
            . 'JOIN supplier_invoice_lines sil ON sil.id=nl.supplier_invoice_line_id WHERE nl.nir_document_id=? ORDER BY nl.id FOR UPDATE'
        );
        $statement->execute([$document['id']]);
        $lines = $statement->fetchAll();
        if (!$lines) {
            throw new HttpException(422, 'Adaugă cel puțin o linie înainte de confirmarea NIR-ului.');
        }
        $stockable = 0;
        $postable = 0;
        foreach ($lines as $line) {
            if (in_array($line['line_type'], self::STOCKABLE_TYPES, true)) {
                $stockable++;
                if (!(int) $line['product_id'] || !(int) $line['product_variant_id'] || trim((string) $line['sku_snapshot']) === '') {
                    throw new HttpException(422, 'Toate produsele fizice trebuie asociate cu un SKU Maison Bébé înainte de confirmare.');
                }
                if (Decimal::cmp((string) $line['accepted_quantity'], '0', 4) < 0) {
                    throw new HttpException(422, 'Cantitatea acceptată nu poate fi negativă.');
                }
                if ((int) $line['track_accounting_stock_snapshot'] === 1 && Decimal::cmp((string) $line['accepted_quantity'], '0', 4) > 0) $postable++;
            }
            $hasDifference = Decimal::cmp((string) $line['received_quantity'], (string) $line['invoiced_quantity'], 4) !== 0
                || Decimal::cmp((string) $line['accepted_quantity'], (string) $line['received_quantity'], 4) !== 0;
            if ($hasDifference && (trim((string) $line['difference_reason']) === '' || $line['difference_type'] === 'none')) {
                throw new HttpException(422, 'Diferențele de recepție trebuie explicate înainte de confirmare.');
            }
            if ($line['line_type'] === 'ignored' && trim((string) $line['ignore_reason']) === '') {
                throw new HttpException(422, 'Linia ignorată nu are justificare.');
            }
        }
        if ($stockable === 0) {
            throw new HttpException(422, 'NIR-ul trebuie să conțină cel puțin un produs urmărit contabil.');
        }
        if ($postable === 0) {
            throw new HttpException(422, 'NIR-ul trebuie să conțină cel puțin o cantitate acceptată pentru un produs urmărit în Stocuri Conta.');
        }
        return $lines;
    }

    private function refreshFinalSnapshots(PDO $pdo, int $nirId, array $lines): void
    {
        $mapping = new ProductMappingService();
        $update = $pdo->prepare(
            'UPDATE nir_lines SET product_id=?,product_variant_id=?,sku_snapshot=?,product_name_snapshot=?,variant_name_snapshot=?,online_stock_mode_snapshot=?,track_accounting_stock_snapshot=? WHERE id=?'
        );
        foreach ($lines as $line) {
            if (!(int) $line['product_variant_id']) {
                continue;
            }
            $variant = $mapping->variant((int) $line['product_variant_id'], $pdo);
            if (!$variant || trim((string) $variant['sku']) === '') {
                throw new HttpException(422, 'SKU-ul unei variante asociate nu mai este disponibil.');
            }
            $update->execute([
                $variant['product_id'], $variant['id'], $variant['sku'], $variant['product_name'], $variant['variant_name'],
                (int) $variant['track_inventory'] ? 'limited' : 'unlimited', (int) $variant['track_accounting_stock'], $line['id'],
            ]);
        }
    }

    private function isPartial(PDO $pdo, int $invoiceId, int $nirId): bool
    {
        $statement = $pdo->prepare(
            'SELECT EXISTS(SELECT 1 FROM nir_lines nl WHERE nl.nir_document_id=? '
            . 'AND nl.previously_received_quantity+nl.accepted_quantity<nl.invoiced_quantity)'
        );
        $statement->execute([$nirId]);
        return (bool) $statement->fetchColumn();
    }

    private function updateSupplierInvoiceReceptionStatus(PDO $pdo, int $invoiceId): void
    {
        $total = $pdo->prepare('SELECT COALESCE(SUM(invoiced_quantity),0) FROM supplier_invoice_lines WHERE supplier_invoice_id=?');
        $total->execute([$invoiceId]);
        $invoiced = (string) $total->fetchColumn();
        $accepted = $pdo->prepare(
            "SELECT COALESCE(SUM(nl.accepted_quantity),0) FROM nir_lines nl JOIN nir_documents n ON n.id=nl.nir_document_id "
            . "WHERE n.supplier_invoice_id=? AND n.document_kind='receipt' AND n.status IN ('Confirmed','PartiallyReceived')"
        );
        $accepted->execute([$invoiceId]);
        $received = (string) $accepted->fetchColumn();
        $reversed=$pdo->prepare("SELECT COUNT(*) FROM nir_documents WHERE supplier_invoice_id=? AND document_kind='receipt' AND status='Reversed'");
        $reversed->execute([$invoiceId]);
        $status = Decimal::cmp($received, $invoiced, 4) >= 0 ? 'received' : (Decimal::cmp($received,'0',4)>0?'partially_received':((int)$reversed->fetchColumn()>0?'reversed':'draft'));
        $pdo->prepare('UPDATE supplier_invoices SET status=? WHERE id=?')->execute([$status, $invoiceId]);
    }

    private function refreshInvoiceTotals(PDO $pdo, int $invoiceId): void
    {
        $statement = $pdo->prepare(
            'SELECT COALESCE(SUM(value_without_vat),0) net,COALESCE(SUM(vat_value),0) vat,COALESCE(SUM(total_with_vat),0) total '
            . 'FROM supplier_invoice_lines WHERE supplier_invoice_id=? AND is_ignored=0'
        );
        $statement->execute([$invoiceId]);
        $totals = $statement->fetch();
        $pdo->prepare('UPDATE supplier_invoices SET total_without_vat=?,vat_total=?,grand_total=? WHERE id=?')
            ->execute([$totals['net'], $totals['vat'], $totals['total'], $invoiceId]);
    }

    private function seedPartialLines(PDO $pdo, int $nirId, int $invoiceId): void
    {
        $statement = $pdo->prepare('SELECT * FROM supplier_invoice_lines WHERE supplier_invoice_id=? ORDER BY sort_order,id');
        $statement->execute([$invoiceId]);
        $insert = $pdo->prepare(
            'INSERT INTO nir_lines '
            . '(nir_document_id,supplier_invoice_line_id,product_id,product_variant_id,sku_snapshot,product_name_snapshot,variant_name_snapshot,unit_of_measure_snapshot,online_stock_mode_snapshot,track_accounting_stock_snapshot,invoiced_quantity,previously_received_quantity,received_quantity,accepted_quantity,damaged_quantity,difference_quantity,difference_type,unit_purchase_price_without_vat,discount_value,vat_rate,value_without_vat,vat_value,total_with_vat,sort_order) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,0,0,0,\'none\',?,?,?,?,?,?,?)'
        );
        foreach ($statement->fetchAll() as $line) {
            $variant = $line['product_variant_id'] ? (new ProductMappingService())->variant((int) $line['product_variant_id'], $pdo) : null;
            $previous = $pdo->prepare(
                "SELECT COALESCE(SUM(nl.accepted_quantity),0) FROM nir_lines nl JOIN nir_documents n ON n.id=nl.nir_document_id "
                . "WHERE nl.supplier_invoice_line_id=? AND n.status IN ('Confirmed','PartiallyReceived') AND n.document_kind='receipt'"
            );
            $previous->execute([$line['id']]);
            $insert->execute([
                $nirId, $line['id'], $line['product_id'], $line['product_variant_id'], $line['maison_bebe_sku'],
                $variant['product_name'] ?? $line['supplier_product_name'], $variant['variant_name'] ?? null,
                $line['unit_of_measure'], $variant && !(bool) $variant['track_inventory'] ? 'unlimited' : 'limited',
                (int) ($variant['track_accounting_stock'] ?? 0), $line['invoiced_quantity'], $previous->fetchColumn(),
                $line['unit_price_without_vat'], $line['discount_value'], $line['vat_rate'],
                $line['value_without_vat'], $line['vat_value'], $line['total_with_vat'], $line['sort_order'],
            ]);
        }
    }

    private function supplier(PDO $pdo, array $header): int
    {
        if ($header['supplier_tax_id_normalized'] === '') {
            throw new HttpException(422, 'CUI-ul furnizorului nu este valid.');
        }
        $pdo->prepare(
            'INSERT INTO accounting_suppliers (legal_name,tax_id,tax_id_normalized,address_json) VALUES (?,?,?,?) '
            . 'ON DUPLICATE KEY UPDATE legal_name=VALUES(legal_name),tax_id=VALUES(tax_id),address_json=VALUES(address_json)'
        )->execute([
            $header['supplier_name'], $header['supplier_tax_id'], $header['supplier_tax_id_normalized'],
            json_encode(['line1' => $header['supplier_address']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $statement = $pdo->prepare('SELECT id FROM accounting_suppliers WHERE tax_id_normalized=?');
        $statement->execute([$header['supplier_tax_id_normalized']]);
        return (int) $statement->fetchColumn();
    }

    private function findInvoice(PDO $pdo, int $supplierId, string $series, string $number): ?array
    {
        $statement = $pdo->prepare(
            'SELECT * FROM supplier_invoices WHERE supplier_id=? AND invoice_series_normalized=? AND invoice_number_normalized=? LIMIT 1'
        );
        $statement->execute([$supplierId, $series, $number]);
        return $statement->fetch() ?: null;
    }

    private function lockDocument(PDO $pdo, int $nirId): array
    {
        $statement = $pdo->prepare(
            'SELECT n.*,si.invoice_date,si.invoice_series,si.invoice_number FROM nir_documents n '
            . 'JOIN supplier_invoices si ON si.id=n.supplier_invoice_id WHERE n.id=? FOR UPDATE'
        );
        $statement->execute([$nirId]);
        $document = $statement->fetch();
        if (!$document) {
            throw new HttpException(404, 'NIR-ul nu a fost găsit.');
        }
        return $document;
    }

    private function assertMutable(array $document): void
    {
        if (!in_array($document['status'], self::MUTABLE_STATUSES, true)) {
            throw new HttpException(422, 'Un NIR confirmat sau inversat este imuabil și nu poate fi editat ori șters.');
        }
    }

    private function normalizeDocumentIdentity(string $value): string
    {
        return strtoupper(preg_replace('/[^\p{L}\p{N}]+/u', '', trim($value)) ?? '');
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }
}
