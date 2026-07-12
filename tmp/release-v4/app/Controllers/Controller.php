<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Auth;

abstract class Controller
{
    protected function storefront(string $viewName, array $data = []): string
    {
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

