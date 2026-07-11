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
        if (!array_key_exists('cartCount', $data)) {
            $data['cartCount'] = (new CartService())->count();
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
