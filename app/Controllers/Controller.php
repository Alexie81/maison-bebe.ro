<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Services\CartService;
use MaisonBebe\Services\WishlistService;

abstract class Controller
{
    protected function storefront(string $viewName, array $data = []): string
    {
        if (!array_key_exists('cartCount', $data) || !array_key_exists('cartProductIds', $data)) {
            $cart = new CartService();
            $data['cartCount'] ??= $cart->count();
            $data['cartProductIds'] ??= $cart->productIds();
        }
        if (!array_key_exists('wishlistCount', $data) || !array_key_exists('wishlistProductIds', $data)) {
            $wishlist = new WishlistService();
            $data['wishlistCount'] ??= $wishlist->count();
            $data['wishlistProductIds'] ??= $wishlist->productIds();
        }

        if (!array_key_exists('announcement', $data)) {
            $statement = Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=?');
            $statement->execute(['announcement_bar']);
            $stored = json_decode((string) ($statement->fetchColumn() ?: ''), true);
            $data['announcement'] = is_array($stored) ? $stored : ['enabled' => true, 'text' => 'Livrare gratuită pentru comenzile de peste 500 lei, pregătite cu grijă ca un cadou.'];
        }
        if (!array_key_exists('hasActiveCollections', $data)) {
            $data['hasActiveCollections'] = (bool) Database::connection()
                ->query("SELECT EXISTS(
                    SELECT 1
                    FROM categories c
                    WHERE c.is_featured=1 AND c.is_active=1 AND c.deleted_at IS NULL
                      AND EXISTS (
                          SELECT 1
                          FROM product_categories pc
                          JOIN products p ON p.id=pc.product_id
                          WHERE pc.category_id=c.id AND p.status='active' AND p.deleted_at IS NULL
                      )
                )")
                ->fetchColumn();
        }
        if (!array_key_exists('hasActiveGiftBox', $data)) {
            $statement = Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=? LIMIT 1');
            $statement->execute(['gift_box_configurator']);
            $stored = $statement->fetchColumn();
            $decoded = $stored === false ? [] : json_decode((string) $stored, true);
            $configuratorEnabled = $stored === false || (bool) ($decoded['enabled'] ?? true);
            $hasConfiguredBoxes = $configuratorEnabled && (bool) Database::connection()
                ->query("SELECT EXISTS(SELECT 1 FROM gift_box_templates WHERE is_active=1 AND deleted_at IS NULL)")
                ->fetchColumn();
            $hasGiftBoxProducts = (bool) Database::connection()
                ->query("SELECT EXISTS(
                    SELECT 1
                    FROM products p
                    JOIN product_categories pc ON pc.product_id=p.id
                    JOIN categories c ON c.id=pc.category_id
                    WHERE p.status='active' AND p.deleted_at IS NULL
                      AND c.slug='gift-box' AND c.is_active=1 AND c.deleted_at IS NULL
                )")
                ->fetchColumn();
            $data['hasActiveGiftBox'] = $hasConfiguredBoxes || $hasGiftBoxProducts;
        }

        $defaults = [
            'meta' => [
                'title' => 'Maison Bébé - daruri pentru începuturi prețioase',
                'description' => 'Haine, accesorii și Gift Box-uri premium pentru bebeluși.',
                'canonical' => absolute_url($_SERVER['REQUEST_URI'] ?? '/'),
                'robots' => 'index,follow',
            ],
            'authUser' => Auth::user(),
            'cartCount' => 0,
            'wishlistCount' => 0,
        ];
        return view($viewName, array_replace_recursive($defaults, $data));
    }
}
