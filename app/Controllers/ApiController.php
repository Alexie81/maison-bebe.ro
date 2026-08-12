<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Repositories\ProductRepository;
use MaisonBebe\Services\GoogleAnalyticsService;

final class ApiController
{
    public function search(Request $request): never
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2) {
            Response::json(['items' => []]);
        }
        $analytics = new GoogleAnalyticsService();
        $products = (new ProductRepository())->search($query);
        $items = array_map(static fn(array $item, int $index): array => [
            'name' => $item['name'],
            'category' => $item['category_name'],
            'price' => money($item['price_minor']),
            'image' => url($item['image_path']),
            'url' => url('/produs/' . $item['slug']),
            'analytics' => $analytics->productItem($item, $index, 'global_search', 'Căutare rapidă'),
        ], $products, array_keys($products));
        Response::json(['items' => $items]);
    }
}
