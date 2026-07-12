<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Repositories\ContentRepository;
use MaisonBebe\Repositories\ProductRepository;

final class StorefrontController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly ContentRepository $content = new ContentRepository()
    ) {}

    public function home(Request $request): string
    {
        return $this->storefront('storefront/home', [
            'sections' => $this->content->homepageSections(),
            'categories' => $this->products->categories(true),
            'products' => $this->products->featured(4),
            'posts' => $this->content->posts(3),
            'meta' => ['canonical' => absolute_url('/')],
        ]);
    }

    public function shop(Request $request): string
    {
        $page = max(1, (int) $request->input('page', 1));
        $filters = [
            'category' => trim((string) $request->input('categorie', '')),
            'collection' => trim((string) $request->input('colectie', '')),
            'material' => trim((string) $request->input('material', '')),
            'stock' => $request->input('stoc') === 'disponibil',
            'sort' => trim((string) $request->input('sort', '')),
            'min_price' => $request->input('pret_min') !== null ? (int) $request->input('pret_min') * 100 : null,
            'max_price' => $request->input('pret_max') !== null ? (int) $request->input('pret_max') * 100 : null,
        ];
        $catalog = $this->products->catalog($filters, 12, ($page - 1) * 12);
        return $this->storefront('storefront/catalog', [
            'heading' => 'Shop', 'description' => 'Piese alese pentru confort, delicatețe și începuturi senine.',
            'catalog' => $catalog, 'page' => $page, 'filters' => $filters,
            'categories' => $this->products->categories(), 'collections' => $this->products->collections(), 'materials' => $this->products->materials(),
            'meta' => ['title' => 'Shop | Maison Bébé', 'canonical' => absolute_url('/shop')],
        ]);
    }

    public function category(Request $request, string $slug): string
    {
        $category = $this->products->category($slug);
        if (!$category) { throw new HttpException(404, 'Categoria nu a fost găsită.'); }
        $page = max(1, (int) $request->input('page', 1));
        $filters = ['category' => $slug, 'sort' => (string) $request->input('sort', '')];
        $catalog = $this->products->catalog($filters, 12, ($page - 1) * 12);
        return $this->storefront('storefront/catalog', [
            'heading' => $category['name'], 'description' => $category['description'], 'catalog' => $catalog, 'page' => $page, 'filters' => $filters,
            'categories' => $this->products->categories(), 'collections' => $this->products->collections(), 'materials' => $this->products->materials(), 'category' => $category,
            'meta' => ['title' => $category['seo_title'] ?: $category['name'] . ' | Maison Bébé', 'description' => $category['seo_description'] ?: $category['description'], 'canonical' => absolute_url('/categorie/' . $slug)],
        ]);
    }

    public function collection(Request $request, string $slug): string
    {
        $collection = $this->products->collection($slug);
        if (!$collection) { throw new HttpException(404, 'Colecția nu a fost găsită.'); }
        $catalog = $this->products->catalog(['collection' => $slug], 12, 0);
        return $this->storefront('storefront/catalog', [
            'heading' => $collection['name'], 'description' => $collection['description'], 'catalog' => $catalog, 'page' => 1, 'filters' => ['collection' => $slug],
            'categories' => $this->products->categories(), 'collections' => $this->products->collections(), 'materials' => $this->products->materials(), 'collection' => $collection,
            'meta' => ['title' => $collection['seo_title'] ?: $collection['name'] . ' | Maison Bébé', 'description' => $collection['seo_description'] ?: $collection['description'], 'canonical' => absolute_url('/colectie/' . $slug)],
        ]);
    }

    public function product(Request $request, string $slug): string
    {
        $product = $this->products->findBySlug($slug);
        if (!$product) { throw new HttpException(404, 'Produsul nu a fost găsit.'); }
        $structured = [
            '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product['name'], 'sku' => $product['sku'],
            'description' => strip_tags((string) $product['short_description']), 'image' => array_map(static fn(array $image): string => absolute_url($image['path']), $product['images']),
            'brand' => ['@type' => 'Brand', 'name' => $product['brand'] ?: 'Maison Bébé'],
            'offers' => ['@type' => 'AggregateOffer', 'lowPrice' => number_format(((int) $product['min_price']) / 100, 2, '.', ''), 'highPrice' => number_format(((int) $product['max_price']) / 100, 2, '.', ''), 'priceCurrency' => 'RON', 'availability' => (int) $product['total_stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => absolute_url('/produs/' . $slug)],
        ];
        return $this->storefront('storefront/product', [
            'product' => $product, 'related' => $this->products->related((int) $product['id'], $product['primary_category_id'] ? (int) $product['primary_category_id'] : null), 'structuredData' => $structured,
            'meta' => ['title' => $product['seo_title'] ?: $product['name'] . ' | Maison Bébé', 'description' => $product['seo_description'] ?: $product['short_description'], 'canonical' => absolute_url('/produs/' . $slug), 'og_image' => absolute_url($product['primary_image'])],
        ]);
    }

    public function giftBox(Request $request): string
    {
        return $this->storefront('storefront/gift-box', ['products' => $this->products->catalog(['category' => 'gift-box'], 4, 0)['items'], 'meta' => ['title' => 'Gift Box-uri | Maison Bébé', 'canonical' => absolute_url('/gift-box')]]);
    }

    public function about(Request $request): string
    {
        $page = $this->content->page('despre-noi');
        return $this->storefront('storefront/page', ['page' => $page, 'pageType' => 'about', 'meta' => ['title' => $page['meta_title'] ?? 'Despre Maison Bébé', 'description' => $page['meta_description'] ?? '', 'canonical' => absolute_url('/despre-noi')]]);
    }

    public function legal(Request $request, string $slug): string
    {
        $page = $this->content->page($slug);
        if (!$page) { throw new HttpException(404, 'Pagina informativă nu a fost găsită.'); }
        return $this->storefront('storefront/page', ['page' => $page, 'pageType' => 'legal', 'meta' => ['title' => $page['meta_title'] ?: $page['title'] . ' | Maison Bébé', 'description' => $page['meta_description'], 'canonical' => absolute_url('/politici/' . $slug)]]);
    }

    public function atelier(Request $request): string
    {
        return $this->storefront('storefront/atelier', ['posts' => $this->content->posts(9), 'blogCategories' => $this->content->blogCategories(), 'meta' => ['title' => 'Atelier Maison Bébé - Povești pentru începuturi prețioase', 'description' => 'Ghiduri, inspirație și povești din universul Maison Bébé.', 'canonical' => absolute_url('/atelier')]]);
    }

    public function article(Request $request, string $slug): string
    {
        $post = $this->content->post($slug);
        if (!$post) { throw new HttpException(404, 'Articolul nu a fost găsit.'); }
        $structured = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'], 'image' => [absolute_url($post['image_path'])], 'datePublished' => date(DATE_ATOM, strtotime($post['published_at'])), 'dateModified' => date(DATE_ATOM, strtotime($post['updated_at'])), 'author' => ['@type' => 'Person', 'name' => $post['author_name']], 'publisher' => ['@type' => 'Organization', 'name' => 'Maison Bébé'], 'mainEntityOfPage' => absolute_url('/atelier/' . $slug)];
        return $this->storefront('storefront/article', ['post' => $post, 'relatedPosts' => array_filter($this->content->posts(4), static fn(array $item): bool => $item['id'] !== $post['id']), 'structuredData' => $structured, 'meta' => ['title' => $post['meta_title'] ?: $post['title'] . ' | Atelier Maison Bébé', 'description' => $post['meta_description'] ?: $post['excerpt'], 'canonical' => absolute_url('/atelier/' . $slug), 'og_image' => absolute_url($post['image_path'])]]);
    }
}

