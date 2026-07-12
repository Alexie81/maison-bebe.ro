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
        $items = ['products', 'content', 'atelier'];
        $xml = $this->head('sitemapindex');
        foreach ($items as $item) {
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
            'atelier' => array_merge([['path' => '/atelier', 'updated_at' => date('Y-m-d H:i:s'), 'priority' => 0.8]], $pdo->query("SELECT CONCAT('/atelier/',slug) path,updated_at,0.7 priority FROM blog_posts WHERE status='published' AND robots_index=1 AND deleted_at IS NULL AND published_at<=NOW()")->fetchAll()),
            default => [],
        };
        if (!$rows) {
            Response::xml($this->head('urlset') . '</urlset>', $type === 'products' || $type === 'atelier' || $type === 'content' ? 200 : 404);
        }
        $xml = $this->head('urlset');
        foreach ($rows as $row) {
            $xml .= '<url><loc>' . $this->escape(absolute_url((string) $row['path'])) . '</loc><lastmod>' . date('c', strtotime((string) $row['updated_at'])) . '</lastmod><priority>' . number_format((float) $row['priority'], 1, '.', '') . '</priority></url>';
        }
        Response::xml($xml . '</urlset>');
    }

    public function robots(Request $request): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /checkout\nDisallow: /cont\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
        exit;
    }

    private function contentRows(\PDO $pdo): array
    {
        $rows = [
            ['path' => '/', 'updated_at' => date('Y-m-d H:i:s'), 'priority' => 1.0],
            ['path' => '/shop', 'updated_at' => date('Y-m-d H:i:s'), 'priority' => 0.9],
        ];
        if ($this->hasActiveGiftBox($pdo)) {
            $rows[] = ['path' => '/gift-box', 'updated_at' => date('Y-m-d H:i:s'), 'priority' => 0.8];
        }
        $categories = $pdo->query("SELECT CONCAT('/categorie/',c.slug) path,c.updated_at,0.7 priority
            FROM categories c
            WHERE c.is_active=1 AND c.deleted_at IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM product_categories pc
                  JOIN products p ON p.id=pc.product_id
                  WHERE pc.category_id=c.id AND p.status='active' AND p.deleted_at IS NULL
              )")->fetchAll();
        return array_merge($rows, $categories);
    }

    private function hasActiveGiftBox(\PDO $pdo): bool
    {
        $statement = $pdo->prepare('SELECT value_json FROM settings WHERE setting_key=? LIMIT 1');
        $statement->execute(['gift_box_configurator']);
        $stored = $statement->fetchColumn();
        $decoded = $stored === false ? [] : json_decode((string) $stored, true);
        $configuratorEnabled = $stored === false || (bool) ($decoded['enabled'] ?? true);
        if ($configuratorEnabled && (bool) $pdo->query("SELECT EXISTS(SELECT 1 FROM gift_box_templates WHERE is_active=1 AND deleted_at IS NULL)")->fetchColumn()) {
            return true;
        }
        return (bool) $pdo->query("SELECT EXISTS(
            SELECT 1
            FROM products p
            JOIN product_categories pc ON pc.product_id=p.id
            JOIN categories c ON c.id=pc.category_id
            WHERE p.status='active' AND p.deleted_at IS NULL
              AND c.slug='gift-box' AND c.is_active=1 AND c.deleted_at IS NULL
        )")->fetchColumn();
    }
    private function head(string $root): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><' . $root . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
