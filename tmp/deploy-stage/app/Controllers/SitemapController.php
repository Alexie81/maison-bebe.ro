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
        $rules = [
            'User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /checkout', 'Disallow: /cont', 'Disallow: /api/', '',
            'User-agent: Googlebot', 'Allow: /', '',
            'User-agent: Googlebot-Image', 'Allow: /', '',
            'User-agent: Bingbot', 'Allow: /', '',
            'User-agent: OAI-SearchBot', 'Allow: /', '',
            'User-agent: ChatGPT-User', 'Allow: /', '',
            'User-agent: GPTBot', 'Allow: /', '',
            'User-agent: ClaudeBot', 'Allow: /', '',
            'User-agent: PerplexityBot', 'Allow: /', '',
            'Sitemap: ' . absolute_url('/sitemap.xml'),
        ];
        echo implode("\n", $rules) . "\n";
        exit;
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
        $categories = $pdo->query("SELECT CONCAT('/categorie/',c.slug) path,c.updated_at,0.7 priority FROM categories c WHERE c.is_active=1 AND c.deleted_at IS NULL AND EXISTS (SELECT 1 FROM product_categories pc JOIN products p ON p.id=pc.product_id WHERE pc.category_id=c.id AND p.status='active' AND p.deleted_at IS NULL)")->fetchAll();
        $collections = $pdo->query("SELECT CONCAT('/colectie/',c.slug) path,c.updated_at,0.7 priority FROM collections c WHERE c.is_active=1 AND c.deleted_at IS NULL AND EXISTS (SELECT 1 FROM collection_products cp JOIN products p ON p.id=cp.product_id WHERE cp.collection_id=c.id AND p.status='active' AND p.deleted_at IS NULL)")->fetchAll();
        return array_merge($rows, $categories, $collections);
    }

    private function hasActiveGiftBox(\PDO $pdo): bool
    {
        $statement=$pdo->prepare('SELECT value_json FROM settings WHERE setting_key=? LIMIT 1');$statement->execute(['gift_box_configurator']);$stored=$statement->fetchColumn();$decoded=$stored===false?[]:json_decode((string)$stored,true);$enabled=$stored===false||(bool)($decoded['enabled']??true);
        if($enabled&&(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM gift_box_templates WHERE is_active=1 AND deleted_at IS NULL)")->fetchColumn()) return true;
        return (bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM products p JOIN product_categories pc ON pc.product_id=p.id JOIN categories c ON c.id=pc.category_id WHERE p.status='active' AND p.deleted_at IS NULL AND c.slug='gift-box' AND c.is_active=1 AND c.deleted_at IS NULL)")->fetchColumn();
    }

    private function changeFrequency(string $path): string { return $path==='/'?'daily':(str_starts_with($path,'/produs/')||$path==='/shop'?'weekly':'monthly'); }
    private function head(string $root): string { return '<?xml version="1.0" encoding="UTF-8"?><'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'; }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
}