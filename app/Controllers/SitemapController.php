<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;

final class SitemapController
{
    public function index(Request $request): never
    {
        $xml = $this->head('sitemapindex');
        foreach (['products', 'content', 'atelier'] as $item) {
            $xml .= '<sitemap><loc>' . $this->escape(absolute_url('/sitemaps/' . $item . '.xml')) . '</loc><lastmod>' . date('c') . '</lastmod></sitemap>';
        }
        Response::xml($xml . '</sitemapindex>');
    }

    public function map(Request $request, string $type): never
    {
        $pdo = Database::connection();
        $rows = match ($type) {
            'products' => $pdo->query("SELECT CONCAT('/produs/',slug) path,updated_at,0.8 priority FROM products WHERE status='active' AND robots_index=1 AND include_sitemap=1 AND deleted_at IS NULL")->fetchAll(),
            'content' => $this->contentRows($pdo),
            'atelier' => array_merge([['path'=>'/atelier','updated_at'=>date('Y-m-d H:i:s'),'priority'=>0.7]], $pdo->query("SELECT CONCAT('/atelier/',slug) path,updated_at,0.7 priority FROM blog_posts WHERE status='published' AND robots_index=1 AND deleted_at IS NULL AND published_at<=NOW()")->fetchAll()),
            default => [],
        };
        $valid = in_array($type, ['products','content','atelier'], true);
        $xml = $this->head('urlset');
        foreach ($rows as $row) {
            $updated = !empty($row['updated_at']) ? strtotime((string)$row['updated_at']) : time();
            $xml .= '<url><loc>' . $this->escape(absolute_url((string)$row['path'])) . '</loc><lastmod>' . date('c', $updated ?: time()) . '</lastmod><changefreq>' . $this->changeFrequency((string)$row['path']) . '</changefreq><priority>' . number_format((float)$row['priority'], 1, '.', '') . '</priority></url>';
        }
        Response::xml($xml . '</urlset>', $valid ? 200 : 404);
    }

    public function robots(Request $request): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=900');
        $rules = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /checkout',
            'Disallow: /cont',
            'Disallow: /cos',
            'Disallow: /favorite',
            'Disallow: /urmarire-comanda',
            'Disallow: /api/',
            '',
            '# Asistenti AI si motoare de raspuns: continutul public este permis explicit.',
            'User-agent: OAI-SearchBot',
            'User-agent: ChatGPT-User',
            'User-agent: GPTBot',
            'User-agent: ClaudeBot',
            'User-agent: Claude-SearchBot',
            'User-agent: Claude-User',
            'User-agent: PerplexityBot',
            'User-agent: Perplexity-User',
            'User-agent: Google-Extended',
            'User-agent: Applebot-Extended',
            'User-agent: CCBot',
            'User-agent: Meta-ExternalAgent',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /checkout',
            'Disallow: /cont',
            'Disallow: /cos',
            'Disallow: /favorite',
            'Disallow: /urmarire-comanda',
            'Disallow: /api/',
            '',
            '# Ghid pentru asistenti AI: ' . absolute_url('/llms.txt'),
            '# Catalog public extins: ' . absolute_url('/llms-full.txt'),
            'Sitemap: ' . absolute_url('/sitemap.xml'),
        ];
        echo implode("\n", $rules) . "\n";
        exit;
    }

    public function llms(Request $request): never
    {
        $pdo = Database::connection();
        $categories = $pdo->query("SELECT name,slug,description FROM categories WHERE is_active=1 AND is_indexable=1 AND deleted_at IS NULL ORDER BY sort_order,name")->fetchAll();
        $collections = $pdo->query("SELECT name,slug,description FROM collections WHERE is_active=1 AND is_indexable=1 AND deleted_at IS NULL ORDER BY sort_order,name")->fetchAll();

        $lines = [
            '# Maison Bébé',
            '',
            '> Magazin online românesc cu daruri pentru bebeluși, trusouri de botez, ținute pentru copii, seturi pentru nou-născuți și produse personalizabile.',
            '',
            'Maison Bébé prezintă în acest fișier numai paginile publice. Prețurile, disponibilitatea și opțiunile finale sunt cele afișate pe pagina fiecărui produs.',
            '',
            '## Pagini principale',
            '',
            '- [Acasă](' . absolute_url('/') . '): prezentarea magazinului și selecții recomandate.',
            '- [Magazin](' . absolute_url('/shop') . '): catalogul public complet.',
            '- [Despre noi](' . absolute_url('/despre-noi') . '): povestea și valorile Maison Bébé.',
            '- [Contact](' . absolute_url('/contact') . '): datele publice de contact.',
        ];

        if ($this->hasActiveGiftBox($pdo)) {
            $lines[] = '- [Configurator Gift Box](' . absolute_url('/gift-box') . '): configurarea unui cadou personalizat.';
        }

        $this->appendDirectory($lines, 'Categorii', '/categorie/', $categories);
        $this->appendDirectory($lines, 'Colecții', '/colectie/', $collections);

        $lines = array_merge($lines, [
            '## Politici și informații',
            '',
            '- [Livrare și retur](' . absolute_url('/politici/livrare-si-retur') . ')',
            '- [Termeni și condiții](' . absolute_url('/politici/termeni-si-conditii') . ')',
            '- [Confidențialitate](' . absolute_url('/politici/confidentialitate') . ')',
            '- [Politica de cookies](' . absolute_url('/politici/cookies') . ')',
            '',
            '## Resurse pentru indexare',
            '',
            '- [Catalog extins pentru asistenți AI](' . absolute_url('/llms-full.txt') . '): toate produsele publice active, cu informații esențiale.',
            '- [Sitemap XML](' . absolute_url('/sitemap.xml') . ')',
            '- [Reguli robots](' . absolute_url('/robots.txt') . ')',
        ]);

        $this->markdown($lines);
    }

    public function llmsFull(Request $request): never
    {
        $pdo = Database::connection();
        $categories = $pdo->query("SELECT name,slug,description FROM categories WHERE is_active=1 AND is_indexable=1 AND deleted_at IS NULL ORDER BY sort_order,name")->fetchAll();
        $collections = $pdo->query("SELECT name,slug,description FROM collections WHERE is_active=1 AND is_indexable=1 AND deleted_at IS NULL ORDER BY sort_order,name")->fetchAll();
        $products = $pdo->query(
            "SELECT p.name,p.slug,p.sku,p.brand,p.short_description,COALESCE(c.name,'Magazin') category_name,"
            . "COALESCE(MIN(CASE WHEN v.is_active=1 THEN v.price_minor END),0) price_minor "
            . "FROM products p "
            . "LEFT JOIN categories c ON c.id=p.primary_category_id "
            . "LEFT JOIN product_variants v ON v.product_id=p.id "
            . "WHERE p.status='active' AND p.robots_index=1 AND p.include_sitemap=1 AND p.deleted_at IS NULL "
            . "GROUP BY p.id,p.name,p.slug,p.sku,p.brand,p.short_description,c.name "
            . "ORDER BY category_name,p.name"
        )->fetchAll();

        $lines = [
            '# Maison Bébé — catalog public extins',
            '',
            '> Reprezentare în format Markdown a conținutului public Maison Bébé, generată automat din catalogul activ.',
            '',
            'Sursa canonică pentru preț, stoc, imagini, variante și opțiuni este întotdeauna pagina produsului. Moneda magazinului este RON.',
            '',
            '## Navigare',
            '',
            '- [Acasă](' . absolute_url('/') . ')',
            '- [Magazin](' . absolute_url('/shop') . ')',
            '- [Despre noi](' . absolute_url('/despre-noi') . ')',
            '- [Contact](' . absolute_url('/contact') . ')',
            '- [Versiunea LLMS scurtă](' . absolute_url('/llms.txt') . ')',
        ];

        if ($this->hasActiveGiftBox($pdo)) {
            $lines[] = '- [Configurator Gift Box](' . absolute_url('/gift-box') . ')';
        }

        $this->appendDirectory($lines, 'Categorii publice', '/categorie/', $categories);
        $this->appendDirectory($lines, 'Colecții publice', '/colectie/', $collections);

        $lines[] = '## Produse publice active';
        $lines[] = '';
        $currentCategory = null;
        foreach ($products as $product) {
            $category = $this->cleanText((string)($product['category_name'] ?? 'Magazin'));
            if ($category !== $currentCategory) {
                $lines[] = '### ' . $category;
                $lines[] = '';
                $currentCategory = $category;
            }
            $name = $this->cleanText((string)$product['name']);
            $price = number_format(((int)$product['price_minor']) / 100, 2, ',', '.') . ' RON';
            $details = [];
            if (trim((string)$product['sku']) !== '') {
                $details[] = 'SKU ' . $this->cleanText((string)$product['sku']);
            }
            if (trim((string)$product['brand']) !== '') {
                $details[] = 'marcă ' . $this->cleanText((string)$product['brand']);
            }
            $details[] = 'de la ' . $price;
            $lines[] = '- [' . $name . '](' . absolute_url('/produs/' . (string)$product['slug']) . ') — ' . implode('; ', $details) . '.';
            $description = $this->cleanText((string)($product['short_description'] ?? ''), 240);
            if ($description !== '') {
                $lines[] = '  ' . $description;
            }
        }

        $lines = array_merge($lines, [
            '',
            '## Politici',
            '',
            '- [Livrare și retur](' . absolute_url('/politici/livrare-si-retur') . ')',
            '- [Termeni și condiții](' . absolute_url('/politici/termeni-si-conditii') . ')',
            '- [Confidențialitate](' . absolute_url('/politici/confidentialitate') . ')',
            '- [Politica de cookies](' . absolute_url('/politici/cookies') . ')',
            '',
            '## Indexare',
            '',
            '- [Sitemap XML](' . absolute_url('/sitemap.xml') . ')',
            '- [Reguli robots](' . absolute_url('/robots.txt') . ')',
        ]);

        $this->markdown($lines);
    }

    private function contentRows(\PDO $pdo): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['path'=>'/','updated_at'=>$now,'priority'=>1.0],
            ['path'=>'/shop','updated_at'=>$now,'priority'=>0.9],
            ['path'=>'/despre-noi','updated_at'=>$now,'priority'=>0.8],
            ['path'=>'/contact','updated_at'=>$now,'priority'=>0.7],
            ['path'=>'/politici/livrare-si-retur','updated_at'=>$now,'priority'=>0.5],
            ['path'=>'/politici/termeni-si-conditii','updated_at'=>$now,'priority'=>0.5],
            ['path'=>'/politici/confidentialitate','updated_at'=>$now,'priority'=>0.4],
            ['path'=>'/politici/cookies','updated_at'=>$now,'priority'=>0.4],
        ];
        if ($this->hasActiveGiftBox($pdo)) {
            $rows[] = ['path'=>'/gift-box','updated_at'=>$now,'priority'=>0.8];
        }
        $categories = $pdo->query("SELECT CONCAT('/categorie/',c.slug) path,c.updated_at,0.7 priority FROM categories c WHERE c.is_active=1 AND c.is_indexable=1 AND c.deleted_at IS NULL AND EXISTS (SELECT 1 FROM product_categories pc JOIN products p ON p.id=pc.product_id WHERE pc.category_id=c.id AND p.status='active' AND p.deleted_at IS NULL)")->fetchAll();
        $collections = $pdo->query("SELECT CONCAT('/colectie/',c.slug) path,c.updated_at,0.7 priority FROM collections c WHERE c.is_active=1 AND c.is_indexable=1 AND c.deleted_at IS NULL AND EXISTS (SELECT 1 FROM collection_products cp JOIN products p ON p.id=cp.product_id WHERE cp.collection_id=c.id AND p.status='active' AND p.deleted_at IS NULL)")->fetchAll();
        return array_merge($rows, $categories, $collections);
    }

    private function hasActiveGiftBox(\PDO $pdo): bool
    {
        $statement=$pdo->prepare('SELECT value_json FROM settings WHERE setting_key=? LIMIT 1');$statement->execute(['gift_box_configurator']);$stored=$statement->fetchColumn();$decoded=$stored===false?[]:json_decode((string)$stored,true);$enabled=$stored===false||(bool)($decoded['enabled']??true);
        if($enabled&&(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM gift_box_templates WHERE is_active=1 AND deleted_at IS NULL)")->fetchColumn()) return true;
        return (bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM products p JOIN product_categories pc ON pc.product_id=p.id JOIN categories c ON c.id=pc.category_id WHERE p.status='active' AND p.deleted_at IS NULL AND c.slug='gift-box' AND c.is_active=1 AND c.deleted_at IS NULL)")->fetchColumn();
    }

    private function appendDirectory(array &$lines, string $title, string $prefix, array $items): void
    {
        if ($items === []) {
            return;
        }
        $lines[] = '';
        $lines[] = '## ' . $title;
        $lines[] = '';
        foreach ($items as $item) {
            $name = $this->cleanText((string)$item['name']);
            $description = $this->cleanText((string)($item['description'] ?? ''), 160);
            $line = '- [' . $name . '](' . absolute_url($prefix . (string)$item['slug']) . ')';
            $lines[] = $description !== '' ? $line . ': ' . $description : $line;
        }
    }

    private function cleanText(string $value, int $limit = 0): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string)preg_replace('/\s+/u', ' ', $value));
        if ($limit > 0 && mb_strlen($value) > $limit) {
            $value = rtrim(mb_substr($value, 0, $limit - 1)) . '…';
        }
        return str_replace(['[', ']'], ['(', ')'], $value);
    }

    private function markdown(array $lines): never
    {
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=900');
        header('X-Content-Type-Options: nosniff');
        echo implode("\n", $lines) . "\n";
        exit;
    }

    private function changeFrequency(string $path): string { return $path==='/'?'daily':(str_starts_with($path,'/produs/')||$path==='/shop'?'weekly':'monthly'); }
    private function head(string $root): string { return '<?xml version="1.0" encoding="UTF-8"?><'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'; }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
}
