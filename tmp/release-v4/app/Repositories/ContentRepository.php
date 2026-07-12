<?php
declare(strict_types=1);

namespace MaisonBebe\Repositories;

use MaisonBebe\Core\Database;

final class ContentRepository
{
    public function homepageSections(): array
    {
        $rows = Database::connection()->query('SELECT section_key,title,content_json FROM homepage_sections WHERE is_active=1 ORDER BY sort_order')->fetchAll();
        $sections = [];
        foreach ($rows as $row) {
            $row['content'] = json_decode((string) $row['content_json'], true) ?: [];
            $sections[$row['section_key']] = $row;
        }
        return $sections;
    }

    public function page(string $slug): ?array
    {
        $statement = Database::connection()->prepare("SELECT * FROM pages WHERE slug=? AND status='published' AND deleted_at IS NULL LIMIT 1");
        $statement->execute([$slug]);
        return $statement->fetch() ?: null;
    }

    public function posts(int $limit = 9, int $offset = 0, ?string $category = null): array
    {
        $params = [];
        $join = '';
        $where = "bp.status='published' AND bp.robots_index=1 AND bp.published_at <= NOW() AND bp.deleted_at IS NULL";
        if ($category) {
            $join = ' JOIN blog_post_categories bpcf ON bpcf.post_id=bp.id JOIN blog_categories bcf ON bcf.id=bpcf.category_id ';
            $where .= ' AND bcf.slug=:category';
            $params['category'] = $category;
        }
        $sql = "SELECT DISTINCT bp.id,bp.title,bp.slug,bp.excerpt,bp.published_at,bp.updated_at,COALESCE(m.path,'/assets/images/brand-board-reference.png') image_path,(SELECT bc.name FROM blog_post_categories bpc JOIN blog_categories bc ON bc.id=bpc.category_id WHERE bpc.post_id=bp.id ORDER BY bpc.is_primary DESC LIMIT 1) category_name FROM blog_posts bp {$join} LEFT JOIN media_assets m ON m.id=bp.featured_image_id WHERE {$where} ORDER BY bp.published_at DESC LIMIT :limit OFFSET :offset";
        $statement = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) { $statement->bindValue(':' . $key, $value); }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function post(string $slug): ?array
    {
        $statement = Database::connection()->prepare("SELECT bp.*,COALESCE(m.path,'/assets/images/brand-board-reference.png') image_path,CONCAT(COALESCE(u.first_name,'Maison'),' ',COALESCE(u.last_name,'Bébé')) author_name FROM blog_posts bp LEFT JOIN media_assets m ON m.id=bp.featured_image_id LEFT JOIN users u ON u.id=bp.author_user_id WHERE bp.slug=? AND bp.status='published' AND bp.published_at<=NOW() AND bp.deleted_at IS NULL LIMIT 1");
        $statement->execute([$slug]);
        $post = $statement->fetch();
        if (!$post) { return null; }
        $post['categories'] = $this->postCategories((int) $post['id']);
        $post['products'] = $this->postProducts((int) $post['id']);
        $post['reading_minutes'] = max(1, (int) ceil(str_word_count(strip_tags((string) $post['content_html'])) / 200));
        return $post;
    }

    public function blogCategories(): array
    {
        return Database::connection()->query("SELECT bc.*,(SELECT COUNT(*) FROM blog_post_categories bpc JOIN blog_posts bp ON bp.id=bpc.post_id WHERE bpc.category_id=bc.id AND bp.status='published') post_count FROM blog_categories bc WHERE bc.is_active=1 ORDER BY bc.name")->fetchAll();
    }

    private function postCategories(int $postId): array
    {
        $statement = Database::connection()->prepare('SELECT bc.* FROM blog_post_categories bpc JOIN blog_categories bc ON bc.id=bpc.category_id WHERE bpc.post_id=? ORDER BY bpc.is_primary DESC,bc.name');
        $statement->execute([$postId]);
        return $statement->fetchAll();
    }

    private function postProducts(int $postId): array
    {
        $statement = Database::connection()->prepare("SELECT p.id,p.name,p.slug,COALESCE(v.price_minor,0) price_minor,COALESCE(m.path,'/assets/images/packaging-reference.png') image_path FROM blog_post_products bpp JOIN products p ON p.id=bpp.product_id LEFT JOIN (SELECT product_id,MIN(price_minor) price_minor FROM product_variants WHERE is_active=1 GROUP BY product_id) v ON v.product_id=p.id LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id WHERE bpp.post_id=? AND p.status='active' ORDER BY bpp.sort_order");
        $statement->execute([$postId]);
        return $statement->fetchAll();
    }
}

