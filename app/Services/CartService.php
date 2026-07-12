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
            $variantStatement = $pdo->prepare("SELECT v.*,p.id product_id,p.name product_name,p.slug,p.status,GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / ') option_label FROM product_variants v JOIN products p ON p.id=v.product_id LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id WHERE v.id=? AND v.is_active=1 GROUP BY v.id FOR UPDATE");
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
            return ['item_id' => $itemId, 'name' => $variant['product_name'], 'variant' => $variant['option_label'] ?: 'Standard', 'quantity' => $quantity, 'slug' => $variant['slug'], 'product_id' => (int) $variant['product_id']];
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
        Database::transaction(static function (PDO $pdo) use ($cart, $itemId): void {
            $statement = $pdo->prepare('SELECT id,customization_json FROM cart_items WHERE id=? AND cart_id=? FOR UPDATE');
            $statement->execute([$itemId, $cart['id']]);
            $item = $statement->fetch();
            if (!$item) {
                return;
            }

            $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            if (($custom['type'] ?? '') === 'gift_box' && ($custom['role'] ?? '') === 'box' && !empty($custom['group'])) {
                $ids = self::cartItemIdsForGiftGroup($pdo, (int) $cart['id'], (string) $custom['group']);
                self::deleteCartItems($pdo, $ids);
                return;
            }

            self::deleteCartItems($pdo, [$itemId]);
        });
    }

    public function removeProduct(int $productId): void
    {
        $cart = $this->current();
        Database::transaction(static function (PDO $pdo) use ($cart, $productId): void {
            $statement = $pdo->prepare('SELECT ci.id,ci.customization_json FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id WHERE ci.cart_id=? AND v.product_id=? ORDER BY ci.created_at DESC FOR UPDATE');
            $statement->execute([$cart['id'], $productId]);
            $ids = [];
            foreach ($statement->fetchAll() as $item) {
                $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
                if (($custom['type'] ?? '') === 'gift_box') {
                    continue;
                }
                $ids[] = (int) $item['id'];
            }
            self::deleteCartItems($pdo, $ids);
        });
    }

    public function normalItemIdForProduct(int $productId): ?int
    {
        $cart = $this->current();
        $statement = Database::connection()->prepare('SELECT ci.id,ci.customization_json FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id WHERE ci.cart_id=? AND v.product_id=? ORDER BY ci.created_at DESC');
        $statement->execute([$cart['id'], $productId]);
        foreach ($statement->fetchAll() as $item) {
            $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            if (($custom['type'] ?? '') !== 'gift_box') {
                return (int) $item['id'];
            }
        }
        return null;
    }

    public function giftBoxConfiguration(string $group): ?array
    {
        if (!preg_match('/^GB-[A-F0-9]{8}$/', $group)) {
            return null;
        }
        foreach ($this->items() as $item) {
            $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            if (($custom['type'] ?? '') === 'gift_box' && ($custom['role'] ?? '') === 'box' && ($custom['group'] ?? '') === $group) {
                $custom['cart_item_id'] = (int) $item['id'];
                return $custom;
            }
        }
        return null;
    }

    public function removeGiftBoxGroup(string $group): void
    {
        if (!preg_match('/^GB-[A-F0-9]{8}$/', $group)) {
            return;
        }
        $cart = $this->current();
        Database::transaction(static function (PDO $pdo) use ($cart, $group): void {
            self::deleteCartItems($pdo, self::cartItemIdsForGiftGroup($pdo, (int) $cart['id'], $group));
        });
    }
    public function applyCoupon(string $code): array
    {
        $cart = $this->current();
        $code = mb_strtoupper(trim($code));
        $pdo = Database::connection();
        $statement = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1");
        $statement->execute([$code]);
        $coupon = $statement->fetch();
        if (!$coupon) throw new HttpException(422, 'Codul promoțional nu este valid sau a expirat.');
        $evaluation = (new CouponEligibilityService())->evaluate($pdo, $coupon, $this->items(), Auth::id());
        if (!$evaluation['eligible']) throw new HttpException(422, $evaluation['message']);
        $pdo->prepare('UPDATE carts SET coupon_code=? WHERE id=?')->execute([$code, $cart['id']]);
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
            $pdo = Database::connection();
            $statement = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1");
            $statement->execute([$cart['coupon_code']]);
            $coupon = $statement->fetch();
            if ($coupon) {
                $evaluation = (new CouponEligibilityService())->evaluate($pdo, $coupon, $items, Auth::id());
                if ($evaluation['eligible']) $discount = $evaluation['discount_minor'];
                else $coupon = null;
            }
        }
        $shipping = (new ShippingPricingService())->cost($subtotal-$discount);
        $visibleCount = 0;
        foreach ($items as $item) {
            $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            if (($custom['type'] ?? '') === 'gift_box' && ($custom['role'] ?? '') === 'component') {
                continue;
            }
            $visibleCount += (int) $item['quantity'];
        }
        return ['items' => $items, 'count' => $visibleCount, 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'shipping_minor' => $shipping, 'grand_total_minor' => $subtotal - $discount + $shipping, 'coupon' => $coupon];
    }

    public function count(): int
    {
        return (int) $this->totals()['count'];
    }

    public function productIds(): array
    {
        $ids = [];
        foreach ($this->items() as $item) {
            $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            if (($custom['type'] ?? '') === 'gift_box') {
                continue;
            }
            $ids[(int) $item['product_id']] = true;
        }
        return array_keys($ids);
    }

    private static function cartItemIdsForGiftGroup(PDO $pdo, int $cartId, string $group): array
    {
        $statement = $pdo->prepare('SELECT id,customization_json FROM cart_items WHERE cart_id=? FOR UPDATE');
        $statement->execute([$cartId]);
        $ids = [];
        foreach ($statement->fetchAll() as $item) {
            $custom = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            if (($custom['type'] ?? '') === 'gift_box' && ($custom['group'] ?? '') === $group) {
                $ids[] = (int) $item['id'];
            }
        }
        return $ids;
    }

    private static function deleteCartItems(PDO $pdo, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare('DELETE FROM gift_box_customizations WHERE cart_item_id IN (' . $placeholders . ')')->execute($ids);
        $pdo->prepare('DELETE FROM cart_items WHERE id IN (' . $placeholders . ')')->execute($ids);
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
