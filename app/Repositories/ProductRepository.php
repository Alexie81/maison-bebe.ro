<?php
declare(strict_types=1);

namespace MaisonBebe\Repositories;

use MaisonBebe\Core\Database;
use PDO;

final class ProductRepository
{
    public function featured(int $limit = 4): array
    {
        return $this->catalog(['featured' => 1], $limit, 0)['items'];
    }

    public function catalog(array $filters = [], int $limit = 12, int $offset = 0): array
    {
        $where = ["p.status = 'active'", 'p.deleted_at IS NULL'];
        $params = [];
        $joins = '';

        if (!empty($filters['query'])) {
            $terms = preg_split('/\s+/u', trim((string) $filters['query']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $expressions = [
                'p.name', 'p.sku', "COALESCE(p.brand,'')", "COALESCE(p.material,'')",
                "COALESCE(p.short_description,'')", "COALESCE(p.description_html,'')",
                "EXISTS (SELECT 1 FROM product_categories qpc JOIN categories qc ON qc.id=qpc.category_id WHERE qpc.product_id=p.id AND qc.deleted_at IS NULL AND CONCAT_WS(' ',qc.name,qc.description) LIKE %s)",
                "EXISTS (SELECT 1 FROM collection_products qcp JOIN collections qco ON qco.id=qcp.collection_id WHERE qcp.product_id=p.id AND qco.deleted_at IS NULL AND CONCAT_WS(' ',qco.name,qco.description) LIKE %s)",
                "EXISTS (SELECT 1 FROM product_variants qv LEFT JOIN variant_option_values qvov ON qvov.variant_id=qv.id LEFT JOIN product_option_values qov ON qov.id=qvov.option_value_id WHERE qv.product_id=p.id AND qv.is_active=1 AND CONCAT_WS(' ',qv.sku,qov.value) LIKE %s)",
            ];
            foreach (array_slice($terms, 0, 6) as $index => $term) {
                $parts = [];
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $term) . '%';
                foreach ($expressions as $fieldIndex => $expression) {
                    $key = 'query_' . $index . '_' . $fieldIndex;
                    $placeholder = ':' . $key;
                    $parts[] = str_contains($expression, '%s') ? sprintf($expression, $placeholder) : $expression . ' LIKE ' . $placeholder;
                    $params[$key] = $like;
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        if (!empty($filters['category'])) {
            $joins .= ' JOIN product_categories pcf ON pcf.product_id = p.id JOIN categories cf ON cf.id = pcf.category_id ';
            $where[] = 'cf.slug = :category AND cf.is_active = 1 AND cf.deleted_at IS NULL';
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['collection'])) {
            $joins .= ' JOIN collection_products cpf ON cpf.product_id = p.id JOIN collections cof ON cof.id = cpf.collection_id ';
            $where[] = 'cof.slug = :collection AND cof.is_active = 1 AND cof.deleted_at IS NULL';
            $params['collection'] = $filters['collection'];
        }
        if (!empty($filters['material'])) {
            $where[] = 'p.material = :material';
            $params['material'] = $filters['material'];
        }
        if (!empty($filters['stock'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants sv WHERE sv.product_id=p.id AND sv.is_active=1 AND sv.stock_qty > 0)';
        }
        if (!empty($filters['featured'])) {
            $where[] = 'p.is_featured = 1';
        }
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $where[] = 'COALESCE(v.price_minor,0) >= :min_price';
            $params['min_price'] = (int) $filters['min_price'];
        }
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $where[] = 'COALESCE(v.price_minor,0) <= :max_price';
            $params['max_price'] = (int) $filters['max_price'];
        }

        $order = match ($filters['sort'] ?? '') {
            'newest' => 'p.published_at DESC, p.id DESC',
            'price_asc' => 'price_minor ASC, p.id DESC',
            'price_desc' => 'price_minor DESC, p.id DESC',
            default => 'p.is_featured DESC, p.published_at DESC, p.id DESC',
        };

        $base = "FROM products p {$joins}
            LEFT JOIN categories primary_category ON primary_category.id=p.primary_category_id
            LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
            LEFT JOIN media_assets m ON m.id=pi.media_id
            LEFT JOIN (
                SELECT product_id, MIN(id) default_variant_id, COUNT(*) variant_count, MIN(price_minor) price_minor, MAX(compare_at_price_minor) compare_at_price_minor, SUM(stock_qty) stock_qty
                FROM product_variants WHERE is_active=1 GROUP BY product_id
            ) v ON v.product_id=p.id
            WHERE " . implode(' AND ', $where);

        $count = Database::connection()->prepare("SELECT COUNT(DISTINCT p.id) {$base}");
        $count->execute($params);

        $sql = "SELECT DISTINCT p.id,p.name,p.slug,p.short_description,p.material,p.is_featured,p.is_gift_box,
                       COALESCE(v.price_minor,0) price_minor,v.compare_at_price_minor,COALESCE(v.stock_qty,0) stock_qty,v.default_variant_id,COALESCE(v.variant_count,0) variant_count,primary_category.name category_name,
                       COALESCE(m.path,'/assets/images/packaging-reference.png') image_path,
                       COALESCE(pi.alt_text,p.name) image_alt
                {$base} ORDER BY {$order} LIMIT :limit OFFSET :offset";
        $statement = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT p.*,c.name category_name,c.slug category_slug,m.path og_image_path,
                       COALESCE(rv.min_price,0) min_price,rv.max_price,rv.total_stock,
                       COALESCE(ri.path,'/assets/images/packaging-reference.png') primary_image
                FROM products p
                LEFT JOIN categories c ON c.id=p.primary_category_id
                LEFT JOIN media_assets m ON m.id=p.og_image_id
                LEFT JOIN (SELECT product_id,MIN(price_minor) min_price,MAX(price_minor) max_price,SUM(stock_qty) total_stock FROM product_variants WHERE is_active=1 GROUP BY product_id) rv ON rv.product_id=p.id
                LEFT JOIN categories primary_category ON primary_category.id=p.primary_category_id
            LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
                LEFT JOIN media_assets ri ON ri.id=pi.media_id
                WHERE p.slug=? AND p.status='active' AND p.deleted_at IS NULL LIMIT 1";
        $statement = Database::connection()->prepare($sql);
        $statement->execute([$slug]);
        $product = $statement->fetch();
        if (!$product) {
            return null;
        }
        $product['images'] = $this->images((int) $product['id']);
        $product['variants'] = $this->variants((int) $product['id']);
        $product['options'] = $this->options((int) $product['id']);
        $product['categories'] = $this->productCategories((int) $product['id']);
        $product['reviews'] = $this->reviews((int) $product['id']);
        return $product;
    }

    public function category(string $slug): ?array
    {
        $statement = Database::connection()->prepare("SELECT c.*,m.path image_path FROM categories c LEFT JOIN media_assets m ON m.id=c.image_id WHERE c.slug=? AND c.is_active=1 AND c.deleted_at IS NULL LIMIT 1");
        $statement->execute([$slug]);
        return $statement->fetch() ?: null;
    }

    public function collection(string $slug): ?array
    {
        $statement = Database::connection()->prepare("SELECT c.*,m.path image_path FROM collections c LEFT JOIN media_assets m ON m.id=c.image_id WHERE c.slug=? AND c.is_active=1 AND c.deleted_at IS NULL LIMIT 1");
        $statement->execute([$slug]);
        return $statement->fetch() ?: null;
    }

    public function categories(bool $featuredOnly = false, ?int $limit = null): array
    {
        $sql = "SELECT c.*,m.path image_path,(SELECT COUNT(*) FROM product_categories pc JOIN products p ON p.id=pc.product_id WHERE pc.category_id=c.id AND p.status='active' AND p.deleted_at IS NULL) product_count FROM categories c LEFT JOIN media_assets m ON m.id=c.image_id WHERE c.is_active=1 AND c.deleted_at IS NULL";
        if ($featuredOnly) {
            $sql .= ' AND c.is_featured=1';
        }
        $sql .= ' ORDER BY c.sort_order,c.name';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return Database::connection()->query($sql)->fetchAll();
    }

    public function collections(): array
    {
        return Database::connection()->query("SELECT c.*,m.path image_path FROM collections c LEFT JOIN media_assets m ON m.id=c.image_id WHERE c.is_active=1 AND c.deleted_at IS NULL ORDER BY c.is_featured DESC,c.name")->fetchAll();
    }

    public function materials(): array
    {
        return Database::connection()->query("SELECT DISTINCT material FROM products WHERE status='active' AND deleted_at IS NULL AND material IS NOT NULL AND TRIM(material) <> '' ORDER BY material")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function search(string $query, int $limit = 6): array
    {
        return $this->catalog(['query' => trim($query)], $limit, 0)['items'];
    }
    public function related(int $productId, ?int $categoryId, int $limit = 4): array
    {
        $related = [];
        if ($categoryId) {
            $categoryItems = $this->catalog(['category' => $this->categorySlugById($categoryId)], $limit + 1, 0)['items'];
            foreach ($categoryItems as $item) {
                if ((int) $item['id'] !== $productId) {
                    $related[(int) $item['id']] = $item;
                }
            }
        }
        if (count($related) < $limit) {
            foreach ($this->catalog([], $limit + 8, 0)['items'] as $item) {
                if ((int) $item['id'] !== $productId) {
                    $related[(int) $item['id']] = $item;
                }
                if (count($related) >= $limit) {
                    break;
                }
            }
        }
        return array_slice(array_values($related), 0, $limit);
    }

    private function categorySlugById(int $categoryId): string
    {
        $statement = Database::connection()->prepare('SELECT slug FROM categories WHERE id=?');
        $statement->execute([$categoryId]);
        return (string) $statement->fetchColumn();
    }

    private function images(int $productId): array
    {
        $statement = Database::connection()->prepare('SELECT pi.*,m.path,m.width,m.height FROM product_images pi JOIN media_assets m ON m.id=pi.media_id WHERE pi.product_id=? ORDER BY pi.sort_order');
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    private function variants(int $productId): array
    {
        $statement = Database::connection()->prepare("SELECT v.*,GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / ') option_label,GROUP_CONCAT(ov.id ORDER BY po.sort_order) option_value_ids FROM product_variants v LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id WHERE v.product_id=? AND v.is_active=1 GROUP BY v.id ORDER BY v.id");
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    private function options(int $productId): array
    {
        $statement = Database::connection()->prepare('SELECT po.id option_id,po.name,ov.id value_id,ov.value,ov.swatch FROM product_options po JOIN product_option_values ov ON ov.option_id=po.id WHERE po.product_id=? ORDER BY po.sort_order,ov.sort_order');
        $statement->execute([$productId]);
        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $grouped[$row['option_id']]['name'] = $row['name'];
            $grouped[$row['option_id']]['values'][] = $row;
        }
        return array_values($grouped);
    }

    private function productCategories(int $productId): array
    {
        $statement = Database::connection()->prepare('SELECT c.id,c.name,c.slug,pc.is_primary FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=? ORDER BY pc.is_primary DESC,c.name');
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    private function reviews(int $productId): array
    {
        $statement = Database::connection()->prepare("SELECT r.rating,r.title,r.body,r.is_verified_purchase,r.admin_reply,r.created_at,CONCAT(COALESCE(u.first_name,'Client'),' ',LEFT(COALESCE(u.last_name,''),1),'.') author FROM reviews r LEFT JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.status='approved' ORDER BY r.created_at DESC LIMIT 20");
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }
}

