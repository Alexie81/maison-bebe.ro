<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DOMDocument;
use DOMElement;
use MaisonBebe\Core\Database;
use RuntimeException;

final class EInvoiceUblService
{
    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function generate(int $invoiceId): array
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            "SELECT i.*,o.order_number,o.payment_method,o.payment_status,"
            . "parent.number parent_number,parent.issue_date parent_issue_date "
            . "FROM invoices i JOIN orders o ON o.id=i.order_id "
            . "LEFT JOIN invoices parent ON parent.id=i.parent_invoice_id "
            . "WHERE i.id=? AND i.status='issued' LIMIT 1"
        );
        $statement->execute([$invoiceId]);
        $invoice = $statement->fetch();
        if (!$invoice) {
            throw new RuntimeException('Factura emisă nu a fost găsită.');
        }

        $statement = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order,id');
        $statement->execute([$invoiceId]);
        $items = $statement->fetchAll();
        if ($items === []) {
            throw new RuntimeException('Factura nu conține linii.');
        }

        $seller = json_decode((string) $invoice['issuer_snapshot_json'], true) ?: [];
        $buyer = json_decode((string) $invoice['customer_snapshot_json'], true) ?: [];
        $this->validateSeller($seller);
        $buyerName = $this->buyerName($buyer);
        if ($buyerName === '') {
            throw new RuntimeException('Numele cumpărătorului lipsește.');
        }

        $isCreditNote = in_array((string) $invoice['document_type'], ['storno', 'credit_note'], true);
        if ($isCreditNote && empty($invoice['parent_number'])) {
            throw new RuntimeException('Documentul de corecție nu are factura inițială asociată.');
        }
        $currency = (string) ($invoice['currency'] ?: 'RON');
        $vatPayer = $this->isVatPayer($seller);
        $category = $vatPayer ? 'S' : 'O';
        $documentRate = 0.0;
        foreach ($items as $vatItem) {
            if ((float) $vatItem['vat_rate'] > 0) {
                $documentRate = (float) $vatItem['vat_rate'];
                break;
            }
        }
        if ($vatPayer && $documentRate <= 0) {
            $documentRate = 21.0;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $rootNamespace = $isCreditNote
            ? 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2'
            : 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
        $root = $dom->createElementNS($rootNamespace, $isCreditNote ? 'CreditNote' : 'Invoice');
        $dom->appendChild($root);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);

        $this->cbc($dom, $root, 'CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1');
        $this->cbc($dom, $root, 'ID', (string) $invoice['number']);
        $this->cbc($dom, $root, 'IssueDate', (string) $invoice['issue_date']);
        if (!$isCreditNote && !empty($invoice['due_date'])) {
            $this->cbc($dom, $root, 'DueDate', (string) $invoice['due_date']);
        }
        $this->cbc($dom, $root, $isCreditNote ? 'CreditNoteTypeCode' : 'InvoiceTypeCode', $isCreditNote ? '381' : '380');
        $this->cbc($dom, $root, 'DocumentCurrencyCode', $currency);

        if ($isCreditNote) {
            $reference = $this->cac($dom, $root, 'BillingReference');
            $documentReference = $this->cac($dom, $reference, 'InvoiceDocumentReference');
            $this->cbc($dom, $documentReference, 'ID', (string) $invoice['parent_number']);
            if (!empty($invoice['parent_issue_date'])) {
                $this->cbc($dom, $documentReference, 'IssueDate', (string) $invoice['parent_issue_date']);
            }
        } elseif (!empty($invoice['order_number'])) {
            $order = $this->cac($dom, $root, 'OrderReference');
            $this->cbc($dom, $order, 'ID', (string) $invoice['order_number']);
        }

        $root->appendChild($this->party($dom, 'AccountingSupplierParty', $seller, true, $this->sellerName($seller), $vatPayer));
        $root->appendChild($this->party($dom, 'AccountingCustomerParty', $buyer, false, $buyerName, $this->isVatPayer($buyer)));

        if (!$isCreditNote) {
            $payment = $this->cac($dom, $root, 'PaymentMeans');
            $this->cbc($dom, $payment, 'PaymentMeansCode', in_array($invoice['payment_method'], ['stripe', 'card'], true) ? '48' : '10');
            $this->cbc($dom, $payment, 'PaymentID', (string) $invoice['number']);
        }

        $subtotal = abs((int) $invoice['subtotal_minor']);
        $discount = abs((int) $invoice['discount_minor']);
        $vat = abs((int) $invoice['vat_minor']);
        $grandTotal = abs((int) $invoice['grand_total_minor']);
        if ($discount > 0) {
            $allowance = $this->cac($dom, $root, 'AllowanceCharge');
            $this->cbc($dom, $allowance, 'ChargeIndicator', 'false');
            $this->cbc($dom, $allowance, 'AllowanceChargeReason', 'Reducere comercială');
            $this->amountNode($dom, $allowance, 'Amount', $discount, $currency);
            $this->amountNode($dom, $allowance, 'BaseAmount', $subtotal, $currency);
            $taxCategory = $this->cac($dom, $allowance, 'TaxCategory');
            $this->taxCategory($dom, $taxCategory, $category, $documentRate, $vatPayer);
        }

        $tax = $this->cac($dom, $root, 'TaxTotal');
        $this->amountNode($dom, $tax, 'TaxAmount', $vat, $currency);
        $taxSubtotal = $this->cac($dom, $tax, 'TaxSubtotal');
        $taxBase = max(0, $subtotal - $discount);
        $this->amountNode($dom, $taxSubtotal, 'TaxableAmount', $taxBase, $currency);
        $this->amountNode($dom, $taxSubtotal, 'TaxAmount', $vat, $currency);
        $taxCategory = $this->cac($dom, $taxSubtotal, 'TaxCategory');
        $this->taxCategory($dom, $taxCategory, $category, $documentRate, $vatPayer);

        $total = $this->cac($dom, $root, 'LegalMonetaryTotal');
        $this->amountNode($dom, $total, 'LineExtensionAmount', $subtotal, $currency);
        $this->amountNode($dom, $total, 'TaxExclusiveAmount', $taxBase, $currency);
        $this->amountNode($dom, $total, 'TaxInclusiveAmount', $grandTotal, $currency);
        if ($discount > 0) {
            $this->amountNode($dom, $total, 'AllowanceTotalAmount', $discount, $currency);
        }
        $this->amountNode($dom, $total, 'PayableAmount', $grandTotal, $currency);

        foreach ($items as $index => $item) {
            $line = $this->cac($dom, $root, $isCreditNote ? 'CreditNoteLine' : 'InvoiceLine');
            $this->cbc($dom, $line, 'ID', (string) ($index + 1));
            $quantity = $this->cbc(
                $dom,
                $line,
                $isCreditNote ? 'CreditedQuantity' : 'InvoicedQuantity',
                $this->decimal(abs((float) $item['quantity']), 3)
            );
            $quantity->setAttribute('unitCode', 'C62');
            $this->amountNode($dom, $line, 'LineExtensionAmount', abs((int) $item['total_minor']), $currency);
            $product = $this->cac($dom, $line, 'Item');
            $this->cbc($dom, $product, 'Name', (string) $item['name']);
            if (trim((string) $item['sku']) !== '') {
                $sellerId = $this->cac($dom, $product, 'SellersItemIdentification');
                $this->cbc($dom, $sellerId, 'ID', (string) $item['sku']);
            }
            $classified = $this->cac($dom, $product, 'ClassifiedTaxCategory');
            $this->taxCategory($dom, $classified, $category, (float) $item['vat_rate'], $vatPayer);
            $price = $this->cac($dom, $line, 'Price');
            $this->amountNode($dom, $price, 'PriceAmount', abs((int) $item['unit_price_minor']), $currency);
        }

        return [
            'filename' => ($isCreditNote ? 'RO-eFactura-Storno-' : 'RO-eFactura-') . $invoice['number'] . '.xml',
            'xml' => $dom->saveXML(),
            // ANAF uses a different upload standard for UBL CreditNote documents.
            // Sending a CreditNote as UBL makes ANAF validate it against the
            // Invoice schema and reject the root element before business rules run.
            'standard' => $isCreditNote ? 'CN' : 'UBL',
            'validation_standard' => $isCreditNote ? 'FCN' : 'FACT1',
            'document_type' => $invoice['document_type'],
        ];
    }

    private function party(DOMDocument $dom, string $type, array $data, bool $seller, string $name, bool $vatPayer): DOMElement
    {
        $wrapper = $dom->createElementNS(self::CAC, 'cac:' . $type);
        $party = $this->cac($dom, $wrapper, 'Party');
        $address = (array) ($data['address'] ?? []);
        $postal = $this->cac($dom, $party, 'PostalAddress');
        $this->cbc($dom, $postal, 'StreetName', (string) ($address['line1'] ?? $address['address'] ?? ''));
        $countyCode = $this->countyCode((string) ($address['county'] ?? $address['city'] ?? ''));
        $this->cbc($dom, $postal, 'CityName', $this->cityName($address, $countyCode));
        if (!empty($address['postal_code'])) $this->cbc($dom, $postal, 'PostalZone', (string) $address['postal_code']);
        $this->cbc($dom, $postal, 'CountrySubentity', $countyCode);
        $country = $this->cac($dom, $postal, 'Country');
        $this->cbc($dom, $country, 'IdentificationCode', strtoupper((string) ($address['country_code'] ?? $address['country'] ?? 'RO')));
        if ($vatPayer) {
            $tax = $this->cac($dom, $party, 'PartyTaxScheme');
            $this->cbc($dom, $tax, 'CompanyID', $this->vatId((string) ($data['vat_code'] ?? $data['tax_id'] ?? '')));
            $scheme = $this->cac($dom, $tax, 'TaxScheme');
            $this->cbc($dom, $scheme, 'ID', 'VAT');
        }
        $legal = $this->cac($dom, $party, 'PartyLegalEntity');
        $this->cbc($dom, $legal, 'RegistrationName', $name);
        $taxId = preg_replace('/^RO/i', '', (string) ($data['tax_id'] ?? '')) ?: '';
        if (!$seller && $taxId === '') $taxId = '0000000000000';
        if ($taxId !== '') $this->cbc($dom, $legal, 'CompanyID', $taxId);
        if ($seller && !empty($data['registration_number'])) $this->cbc($dom, $legal, 'CompanyLegalForm', (string) $data['registration_number']);
        $email = trim((string) ($data['billing_email'] ?? $data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($email !== '' || $phone !== '') {
            $contact = $this->cac($dom, $party, 'Contact');
            if ($phone !== '') $this->cbc($dom, $contact, 'Telephone', $phone);
            if ($email !== '') $this->cbc($dom, $contact, 'ElectronicMail', $email);
        }
        return $wrapper;
    }

    private function taxCategory(DOMDocument $dom, DOMElement $node, string $id, float $rate, bool $vatPayer): void
    {
        $this->cbc($dom, $node, 'ID', $id);
        if ($vatPayer) $this->cbc($dom, $node, 'Percent', $this->decimal($rate, 2));
        if (!$vatPayer) $this->cbc($dom, $node, 'TaxExemptionReason', 'Operațiune efectuată de o persoană neînregistrată în scopuri de TVA.');
        $scheme = $this->cac($dom, $node, 'TaxScheme');
        $this->cbc($dom, $scheme, 'ID', 'VAT');
    }

    private function validateSeller(array $seller): void
    {
        $address = (array) ($seller['address'] ?? []);
        foreach (['legal_name', 'tax_id', 'registration_number'] as $field) {
            if (trim((string) ($seller[$field] ?? '')) === '') throw new RuntimeException('Datele firmei sunt incomplete: ' . $field);
        }
        if (trim((string) ($address['line1'] ?? '')) === '' || trim((string) ($address['city'] ?? '')) === '') {
            throw new RuntimeException('Adresa firmei este incompletă.');
        }
    }

    private function sellerName(array $data): string { return trim((string) ($data['legal_name'] ?? $data['company_name'] ?? $data['name'] ?? '')); }
    private function buyerName(array $data): string
    {
        $company = trim((string) ($data['company_name'] ?? ''));
        if ($company !== '') return $company;
        $person = trim((string) ($data['first_name'] ?? '') . ' ' . (string) ($data['last_name'] ?? ''));
        return $person !== '' ? $person : trim((string) ($data['name'] ?? $data['email'] ?? ''));
    }
    private function isVatPayer(array $data): bool
    {
        $vat = trim((string) ($data['vat_code'] ?? ''));
        $tax = trim((string) ($data['tax_id'] ?? ''));
        $status = mb_strtolower(trim((string) ($data['vat_status'] ?? '')));
        return $vat !== '' || str_starts_with(strtoupper($tax), 'RO') || in_array($status, ['platitor', 'plătitor', 'registered', 'active'], true);
    }
    private function vatId(string $id): string { $id = preg_replace('/\s+/', '', strtoupper($id)) ?: ''; return str_starts_with($id, 'RO') ? $id : 'RO' . $id; }
    private function cityName(array $address, string $countyCode): string
    {
        $city = trim((string) ($address['city'] ?? ''));
        if ($countyCode === 'RO-B') {
            $haystack = mb_strtolower($city . ' ' . (string) ($address['line1'] ?? '') . ' ' . (string) ($address['county'] ?? ''));
            if (preg_match('/sector\s*([1-6])/u', $haystack, $match)) return 'SECTOR' . $match[1];
        }
        return $city;
    }
    private function countyCode(string $county): string
    {
        $normalized = mb_strtolower(strtr(trim($county), ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t']));
        $map = ['alba'=>'RO-AB','arges'=>'RO-AG','arad'=>'RO-AR','bucuresti'=>'RO-B','bacau'=>'RO-BC','bihor'=>'RO-BH','bistrita-nasaud'=>'RO-BN','braila'=>'RO-BR','botosani'=>'RO-BT','brasov'=>'RO-BV','buzau'=>'RO-BZ','cluj'=>'RO-CJ','calarasi'=>'RO-CL','caras-severin'=>'RO-CS','constanta'=>'RO-CT','covasna'=>'RO-CV','dambovita'=>'RO-DB','dolj'=>'RO-DJ','gorj'=>'RO-GJ','galati'=>'RO-GL','giurgiu'=>'RO-GR','hunedoara'=>'RO-HD','harghita'=>'RO-HR','ilfov'=>'RO-IF','ialomita'=>'RO-IL','iasi'=>'RO-IS','mehedinti'=>'RO-MH','maramures'=>'RO-MM','mures'=>'RO-MS','neamt'=>'RO-NT','olt'=>'RO-OT','prahova'=>'RO-PH','sibiu'=>'RO-SB','salaj'=>'RO-SJ','satu mare'=>'RO-SM','suceava'=>'RO-SV','tulcea'=>'RO-TL','timis'=>'RO-TM','teleorman'=>'RO-TR','valcea'=>'RO-VL','vrancea'=>'RO-VN','vaslui'=>'RO-VS'];
        if (str_contains($normalized, 'sector') || str_contains($normalized, 'bucuresti')) return 'RO-B';
        return $map[$normalized] ?? 'RO-B';
    }
    private function cac(DOMDocument $dom, DOMElement $parent, string $name): DOMElement { $node = $dom->createElementNS(self::CAC, 'cac:' . $name); $parent->appendChild($node); return $node; }
    private function cbc(DOMDocument $dom, DOMElement $parent, string $name, string $value): DOMElement { $node = $dom->createElementNS(self::CBC, 'cbc:' . $name); $node->appendChild($dom->createTextNode($value)); $parent->appendChild($node); return $node; }
    private function amountNode(DOMDocument $dom, DOMElement $parent, string $name, int $minor, string $currency): DOMElement { $node = $this->cbc($dom, $parent, $name, $this->amount($minor)); $node->setAttribute('currencyID', $currency); return $node; }
    private function amount(int $minor): string { return number_format($minor / 100, 2, '.', ''); }
    private function decimal(float $value, int $precision): string { return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') ?: '0'; }
}
