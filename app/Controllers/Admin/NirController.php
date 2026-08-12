<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\AccountingAuditService;
use MaisonBebe\Services\BnrExchangeRateService;
use MaisonBebe\Services\NirPdfService;
use MaisonBebe\Services\NirService;
use MaisonBebe\Services\ProductMappingService;
use MaisonBebe\Services\XlsxService;
use RuntimeException;

final class NirController
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + [
            'adminUser' => Auth::user(),
            'notice' => Session::flash('admin_notice'),
            'error' => Session::flash('admin_error'),
        ], 'layouts/admin');
    }

    public function index(Request $request): string
    {
        $pdo = Database::connection();
        [$where, $params, $filters] = $this->filters($request);
        $sql = "SELECT n.*,si.invoice_series,si.invoice_number,si.invoice_date,si.total_without_vat,si.vat_total,si.grand_total,
                       si.supplier_name_snapshot,w.name warehouse_name,
                       CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) creator_name,
                       COUNT(DISTINCT nl.id) line_count,
                       SUM(CASE WHEN nl.difference_type<>'none' THEN 1 ELSE 0 END) difference_count,
                       SUM(CASE WHEN sil.line_type IN ('stockable','made_to_order','assembled_bundle') AND nl.product_variant_id IS NULL THEN 1 ELSE 0 END) unmapped_count
                FROM nir_documents n JOIN supplier_invoices si ON si.id=n.supplier_invoice_id
                JOIN accounting_warehouses w ON w.id=n.warehouse_id
                LEFT JOIN users u ON u.id=n.created_by LEFT JOIN nir_lines nl ON nl.nir_document_id=n.id
                LEFT JOIN supplier_invoice_lines sil ON sil.id=nl.supplier_invoice_line_id
                WHERE " . implode(' AND ', $where) . " GROUP BY n.id ORDER BY n.created_at DESC LIMIT 500";
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $items = $statement->fetchAll();
        $stats = [
            'drafts' => (int) $pdo->query("SELECT COUNT(*) FROM nir_documents WHERE status='Draft'")->fetchColumn(),
            'ready' => (int) $pdo->query("SELECT COUNT(*) FROM nir_documents WHERE status IN ('ReadyForConfirmation','InReception')")->fetchColumn(),
            'confirmed_month' => (int) $pdo->query("SELECT COUNT(*) FROM nir_documents WHERE status IN ('Confirmed','PartiallyReceived','Reversed') AND confirmed_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn(),
            'differences' => (int) $pdo->query("SELECT COUNT(DISTINCT n.id) FROM nir_documents n JOIN nir_lines l ON l.nir_document_id=n.id WHERE l.difference_type<>'none'")->fetchColumn(),
            'late' => (int) $pdo->query('SELECT COUNT(*) FROM nir_documents WHERE is_late_entered=1')->fetchColumn(),
            'unmapped' => (int) $pdo->query("SELECT COUNT(*) FROM nir_lines nl JOIN supplier_invoice_lines sil ON sil.id=nl.supplier_invoice_line_id JOIN nir_documents n ON n.id=nl.nir_document_id WHERE n.status IN ('Draft','RequiresProductMapping','InReception','ReadyForConfirmation') AND sil.line_type IN ('stockable','made_to_order','assembled_bundle') AND nl.product_variant_id IS NULL")->fetchColumn(),
        ];
        $suppliers = $pdo->query('SELECT id,legal_name,tax_id FROM accounting_suppliers WHERE is_active=1 ORDER BY legal_name')->fetchAll();
        $warehouses = $pdo->query('SELECT id,name FROM accounting_warehouses WHERE is_active=1 ORDER BY is_default DESC,name')->fetchAll();
        return $this->admin('admin/nir-index', compact('items', 'stats', 'filters', 'suppliers', 'warehouses'));
    }

    public function form(Request $request, ?string $id = null): string
    {
        $pdo = Database::connection();
        $document = null;
        $lines = [];
        if ($id !== null) {
            $document = (new NirService())->document((int) $id);
            if (in_array($document['status'], ['Confirmed', 'PartiallyReceived', 'Reversed'], true)) {
                Response::redirect('/admin/nir-uri/' . $id);
            }
            $lines = (new NirService())->lines((int) $id);
        }
        $suppliers = $pdo->query('SELECT * FROM accounting_suppliers WHERE is_active=1 ORDER BY legal_name')->fetchAll();
        $warehouses = $pdo->query('SELECT * FROM accounting_warehouses WHERE is_active=1 ORDER BY is_default DESC,name')->fetchAll();
        $products = (new ProductMappingService())->candidates();
        $importToken = trim((string) $request->input('import_token', ''));
        $importRows = $importToken !== '' ? (array) Session::get('nir_import_mapped_' . $importToken, []) : [];
        return $this->admin('admin/nir-form', compact('document', 'lines', 'suppliers', 'warehouses', 'products', 'importToken', 'importRows'));
    }

    public function exchangeRate(Request $request): never
    {
        try {
            Response::json((new BnrExchangeRateService())->latest((string)$request->input('currency','RON')));
        } catch (RuntimeException $exception) {
            Response::json(['message'=>$exception->getMessage()],503);
        }
    }

    public function create(Request $request): never
    {
        $input = $request->all();
        $nirId = (new NirService())->createDraft($input);
        $token = trim((string) ($input['import_token'] ?? ''));
        if ($token !== '') {
            Session::forget('nir_import_mapped_' . $token);
        }
        $this->storeSourceAttachments($nirId);
        Session::flash('admin_notice', 'Ciorna NIR a fost creată. Completează asocierea și recepția produselor.');
        Response::redirect('/admin/nir-uri/' . $nirId . '/edit');
    }

    public function update(Request $request, string $id): never
    {
        (new NirService())->updateDraft((int) $id, $request->all());
        $this->storeSourceAttachments((int) $id);
        Session::flash('admin_notice', 'Ciorna NIR a fost salvată. Nu a fost modificat niciun stoc.');
        Response::redirect('/admin/nir-uri/' . $id . '/edit');
    }

    public function show(Request $request, string $id): string
    {
        $service = new NirService();
        $document = $service->document((int) $id);
        $lines = $service->lines((int) $id);
        $movements = Database::connection()->prepare('SELECT * FROM accounting_stock_movements WHERE source_document_type=\'NIR\' AND source_document_id=? ORDER BY id');
        $movements->execute([(int) $id]);
        $artifacts=Database::connection()->prepare('SELECT * FROM nir_artifacts WHERE nir_document_id=? ORDER BY artifact_type,generated_at DESC');$artifacts->execute([(int)$id]);
        return $this->admin('admin/nir-show', ['document' => $document, 'lines' => $lines, 'movements' => $movements->fetchAll(),'artifacts'=>$artifacts->fetchAll()]);
    }

    public function confirm(Request $request, string $id): never
    {
        $result = (new NirService())->confirm((int) $id, $request->all());
        Session::flash('admin_notice', !empty($result['pdf_error'])
            ? 'NIR-ul a fost confirmat și Stocuri Conta a fost actualizat. PDF-ul poate fi regenerat din pagina documentului.'
            : 'NIR-ul a fost confirmat. A fost modificat exclusiv registrul Stocuri Conta.');
        Response::redirect('/admin/nir-uri/' . $id);
    }

    public function reverse(Request $request, string $id): never
    {
        $reversalId = (new NirService())->reverse(
            (int) $id,
            trim((string) $request->input('reversal_date', '')),
            trim((string) $request->input('reason', '')),
            $request->all()
        );
        Session::flash('admin_notice', 'Documentul de inversare a fost creat. Mișcările inițiale au rămas în registru.');
        Response::redirect('/admin/nir-uri/' . $reversalId);
    }

    public function delete(Request $request, string $id): never
    {
        (new NirService())->deleteDraft((int) $id);
        Session::flash('admin_notice', 'Ciorna NIR a fost ștearsă.');
        Response::redirect('/admin/nir-uri');
    }

    public function pdf(Request $request, string $id): never
    {
        $nirId = (int) $id;
        $relative = (new NirPdfService())->generate($nirId)['path'];
        $path = BASE_PATH . '/storage' . $relative;
        $document = (new NirService())->document($nirId);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . ($document['formatted_number'] ?: 'nir-' . $nirId) . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function xlsx(Request $request, string $id): never
    {
        $service = new NirService();
        $document = $service->document((int) $id);
        $rows = array_map(static fn(array $line): array => [
            $line['sku_snapshot'], $line['product_name_snapshot'], $line['variant_name_snapshot'], $line['unit_of_measure_snapshot'],
            $line['invoiced_quantity'], $line['received_quantity'], $line['accepted_quantity'], $line['damaged_quantity'],
            $line['difference_type'], $line['difference_reason'], $line['unit_purchase_price_without_vat'],
            $line['discount_value'], $line['allocated_acquisition_cost'], $line['vat_rate'], $line['value_without_vat'],
            $line['vat_value'], $line['total_with_vat'],
        ], $service->lines((int) $id));
        $isReversal=($document['document_kind']??'')==='reversal';
        $statusLabels=['Draft'=>'Ciornă','RequiresProductMapping'=>'Necesită asociere produse','InReception'=>'Recepție în lucru','ReadyForConfirmation'=>'Gata de confirmare','Confirmed'=>'Confirmat','PartiallyReceived'=>'Recepționat parțial','Reversed'=>'Inversat'];
        $metadata=[
            'Tip document' => $isReversal?'Inversare NIR':'Notă de recepție și constatare de diferențe',
            'Status' => $statusLabels[$document['status']]??$document['status'],
            'Document' => $document['formatted_number'] ?: '#' . $id,
            'Furnizor' => $document['supplier_name_snapshot'],
            'Factura furnizorului' => $document['invoice_series'] . ' ' . $document['invoice_number'],
            'Data recepției' => $document['receipt_date'],
            'Recepționat de' => trim((string)$document['creator_name'])?:'Sistem',
            'Confirmat de' => trim((string)$document['confirmer_name'])?:trim((string)$document['creator_name']),
            'Generat la' => date('d.m.Y H:i'),
        ];
        if($isReversal){$metadata['NIR inițial']=$document['original_formatted_number']?:'#'.(int)$document['original_nir_id'];$metadata['Motivul inversării']=$document['reversal_reason'];}
        elseif($document['status']==='Reversed'){$metadata['Document de inversare']=$document['reversal_formatted_number']?:'—';$metadata['Motivul inversării']=$document['reversal_reason'];}
        $binary = (new XlsxService())->export($isReversal?'Inversare NIR':'NIR', [
            'SKU','Produs','Variantă','UM','Cantitate facturată','Cantitate recepționată','Cantitate acceptată','Deteriorată',
            'Tip diferență','Motiv diferență','Preț unitar fără TVA','Discount','Cost repartizat','Cotă TVA','Valoare fără TVA','TVA','Total',
        ], $rows, $metadata);
        if(in_array($document['status'],['Confirmed','PartiallyReceived','Reversed'],true)){
            $pdo=Database::connection();$stored=$pdo->prepare("SELECT * FROM nir_artifacts WHERE nir_document_id=? AND artifact_type='xlsx' ORDER BY id DESC LIMIT 1");$stored->execute([(int)$id]);$artifact=$stored->fetch();
            $relative=$artifact?(string)$artifact['path']:'/nir/'.date('Y/m').'/nir-'.(int)$id.'.xlsx';$path=BASE_PATH.'/storage'.$relative;
            if(!is_dir(dirname($path))&&!mkdir(dirname($path),0750,true)&&!is_dir(dirname($path)))throw new HttpException(500,'Directorul exporturilor NIR nu poate fi creat.');
            if(file_put_contents($path,$binary,LOCK_EX)===false)throw new HttpException(500,'Exportul XLSX nu a putut fi arhivat.');$hash=hash('sha256',$binary);
            if($artifact){$pdo->prepare('UPDATE nir_artifacts SET sha256=?,size_bytes=?,document_version=document_version+1,generated_at=NOW(),generated_by=? WHERE id=?')->execute([$hash,strlen($binary),Auth::id(),$artifact['id']]);$artifactId=(int)$artifact['id'];}
            else{$pdo->prepare("INSERT INTO nir_artifacts (nir_document_id,artifact_type,path,mime_type,sha256,size_bytes,generated_by) VALUES (?,'xlsx',?,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',?,?,?)")->execute([(int)$id,$relative,$hash,strlen($binary),Auth::id()]);$artifactId=(int)$pdo->lastInsertId();}
            (new AccountingAuditService())->record('nir.xlsx.generated','nir_document',(int)$id,[],['artifact_id'=>$artifactId,'sha256'=>$hash]);
        }
        $this->xlsxResponse($binary, ($document['formatted_number'] ?: 'nir-' . $id) . '.xlsx');
    }

    public function attachment(Request $request,string $id,string $artifact):never
    {
        $statement=Database::connection()->prepare('SELECT * FROM nir_artifacts WHERE id=? AND nir_document_id=?');$statement->execute([(int)$artifact,(int)$id]);$item=$statement->fetch();
        if(!$item)throw new HttpException(404,'Atașamentul NIR nu a fost găsit.');
        $path=BASE_PATH.'/storage'.$item['path'];if(!is_file($path))throw new HttpException(404,'Fișierul atașat nu mai este disponibil pe disc.');
        header('Content-Type: '.$item['mime_type']);header('Content-Disposition: attachment; filename="'.basename((string)$item['path']).'"');header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');readfile($path);exit;
    }

    public function exportList(Request $request): never
    {
        [$where, $params, $filters] = $this->filters($request);
        $statement = Database::connection()->prepare(
            "SELECT n.formatted_number,n.receipt_date,si.supplier_name_snapshot,si.invoice_series,si.invoice_number,si.invoice_date,w.name warehouse_name,si.total_without_vat,si.vat_total,si.grand_total,n.status,n.is_late_entered,n.created_at
             FROM nir_documents n JOIN supplier_invoices si ON si.id=n.supplier_invoice_id JOIN accounting_warehouses w ON w.id=n.warehouse_id
             WHERE " . implode(' AND ', $where) . ' ORDER BY n.created_at DESC'
        );
        $statement->execute($params);
        $rows = array_map(static fn(array $row): array => array_values($row), $statement->fetchAll());
        $binary = (new XlsxService())->export('Lista NIR-uri', [
            'Număr NIR','Data recepției','Furnizor','Serie factură','Număr factură','Data facturii','Gestiune','Valoare fără TVA','TVA','Total','Status','Introdus ulterior','Creat la',
        ], $rows, ['Filtre' => json_encode($filters, JSON_UNESCAPED_UNICODE), 'Generat la' => date('d.m.Y H:i')]);
        $this->xlsxResponse($binary, 'lista-nir-uri-' . date('Y-m-d') . '.xlsx');
    }

    public function import(Request $request): string
    {
        return $this->admin('admin/nir-import', ['preview' => null, 'token' => null]);
    }

    public function importPreview(Request $request): string
    {
        $file = $_FILES['invoice_xlsx'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 12 * 1024 * 1024) {
            throw new HttpException(422, 'Selectează un fișier XLSX valid, de cel mult 12 MB.');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new HttpException(422, 'Importul acceptă numai fișiere XLSX.');
        }
        try {
            $preview = (new XlsxService())->import((string) $file['tmp_name']);
        } catch (RuntimeException $exception) {
            throw new HttpException(422, $exception->getMessage());
        }
        $token = bin2hex(random_bytes(16));
        Session::put('nir_import_preview_' . $token, $preview);
        return $this->admin('admin/nir-import', compact('preview', 'token'));
    }

    public function importMap(Request $request): never
    {
        $token = trim((string) $request->input('token', ''));
        $preview = (array) Session::get('nir_import_preview_' . $token, []);
        if (!$preview || !isset($preview['rows'])) {
            throw new HttpException(422, 'Previzualizarea importului a expirat. Încarcă fișierul din nou.');
        }
        $fields = ['name','supplier_code','ean','sku','unit','quantity','unit_price','discount','vat_rate','net','vat','total'];
        $columns = [];
        foreach ($fields as $field) $columns[$field] = (int) $request->input('map_' . $field, -1);
        if ($columns['name'] < 0 || $columns['quantity'] < 0 || $columns['unit_price'] < 0) {
            throw new HttpException(422, 'Mapează cel puțin denumirea, cantitatea și prețul unitar.');
        }
        $mapped = [];
        foreach ($preview['rows'] as $row) {
            $value = static fn(string $field): string => $columns[$field] >= 0 ? trim((string) ($row[$columns[$field]] ?? '')) : '';
            if ($value('name') === '' && $value('sku') === '' && $value('supplier_code') === '') continue;
            $mapped['line_name'][] = $value('name') ?: ($value('sku') ?: $value('supplier_code'));
            $mapped['line_supplier_code'][] = $value('supplier_code');
            $mapped['line_imported_ean'][] = $value('ean');
            $mapped['line_imported_sku'][] = $value('sku');
            $mapped['line_unit'][] = $value('unit') ?: 'buc';
            $mapped['line_invoiced_quantity'][] = $value('quantity');
            $mapped['line_received_quantity'][] = $value('quantity');
            $mapped['line_accepted_quantity'][] = $value('quantity');
            $mapped['line_damaged_quantity'][] = '0';
            $mapped['line_unit_price'][] = $value('unit_price');
            $mapped['line_discount'][] = $value('discount') ?: '0';
            $mapped['line_vat_rate'][] = $value('vat_rate') ?: '0';
            $mapped['line_value_without_vat'][] = $value('net');
            $mapped['line_vat_value'][] = $value('vat');
            $mapped['line_total'][] = $value('total');
            $mapped['line_allocated_cost'][] = '0';
            $mapped['line_type'][] = 'stockable';
            $mapped['line_variant_id'][] = '0';
            $mapped['line_difference_type'][] = 'none';
            $mapped['line_difference_reason'][] = '';
            $mapped['line_observations'][] = '';
            $mapped['line_ignore_reason'][] = '';
            $mapped['line_original_json'][] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (empty($mapped['line_name'])) {
            throw new HttpException(422, 'Nu există rânduri importabile după mapare.');
        }
        Session::put('nir_import_mapped_' . $token, $mapped);
        Session::forget('nir_import_preview_' . $token);
        Response::redirect('/admin/nir-uri/nou?import_token=' . rawurlencode($token));
    }

    private function filters(Request $request): array
    {
        $filters = [
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
            'supplier' => (int) $request->input('supplier', 0),
            'number' => trim((string) $request->input('number', '')),
            'invoice' => trim((string) $request->input('invoice', '')),
            'sku' => trim((string) $request->input('sku', '')),
            'product' => trim((string) $request->input('product', '')),
            'status' => trim((string) $request->input('status', '')),
            'warehouse' => (int) $request->input('warehouse', 0),
            'late' => trim((string) $request->input('late', '')),
            'differences' => trim((string) $request->input('differences', '')),
        ];
        $where = ['1=1']; $params = [];
        if ($filters['from'] !== '') { $where[] = 'n.receipt_date>=?'; $params[] = $filters['from']; }
        if ($filters['to'] !== '') { $where[] = 'n.receipt_date<=?'; $params[] = $filters['to']; }
        if ($filters['supplier']) { $where[] = 'n.supplier_id=?'; $params[] = $filters['supplier']; }
        if ($filters['number'] !== '') { $where[] = 'n.formatted_number LIKE ?'; $params[] = '%' . $filters['number'] . '%'; }
        if ($filters['invoice'] !== '') { $where[] = "CONCAT(si.invoice_series,' ',si.invoice_number) LIKE ?"; $params[] = '%' . $filters['invoice'] . '%'; }
        if ($filters['sku'] !== '') { $where[] = 'EXISTS(SELECT 1 FROM nir_lines fl WHERE fl.nir_document_id=n.id AND fl.sku_snapshot LIKE ?)'; $params[] = '%' . $filters['sku'] . '%'; }
        if ($filters['product'] !== '') { $where[] = 'EXISTS(SELECT 1 FROM nir_lines fl WHERE fl.nir_document_id=n.id AND fl.product_name_snapshot LIKE ?)'; $params[] = '%' . $filters['product'] . '%'; }
        if ($filters['status'] !== '') { $where[] = 'n.status=?'; $params[] = $filters['status']; }
        if ($filters['warehouse']) { $where[] = 'n.warehouse_id=?'; $params[] = $filters['warehouse']; }
        if ($filters['late'] === '1') $where[] = 'n.is_late_entered=1';
        if ($filters['late'] === '0') $where[] = 'n.is_late_entered=0';
        if ($filters['differences'] === '1') $where[] = "EXISTS(SELECT 1 FROM nir_lines dl WHERE dl.nir_document_id=n.id AND dl.difference_type<>'none')";
        return [$where, $params, $filters];
    }

    private function storeSourceAttachments(int $nirId): void
    {
        $allowed = [
            'source_pdf' => ['pdf', 'application/pdf', 'source_pdf'],
            'source_xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'source_xlsx'],
            'source_xml' => ['xml', 'application/xml', 'source_xml'],
            'delivery_note_attachment' => ['pdf', 'application/pdf', 'delivery_note'],
        ];
        $pdo = Database::connection();
        foreach ($allowed as $field => [$extension, $mime, $type]) {
            $file = $_FILES[$field] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (($file['error'] ?? null) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 12 * 1024 * 1024 || strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== $extension) {
                throw new HttpException(422, 'Un atașament nu este valid sau depășește 12 MB.');
            }
            $relative = '/nir/source/' . date('Y/m') . '/' . $nirId . '-' . $field . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
            $path = BASE_PATH . '/storage' . $relative;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true) && !is_dir(dirname($path))) throw new HttpException(500, 'Directorul atașamentelor nu poate fi creat.');
            if (!move_uploaded_file((string) $file['tmp_name'], $path)) throw new HttpException(500, 'Atașamentul nu a putut fi salvat.');
            $hash = hash_file('sha256', $path);
            $pdo->prepare('INSERT INTO nir_artifacts (nir_document_id,artifact_type,path,mime_type,sha256,size_bytes,generated_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$nirId, $type, $relative, $mime, $hash, filesize($path), Auth::id()]);
            (new AccountingAuditService())->record('nir.source_attachment.saved', 'nir_document', $nirId, [], ['type' => $type, 'sha256' => $hash]);
        }
    }

    private function xlsxResponse(string $binary, string $filename): never
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($binary));
        header('X-Content-Type-Options: nosniff');
        echo $binary;
        exit;
    }
}
