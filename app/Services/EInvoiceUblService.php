<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use DOMDocument;
use MaisonBebe\Core\Database;
use RuntimeException;

final class EInvoiceUblService
{
    public function generate(int $invoiceId): array
    {
        $pdo=Database::connection();
        $q=$pdo->prepare('SELECT * FROM invoices WHERE id=? AND status=\'issued\' LIMIT 1');$q->execute([$invoiceId]);$invoice=$q->fetch();
        if(!$invoice) throw new RuntimeException('Factura emisă nu a fost găsită.');
        $q=$pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order');$q->execute([$invoiceId]);$items=$q->fetchAll();
        $issuer=json_decode((string)$invoice['issuer_snapshot_json'],true)?:[];
        $customer=json_decode((string)$invoice['customer_snapshot_json'],true)?:[];
        $this->requireIssuer($issuer);
        $dom=new DOMDocument('1.0','UTF-8');$dom->formatOutput=true;
        $root=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2','Invoice');$dom->appendChild($root);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns:cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns:cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $add=function(string $name,string $value)use($dom,$root){$root->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:'.$name,$value));};
        $add('CustomizationID','urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1');
        $add('ID',(string)$invoice['number']);$add('IssueDate',(string)$invoice['issue_date']);$add('DueDate',(string)$invoice['due_date']);$add('InvoiceTypeCode','380');$add('DocumentCurrencyCode',(string)$invoice['currency']);
        $root->appendChild($this->party($dom,'AccountingSupplierParty',$issuer,true));
        $root->appendChild($this->party($dom,'AccountingCustomerParty',$customer,false));
        $tax=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:TaxTotal');$taxAmount=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:TaxAmount',$this->amount((int)$invoice['vat_minor']));$taxAmount->setAttribute('currencyID',(string)$invoice['currency']);$tax->appendChild($taxAmount);$root->appendChild($tax);
        $total=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:LegalMonetaryTotal');
        foreach(['LineExtensionAmount'=>(int)$invoice['subtotal_minor'],'TaxExclusiveAmount'=>(int)$invoice['grand_total_minor']-(int)$invoice['vat_minor'],'TaxInclusiveAmount'=>(int)$invoice['grand_total_minor'],'PayableAmount'=>(int)$invoice['grand_total_minor']] as $name=>$minor){$el=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:'.$name,$this->amount($minor));$el->setAttribute('currencyID',(string)$invoice['currency']);$total->appendChild($el);}$root->appendChild($total);
        foreach($items as $index=>$item){$line=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:InvoiceLine');$id=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:ID',(string)($index+1));$line->appendChild($id);$qty=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:InvoicedQuantity',(string)$item['quantity']);$qty->setAttribute('unitCode','C62');$line->appendChild($qty);$ext=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:LineExtensionAmount',$this->amount((int)$item['total_minor']));$ext->setAttribute('currencyID',(string)$invoice['currency']);$line->appendChild($ext);$product=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:Item');$product->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:Name',(string)$item['name']));$line->appendChild($product);$price=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:Price');$pa=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:PriceAmount',$this->amount((int)$item['unit_price_minor']));$pa->setAttribute('currencyID',(string)$invoice['currency']);$price->appendChild($pa);$line->appendChild($price);$root->appendChild($line);}
        return ['filename'=>'RO-eFactura-'.$invoice['number'].'.xml','xml'=>$dom->saveXML()];
    }

    private function party(DOMDocument $dom,string $type,array $data,bool $supplier): \DOMElement
    {
        $wrapper=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:'.$type);$party=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:Party');$wrapper->appendChild($party);
        $name=(string)($data['legal_name']??trim(($data['first_name']??'').' '.($data['last_name']??''))?:($data['name']??'Client'));
        $pn=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:PartyName');$pn->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:Name',$name));$party->appendChild($pn);
        $address=(array)($data['address']??[]);$pa=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:PostalAddress');$pa->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:StreetName',(string)($address['line1']??$address['address']??'')));$pa->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:CityName',(string)($address['city']??'')));$country=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:Country');$country->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:IdentificationCode',(string)($address['country']??'RO')));$pa->appendChild($country);$party->appendChild($pa);
        if(!empty($data['tax_id'])){$tax=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:PartyTaxScheme');$tax->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:CompanyID',(string)$data['tax_id']));$scheme=$dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2','cac:TaxScheme');$scheme->appendChild($dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2','cbc:ID','VAT'));$tax->appendChild($scheme);$party->appendChild($tax);}
        return $wrapper;
    }
    private function requireIssuer(array $issuer):void
    {
        $address=(array)($issuer['address']??[]);
        foreach(['legal_name','tax_id','registration_number'] as $field) if(trim((string)($issuer[$field]??''))==='') throw new RuntimeException('Datele firmei sunt incomplete: '.$field.'.');
        if(trim((string)($address['line1']??''))===''||trim((string)($address['city']??''))==='') throw new RuntimeException('Adresa firmei este incompletă.');
    }
    private function amount(int $minor):string{return number_format($minor/100,2,'.','');}
}