<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\OrderExportService;

final class AdminController extends Controller
{
    private function admin(string $view,array $data=[]):string{return view($view,$data+['adminUser'=>Auth::user(),'notice'=>Session::flash('admin_notice'),'error'=>Session::flash('admin_error')],'layouts/admin');}
    public function dashboard(Request $request):string
    {
        $pdo = Database::connection();
        $period = strtolower(trim((string) $request->input('period', '7d')));
        $allowedPeriods = ['7d','week','1m','3m','6m','1y','all','custom'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = '7d';
        }

        $today = new \DateTimeImmutable('today');
        $end = $today;
        $start = match ($period) {
            'week' => $today->modify('monday this week'),
            '1m' => $today->modify('-1 month')->modify('+1 day'),
            '3m' => $today->modify('-3 months')->modify('+1 day'),
            '6m' => $today->modify('-6 months')->modify('+1 day'),
            '1y' => $today->modify('-1 year')->modify('+1 day'),
            default => $today->modify('-6 days'),
        };

        if ($period === 'all') {
            $firstOrder = (string) ($pdo->query('SELECT MIN(DATE(created_at)) FROM orders')->fetchColumn() ?: '');
            $start = $firstOrder !== '' ? new \DateTimeImmutable($firstOrder) : $today->modify('-6 days');
        } elseif ($period === 'custom') {
            $from = trim((string) $request->input('from', ''));
            $to = trim((string) $request->input('to', ''));
            $fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
            $toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
            if ($fromDate && $toDate) {
                $start = $fromDate;
                $end = $toDate > $today ? $today : $toDate;
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                if ($start > $today) {
                    $start = $today;
                }
                if ($end > $today) {
                    $end = $today;
                }
            } else {
                $period = '7d';
                $start = $today->modify('-6 days');
            }
        }

        $startSql = $start->format('Y-m-d');
        $endSql = $end->format('Y-m-d');
        $rangeCondition = 'created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';

        $salesStatement = $pdo->prepare("SELECT COALESCE(SUM(grand_total_minor),0) FROM orders WHERE {$rangeCondition} AND order_status<>'cancelled'");
        $salesStatement->execute([$startSql,$endSql]);
        $ordersStatement = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE {$rangeCondition}");
        $ordersStatement->execute([$startSql,$endSql]);
        $customersStatement = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$rangeCondition}");
        $customersStatement->execute([$startSql,$endSql]);

        $kpis = [
            'sales' => (int) $salesStatement->fetchColumn(),
            'orders' => (int) $ordersStatement->fetchColumn(),
            'customers' => (int) $customersStatement->fetchColumn(),
            'low_stock' => (int) $pdo->query('SELECT COUNT(*) FROM product_variants WHERE is_active=1 AND stock_qty>0 AND stock_qty<=low_stock_threshold')->fetchColumn(),
        ];
        $recent = $pdo->query('SELECT id,order_number,email,grand_total_minor,order_status,created_at FROM orders ORDER BY created_at DESC LIMIT 7')->fetchAll();
        $notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 6')->fetchAll();

        $days = max(1, (int) $start->diff($end)->days + 1);
        if ($days <= 93) {
            $chartSql = "SELECT DATE(created_at) bucket,SUM(grand_total_minor) total FROM orders WHERE {$rangeCondition} AND order_status<>'cancelled' GROUP BY DATE(created_at) ORDER BY bucket";
            $chartMode = 'day';
        } elseif ($days <= 550) {
            $chartSql = "SELECT DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) bucket,SUM(grand_total_minor) total FROM orders WHERE {$rangeCondition} AND order_status<>'cancelled' GROUP BY bucket ORDER BY bucket";
            $chartMode = 'week';
        } else {
            $chartSql = "SELECT DATE_FORMAT(created_at,'%Y-%m-01') bucket,SUM(grand_total_minor) total FROM orders WHERE {$rangeCondition} AND order_status<>'cancelled' GROUP BY bucket ORDER BY bucket";
            $chartMode = 'month';
        }
        $chartStatement = $pdo->prepare($chartSql);
        $chartStatement->execute([$startSql,$endSql]);
        $chart = $chartStatement->fetchAll();
        foreach ($chart as &$row) {
            $date = new \DateTimeImmutable((string) $row['bucket']);
            $row['label'] = match ($chartMode) {
                'week' => 'Săpt. '.$date->format('d.m'),
                'month' => $date->format('m.Y'),
                default => $date->format('d.m'),
            };
        }
        unset($row);

        $periodLabels = ['7d'=>'7 zile','week'=>'Săptămâna curentă','1m'=>'O lună','3m'=>'3 luni','6m'=>'6 luni','1y'=>'Un an','all'=>'Tot timpul','custom'=>'Perioadă personalizată'];
        $rangeLabel = $periodLabels[$period].' · '.$start->format('d.m.Y').' – '.$end->format('d.m.Y');
        $setup=[
            ['title'=>'Datele firmei','text'=>'Denumire, CUI, adresă, bancă și serie de facturare.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM company_profiles c WHERE c.is_active=1 AND c.legal_name<>'' AND c.tax_id<>'' AND JSON_UNQUOTE(JSON_EXTRACT(c.address_json,'$.line1'))<>'' AND EXISTS(SELECT 1 FROM company_bank_accounts b WHERE b.company_profile_id=c.id))")->fetchColumn(),'url'=>'/admin/facturare/firma','action'=>'Verifică datele'],
            ['title'=>'Emailurile magazinului','text'=>'Mesajele pentru comenzi, facturi și recuperarea parolei.','done'=>(bool)$pdo->query("SELECT COUNT(DISTINCT purpose)>=3 FROM email_senders WHERE is_active=1 AND purpose IN ('orders','invoices','recovery')")->fetchColumn(),'url'=>'/admin/setari/email','action'=>'Configurează email'],
            ['title'=>'Metode de plată','text'=>'Activează plata ramburs și/sau plata online de test.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM payment_providers WHERE is_enabled=1)")->fetchColumn(),'url'=>'/admin/setari/plati','action'=>'Configurează plata'],
            ['title'=>'Livrarea','text'=>'Alege curierul, serviciile și pragul de livrare gratuită.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM shipping_providers WHERE is_enabled=1)")->fetchColumn(),'url'=>'/admin/setari/livrare','action'=>'Configurează livrarea'],
            ['title'=>'Produsele','text'=>'Adaugă fotografii, preț, stoc și variante pentru cel puțin un produs.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM products p JOIN product_variants v ON v.product_id=p.id WHERE p.status='active' AND p.deleted_at IS NULL AND v.is_active=1)")->fetchColumn(),'url'=>'/admin/produse','action'=>'Vezi produsele'],
            ['title'=>'Categorii și colecții','text'=>'Organizează produsele ca oamenii să le găsească ușor.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM categories WHERE is_active=1 AND deleted_at IS NULL)")->fetchColumn(),'url'=>'/admin/categorii','action'=>'Organizează catalogul'],
            ['title'=>'Facturarea','text'=>'Alege șablonul implicit și verifică seria de facturi.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM invoice_templates WHERE is_default=1 AND is_active=1) AND EXISTS(SELECT 1 FROM invoice_series WHERE is_active=1)")->fetchColumn(),'url'=>'/admin/facturare/sabloane','action'=>'Alege șablonul'],
            ['title'=>'Comandă completă de test','text'=>'Plasează o comandă, verifică emailul, plata, factura și statusurile.','done'=>(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM orders)")->fetchColumn(),'url'=>'/','action'=>'Deschide magazinul'],
        ];
        return $this->admin('admin/dashboard',compact('kpis','recent','notifications','chart','setup','period','periodLabels','rangeLabel','startSql','endSql'));
    }
    public function orders(Request $request): string
    {
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(order_number LIKE ? OR email LIKE ? OR phone LIKE ? OR first_name LIKE ? OR last_name LIKE ?)'; $like = '%'.$q.'%'; $params = [$like,$like,$like,$like,$like]; }
        if ($status !== '') { $where[] = 'order_status=?'; $params[] = $status; }
        $statement = Database::connection()->prepare('SELECT * FROM orders WHERE '.implode(' AND ',$where).' ORDER BY created_at DESC LIMIT 500');
        $statement->execute($params);
        $orders = $statement->fetchAll();
        $export = strtolower(trim((string) $request->input('export', '')));
        if (in_array($export, ['csv','pdf'], true)) {
            $service = new OrderExportService();
            $body = $export === 'pdf' ? $service->pdf($orders) : $service->csv($orders);
            $date = date('Y-m-d');
            header('Content-Type: '.($export === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8'));
            header('Content-Disposition: attachment; filename="comenzi-maison-bebe-'.$date.'.'.$export.'"');
            header('Content-Length: '.strlen($body));
            header('X-Content-Type-Options: nosniff');
            echo $body;
            exit;
        }
        return $this->admin('admin/orders', compact('orders','q','status'));
    }
    public function order(Request $request,string $id):string{$pdo=Database::connection();$statement=$pdo->prepare('SELECT * FROM orders WHERE id=?');$statement->execute([(int)$id]);$order=$statement->fetch();if(!$order){throw new HttpException(404,'Comanda nu a fost găsită.');}$items=$pdo->prepare('SELECT * FROM order_items WHERE order_id=?');$items->execute([$id]);$history=$pdo->prepare('SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC');$history->execute([$id]);$notes=$pdo->prepare('SELECT n.*,CONCAT(u.first_name,\' \',u.last_name) author FROM order_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.order_id=? ORDER BY n.created_at DESC');$notes->execute([$id]);$shipment=$pdo->prepare('SELECT * FROM shipments WHERE order_id=? ORDER BY id DESC LIMIT 1');$shipment->execute([$id]);$invoiceStatement=$pdo->prepare("SELECT i.id,i.number,i.status,i.document_hash,(SELECT q.status FROM email_queue q WHERE q.correlation_id=CONCAT('invoice:',i.id) OR q.correlation_id LIKE CONCAT('invoice:',i.id,':%') ORDER BY q.id DESC LIMIT 1) email_status,(SELECT q.sent_at FROM email_queue q WHERE q.correlation_id=CONCAT('invoice:',i.id) OR q.correlation_id LIKE CONCAT('invoice:',i.id,':%') ORDER BY q.id DESC LIMIT 1) email_sent_at FROM invoices i WHERE i.order_id=? AND i.document_type='invoice' ORDER BY i.id DESC LIMIT 1");$invoiceStatement->execute([(int)$id]);return $this->admin('admin/order',['order'=>$order,'items'=>$items->fetchAll(),'history'=>$history->fetchAll(),'notes'=>$notes->fetchAll(),'shipment'=>$shipment->fetch()?:null,'invoiceState'=>$invoiceStatement->fetch()?:null]);}
    public function updateOrder(Request $request,string $id): never
    {
        $allowed=['new'=>['confirmed','cancelled'],'confirmed'=>['processing','cancelled'],'processing'=>['ready_for_shipping','cancelled'],'ready_for_shipping'=>['shipped','cancelled'],'shipped'=>['delivered','returned'],'delivered'=>['return_requested','partially_refunded','refunded'],'return_requested'=>['returned'],'returned'=>['refunded']];
        $labels=['confirmed'=>'Confirmată','processing'=>'În pregătire','ready_for_shipping'=>'Pregătită pentru curier','shipped'=>'Expediată','delivered'=>'Livrată','cancelled'=>'Anulată','return_requested'=>'Retur solicitat','returned'=>'Returnată','partially_refunded'=>'Rambursată parțial','refunded'=>'Rambursată'];
        $messages=['confirmed'=>'Comanda a fost confirmată și intră în pregătire.','processing'=>'Pregătim cu grijă produsele din comandă.','ready_for_shipping'=>'Comanda este ambalată și pregătită pentru curier.','shipped'=>'Comanda a plecat către tine.','delivered'=>'Comanda a fost livrată. Îți mulțumim!','cancelled'=>'Comanda a fost anulată.','return_requested'=>'Solicitarea de retur a fost înregistrată.','returned'=>'Produsele returnate au ajuns la noi.','partially_refunded'=>'O parte din valoarea comenzii a fost rambursată.','refunded'=>'Valoarea comenzii a fost rambursată.'];
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $statement=$pdo->prepare('SELECT order_status,email,order_number,public_token FROM orders WHERE id=? FOR UPDATE');$statement->execute([(int)$id]);$order=$statement->fetch();$new=(string)$request->input('status','');
            if(!$order||!in_array($new,$allowed[$order['order_status']]??[],true)) throw new HttpException(422,'Tranziția de status nu este permisă din etapa curentă.');
            $label=$labels[$new]??ucfirst(str_replace('_',' ',$new));$message=trim((string)$request->input('public_message',''))?:($messages[$new]??'Comanda a trecut în etapa: '.$label.'.');
            $pdo->prepare('UPDATE orders SET order_status=?,updated_at=NOW() WHERE id=?')->execute([$new,(int)$id]);
            $pdo->prepare("INSERT INTO order_status_history (order_id,old_status,new_status,public_label,public_message,is_public,source,changed_by_user_id) VALUES (?,?,?,?,?,1,'admin',?)")->execute([(int)$id,$order['order_status'],$new,$label,$message,Auth::id()]);
            if($request->input('notify')) $pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at) VALUES ('order_status',?,?,?,'pending',NOW())")->execute([$order['email'],'Actualizare comandă '.$order['order_number'],json_encode(['status'=>$new,'status_label'=>$label,'message'=>$message,'order_number'=>$order['order_number'],'tracking_url'=>absolute_url('/urmarire-comanda?token='.rawurlencode((string)$order['public_token']))],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $note=trim((string)$request->input('internal_note',''));if($note!=='') $pdo->prepare('INSERT INTO order_notes (order_id,user_id,note) VALUES (?,?,?)')->execute([(int)$id,Auth::id(),$note]);
            $pdo->commit();Session::flash('admin_notice','Statusul comenzii a fost actualizat la „'.$label.'”.');Response::redirect('/admin/comenzi/'.$id);
        }catch(\Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }    public function customers(Request $request):string{$customers=Database::connection()->query("SELECT u.id,u.email,u.first_name,u.last_name,u.status,u.created_at,COUNT(o.id) orders_count,COALESCE(SUM(o.grand_total_minor),0) total_spent FROM users u LEFT JOIN orders o ON o.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC LIMIT 100")->fetchAll();return $this->admin('admin/customers',compact('customers'));}
    public function customer(Request $request,string $id):string
    {
        $pdo=Database::connection();
        $statement=$pdo->prepare('SELECT id,email,first_name,last_name,phone,status,email_verified_at,last_login_at,created_at FROM users WHERE id=? AND deleted_at IS NULL');
        $statement->execute([(int)$id]);
        $customer=$statement->fetch();
        if(!$customer){throw new HttpException(404,'Clientul nu a fost găsit.');}
        $ordersStatement=$pdo->prepare("SELECT o.*,GROUP_CONCAT(CONCAT(oi.name_snapshot,' × ',oi.quantity) ORDER BY oi.id SEPARATOR '||') purchased_products FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.user_id=? OR (o.user_id IS NULL AND o.email=?) GROUP BY o.id ORDER BY o.created_at DESC");
        $ordersStatement->execute([(int)$id,$customer['email']]);
        $orders=$ordersStatement->fetchAll();
        $totalSpent=array_sum(array_map(static fn(array $order):int=>(int)$order['grand_total_minor'],$orders));
        return $this->admin('admin/customer',compact('customer','orders','totalSpent'));
    }
    public function notifications(Request $request):string{$items=Database::connection()->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100')->fetchAll();return $this->admin('admin/notifications',compact('items'));}
    public function unread(Request $request):never{$items=Database::connection()->query('SELECT id,type,title,body,url,created_at FROM notifications WHERE read_at IS NULL ORDER BY id DESC LIMIT 20')->fetchAll();Response::json(['items'=>$items,'count'=>count($items)]);}
    public function markRead(Request $request,string $id):never{Database::connection()->prepare('UPDATE notifications SET read_at=NOW() WHERE id=?')->execute([(int)$id]);Response::json(['ok'=>true]);}
    public function markAllRead(Request $request):never{Database::connection()->exec('UPDATE notifications SET read_at=NOW() WHERE read_at IS NULL');Response::json(['ok'=>true]);}
    public function coupons(Request $request): string
    {
        $pdo=Database::connection();$items=$pdo->query('SELECT c.*,(SELECT COUNT(*) FROM coupon_usages cu WHERE cu.coupon_id=c.id) used_total FROM coupons c ORDER BY c.created_at DESC')->fetchAll();
        $categories=$pdo->query('SELECT id,name FROM categories WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
        $collections=$pdo->query('SELECT id,name FROM collections WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
        $products=$pdo->query("SELECT p.id,p.name,
            (SELECT GROUP_CONCAT(pc.category_id ORDER BY pc.category_id) FROM product_categories pc WHERE pc.product_id=p.id) category_ids,
            (SELECT GROUP_CONCAT(cp.collection_id ORDER BY cp.collection_id) FROM collection_products cp WHERE cp.product_id=p.id) collection_ids,
            COALESCE((SELECT ma.path FROM product_images pi JOIN media_assets ma ON ma.id=pi.media_id WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.sort_order,pi.id LIMIT 1),'/assets/images/packaging-reference.png') image_path
            FROM products p WHERE p.deleted_at IS NULL ORDER BY p.name")->fetchAll();
        $couponProducts=[];foreach($pdo->query('SELECT coupon_id,product_id,mode FROM coupon_products')->fetchAll() as $row){$couponProducts[(int)$row['coupon_id']][]=(int)$row['product_id'];$couponModes[(int)$row['coupon_id']]=$row['mode'];}
        $couponCategories=[];foreach($pdo->query('SELECT coupon_id,category_id,mode FROM coupon_categories')->fetchAll() as $row){$couponCategories[(int)$row['coupon_id']][]=(int)$row['category_id'];$couponModes[(int)$row['coupon_id']]=$row['mode'];}
        $couponCollections=[];foreach($pdo->query('SELECT coupon_id,collection_id,mode FROM coupon_collections')->fetchAll() as $row){$couponCollections[(int)$row['coupon_id']][]=(int)$row['collection_id'];$couponModes[(int)$row['coupon_id']]=$row['mode'];}
        $couponModes=$couponModes??[];return $this->admin('admin/coupons',compact('items','categories','collections','products','couponProducts','couponCategories','couponCollections','couponModes'));
    }
    public function saveCoupon(Request $request): never
    {
        $code=mb_strtoupper(trim((string)$request->input('code','')));$type=(string)$request->input('discount_type','percent');$value=(float)$request->input('discount_value',0);$active=$request->input('is_active')?1:0;
        if($code===''||!in_array($type,['percent','fixed'],true)||$value<=0){throw new HttpException(422,'Datele cuponului nu sunt valide.');}
        $storedValue=$type==='fixed'?(int)round($value*100):(int)round($value);$minimum=(int)round(((float)$request->input('minimum_order',0))*100);$maximum=trim((string)$request->input('maximum_discount',''))!==''?(int)round(((float)$request->input('maximum_discount'))*100):null;
        $maxUses=max(0,(int)$request->input('max_uses',0))?:null;$maxPerUser=max(0,(int)$request->input('max_uses_per_user',0))?:null;$starts=trim((string)$request->input('starts_at',''))?:null;$ends=trim((string)$request->input('ends_at',''))?:null;
        if($starts&&$ends&&strtotime($ends)<=strtotime($starts)){throw new HttpException(422,'Data expirării trebuie să fie după data începerii.');}
        $mode=in_array($request->input('eligibility_mode'),['include','exclude'],true)?(string)$request->input('eligibility_mode'):'include';$productIds=array_values(array_unique(array_filter(array_map('intval',(array)$request->input('product_ids',[])))));$categoryIds=array_values(array_unique(array_filter(array_map('intval',(array)$request->input('category_ids',[])))));$collectionIds=array_values(array_unique(array_filter(array_map('intval',(array)$request->input('collection_ids',[])))));
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$couponId=(int)$request->input('coupon_id',0);if($couponId>0){$pdo->prepare('UPDATE coupons SET code=?,discount_type=?,discount_value=?,minimum_order_minor=?,maximum_discount_minor=?,max_uses=?,max_uses_per_user=?,starts_at=?,ends_at=?,is_active=? WHERE id=?')->execute([$code,$type,$storedValue,$minimum,$maximum,$maxUses,$maxPerUser,$starts,$ends,$active,$couponId]);}else{$pdo->prepare('INSERT INTO coupons (code,discount_type,discount_value,minimum_order_minor,maximum_discount_minor,max_uses,max_uses_per_user,starts_at,ends_at,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$code,$type,$storedValue,$minimum,$maximum,$maxUses,$maxPerUser,$starts,$ends,$active]);$couponId=(int)$pdo->lastInsertId();}$pdo->prepare('DELETE FROM coupon_products WHERE coupon_id=?')->execute([$couponId]);$pdo->prepare('DELETE FROM coupon_categories WHERE coupon_id=?')->execute([$couponId]);$pdo->prepare('DELETE FROM coupon_collections WHERE coupon_id=?')->execute([$couponId]);$pi=$pdo->prepare('INSERT INTO coupon_products (coupon_id,product_id,mode) VALUES (?,?,?)');foreach($productIds as $productId){$pi->execute([$couponId,$productId,$mode]);}$ci=$pdo->prepare('INSERT INTO coupon_categories (coupon_id,category_id,mode) VALUES (?,?,?)');foreach($categoryIds as $categoryId){$ci->execute([$couponId,$categoryId,$mode]);}$coi=$pdo->prepare('INSERT INTO coupon_collections (coupon_id,collection_id,mode) VALUES (?,?,?)');foreach($collectionIds as $collectionId){$coi->execute([$couponId,$collectionId,$mode]);}$pdo->commit();}catch(\Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
        Response::redirect('/admin/cupoane');
    }
    public function cms(Request $request):string{$sections=Database::connection()->query('SELECT * FROM homepage_sections ORDER BY sort_order')->fetchAll();$pages=Database::connection()->query('SELECT * FROM pages ORDER BY title')->fetchAll();return $this->admin('admin/cms',compact('sections','pages'));}
    public function saveCms(Request $request):never{$key=(string)$request->input('section_key','');$title=trim((string)$request->input('title',''));$active=$request->input('is_active')?1:0;Database::connection()->prepare('UPDATE homepage_sections SET title=?,is_active=?,updated_by=? WHERE section_key=?')->execute([$title,$active,Auth::id(),$key]);Response::redirect('/admin/cms');}
    public function shipments(Request $request):string{$items=Database::connection()->query('SELECT s.*,o.order_number,sp.name provider_name FROM shipments s JOIN orders o ON o.id=s.order_id LEFT JOIN shipping_providers sp ON sp.id=s.provider_id ORDER BY s.updated_at DESC')->fetchAll();return $this->admin('admin/shipments',compact('items'));}
}
