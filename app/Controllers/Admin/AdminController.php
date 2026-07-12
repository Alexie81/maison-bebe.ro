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

final class AdminController extends Controller
{
    private function admin(string $view,array $data=[]):string{return view($view,$data+['adminUser'=>Auth::user(),'notice'=>Session::flash('admin_notice'),'error'=>Session::flash('admin_error')],'layouts/admin');}
    public function dashboard(Request $request):string
    {
        $pdo=Database::connection();$kpis=['sales'=>(int)$pdo->query("SELECT COALESCE(SUM(grand_total_minor),0) FROM orders WHERE DATE(created_at)=CURDATE() AND order_status<>'cancelled'")->fetchColumn(),'orders'=>(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn(),'customers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),'low_stock'=>(int)$pdo->query('SELECT COUNT(*) FROM product_variants WHERE is_active=1 AND stock_qty<=low_stock_threshold')->fetchColumn()];
        $recent=$pdo->query('SELECT id,order_number,email,grand_total_minor,order_status,created_at FROM orders ORDER BY created_at DESC LIMIT 7')->fetchAll();$notifications=$pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 6')->fetchAll();$chart=$pdo->query("SELECT DATE(created_at) day,SUM(grand_total_minor) total FROM orders WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 11 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();
        return $this->admin('admin/dashboard',compact('kpis','recent','notifications','chart'));
    }
    public function orders(Request $request):string{$q=trim((string)$request->input('q',''));$status=trim((string)$request->input('status',''));$where=['1=1'];$params=[];if($q!==''){$where[]='(order_number LIKE ? OR email LIKE ? OR phone LIKE ?)';$like='%'.$q.'%';$params=[$like,$like,$like];}if($status!==''){$where[]='order_status=?';$params[]=$status;}$statement=Database::connection()->prepare('SELECT * FROM orders WHERE '.implode(' AND ',$where).' ORDER BY created_at DESC LIMIT 100');$statement->execute($params);return $this->admin('admin/orders',['orders'=>$statement->fetchAll(),'q'=>$q,'status'=>$status]);}
    public function order(Request $request,string $id):string{$pdo=Database::connection();$statement=$pdo->prepare('SELECT * FROM orders WHERE id=?');$statement->execute([(int)$id]);$order=$statement->fetch();if(!$order){throw new HttpException(404,'Comanda nu a fost gÄƒsitÄƒ.');}$items=$pdo->prepare('SELECT * FROM order_items WHERE order_id=?');$items->execute([$id]);$history=$pdo->prepare('SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC');$history->execute([$id]);$notes=$pdo->prepare('SELECT n.*,CONCAT(u.first_name,\' \',u.last_name) author FROM order_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.order_id=? ORDER BY n.created_at DESC');$notes->execute([$id]);$shipment=$pdo->prepare('SELECT * FROM shipments WHERE order_id=? ORDER BY id DESC LIMIT 1');$shipment->execute([$id]);return $this->admin('admin/order',['order'=>$order,'items'=>$items->fetchAll(),'history'=>$history->fetchAll(),'notes'=>$notes->fetchAll(),'shipment'=>$shipment->fetch()?:null]);}
    public function updateOrder(Request $request,string $id):never{$allowed=['new'=>['confirmed','cancelled'],'confirmed'=>['processing','cancelled'],'processing'=>['ready_for_shipping','cancelled'],'ready_for_shipping'=>['shipped','cancelled'],'shipped'=>['delivered','returned'],'delivered'=>['return_requested','partially_refunded','refunded'],'return_requested'=>['returned'],'returned'=>['refunded']];$pdo=Database::connection();$pdo->beginTransaction();$s=$pdo->prepare('SELECT order_status,email,order_number FROM orders WHERE id=? FOR UPDATE');$s->execute([$id]);$order=$s->fetch();$new=(string)$request->input('status','');if(!$order||!in_array($new,$allowed[$order['order_status']]??[],true)){$pdo->rollBack();throw new HttpException(422,'TranziÈ›ia de status nu este permisÄƒ.');}$pdo->prepare('UPDATE orders SET order_status=? WHERE id=?')->execute([$new,$id]);$label=ucfirst(str_replace('_',' ',$new));$message=trim((string)$request->input('public_message',''))?:'Comanda a trecut Ã®n etapa: '.$label.'.';$pdo->prepare("INSERT INTO order_status_history (order_id,old_status,new_status,public_label,public_message,is_public,source,changed_by_user_id) VALUES (?,?,?,?,?,1,'admin',?)")->execute([$id,$order['order_status'],$new,$label,$message,Auth::id()]);if($request->input('notify')){$pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at) VALUES ('order_status',?,?,?,'pending',NOW())")->execute([$order['email'],'Actualizare comandÄƒ '.$order['order_number'],json_encode(['status'=>$new,'message'=>$message],JSON_UNESCAPED_UNICODE)]);}$note=trim((string)$request->input('internal_note',''));if($note!==''){$pdo->prepare('INSERT INTO order_notes (order_id,user_id,note) VALUES (?,?,?)')->execute([$id,Auth::id(),$note]);}$pdo->commit();Response::redirect('/admin/comenzi/'.$id);}
    public function customers(Request $request):string{$customers=Database::connection()->query("SELECT u.id,u.email,u.first_name,u.last_name,u.status,u.created_at,COUNT(o.id) orders_count,COALESCE(SUM(o.grand_total_minor),0) total_spent FROM users u LEFT JOIN orders o ON o.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC LIMIT 100")->fetchAll();return $this->admin('admin/customers',compact('customers'));}
    public function customer(Request $request,string $id):string
    {
        $pdo=Database::connection();
        $statement=$pdo->prepare('SELECT id,email,first_name,last_name,phone,status,email_verified_at,last_login_at,created_at FROM users WHERE id=? AND deleted_at IS NULL');
        $statement->execute([(int)$id]);
        $customer=$statement->fetch();
        if(!$customer){throw new HttpException(404,'Clientul nu a fost gÄƒsit.');}
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
        $categories=$pdo->query('SELECT id,name FROM categories WHERE deleted_at IS NULL ORDER BY name')->fetchAll();$products=$pdo->query('SELECT id,name FROM products WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
        $couponProducts=[];foreach($pdo->query('SELECT coupon_id,product_id,mode FROM coupon_products')->fetchAll() as $row){$couponProducts[(int)$row['coupon_id']][]=(int)$row['product_id'];$couponModes[(int)$row['coupon_id']]=$row['mode'];}
        $couponCategories=[];foreach($pdo->query('SELECT coupon_id,category_id,mode FROM coupon_categories')->fetchAll() as $row){$couponCategories[(int)$row['coupon_id']][]=(int)$row['category_id'];$couponModes[(int)$row['coupon_id']]=$row['mode'];}
        $couponModes=$couponModes??[];return $this->admin('admin/coupons',compact('items','categories','products','couponProducts','couponCategories','couponModes'));
    }
    public function saveCoupon(Request $request): never
    {
        $code=mb_strtoupper(trim((string)$request->input('code','')));$type=(string)$request->input('discount_type','percent');$value=(float)$request->input('discount_value',0);$active=$request->input('is_active')?1:0;
        if($code===''||!in_array($type,['percent','fixed'],true)||$value<=0){throw new HttpException(422,'Datele cuponului nu sunt valide.');}
        $storedValue=$type==='fixed'?(int)round($value*100):(int)round($value);$minimum=(int)round(((float)$request->input('minimum_order',0))*100);$maximum=trim((string)$request->input('maximum_discount',''))!==''?(int)round(((float)$request->input('maximum_discount'))*100):null;
        $maxUses=max(0,(int)$request->input('max_uses',0))?:null;$maxPerUser=max(0,(int)$request->input('max_uses_per_user',0))?:null;$starts=trim((string)$request->input('starts_at',''))?:null;$ends=trim((string)$request->input('ends_at',''))?:null;
        if($starts&&$ends&&strtotime($ends)<=strtotime($starts)){throw new HttpException(422,'Data expirării trebuie să fie după data începerii.');}
        $mode=in_array($request->input('eligibility_mode'),['include','exclude'],true)?(string)$request->input('eligibility_mode'):'include';$productIds=array_values(array_unique(array_filter(array_map('intval',(array)$request->input('product_ids',[])))));$categoryIds=array_values(array_unique(array_filter(array_map('intval',(array)$request->input('category_ids',[])))));
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$couponId=(int)$request->input('coupon_id',0);if($couponId>0){$pdo->prepare('UPDATE coupons SET code=?,discount_type=?,discount_value=?,minimum_order_minor=?,maximum_discount_minor=?,max_uses=?,max_uses_per_user=?,starts_at=?,ends_at=?,is_active=? WHERE id=?')->execute([$code,$type,$storedValue,$minimum,$maximum,$maxUses,$maxPerUser,$starts,$ends,$active,$couponId]);}else{$pdo->prepare('INSERT INTO coupons (code,discount_type,discount_value,minimum_order_minor,maximum_discount_minor,max_uses,max_uses_per_user,starts_at,ends_at,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$code,$type,$storedValue,$minimum,$maximum,$maxUses,$maxPerUser,$starts,$ends,$active]);$couponId=(int)$pdo->lastInsertId();}$pdo->prepare('DELETE FROM coupon_products WHERE coupon_id=?')->execute([$couponId]);$pdo->prepare('DELETE FROM coupon_categories WHERE coupon_id=?')->execute([$couponId]);$pi=$pdo->prepare('INSERT INTO coupon_products (coupon_id,product_id,mode) VALUES (?,?,?)');foreach($productIds as $productId){$pi->execute([$couponId,$productId,$mode]);}$ci=$pdo->prepare('INSERT INTO coupon_categories (coupon_id,category_id,mode) VALUES (?,?,?)');foreach($categoryIds as $categoryId){$ci->execute([$couponId,$categoryId,$mode]);}$pdo->commit();}catch(\Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
        Response::redirect('/admin/cupoane');
    }
    public function cms(Request $request):string{$sections=Database::connection()->query('SELECT * FROM homepage_sections ORDER BY sort_order')->fetchAll();$pages=Database::connection()->query('SELECT * FROM pages ORDER BY title')->fetchAll();return $this->admin('admin/cms',compact('sections','pages'));}
    public function saveCms(Request $request):never{$key=(string)$request->input('section_key','');$title=trim((string)$request->input('title',''));$active=$request->input('is_active')?1:0;Database::connection()->prepare('UPDATE homepage_sections SET title=?,is_active=?,updated_by=? WHERE section_key=?')->execute([$title,$active,Auth::id(),$key]);Response::redirect('/admin/cms');}
    public function shipments(Request $request):string{$items=Database::connection()->query('SELECT s.*,o.order_number,sp.name provider_name FROM shipments s JOIN orders o ON o.id=s.order_id LEFT JOIN shipping_providers sp ON sp.id=s.provider_id ORDER BY s.updated_at DESC')->fetchAll();return $this->admin('admin/shipments',compact('items'));}
}

