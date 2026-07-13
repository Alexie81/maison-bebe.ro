<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;

final class WishlistService
{
    public const COOKIE = 'maison_wishlist';

    public function currentId(): int
    {
        $pdo = Database::connection();
        if (Auth::id()) {
            $statement = $pdo->prepare('SELECT id FROM wishlists WHERE user_id=? LIMIT 1');
            $statement->execute([Auth::id()]);
            $id = $statement->fetchColumn();
            if (!$id) { $pdo->prepare('INSERT INTO wishlists (user_id) VALUES (?)')->execute([Auth::id()]); $id = $pdo->lastInsertId(); }
            return (int) $id;
        }
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            setcookie(self::COOKIE, $token, ['expires'=>time()+86400*90,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
            $_COOKIE[self::COOKIE] = $token;
        }
        $statement = $pdo->prepare('SELECT id FROM wishlists WHERE token=? LIMIT 1');
        $statement->execute([$token]);
        $id = $statement->fetchColumn();
        if (!$id) { $pdo->prepare('INSERT INTO wishlists (token) VALUES (?)')->execute([$token]); $id = $pdo->lastInsertId(); }
        return (int) $id;
    }

    public function toggle(int $productId): bool
    {
        $wishlistId = $this->currentId();
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT 1 FROM wishlist_items WHERE wishlist_id=? AND product_id=?');
        $statement->execute([$wishlistId, $productId]);
        if ($statement->fetchColumn()) {
            $pdo->prepare('DELETE FROM wishlist_items WHERE wishlist_id=? AND product_id=?')->execute([$wishlistId, $productId]);
            return false;
        }
        $pdo->prepare('INSERT INTO wishlist_items (wishlist_id,product_id) SELECT ?,id FROM products WHERE id=? AND status=\'active\'')->execute([$wishlistId, $productId]);
        return true;
    }

    public function count(): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM wishlist_items WHERE wishlist_id=?');
        $statement->execute([$this->currentId()]);
        return (int) $statement->fetchColumn();
    }

    public function productIds(): array
    {
        $statement = Database::connection()->prepare('SELECT product_id FROM wishlist_items WHERE wishlist_id=?');
        $statement->execute([$this->currentId()]);
        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function items(): array
    {
        $statement = Database::connection()->prepare("SELECT p.id,p.name,p.slug,p.short_description,COALESCE(v.price_minor,0) price_minor,v.stock_qty,COALESCE(m.path,'/assets/images/packaging-reference.png') image_path,p.name image_alt FROM wishlist_items wi JOIN products p ON p.id=wi.product_id LEFT JOIN (SELECT product_id,MIN(price_minor) price_minor,SUM(stock_qty) stock_qty FROM product_variants WHERE is_active=1 GROUP BY product_id) v ON v.product_id=p.id LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id WHERE wi.wishlist_id=? AND p.status='active' ORDER BY wi.created_at DESC");
        $statement->execute([$this->currentId()]);
        return $statement->fetchAll();
    }
}
