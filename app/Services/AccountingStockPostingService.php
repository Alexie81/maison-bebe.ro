<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use RuntimeException;
use PDO;
use PDOException;

final class AccountingStockPostingService
{
    public function postNir(int $nirId, ?string $correlationId = null, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $correlationId ??= 'nir:' . $nirId . ':' . bin2hex(random_bytes(8));
        $statement = $pdo->prepare(
            'SELECT n.*,si.supplier_name_snapshot,si.currency,si.exchange_rate,nl.id nir_line_id,nl.product_id,nl.product_variant_id,nl.sku_snapshot,'
            . 'nl.product_name_snapshot,nl.variant_name_snapshot,nl.supplier_invoice_line_id,nl.invoiced_quantity,nl.accepted_quantity,nl.value_without_vat,nl.unit_purchase_price_without_vat,'
            . 'nl.allocated_acquisition_cost,nl.track_accounting_stock_snapshot '
            . 'FROM nir_documents n JOIN supplier_invoices si ON si.id=n.supplier_invoice_id '
            . 'JOIN nir_lines nl ON nl.nir_document_id=n.id WHERE n.id=? ORDER BY nl.sort_order,nl.id'
        );
        $statement->execute([$nirId]);
        $rows = $statement->fetchAll();
        if (!$rows) {
            throw new RuntimeException('NIR-ul nu conține linii care pot fi postate.');
        }
        $variantIds = [];
        foreach ($rows as $row) {
            if (!(bool) $row['track_accounting_stock_snapshot']) {
                continue;
            }
            $quantity = Decimal::normalize($row['accepted_quantity'], 4);
            if (Decimal::cmp($quantity, '0', 4) <= 0) {
                continue;
            }
            if (!(int) $row['product_id'] || !(int) $row['product_variant_id'] || trim((string) $row['sku_snapshot']) === '') {
                throw new RuntimeException('O linie stocabilă din NIR nu are asociere SKU completă.');
            }
            $baseValue = Decimal::normalize($row['value_without_vat'], 2);
            $allocated = Decimal::normalize($row['allocated_acquisition_cost'], 2);
            $invoiced = Decimal::normalize($row['invoiced_quantity'], 4);
            $baseUnitCost = Decimal::cmp($invoiced, '0', 4) > 0
                ? Decimal::div($baseValue, $invoiced, 6)
                : Decimal::normalize($row['unit_purchase_price_without_vat'], 6);
            $allocatedUnitCost = Decimal::cmp($quantity, '0', 4) > 0 ? Decimal::div($allocated, $quantity, 6) : '0.000000';
            $unitCost = Decimal::add($baseUnitCost, $allocatedUnitCost, 6);
            $currency=strtoupper((string)($row['currency']??'RON'));
            $exchangeRate=$currency==='RON'?'1.000000':Decimal::normalize($row['exchange_rate']??'0',6);
            if(Decimal::cmp($exchangeRate,'0',6)<=0)throw new RuntimeException('NIR-ul în '.$currency.' nu are un curs valutar valid.');
            $unitCost=Decimal::mul($unitCost,$exchangeRate,6);
            $isReversal = $row['document_kind'] === 'reversal';
            $reversalOf = null;
            if ($isReversal && (int) $row['original_nir_id']) {
                $original = $pdo->prepare(
                    "SELECT m.id FROM accounting_stock_movements m JOIN nir_lines l ON l.id=m.source_document_line_id "
                    . "WHERE m.source_document_type='NIR' AND l.nir_document_id=? AND l.supplier_invoice_line_id=? "
                    . "AND m.movement_type='NIR_IN' ORDER BY m.id LIMIT 1"
                );
                $original->execute([(int) $row['original_nir_id'], (int) $row['supplier_invoice_line_id']]);
                $reversalOf = (int) $original->fetchColumn() ?: null;
            }
            $this->insertMovement($pdo, [
                'product_id' => (int) $row['product_id'],
                'product_variant_id' => (int) $row['product_variant_id'],
                'sku_snapshot' => (string) $row['sku_snapshot'],
                'product_name_snapshot' => (string) $row['product_name_snapshot'],
                'variant_name_snapshot' => $row['variant_name_snapshot'],
                'warehouse_id' => (int) $row['warehouse_id'],
                'effective_date' => (string) $row['receipt_date'],
                'movement_type' => $isReversal ? 'NIR_REVERSAL_OUT' : 'NIR_IN',
                'quantity_in' => $isReversal ? '0.0000' : $quantity,
                'quantity_out' => $isReversal ? $quantity : '0.0000',
                'source_unit_cost' => $unitCost,
                'source_document_type' => 'NIR',
                'source_document_id' => $nirId,
                'source_document_line_id' => (int) $row['nir_line_id'],
                'source_document_series' => $row['series'],
                'source_document_number' => $row['formatted_number'],
                'counterparty_snapshot' => $row['supplier_name_snapshot'],
                'is_late_posted' => (int) $row['is_late_entered'],
                'reversal_of_movement_id' => $reversalOf,
                'correlation_id' => $correlationId,
                'idempotency_key' => ($isReversal ? 'nir-reversal:' : 'nir:') . $nirId . ':line:' . $row['nir_line_id'],
            ]);
            $variantIds[] = (int) $row['product_variant_id'];
        }
        if ($variantIds) {
            (new AccountingStockProjectionService())->rebuildVariants(
                $variantIds,
                (string) $rows[0]['receipt_date'],
                $rows[0]['document_kind'] === 'reversal' ? 'Inversare NIR ' . $rows[0]['formatted_number'] : 'Confirmare NIR ' . $rows[0]['formatted_number'],
                $correlationId,
                $pdo
            );
        }
        return array_values(array_unique($variantIds));
    }

    /**
     * Repairs NIR movements created when the source invoice currency was not
     * selected by the posting query and the exchange rate therefore defaulted to 1.
     */
    public function repairForeignCurrencyNirCosts(?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->query(
            "SELECT m.id movement_id,m.product_variant_id,m.effective_date,m.source_unit_cost,"
            . "nl.invoiced_quantity,nl.accepted_quantity,nl.value_without_vat,nl.unit_purchase_price_without_vat,nl.allocated_acquisition_cost,"
            . "si.currency,si.exchange_rate "
            . "FROM accounting_stock_movements m "
            . "JOIN nir_lines nl ON nl.id=m.source_document_line_id "
            . "JOIN nir_documents n ON n.id=m.source_document_id AND n.id=nl.nir_document_id "
            . "JOIN supplier_invoices si ON si.id=n.supplier_invoice_id "
            . "WHERE m.source_document_type='NIR' AND UPPER(si.currency)<>'RON' "
            . "AND m.movement_type IN ('NIR_IN','NIR_REVERSAL_OUT') ORDER BY m.id"
        );
        $update = $pdo->prepare('UPDATE accounting_stock_movements SET source_unit_cost=? WHERE id=?');
        $variantIds = [];
        $earliestDate = null;
        $updated = 0;

        foreach ($statement->fetchAll() as $row) {
            $accepted = Decimal::normalize($row['accepted_quantity'], 4);
            if (Decimal::cmp($accepted, '0', 4) <= 0) {
                continue;
            }
            $baseValue = Decimal::normalize($row['value_without_vat'], 2);
            $allocated = Decimal::normalize($row['allocated_acquisition_cost'], 2);
            $invoiced = Decimal::normalize($row['invoiced_quantity'], 4);
            $baseUnitCost = Decimal::cmp($invoiced, '0', 4) > 0
                ? Decimal::div($baseValue, $invoiced, 6)
                : Decimal::normalize($row['unit_purchase_price_without_vat'], 6);
            $allocatedUnitCost = Decimal::div($allocated, $accepted, 6);
            $invoiceUnitCost = Decimal::add($baseUnitCost, $allocatedUnitCost, 6);
            $rate = Decimal::normalize($row['exchange_rate'] ?? '0', 6);
            if (Decimal::cmp($rate, '0', 6) <= 0) {
                throw new RuntimeException('NIR-ul in ' . strtoupper((string) $row['currency']) . ' nu are un curs valutar valid.');
            }
            $correctCost = Decimal::mul($invoiceUnitCost, $rate, 6);
            $currentCost = Decimal::normalize($row['source_unit_cost'] ?? '0', 6);
            if (Decimal::cmp($correctCost, $currentCost, 6) === 0) {
                continue;
            }
            $update->execute([$correctCost, (int) $row['movement_id']]);
            $variantIds[] = (int) $row['product_variant_id'];
            $effectiveDate = (string) $row['effective_date'];
            $earliestDate = $earliestDate === null || $effectiveDate < $earliestDate ? $effectiveDate : $earliestDate;
            $updated++;
        }

        $variantIds = array_values(array_unique($variantIds));
        if ($variantIds !== []) {
            (new AccountingStockProjectionService())->rebuildVariants(
                $variantIds,
                $earliestDate,
                'Corectare conversie valutara NIR in RON',
                'nir-currency-repair:' . bin2hex(random_bytes(10)),
                $pdo
            );
        }

        return [
            'movements_updated' => $updated,
            'variants_rebuilt' => count($variantIds),
            'earliest_date' => $earliestDate,
        ];
    }

    public function postSalesInvoiceOutflow(int $invoiceId, ?string $correlationId = null, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $correlationId ??= 'sales-invoice:' . $invoiceId . ':' . bin2hex(random_bytes(8));
        $invoiceStatement = $pdo->prepare('SELECT * FROM invoices WHERE id=?');
        $invoiceStatement->execute([$invoiceId]);
        $invoice = $invoiceStatement->fetch();
        if (!$invoice || $invoice['status'] !== 'issued') {
            return [];
        }
        $effectiveDate = (string) ($invoice['delivery_date'] ?: $invoice['issue_date']);
        $warehouseId = (int) $pdo->query('SELECT id FROM accounting_warehouses WHERE is_default=1 AND is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
        if (!$warehouseId) {
            throw new RuntimeException('Nu există o gestiune contabilă implicită.');
        }
        $statement = $pdo->prepare(
            'SELECT ii.*,oi.product_id,oi.variant_id,oi.customization_json,p.name product_name,p.is_gift_box,'
            . 'v.sku variant_sku,v.cost_minor,v.track_accounting_stock,'
            . "COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name "
            . 'FROM invoice_items ii LEFT JOIN order_items oi ON oi.id=ii.order_item_id '
            . 'LEFT JOIN products p ON p.id=oi.product_id LEFT JOIN product_variants v ON v.id=oi.variant_id '
            . 'LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id '
            . 'LEFT JOIN product_options po ON po.id=ov.option_id WHERE ii.invoice_id=? GROUP BY ii.id ORDER BY ii.sort_order,ii.id'
        );
        $statement->execute([$invoiceId]);
        $variantIds = [];
        $scopeService = new AccountingStockScopeService();
        foreach ($statement->fetchAll() as $row) {
            if (str_starts_with((string) $row['sku'], 'OPT-') || str_starts_with((string) $row['sku'], 'PERS-')) {
                continue;
            }
            if (!$row['order_item_id']) {
                continue;
            }
            $customization=json_decode((string)($row['customization_json']??''),true)?:[];
            $set=(new ProductSetService())->snapshotFromCustomization($customization);
            if($set){
                $component=null;
                foreach((array)$set['components'] as $candidate){if((string)($candidate['sku']??'')===(string)$row['sku']){$component=$candidate;break;}}
                if(!$component)throw new RuntimeException('O componentă a setului de pe factură nu mai poate fi identificată după SKU.');
                $row['product_id']=(int)$component['product_id'];$row['variant_id']=(int)$component['variant_id'];
                $row['product_name']=(string)$component['name'];$row['variant_name']=(string)($component['variant']??'Standard');
                $row['variant_sku']=(string)$component['sku'];$row['cost_minor']=$component['cost_minor']??null;
                $row['track_accounting_stock']=!empty($component['track_accounting_stock'])?1:0;
            }
            if (!(int) $row['product_id'] || !(int) $row['variant_id'] || trim((string) $row['sku']) === '') {
                (new AccountingAuditService())->record(
                    'accounting.sales_invoice.unmapped_line',
                    'invoice_item',
                    (int) $row['id'],
                    [],
                    ['invoice_id' => $invoiceId, 'sku' => $row['sku']],
                    'Linie stocabilă fără SKU sau variantă',
                    $correlationId,
                    $pdo
                );
                throw new RuntimeException('Factura conține o linie stocabilă fără asociere SKU. Ieșirea contabilă nu a fost postată.');
            }
            $accountingTarget = $scopeService->resolveVariant((int) $row['variant_id'], $pdo);
            if (!$accountingTarget) continue;
            $row['variant_id'] = (int) $accountingTarget['id'];
            $row['product_id'] = (int) $accountingTarget['product_id'];
            $row['sku'] = (string) $accountingTarget['sku'];
            $row['product_name'] = (string) $accountingTarget['product_name'];
            $row['variant_name'] = (string) $accountingTarget['variant_name'];
            $row['cost_minor'] = $accountingTarget['cost_minor'] ?? $row['cost_minor'];
            $balance = $pdo->prepare('SELECT calculated_unit_cost FROM accounting_stock_balances WHERE product_variant_id=? AND warehouse_id=?');
            $balance->execute([(int) $row['variant_id'], $warehouseId]);
            $unitCost = $balance->fetchColumn();
            if (!empty($accountingTarget['is_product_scope'])) {
                $aggregate = $pdo->prepare(
                    'SELECT COALESCE(SUM(b.current_quantity),0) quantity,COALESCE(SUM(b.current_accounting_value),0) value '
                    . 'FROM product_variants av LEFT JOIN accounting_stock_balances b ON b.product_variant_id=av.id AND b.warehouse_id=? WHERE av.product_id=?'
                );
                $aggregate->execute([$warehouseId, (int) $row['product_id']]);
                $aggregate = $aggregate->fetch();
                if ($aggregate && Decimal::cmp((string) $aggregate['quantity'], '0', 4) !== 0) {
                    $unitCost = Decimal::div((string) $aggregate['value'], (string) $aggregate['quantity'], 6);
                }
            }
            if ($unitCost === false || Decimal::cmp((string) $unitCost, '0', 6) === 0) {
                $unitCost = $row['cost_minor'] !== null ? bcdiv((string) $row['cost_minor'], '100', 6) : '0.000000';
            }
            $this->insertMovement($pdo, [
                'product_id' => (int) $row['product_id'],
                'product_variant_id' => (int) $row['variant_id'],
                'sku_snapshot' => (string) $row['sku'],
                'product_name_snapshot' => (string) $row['product_name'],
                'variant_name_snapshot' => (string) $row['variant_name'],
                'warehouse_id' => $warehouseId,
                'effective_date' => $effectiveDate,
                'movement_type' => 'SALES_INVOICE_OUT',
                'quantity_in' => '0.0000',
                'quantity_out' => Decimal::normalize($row['quantity'], 4),
                'source_unit_cost' => Decimal::normalize($unitCost, 6),
                'source_document_type' => 'SALES_INVOICE',
                'source_document_id' => $invoiceId,
                'source_document_line_id' => (int) $row['id'],
                'source_document_series' => null,
                'source_document_number' => $invoice['number'],
                'counterparty_snapshot' => $this->customerName($invoice),
                'is_late_posted' => $effectiveDate < date('Y-m-d') ? 1 : 0,
                'reversal_of_movement_id' => null,
                'correlation_id' => $correlationId,
                'idempotency_key' => 'sales-invoice:' . $invoiceId . ':line:' . $row['id'] . ':out',
            ]);
            $variantIds[] = (int) $row['variant_id'];
        }
        if ($variantIds) {
            (new AccountingStockProjectionService())->rebuildVariants(
                $variantIds,
                $effectiveDate,
                'Postare factură de ieșire ' . $invoice['number'],
                $correlationId,
                $pdo
            );
        }
        $pdo->prepare('UPDATE invoices SET accounting_posted_at=COALESCE(accounting_posted_at,NOW()) WHERE id=?')->execute([$invoiceId]);
        (new AccountingAuditService())->record(
            'accounting.sales_invoice.posted',
            'invoice',
            $invoiceId,
            [],
            ['effective_date' => $effectiveDate, 'variants' => array_values(array_unique($variantIds))],
            null,
            $correlationId,
            $pdo
        );
        return array_values(array_unique($variantIds));
    }

    public function reverseSalesInvoiceOutflow(
        int $originalInvoiceId,
        int $reversalInvoiceId,
        string $effectiveDate,
        bool $physicalReturn,
        ?string $correlationId = null,
        ?PDO $pdo = null
    ): array {
        $pdo ??= Database::connection();
        $correlationId ??= 'sales-reversal:' . $reversalInvoiceId . ':' . bin2hex(random_bytes(8));
        if (!$physicalReturn) {
            (new AccountingAuditService())->record(
                'accounting.sales_invoice.reversed_without_stock_return',
                'invoice',
                $reversalInvoiceId,
                [],
                ['original_invoice_id' => $originalInvoiceId],
                'Stornare financiară fără retur fizic',
                $correlationId,
                $pdo
            );
            return [];
        }
        $statement = $pdo->prepare(
            "SELECT * FROM accounting_stock_movements WHERE source_document_type='SALES_INVOICE' "
            . "AND source_document_id=? AND movement_type='SALES_INVOICE_OUT' ORDER BY id"
        );
        $statement->execute([$originalInvoiceId]);
        $variantIds = [];
        foreach ($statement->fetchAll() as $movement) {
            $returned=$pdo->prepare("SELECT EXISTS(SELECT 1 FROM accounting_stock_movements WHERE reversal_of_movement_id=? AND movement_type IN ('SALES_INVOICE_REVERSAL_IN','CUSTOMER_RETURN_IN'))");$returned->execute([$movement['id']]);if((bool)$returned->fetchColumn())continue;
            $this->insertMovement($pdo, [
                'product_id' => (int) $movement['product_id'],
                'product_variant_id' => (int) $movement['product_variant_id'],
                'sku_snapshot' => $movement['sku_snapshot'],
                'product_name_snapshot' => $movement['product_name_snapshot'],
                'variant_name_snapshot' => $movement['variant_name_snapshot'],
                'warehouse_id' => (int) $movement['warehouse_id'],
                'effective_date' => $effectiveDate,
                'movement_type' => 'SALES_INVOICE_REVERSAL_IN',
                'quantity_in' => $movement['quantity_out'],
                'quantity_out' => '0.0000',
                'source_unit_cost' => $movement['source_unit_cost'],
                'source_document_type' => 'SALES_INVOICE_REVERSAL',
                'source_document_id' => $reversalInvoiceId,
                'source_document_line_id' => (int) $movement['source_document_line_id'],
                'source_document_series' => null,
                'source_document_number' => (string) $reversalInvoiceId,
                'counterparty_snapshot' => $movement['counterparty_snapshot'],
                'is_late_posted' => $effectiveDate < date('Y-m-d') ? 1 : 0,
                'reversal_of_movement_id' => (int) $movement['id'],
                'correlation_id' => $correlationId,
                'idempotency_key' => 'sales-reversal:' . $reversalInvoiceId . ':movement:' . $movement['id'],
            ]);
            $variantIds[] = (int) $movement['product_variant_id'];
        }
        if ($variantIds) {
            (new AccountingStockProjectionService())->rebuildVariants($variantIds, $effectiveDate, 'Retur fizic aferent stornării', $correlationId, $pdo);
        }
        return array_values(array_unique($variantIds));
    }

    public function postCustomerReturnForOrder(int $orderId, string $effectiveDate, ?string $correlationId = null, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $correlationId ??= 'customer-return:' . $orderId . ':' . bin2hex(random_bytes(8));
        (new AccountingPeriodService())->assertPostingAllowed($effectiveDate, false, null, $pdo);
        $order=$pdo->prepare('SELECT order_number FROM orders WHERE id=?');$order->execute([$orderId]);$orderNumber=$order->fetchColumn();
        $invoice=$pdo->prepare("SELECT id,number FROM invoices WHERE order_id=? AND document_type='invoice' AND status='issued' ORDER BY id DESC LIMIT 1");
        $invoice->execute([$orderId]);$invoice=$invoice->fetch();
        if(!$invoice)return [];
        $statement=$pdo->prepare("SELECT * FROM accounting_stock_movements WHERE source_document_type='SALES_INVOICE' AND source_document_id=? AND movement_type='SALES_INVOICE_OUT' ORDER BY id");
        $statement->execute([(int)$invoice['id']]);$variantIds=[];
        foreach($statement->fetchAll() as $movement){
            $returned=$pdo->prepare("SELECT EXISTS(SELECT 1 FROM accounting_stock_movements WHERE reversal_of_movement_id=? AND movement_type IN ('SALES_INVOICE_REVERSAL_IN','CUSTOMER_RETURN_IN'))");$returned->execute([$movement['id']]);if((bool)$returned->fetchColumn())continue;
            $this->insertMovement($pdo,[
                'product_id'=>(int)$movement['product_id'],'product_variant_id'=>(int)$movement['product_variant_id'],'sku_snapshot'=>$movement['sku_snapshot'],
                'product_name_snapshot'=>$movement['product_name_snapshot'],'variant_name_snapshot'=>$movement['variant_name_snapshot'],'warehouse_id'=>(int)$movement['warehouse_id'],
                'effective_date'=>$effectiveDate,'movement_type'=>'CUSTOMER_RETURN_IN','quantity_in'=>$movement['quantity_out'],'quantity_out'=>'0.0000','source_unit_cost'=>$movement['source_unit_cost'],
                'source_document_type'=>'CUSTOMER_RETURN','source_document_id'=>$orderId,'source_document_line_id'=>(int)$movement['source_document_line_id'],'source_document_series'=>null,
                'source_document_number'=>(string)($orderNumber?:$orderId),'counterparty_snapshot'=>$movement['counterparty_snapshot'],'is_late_posted'=>$effectiveDate<date('Y-m-d')?1:0,
                'reversal_of_movement_id'=>(int)$movement['id'],'correlation_id'=>$correlationId,'idempotency_key'=>'customer-return:'.$orderId.':movement:'.$movement['id'],
            ]);$variantIds[]=(int)$movement['product_variant_id'];
        }
        if($variantIds)(new AccountingStockProjectionService())->rebuildVariants($variantIds,$effectiveDate,'Retur client recepționat pentru comanda '.$orderNumber,$correlationId,$pdo);
        (new AccountingAuditService())->record('accounting.customer_return.posted','order',$orderId,[],['invoice_id'=>(int)$invoice['id'],'variants'=>array_values(array_unique($variantIds))],null,$correlationId,$pdo);
        return array_values(array_unique($variantIds));
    }

    private function insertMovement(PDO $pdo, array $movement): int
    {
        $existing = $pdo->prepare('SELECT id FROM accounting_stock_movements WHERE idempotency_key=?');
        $existing->execute([$movement['idempotency_key']]);
        if ($id = (int) $existing->fetchColumn()) {
            return $id;
        }
        try {
            $pdo->prepare(
                'INSERT INTO accounting_stock_movements '
                . '(product_id,product_variant_id,sku_snapshot,product_name_snapshot,variant_name_snapshot,warehouse_id,effective_date,movement_type,quantity_in,quantity_out,source_unit_cost,source_document_type,source_document_id,source_document_line_id,source_document_series,source_document_number,counterparty_snapshot,is_late_posted,created_by,reversal_of_movement_id,correlation_id,idempotency_key) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $movement['product_id'], $movement['product_variant_id'], $movement['sku_snapshot'],
                $movement['product_name_snapshot'], $movement['variant_name_snapshot'], $movement['warehouse_id'],
                $movement['effective_date'], $movement['movement_type'], $movement['quantity_in'], $movement['quantity_out'],
                $movement['source_unit_cost'], $movement['source_document_type'], $movement['source_document_id'],
                $movement['source_document_line_id'], $movement['source_document_series'], $movement['source_document_number'],
                $movement['counterparty_snapshot'], $movement['is_late_posted'], Auth::id(),
                $movement['reversal_of_movement_id'], $movement['correlation_id'], $movement['idempotency_key'],
            ]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing->execute([$movement['idempotency_key']]);
            $id = (int) $existing->fetchColumn();
            if (!$id) {
                throw $exception;
            }
            return $id;
        }
    }

    private function customerName(array $invoice): string
    {
        $customer = json_decode((string) $invoice['customer_snapshot_json'], true) ?: [];
        return trim((string) ($customer['company_name'] ?? ''))
            ?: trim((string) (($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')))
            ?: 'Client';
    }
}
