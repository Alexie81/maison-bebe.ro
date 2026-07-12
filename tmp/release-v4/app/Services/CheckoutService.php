<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class CheckoutService
{
    public function __construct(private readonly CartService $cart = new CartService(), private readonly NotificationService $notifications = new NotificationService()) {}

    public function create(array $payload): array
    {
        $required = ['email','phone','first_name','last_name','address','city','county','postal_code','customer_type','payment_method','shipping_method','idempotency_key'];
        foreach ($required as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') { throw new HttpException(422, 'Completează toate câmpurile obligatorii.'); }
        }
        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) { throw new HttpException(422, 'Adresa de email nu este validă.'); }
        if (!in_array($payload['customer_type'], ['individual','company'], true)) { throw new HttpException(422, 'Tip client invalid.'); }
        if ($payload['customer_type'] === 'company' && (empty($payload['company_name']) || empty($payload['tax_id']))) { throw new HttpException(422, 'Denumirea companiei și CUI/CIF sunt obligatorii pentru persoana juridică.'); }
        if (empty($payload['terms'])) { throw new HttpException(422, 'Acceptarea termenilor este obligatorie.'); }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $payload['idempotency_key'])) { throw new HttpException(422, 'Cheia de siguranță a checkout-ului este invalidă.'); }

        $existing = Database::connection()->prepare('SELECT id,order_number,public_token FROM orders WHERE idempotency_key=?');
        $existing->execute([$payload['idempotency_key']]);
        if ($order = $existing->fetch()) { return $order; }

        $cart = $this->cart->current();
        return Database::transaction(function (PDO $pdo) use ($payload, $cart): array {
            $itemsStatement = $pdo->prepare("SELECT ci.id cart_item_id,ci.quantity,ci.customization_json,v.id variant_id,v.product_id,v.sku,v.price_minor,v.stock_qty,p.name,p.status,GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / ') options_label FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id JOIN products p ON p.id=v.product_id LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id WHERE ci.cart_id=? GROUP BY ci.id FOR UPDATE");
            $itemsStatement->execute([$cart['id']]);
            $items = $itemsStatement->fetchAll();
            if (!$items) { throw new HttpException(422, 'Coșul este gol.'); }
            $subtotal = 0;
            foreach ($items as $item) {
                if ($item['status'] !== 'active' || (int) $item['stock_qty'] < (int) $item['quantity']) { throw new HttpException(422, 'Un produs nu mai are stoc suficient.'); }
                $subtotal += (int) $item['price_minor'] * (int) $item['quantity'];
            }
            $discount = 0; $couponId = null;
            if ($cart['coupon_code']) {
                $couponStmt = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) FOR UPDATE");
                $couponStmt->execute([$cart['coupon_code']]); $coupon = $couponStmt->fetch();
                if ($coupon && $subtotal >= (int) $coupon['minimum_order_minor']) {
                    $discount = $coupon['discount_type'] === 'percent' ? (int) round($subtotal * ((int) $coupon['discount_value'] / 100)) : (int) $coupon['discount_value'];
                    $discount = min($discount, $subtotal); $couponId = $coupon['id'];
                }
            }
            $shipping = $subtotal - $discount >= (int) env('FREE_SHIPPING_THRESHOLD', 50000) ? 0 : 2500;
            $grandTotal = $subtotal - $discount + $shipping;
            $provider = $pdo->prepare('SELECT code FROM payment_providers WHERE code=? AND is_enabled=1 LIMIT 1');
            $provider->execute([$payload['payment_method']]);
            if (!$provider->fetchColumn()) { throw new HttpException(422, 'Metoda de plată nu este disponibilă.'); }

            $orderNumber = 'MB' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $publicToken = bin2hex(random_bytes(32));
            $customerSnapshot = ['type'=>$payload['customer_type'],'first_name'=>$payload['first_name'],'last_name'=>$payload['last_name'],'email'=>$payload['email'],'phone'=>$payload['phone'],'company_name'=>$payload['company_name'] ?? null,'tax_id'=>$payload['tax_id'] ?? null,'registration_number'=>$payload['registration_number'] ?? null];
            $insert = $pdo->prepare("INSERT INTO orders (order_number,public_token,idempotency_key,user_id,email,phone,customer_type,customer_snapshot_json,subtotal_minor,discount_total_minor,shipping_total_minor,tax_total_minor,grand_total_minor,order_status,payment_status,fulfillment_status,payment_method,shipping_method,coupon_code,gift_message) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'new','unpaid','unfulfilled',?,?,?,?)");
            $insert->execute([$orderNumber,$publicToken,$payload['idempotency_key'],Auth::id(),mb_strtolower($payload['email']),$payload['phone'],$payload['customer_type'],json_encode($customerSnapshot,JSON_UNESCAPED_UNICODE),$subtotal,$discount,$shipping,0,$grandTotal,$payload['payment_method'],$payload['shipping_method'],$cart['coupon_code'],$payload['gift_message'] ?? null]);
            $orderId = (int) $pdo->lastInsertId();
            $address = ['name'=>trim($payload['first_name'].' '.$payload['last_name']),'company_name'=>$payload['company_name']??null,'tax_id'=>$payload['tax_id']??null,'registration_number'=>$payload['registration_number']??null,'line1'=>$payload['address'],'line2'=>$payload['address_2']??null,'city'=>$payload['city'],'county'=>$payload['county'],'postal_code'=>$payload['postal_code'],'country_code'=>'RO','phone'=>$payload['phone']];
            $addressStmt = $pdo->prepare('INSERT INTO order_addresses (order_id,type,snapshot_json) VALUES (?,?,?)');
            $addressStmt->execute([$orderId,'billing',json_encode($address,JSON_UNESCAPED_UNICODE)]); $addressStmt->execute([$orderId,'shipping',json_encode($address,JSON_UNESCAPED_UNICODE)]);
            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id,product_id,variant_id,name_snapshot,sku_snapshot,options_json,unit_price_minor,quantity,total_minor,customization_json,customization_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $movement = $pdo->prepare("INSERT INTO inventory_movements (variant_id,movement_type,quantity,reference_type,reference_id,note) VALUES (?,'sale',?,'order',?,'Comandă confirmată')");
            foreach ($items as $item) {
                $total = (int) $item['price_minor'] * (int) $item['quantity'];
                $itemStmt->execute([$orderId,$item['product_id'],$item['variant_id'],$item['name'],$item['sku'],json_encode(['label'=>$item['options_label']],JSON_UNESCAPED_UNICODE),$item['price_minor'],$item['quantity'],$total,$item['customization_json'],hash('sha256',(string)$item['customization_json'])]);
                $pdo->prepare('UPDATE product_variants SET stock_qty=stock_qty-? WHERE id=?')->execute([$item['quantity'],$item['variant_id']]);
                $movement->execute([$item['variant_id'],-(int)$item['quantity'],$orderId]);
            }
            $pdo->prepare("INSERT INTO order_status_history (order_id,old_status,new_status,public_label,public_message,is_public,source) VALUES (?,NULL,'new','Comandă primită','Am primit comanda și o pregătim cu grijă.',1,'system')")->execute([$orderId]);
            $pdo->prepare("INSERT INTO payments (order_id,provider,amount_minor,currency,status,idempotency_key) VALUES (?,?,?,'RON',?,?)")->execute([$orderId,$payload['payment_method'],$grandTotal,$payload['payment_method']==='cod'?'unpaid':'pending',hash('sha256','payment:'.$orderId.':'.$payload['payment_method'])]);
            if ($couponId) { $pdo->prepare('INSERT INTO coupon_usages (coupon_id,user_id,order_id) VALUES (?,?,?)')->execute([$couponId,Auth::id(),$orderId]); }
            $pdo->prepare("UPDATE carts SET status='converted',updated_at=NOW() WHERE id=?")->execute([$cart['id']]);
            $order = ['id'=>$orderId,'order_number'=>$orderNumber,'public_token'=>$publicToken,'email'=>$payload['email'],'grand_total_minor'=>$grandTotal];
            $this->notifications->newOrder($pdo,$order);
            return $order;
        });
    }
}

