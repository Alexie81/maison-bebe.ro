<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use RuntimeException;
use Throwable;

final class InvoiceStornoService
{
    /**
     * Issues one full storno document for an issued sales invoice.
     *
     * The original invoice remains immutable. When $physicalReturn is true,
     * Stocuri Conta receives inverse movements; otherwise the correction is
     * financial only.
     */
    public function issueFull(
        int $originalInvoiceId,
        string $issueDate,
        string $reason,
        bool $physicalReturn,
        bool $periodOverride = false,
        ?string $periodOverrideReason = null
    ): int {
        $issueDate = trim($issueDate);
        $reason = trim($reason);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $issueDate || $issueDate > date('Y-m-d')) {
            throw new RuntimeException('Data stornării nu este validă și nu poate fi în viitor.');
        }
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Completează motivul stornării în cel puțin 5 caractere.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                "SELECT i.*,s.prefix series_prefix FROM invoices i "
                . "LEFT JOIN invoice_series s ON s.id=i.series_id WHERE i.id=? FOR UPDATE"
            );
            $statement->execute([$originalInvoiceId]);
            $original = $statement->fetch();
            if (!$original || $original['document_type'] !== 'invoice' || $original['status'] !== 'issued') {
                throw new RuntimeException('Poate fi stornată doar o factură de ieșire emisă.');
            }
            if ($issueDate < (string) $original['issue_date']) {
                throw new RuntimeException('Data stornării nu poate fi anterioară facturii inițiale.');
            }

            $existing = $pdo->prepare(
                "SELECT id FROM invoices WHERE parent_invoice_id=? AND document_type='storno' "
                . "AND status IN ('issuing','issued') ORDER BY id LIMIT 1 FOR UPDATE"
            );
            $existing->execute([$originalInvoiceId]);
            if ($existingId = (int) $existing->fetchColumn()) {
                $pdo->commit();
                return $existingId;
            }

            if ($physicalReturn) {
                (new AccountingPeriodService())->assertPostingAllowed(
                    $issueDate,
                    $periodOverride,
                    $periodOverrideReason,
                    $pdo
                );
            }

            $seriesId = (int) ($original['series_id'] ?? 0);
            if (!$seriesId) {
                throw new RuntimeException('Factura inițială nu are o serie fiscală asociată.');
            }
            $seriesStatement = $pdo->prepare('SELECT * FROM invoice_series WHERE id=? AND is_active=1 FOR UPDATE');
            $seriesStatement->execute([$seriesId]);
            $series = $seriesStatement->fetch();
            if (!$series) {
                throw new RuntimeException('Seria fiscală a facturii inițiale nu mai este activă.');
            }
            $number = (string) $series['prefix'] . str_pad((string) $series['next_number'], 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE invoice_series SET next_number=next_number+1 WHERE id=?')->execute([$seriesId]);

            $notes = 'Stornare integrală a facturii ' . (string) $original['number'] . '. Motiv: ' . $reason;
            $insert = $pdo->prepare(
                "INSERT INTO invoices "
                . "(order_id,company_profile_id,series_id,template_version_id,connector_id,parent_invoice_id,document_type,customer_type,number,status,currency,issue_date,delivery_date,due_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,discount_minor,vat_minor,grand_total_minor,notes) "
                . "VALUES (?,?,?,?,?,?,'storno',?,?,'issuing',?,?,?,?,?,?,?,?,?,?,?)"
            );
            $insert->execute([
                $original['order_id'], $original['company_profile_id'], $seriesId,
                $original['template_version_id'], $original['connector_id'], $originalInvoiceId,
                $original['customer_type'], $number, $original['currency'], $issueDate, $issueDate, $issueDate,
                $original['issuer_snapshot_json'], $original['customer_snapshot_json'],
                -abs((int) $original['subtotal_minor']),
                -abs((int) $original['discount_minor']),
                -abs((int) $original['vat_minor']),
                -abs((int) $original['grand_total_minor']),
                $notes,
            ]);
            $stornoId = (int) $pdo->lastInsertId();

            $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order,id');
            $items->execute([$originalInvoiceId]);
            $rows = $items->fetchAll();
            if ($rows === []) {
                throw new RuntimeException('Factura inițială nu conține poziții care pot fi stornate.');
            }
            $insertItem = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id,order_item_id,name,sku,quantity,unit_price_minor,discount_minor,vat_rate,vat_minor,total_minor,sort_order) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($rows as $row) {
                $insertItem->execute([
                    $stornoId,
                    $row['order_item_id'],
                    $row['name'],
                    $row['sku'],
                    -abs((float) $row['quantity']),
                    abs((int) $row['unit_price_minor']),
                    -abs((int) $row['discount_minor']),
                    $row['vat_rate'],
                    -abs((int) $row['vat_minor']),
                    -abs((int) $row['total_minor']),
                    $row['sort_order'],
                ]);
            }

            $payload = json_encode([
                'original_invoice_id' => $originalInvoiceId,
                'original_invoice_number' => $original['number'],
                'reason' => $reason,
                'physical_return' => $physicalReturn,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pdo->prepare(
                "INSERT INTO invoice_events (invoice_id,event_type,status,created_by,payload_json) "
                . "VALUES (?,'storno_started','issuing',?,?)"
            )->execute([$stornoId, Auth::id(), $payload]);

            $pdfInvoice = $pdo->prepare(
                'SELECT i.*,parent.number parent_number FROM invoices i LEFT JOIN invoices parent ON parent.id=i.parent_invoice_id WHERE i.id=?'
            );
            $pdfInvoice->execute([$stornoId]);
            $pdfItems = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order,id');
            $pdfItems->execute([$stornoId]);
            $relative = '/invoices/' . date('Y/m', strtotime($issueDate)) . '/storno-' . $stornoId . '-' . bin2hex(random_bytes(8)) . '.pdf';
            $path = BASE_PATH . '/storage' . $relative;
            (new PdfInvoiceRenderer())->render($pdfInvoice->fetch(), $pdfItems->fetchAll(), $path);
            $hash = hash_file('sha256', $path);

            $pdo->prepare("UPDATE invoices SET status='issued',document_hash=?,issued_at=NOW() WHERE id=?")
                ->execute([$hash, $stornoId]);
            $pdo->prepare(
                "INSERT INTO invoice_artifacts (invoice_id,artifact_type,path,mime_type,sha256,size_bytes) "
                . "VALUES (?,'pdf',?,'application/pdf',?,?)"
            )->execute([$stornoId, $relative, $hash, filesize($path)]);
            $pdo->prepare(
                "INSERT INTO invoice_events (invoice_id,event_type,status,created_by,payload_json) "
                . "VALUES (?,'issued','issued',?,?)"
            )->execute([$stornoId, Auth::id(), $payload]);
            $pdo->prepare(
                "INSERT INTO invoice_events (invoice_id,event_type,status,created_by,payload_json) "
                . "VALUES (?,'storno_issued','storned',?,?)"
            )->execute([$originalInvoiceId, Auth::id(), json_encode([
                'storno_invoice_id' => $stornoId,
                'storno_invoice_number' => $number,
                'reason' => $reason,
                'physical_return' => $physicalReturn,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

            (new AccountingStockPostingService())->reverseSalesInvoiceOutflow(
                $originalInvoiceId,
                $stornoId,
                $issueDate,
                $physicalReturn,
                'sales-storno:' . $stornoId,
                $pdo
            );

            $pdo->commit();
            return $stornoId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
