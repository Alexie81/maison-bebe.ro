<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\AccountingPeriodService;
use MaisonBebe\Services\AccountingSettingsService;
use MaisonBebe\Services\AccountingStockProjectionService;
use MaisonBebe\Services\AccountingStockPeriodExportService;
use MaisonBebe\Services\AccountingStockReportService;
use MaisonBebe\Services\XlsxService;

final class AccountingStockController
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + [
            'adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice'), 'error' => Session::flash('admin_error'),
        ], 'layouts/admin');
    }

    public function index(Request $request): string
    {
        $pdo = Database::connection();
        [$where, $params, $filters] = $this->filters($request);
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [25, 50, 100], true)) $perPage = 25;
        $page = max(1, (int) $request->input('page', 1));
        $periodActive = $filters['from'] !== '' || $filters['to'] !== '';
        if ($periodActive) {
            $this->assertPeriod($filters['from'], $filters['to']);
        }
        $quantityExpression = $this->scopeBalanceExpression('current_quantity');
        $valueExpression = $this->scopeBalanceExpression('current_accounting_value');
        $minimumExpression = $this->scopeBalanceExpression('minimum_historical_quantity', 'MIN');
        $currentNegativeExpression = $this->scopeBalanceExpression('has_current_negative_balance', 'MAX');
        $historicalNegativeExpression = $this->scopeBalanceExpression('has_historical_negative_balance', 'MAX');
        $lastMovementExpression = $this->scopeBalanceExpression('last_movement_date', 'MAX', 'NULL');
        $countSql = "SELECT COUNT(*) FROM (
                SELECT v.id,w.id warehouse_id
                FROM product_variants v JOIN products p ON p.id=v.product_id AND (p.deleted_at IS NULL OR p.is_gift_box=1)
                CROSS JOIN accounting_warehouses w
                LEFT JOIN accounting_stock_balances b ON b.product_variant_id=v.id AND b.warehouse_id=w.id
                WHERE w.is_active=1 AND " . implode(' AND ', $where) . " GROUP BY v.id,w.id
            ) accounting_scopes";
        $countStatement = $pdo->prepare($countSql);
        $countStatement->execute($params);
        $resultCount = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($resultCount / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT v.id variant_id,v.track_accounting_stock raw_tracking_mode,CASE WHEN v.track_accounting_stock=1 THEN v.sku ELSE p.sku END sku,
                       CASE WHEN v.track_accounting_stock=1 THEN v.track_inventory ELSE EXISTS(SELECT 1 FROM product_variants ati WHERE ati.product_id=p.id AND ati.track_inventory=1) END track_inventory,
                       1 track_accounting_stock,v.is_active variant_active,
                       p.id product_id,p.name product_name,p.status product_status,p.is_gift_box,
                       COALESCE((SELECT ma.path FROM product_images pi JOIN media_assets ma ON ma.id=pi.media_id WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.sort_order,pi.id LIMIT 1),'/assets/images/packaging-reference.png') image_path,
                       CASE WHEN v.track_accounting_stock=1 THEN COALESCE(GROUP_CONCAT(DISTINCT ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') ELSE 'Produs (fără variante)' END variant_name,
                       COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),'Fără categorie') category_name,
                       w.id warehouse_id,w.name warehouse_name,
                       {$quantityExpression} current_quantity,{$valueExpression} current_accounting_value,
                       CASE WHEN {$quantityExpression}<>0 THEN {$valueExpression}/{$quantityExpression} ELSE COALESCE(b.calculated_unit_cost,0) END calculated_unit_cost,
                       {$minimumExpression} minimum_historical_quantity,
                       {$currentNegativeExpression} has_current_negative_balance,
                       {$historicalNegativeExpression} has_historical_negative_balance,{$lastMovementExpression} last_movement_date,
                       CASE WHEN v.track_accounting_stock=1 THEN COALESCE((SELECT SUM(sm.quantity_in) FROM accounting_stock_movements sm WHERE sm.product_variant_id=v.id AND sm.warehouse_id=w.id),0) ELSE COALESCE((SELECT SUM(sm.quantity_in) FROM accounting_stock_movements sm WHERE sm.product_id=p.id AND sm.warehouse_id=w.id),0) END total_in,
                       CASE WHEN v.track_accounting_stock=1 THEN COALESCE((SELECT SUM(sm.quantity_out) FROM accounting_stock_movements sm WHERE sm.product_variant_id=v.id AND sm.warehouse_id=w.id),0) ELSE COALESCE((SELECT SUM(sm.quantity_out) FROM accounting_stock_movements sm WHERE sm.product_id=p.id AND sm.warehouse_id=w.id),0) END total_out
                FROM product_variants v JOIN products p ON p.id=v.product_id AND (p.deleted_at IS NULL OR p.is_gift_box=1)
                CROSS JOIN accounting_warehouses w
                LEFT JOIN accounting_stock_balances b ON b.product_variant_id=v.id AND b.warehouse_id=w.id
                LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
                LEFT JOIN product_options po ON po.id=ov.option_id LEFT JOIN product_categories pc ON pc.product_id=p.id
                LEFT JOIN categories c ON c.id=pc.category_id
                WHERE w.is_active=1 AND " . implode(' AND ', $where) . " GROUP BY v.id,w.id ORDER BY has_current_negative_balance DESC,p.name,variant_name LIMIT {$perPage} OFFSET {$offset}";
        $statement = $pdo->prepare($sql); $statement->execute($params); $items = $statement->fetchAll();
        if ($periodActive) {
            foreach ($items as &$item) {
                $item += $this->periodTotals($pdo, $item, $filters['from'], $filters['to']);
            }
            unset($item);
        }

        $scopeCondition = (new \MaisonBebe\Services\AccountingStockScopeService())->listingCondition('sv');
        $searchQuantity = $this->scopeBalanceExpressionForAliases('current_quantity', 'sv', 'sp', 'sw', 'sb');
        $searchProducts = $pdo->query(
            "SELECT sv.id variant_id,CASE WHEN sv.track_accounting_stock=1 THEN sv.sku ELSE sp.sku END sku,
                    sp.id product_id,sp.name product_name,sp.is_gift_box,sw.id warehouse_id,
                    CASE WHEN sv.track_accounting_stock=1 THEN COALESCE(GROUP_CONCAT(DISTINCT sov.value ORDER BY spo.sort_order SEPARATOR ' / '),'Standard') ELSE 'Produs (fără variante)' END variant_name,
                    COALESCE((SELECT ma.path FROM product_images spi JOIN media_assets ma ON ma.id=spi.media_id WHERE spi.product_id=sp.id ORDER BY spi.is_primary DESC,spi.sort_order,spi.id LIMIT 1),'/assets/images/packaging-reference.png') image_path,
                    {$searchQuantity} search_quantity
             FROM product_variants sv JOIN products sp ON sp.id=sv.product_id AND (sp.deleted_at IS NULL OR sp.is_gift_box=1)
             CROSS JOIN accounting_warehouses sw
             LEFT JOIN accounting_stock_balances sb ON sb.product_variant_id=sv.id AND sb.warehouse_id=sw.id
             LEFT JOIN variant_option_values svov ON svov.variant_id=sv.id
             LEFT JOIN product_option_values sov ON sov.id=svov.option_value_id
             LEFT JOIN product_options spo ON spo.id=sov.option_id
             WHERE sw.is_active=1 AND {$scopeCondition}
             GROUP BY sv.id,sw.id ORDER BY sp.name,variant_name LIMIT 1000"
        )->fetchAll();
        $stats = [
            'value' => (string) $pdo->query('SELECT COALESCE(SUM(current_accounting_value),0) FROM accounting_stock_balances')->fetchColumn(),
            'positive' => (int) $pdo->query('SELECT COUNT(*) FROM accounting_stock_balances WHERE current_quantity>0')->fetchColumn(),
            'zero' => (int) $pdo->query('SELECT COUNT(*) FROM product_variants v WHERE v.track_accounting_stock=1 AND NOT EXISTS(SELECT 1 FROM accounting_stock_balances b WHERE b.product_variant_id=v.id AND b.current_quantity<>0)')->fetchColumn(),
            'negative' => (int) $pdo->query('SELECT COUNT(*) FROM accounting_stock_balances WHERE current_quantity<0')->fetchColumn(),
            'uncovered' => (int) $pdo->query("SELECT COUNT(*) FROM accounting_stock_movements m JOIN accounting_stock_balances b ON b.product_variant_id=m.product_variant_id AND b.warehouse_id=m.warehouse_id JOIN accounting_stock_valuations v ON v.movement_id=m.id AND v.valuation_run_id=b.projection_version WHERE m.movement_type='SALES_INVOICE_OUT' AND v.balance_quantity_after<0")->fetchColumn(),
            'late' => (int) $pdo->query('SELECT COUNT(*) FROM nir_documents WHERE is_late_entered=1 AND status IN (\'Confirmed\',\'PartiallyReceived\',\'Reversed\')')->fetchColumn(),
            'month_movements' => (int) $pdo->query("SELECT COUNT(*) FROM accounting_stock_movements WHERE effective_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn(),
            'last_nir' => (string) ($pdo->query("SELECT formatted_number FROM nir_documents WHERE status IN ('Confirmed','PartiallyReceived','Reversed') ORDER BY confirmed_at DESC LIMIT 1")->fetchColumn() ?: '—'),
        ];
        $uncovered = $this->uncovered();
        $warehouses = $pdo->query('SELECT * FROM accounting_warehouses WHERE is_active=1 ORDER BY is_default DESC,name')->fetchAll();
        $categories = $pdo->query('SELECT id,name FROM categories WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
        $settings = (new AccountingSettingsService())->get();
        $periods = $pdo->query('SELECT p.*,CONCAT(COALESCE(u.first_name,\'\'),\' \',COALESCE(u.last_name,\'\')) locked_by_name FROM accounting_periods p LEFT JOIN users u ON u.id=p.locked_by ORDER BY p.start_date DESC')->fetchAll();
        return $this->admin('admin/accounting-stock-index', compact('items','stats','uncovered','filters','warehouses','categories','settings','periods','searchProducts','resultCount','page','perPage','totalPages','periodActive'));
    }

    public function card(Request $request, string $variant): string
    {
        $pdo = Database::connection(); $variantId=(int)$variant;
        $warehouseId=(int)$request->input('warehouse', $pdo->query('SELECT id FROM accounting_warehouses WHERE is_default=1 ORDER BY id LIMIT 1')->fetchColumn());
        $asOf=trim((string)$request->input('as_of',date('Y-m-d')));
        $header=$pdo->prepare("SELECT v.id variant_id,v.sku,v.track_inventory,v.track_accounting_stock,v.is_active,p.id product_id,p.name product_name,p.status product_status,p.is_gift_box,
                              COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
                              COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),'Fără categorie') category_name,w.id warehouse_id,w.name warehouse_name,
                              COALESCE(b.current_quantity,0) current_quantity,COALESCE(b.current_accounting_value,0) current_accounting_value,COALESCE(b.calculated_unit_cost,0) calculated_unit_cost,
                              COALESCE(b.minimum_historical_quantity,0) minimum_historical_quantity,b.last_movement_date,b.projection_version
                       FROM product_variants v JOIN products p ON p.id=v.product_id CROSS JOIN accounting_warehouses w
                       LEFT JOIN accounting_stock_balances b ON b.product_variant_id=v.id AND b.warehouse_id=w.id
                       LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id
                       LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id
                       WHERE v.id=? AND w.id=? GROUP BY v.id,w.id");
        $header->execute([$variantId,$warehouseId]); $item=$header->fetch();
        if(!$item) throw new HttpException(404,'Fișa de stoc nu a fost găsită.');
        $movements=$pdo->prepare("SELECT m.*,v.calculated_unit_cost,v.calculated_movement_value,v.balance_quantity_after,v.balance_value_after,
                                  CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) creator_name
                           FROM accounting_stock_movements m LEFT JOIN accounting_stock_valuations v ON v.movement_id=m.id AND v.valuation_run_id=?
                           LEFT JOIN users u ON u.id=m.created_by WHERE m.product_variant_id=? AND m.warehouse_id=?
                           ORDER BY m.effective_date,m.effective_time,m.posted_at,m.id");
        $movements->execute([(int)$item['projection_version'],$variantId,$warehouseId]);
        $asOfBalance=(new AccountingStockProjectionService())->asOf($variantId,$warehouseId,$asOf);
        $warehouses=$pdo->query('SELECT * FROM accounting_warehouses WHERE is_active=1 ORDER BY is_default DESC,name')->fetchAll();
        return $this->admin('admin/accounting-stock-card',['item'=>$item,'movements'=>$movements->fetchAll(),'asOf'=>$asOf,'asOfBalance'=>$asOfBalance,'warehouses'=>$warehouses]);
    }

    public function cardXlsx(Request $request,string $variant):never
    {
        $warehouseId=(int)$request->input('warehouse',Database::connection()->query('SELECT id FROM accounting_warehouses WHERE is_default=1 ORDER BY id LIMIT 1')->fetchColumn());
        [$item,$movements]=(new AccountingStockReportService())->data((int)$variant,$warehouseId);
        $rows=array_map(static fn(array $m):array=>[$m['effective_date'],$m['posted_at'],$m['movement_type'],$m['source_document_number'],$m['counterparty_snapshot'],$m['quantity_in'],$m['quantity_out'],$m['calculated_unit_cost'],$m['calculated_movement_value'],$m['balance_quantity_after'],$m['balance_value_after']],$movements);
        $binary=(new XlsxService())->export('Fisa stoc',['Data efectivă','Postat la','Tip','Document','Partener','Intrare','Ieșire','Cost unitar','Valoare mișcare','Sold cantitativ','Sold valoric'],$rows,['SKU'=>$item['sku'],'Produs'=>$item['product_name'].' / '.$item['variant_name'],'Gestiune'=>$item['warehouse_name'],'Generat la'=>date('d.m.Y H:i')]);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="fisa-stoc-'.preg_replace('/[^A-Za-z0-9_-]+/','-',$item['sku']).'.xlsx"');header('Content-Length: '.strlen($binary));header('X-Content-Type-Options: nosniff');echo $binary;exit;
    }

    public function cardPdf(Request $request,string $variant):never
    {
        $warehouseId=(int)$request->input('warehouse',Database::connection()->query('SELECT id FROM accounting_warehouses WHERE is_default=1 ORDER BY id LIMIT 1')->fetchColumn());
        $pdf=(new AccountingStockReportService())->pdf((int)$variant,$warehouseId);
        header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="fisa-stoc-'.(int)$variant.'.pdf"');header('Content-Length: '.strlen($pdf));header('X-Content-Type-Options: nosniff');echo $pdf;exit;
    }

    public function saveSettings(Request $request): never
    {
        (new AccountingSettingsService())->save($request->all());
        Session::flash('admin_notice','Setările Stocuri Conta au fost salvate.');
        Response::redirect('/admin/stocuri-conta');
    }

    public function savePeriod(Request $request): never
    {
        (new AccountingPeriodService())->save(
            trim((string)$request->input('start_date','')),trim((string)$request->input('end_date','')),
            (bool)$request->input('is_locked'),trim((string)$request->input('reason',''))
        );
        Session::flash('admin_notice','Perioada contabilă a fost actualizată și auditată.');
        Response::redirect('/admin/stocuri-conta#perioade');
    }

    public function export(Request $request): never
    {
        $type=trim((string)$request->input('type','current'));
        $from=trim((string)$request->input('from',''));
        $to=trim((string)$request->input('to',''));
        if($from!==''||$to!==''){
            $export=(new AccountingStockPeriodExportService())->generate($from,$to);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$export['filename'].'"');header('Content-Length: '.strlen($export['binary']));header('X-Content-Type-Options: nosniff');echo $export['binary'];exit;
        }
        if($type==='uncovered'){
            $rows=array_map(static fn(array $r):array=>[$r['effective_date'],$r['source_document_number'],$r['sku_snapshot'],$r['product_name_snapshot'],$r['quantity_out'],$r['balance_quantity_after'],$r['uncovered_quantity'],$r['status_label']],$this->uncovered());
            $headers=['Data ieșirii','Factură','SKU','Produs','Cantitate ieșită','Sold după','Cantitate neacoperită','Status'];
            $name='iesiri-neacoperite';
        }else{
            [$where,$params,$filters]=$this->filters($request);$pdo=Database::connection();
            $statement=$pdo->prepare("SELECT v.sku,p.name,IF(p.is_gift_box=1,'Cutie cadou','Produs') article_type,COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,w.name warehouse_name,IF(v.track_inventory=0,'Nelimitată','Conform magazinului') online_availability,COALESCE(b.current_quantity,0),COALESCE(b.calculated_unit_cost,0),COALESCE(b.current_accounting_value,0),COALESCE(b.minimum_historical_quantity,0),b.last_movement_date FROM product_variants v JOIN products p ON p.id=v.product_id CROSS JOIN accounting_warehouses w LEFT JOIN accounting_stock_balances b ON b.product_variant_id=v.id AND b.warehouse_id=w.id LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id WHERE w.is_active=1 AND ".implode(' AND ',$where).' GROUP BY v.id,w.id ORDER BY p.name');
            $statement->execute($params);$rows=array_map(static fn(array $r):array=>array_values($r),$statement->fetchAll());
            $headers=['SKU','Produs','Tip articol','Variantă','Gestiune','Disponibilitate online','Stoc Conta','Cost unitar','Valoare contabilă','Sold minim istoric','Ultima mișcare'];$name='stocuri-conta';
        }
        $binary=(new XlsxService())->export('Stocuri Conta',$headers,$rows,['Tip export'=>$type,'Generat la'=>date('d.m.Y H:i')]);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$name.'-'.date('Y-m-d').'.xlsx"');header('Content-Length: '.strlen($binary));header('X-Content-Type-Options: nosniff');echo $binary;exit;
    }

    private function uncovered(): array
    {
        return Database::connection()->query("SELECT m.*,v.balance_quantity_after,
                   LEAST(m.quantity_out,ABS(LEAST(v.balance_quantity_after,0))) uncovered_quantity,
                   CASE WHEN b.has_current_negative_balance=1 THEN 'Neacoperit' WHEN b.has_historical_negative_balance=1 THEN 'Sold negativ istoric' ELSE 'Acoperit prin document introdus ulterior' END status_label
            FROM accounting_stock_movements m JOIN accounting_stock_balances b ON b.product_variant_id=m.product_variant_id AND b.warehouse_id=m.warehouse_id
            JOIN accounting_stock_valuations v ON v.movement_id=m.id AND v.valuation_run_id=b.projection_version
            WHERE m.movement_type='SALES_INVOICE_OUT' AND v.balance_quantity_after<0 ORDER BY m.effective_date DESC,m.id DESC LIMIT 500")->fetchAll();
    }

    private function filters(Request $request): array
    {
        $filters=['q'=>trim((string)$request->input('q','')),'type'=>trim((string)$request->input('type','')),'category'=>(int)$request->input('category',0),'warehouse'=>(int)$request->input('warehouse',0),'balance'=>trim((string)$request->input('balance','')),'unlimited'=>trim((string)$request->input('unlimited','')),'status'=>trim((string)$request->input('status','')),'from'=>trim((string)$request->input('from','')),'to'=>trim((string)$request->input('to',''))];
        $where=[(new \MaisonBebe\Services\AccountingStockScopeService())->listingCondition('v')];$params=[];
        if($filters['q']!==''){
            $needle='%'.$filters['q'].'%';
            $where[]='(v.sku LIKE ? OR p.sku LIKE ? OR p.name LIKE ? OR EXISTS('
                . 'SELECT 1 FROM accounting_stock_movements sm '
                . 'WHERE sm.warehouse_id=w.id AND ((v.track_accounting_stock=1 AND sm.product_variant_id=v.id) '
                . 'OR (v.track_accounting_stock=0 AND sm.product_id=p.id)) '
                . 'AND (sm.source_document_number LIKE ? OR sm.counterparty_snapshot LIKE ? '
                . 'OR sm.product_name_snapshot LIKE ? OR sm.sku_snapshot LIKE ?)))';
            array_push($params,$needle,$needle,$needle,$needle,$needle,$needle,$needle);
        }
        if($filters['type']==='gift_box')$where[]='p.is_gift_box=1';
        if($filters['type']==='product')$where[]='p.is_gift_box=0';
        if($filters['category']){$where[]='EXISTS(SELECT 1 FROM product_categories fpc WHERE fpc.product_id=p.id AND fpc.category_id=?)';$params[]=$filters['category'];}
        if($filters['warehouse']){$where[]='w.id=?';$params[]=$filters['warehouse'];}
        $scopeQuantity=$this->scopeBalanceExpression('current_quantity');
        if($filters['balance']==='positive')$where[]=$scopeQuantity.'>0';
        if($filters['balance']==='zero')$where[]=$scopeQuantity.'=0';
        if($filters['balance']==='negative')$where[]=$scopeQuantity.'<0';
        if($filters['unlimited']==='1')$where[]='NOT EXISTS(SELECT 1 FROM product_variants ati_filter WHERE ati_filter.product_id=p.id AND ati_filter.track_inventory=1)';
        if($filters['status']==='active')$where[]="p.status='active' AND (v.track_accounting_stock=0 OR v.is_active=1)";
        if($filters['status']==='inactive')$where[]="(p.status<>'active' OR v.is_active=0)";
        return [$where,$params,$filters];
    }

    private function scopeBalanceExpression(string $column, string $aggregate = 'SUM', string $fallback = '0'): string
    {
        $allowed = ['current_quantity','current_accounting_value','minimum_historical_quantity','has_current_negative_balance','has_historical_negative_balance','last_movement_date'];
        if (!in_array($column, $allowed, true) || !in_array($aggregate, ['SUM','MIN','MAX'], true)) {
            throw new \LogicException('Expresie contabilă invalidă.');
        }
        return "CASE WHEN v.track_accounting_stock=1 THEN COALESCE(b.{$column},{$fallback}) ELSE COALESCE((SELECT {$aggregate}(scope_b.{$column}) FROM accounting_stock_balances scope_b JOIN product_variants scope_v ON scope_v.id=scope_b.product_variant_id WHERE scope_v.product_id=p.id AND scope_b.warehouse_id=w.id),{$fallback}) END";
    }

    private function scopeBalanceExpressionForAliases(string $column, string $variant, string $product, string $warehouse, string $balance): string
    {
        return "CASE WHEN {$variant}.track_accounting_stock=1 THEN COALESCE({$balance}.{$column},0) ELSE COALESCE((SELECT SUM(scope_b.{$column}) FROM accounting_stock_balances scope_b JOIN product_variants scope_v ON scope_v.id=scope_b.product_variant_id WHERE scope_v.product_id={$product}.id AND scope_b.warehouse_id={$warehouse}.id),0) END";
    }

    private function assertPeriod(string $from, string $to): void
    {
        $start=\DateTimeImmutable::createFromFormat('!Y-m-d',$from);$end=\DateTimeImmutable::createFromFormat('!Y-m-d',$to);
        if(!$start||$start->format('Y-m-d')!==$from||!$end||$end->format('Y-m-d')!==$to||$start>$end)throw new HttpException(422,'Selectează o perioadă validă pentru Stocuri Conta.');
        if($end>new \DateTimeImmutable('today')||(int)$start->diff($end)->format('%a')>366)throw new HttpException(422,'Perioada poate avea cel mult 366 de zile și nu poate include zile viitoare.');
    }

    private function periodTotals(\PDO $pdo,array $item,string $from,string $to):array
    {
        $productScope=(int)$item['raw_tracking_mode']!==1;
        $field=$productScope?'product_id':'product_variant_id';
        $statement=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN effective_date<? THEN quantity_in-quantity_out ELSE 0 END),0) period_opening,COALESCE(SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN quantity_in ELSE 0 END),0) period_in,COALESCE(SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN quantity_out ELSE 0 END),0) period_out,COALESCE(SUM(CASE WHEN effective_date<=? THEN quantity_in-quantity_out ELSE 0 END),0) period_closing FROM accounting_stock_movements WHERE {$field}=? AND warehouse_id=?");
        $statement->execute([$from,$from,$to,$from,$to,$to,$productScope?$item['product_id']:$item['variant_id'],$item['warehouse_id']]);
        return $statement->fetch()?:['period_opening'=>0,'period_in'=>0,'period_out'=>0,'period_closing'=>0];
    }
}
