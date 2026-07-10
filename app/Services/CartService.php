<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;
use PDOException;

final class CartService
{
    public const COOKIE = 'maison_cart';

    public function current(): array
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = $this->issueToken();
        }

        $pdo = Database::connection();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $statement = $pdo->prepare('SELECT * FROM carts WHERE token=? LIMIT 1');
            $statement->execute([$token]);
            $cart = $statement->fetch();

            if ($cart && $cart['status'] === 'active') {
                if (Auth::id() && !$cart['user_id']) {
                    $pdo->prepare('UPDATE carts SET user_id=? WHERE id=?')->execute([Auth::id(), $cart['id']]);
                    $cart['user_id'] = Auth::id();
                }
                return $cart;
            }

            if ($cart && $cart['status'] !== 'active') {
                $token = $this->issueToken();
                continue;
            }

            try {
                $pdo->prepare("INSERT INTO carts (user_id,token,status) VALUES (?,?,'active')")->execute([Auth::id(), $token]);
                return ['id' => (int) $pdo->lastInsertId(), 'user_id' => Auth::id(), 'token' => $token, 'coupon_code' => null, 'currency' => 'RON', 'status' => 'active'];
            } catch (PDOException) {
                $token = $this->issueToken();
            }
        }

        throw new HttpException(500, 'Cosul nu a putut fi initializat. Reimprospateaza pagina.');
    }

    public function add(int $variantId, int $quantity = 1, array $customization = []): array
    {
        if ($quantity < 1 || $quantity > 20) {
            throw new HttpException(422, 'Cantitatea selectata nu este valida.');
        }
        return Database::transaction(function (PDO $pdo) use ($variantId, $quantity, $customization): array {
            $variantStatement = $pdo->prepare("SELECT v.*,p.name product_name,p.slug,p.status,GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / ') option_label FROM product_variants v JOIN products p ON p.id=v.product_id LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id WHERE v.id=? AND v.is_active=1 GROUP BY v.id FOR UPDATE");
            $variantStatement->execute([$variantId]);
            $variant = $variantStatement->fetch();
            if (!$variant || $variant['status'] !== 'active') {
                throw new HttpException(422, 'Varianta nu mai este disponibila.');
            }
            $cart = $this->current();
            $hash = hash('sha256', json_encode($customization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $existing = $pdo->prepare('SELECT id,quantity FROM cart_items WHERE cart_id=? AND variant_id=? AND customization_hash=? FOR UPDATE');
            $existing->execute([$cart['id'], $variantId, $hash]);
            $item = $existing->fetch();
            $newQuantity = $quantity + (int) ($item['quantity'] ?? 0);
            if ($newQuantity > (int) $variant['stock_qty']) {
                throw new HttpException(422, 'Stoc insuficient pentru cantitatea aleasa.');
            }
            if ($item) {
                $pdo->prepare('UPDATE cart_items SET quantity=?,updated_at=NOW() WHERE id=?')->execute([$newQuantity, $item['id']]);
                $itemId = (int) $item['id'];
            } else {
                $pdo->prepare('INSERT INTO cart_items (cart_id,variant_id,quantity,customization_json,customization_hash) VALUES (?,?,?,?,?)')->execute([$cart['id'], $variantId, $quantity, $customization ? json_encode($customization, JSON_UNESCAPED_UNICODE) : null, $hash]);
                $itemId = (int) $pdo->lastInsertId();
            }
            return ['item_id' => $itemId, 'name' => $variant['product_name'], 'variant' => $variant['option_label'] ?: 'Standard', 'quantity' => $quantity, 'slug' => $variant['slug']];
        });
    }

    public function update(int $itemId, int $quantity): void
    {
        $cart = $this->current();
        if ($quantity <= 0) {
            $this->remove($itemId);
            return;
        }
        Database::transaction(static function (PDO $pdo) use ($cart, $itemId, $quantity): void {
            $statement = $pdo->prepare('SELECT ci.id,v.stock_qty FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id WHERE ci.id=? AND ci.cart_id=? FOR UPDATE');
            $statement->execute([$itemId, $cart['id']]);
            $item = $statement->fetch();
            if (!$item) {
                throw new HttpException(404, 'Produsul din cos nu exista.');
            }
            if ($quantity > (int) $item['stock_qty']) {
                throw new HttpException(422, 'Stoc insuficient.');
            }
            $pdo->prepare('UPDATE cart_items SET quantity=?,updated_at=NOW() WHERE id=?')->execute([$quantity, $itemId]);
        });
    }

    public function remove(int $itemId): void
    {
        $cart = $this->current();
        $statement = Database::connection()->prepare('DELETE FROM cart_items WHERE id=? AND cart_id=?');
        $statement->execute([$itemId, $cart['id']]);
    }

    public function applyCoupon(string $code): array
    {
        $cart = $this->current();
        $code = mb_strtoupper(trim($code));
        $statement = Database::connection()->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1");
        $statement->execute([$code]);
        $coupon = $statement->fetch();
        if (!$coupon) {
            throw new HttpException(422, 'Codul promotional nu este valid.');
        }
        $totals = $this->totals();
        if ($totals['subtotal_minor'] < (int) $coupon['minimum_order_minor']) {
            throw new HttpException(422, 'Valoarea minima pentru acest cod nu a fost atinsa.');
        }
        Database::connection()->prepare('UPDATE carts SET coupon_code=? WHERE id=?')->execute([$code, $cart['id']]);
        return $this->totals();
    }

    public function items(): array
    {
        $cart = $this->current();
        $statement = Database::connection()->prepare("SELECT ci.id,ci.quantity,ci.customization_json,v.id variant_id,v.price_minor,v.compare_at_price_minor,v.stock_qty,v.sku,p.id product_id,p.name,p.slug,GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / ') variant_label,COALESCE(m.path,'/assets/images/packaging-reference.png') image_path FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id JOIN products p ON p.id=v.product_id LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id WHERE ci.cart_id=? GROUP BY ci.id ORDER BY ci.created_at");
        $statement->execute([$cart['id']]);
        return $statement->fetchAll();
    }

    public function totals(): array
    {
        $cart = $this->current();
        $items = $this->items();
        $subtotal = array_sum(array_map(static fn(array $item): int => (int) $item['price_minor'] * (int) $item['quantity'], $items));
        $discount = 0;
        $coupon = null;
        if ($cart['coupon_code']) {
            $statement = Database::connection()->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1");
            $statement->execute([$cart['coupon_code']]);
            $coupon = $statement->fetch();
            if ($coupon && $subtotal >= (int) $coupon['minimum_order_minor']) {
                $discount = $coupon['discount_type'] === 'percent' ? (int) round($subtotal * ((int) $coupon['discount_value'] / 100)) : (int) $coupon['discount_value'];
                if ($coupon['maximum_discount_minor']) {
                    $discount = min($discount, (int) $coupon['maximum_discount_minor']);
                }
                $discount = min($discount, $subtotal);
            }
        }
        $threshold = (int) env('FREE_SHIPPING_THRESHOLD', 50000);
        $shipping = $subtotal - $discount >= $threshold ? 0 : 2500;
        return ['items' => $items, 'count' => array_sum(array_column($items, 'quantity')), 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'shipping_minor' => $shipping, 'grand_total_minor' => $subtotal - $discount + $shipping, 'coupon' => $coupon];
    }

    public function count(): int
    {
        return (int) $this->totals()['count'];
    }

    private function issueToken(): string
    {
        $token = bin2hex(random_bytes(32));
        setcookie(self::COOKIE, $token, ['expires' => time() + 86400 * 30, 'path' => '/', 'secure' => $this->isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[self::COOKIE] = $token;
        return $token;
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
