<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Repositories\ProductRepository;

final class ApiController
{
    public function search(Request $request): never
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2) {
            Response::json(['items' => []]);
        }
        $items = array_map(static fn(array $item): array => [
            'name' => $item['name'],
            'category' => $item['category_name'],
            'price' => money($item['price_minor']),
            'image' => url($item['image_path']),
            'url' => url('/produs/' . $item['slug']),
        ], (new ProductRepository())->search($query));
        Response::json(['items' => $items]);
    }
}

