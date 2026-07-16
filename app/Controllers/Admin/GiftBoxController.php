<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\UploadService;
use PDO;

final class GiftBoxController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + [
            'adminUser' => Auth::user(),
            'notice' => Session::flash('admin_notice'),
            'error' => Session::flash('admin_error'),
        ], 'layouts/admin');
    }

    public function index(Request $request): string
    {
        $pdo = Database::connection();
        $setting = $this->setting();
        $boxes = $pdo->query("SELECT t.*,COALESCE(m.path,'/assets/images/giftbox-clean-v4.png') image_path,
                         COALESCE(v.price_minor,t.base_price_minor) price_minor,COALESCE(v.stock_qty,t.stock_qty,0) current_stock
                  FROM gift_box_templates t
                  LEFT JOIN media_assets m ON m.id=t.image_id
                  LEFT JOIN (
                    SELECT product_id,MIN(price_minor) price_minor,SUM(stock_qty) stock_qty
                    FROM product_variants WHERE is_active=1 GROUP BY product_id
                  ) v ON v.product_id=t.product_id
                  WHERE t.deleted_at IS NULL
                  ORDER BY t.sort_order,t.id")->fetchAll();
        return $this->admin('admin/gift-box-index', compact('setting', 'boxes'));
    }

    public function saveSettings(Request $request): never
    {
        $enabled = $request->input('enabled') ? true : false;
        Database::connection()->prepare("INSERT INTO settings (setting_key,value_json,updated_by) VALUES ('gift_box_configurator',?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_by=VALUES(updated_by)")
            ->execute([json_encode(['enabled' => $enabled], JSON_UNESCAPED_UNICODE), Auth::id()]);
        $this->audit('gift_box.settings.updated', null, ['enabled' => $enabled]);
        Session::flash('admin_notice', $enabled ? 'Configuratorul Gift Box este activ pe website.' : 'Configuratorul Gift Box a fost ascuns de pe website.');
        Response::redirect('/admin/gift-box');
    }

    public function form(Request $request, ?string $id = null): string
    {
        $pdo = Database::connection();
        $box = null;
        if ($id !== null) {
            $statement = $pdo->prepare("SELECT t.*,COALESCE(m.path,'/assets/images/giftbox-clean-v4.png') image_path,COALESCE(v.price_minor,t.base_price_minor) price_minor,COALESCE(v.stock_qty,t.stock_qty,0) current_stock
                FROM gift_box_templates t
                LEFT JOIN media_assets m ON m.id=t.image_id
                LEFT JOIN (SELECT product_id,MIN(price_minor) price_minor,SUM(stock_qty) stock_qty FROM product_variants WHERE is_active=1 GROUP BY product_id) v ON v.product_id=t.product_id
                WHERE t.id=? AND t.deleted_at IS NULL LIMIT 1");
            $statement->execute([(int) $id]);
            $box = $statement->fetch();
            if (!$box) {
                throw new HttpException(404, 'Cutia Gift Box nu există.');
            }
        }
        $categories = $pdo->query("SELECT id,name FROM categories WHERE deleted_at IS NULL AND is_active=1 ORDER BY name")->fetchAll();
        $collections = $pdo->query("SELECT id,name FROM collections WHERE deleted_at IS NULL AND is_active=1 ORDER BY name")->fetchAll();
        $products = $pdo->query("SELECT p.id,p.name,
            COALESCE((SELECT GROUP_CONCAT(pc.category_id ORDER BY pc.category_id) FROM product_categories pc WHERE pc.product_id=p.id),'') category_ids,
            COALESCE((SELECT GROUP_CONCAT(cp.collection_id ORDER BY cp.collection_id) FROM collection_products cp WHERE cp.product_id=p.id),'') collection_ids,
            COALESCE((SELECT ma.path FROM product_images pi JOIN media_assets ma ON ma.id=pi.media_id WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.sort_order,pi.id LIMIT 1),'/assets/images/packaging-reference.png') image_path
            FROM products p WHERE p.deleted_at IS NULL AND p.status='active' AND p.is_gift_box=0 ORDER BY p.name")->fetchAll();
        $rules = json_decode((string)($box['rules_json'] ?? ''), true) ?: [];
        $selectedProductIds = array_values(array_unique(array_map('intval',(array)($rules['product_ids'] ?? []))));
        $selectedCategoryIds = array_values(array_unique(array_map('intval',(array)($rules['category_ids'] ?? []))));
        $selectedCollectionIds = array_values(array_unique(array_map('intval',(array)($rules['collection_ids'] ?? []))));
        return $this->admin('admin/gift-box-form', compact('box','categories','collections','products','selectedProductIds','selectedCategoryIds','selectedCollectionIds'));
    }

    public function save(Request $request, ?string $id = null): never
    {
        $name = trim((string) $request->input('name', ''));
        $slug = $this->slug((string) $request->input('slug', $name));
        $description = trim((string) $request->input('description', ''));
        $price = max(0, (int) round(((float) $request->input('price', 0)) * 100));
        $stock = max(0, (int) $request->input('stock_qty', 0));
        $min = max(0, (int) $request->input('min_components', 1));
        $max = max($min, (int) $request->input('max_components', 6));
        $sort = (int) $request->input('sort_order', 0);
        $active = $request->input('is_active') ? 1 : 0;
        $productIds = array_values(array_unique(array_filter(array_map('intval',(array)$request->input('product_ids',[])))));
        $categoryIds = array_values(array_unique(array_filter(array_map('intval',(array)$request->input('category_ids',[])))));
        $collectionIds = array_values(array_unique(array_filter(array_map('intval',(array)$request->input('collection_ids',[])))));
        $rulesJson = json_encode(['catalog_scope'=>true,'product_ids'=>$productIds,'category_ids'=>$categoryIds,'collection_ids'=>$collectionIds], JSON_UNESCAPED_UNICODE);

        if ($name === '' || $slug === '') {
            throw new HttpException(422, 'Completează numele și slugul cutiei.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $existing = null;
            if ($id !== null) {
                $statement = $pdo->prepare('SELECT * FROM gift_box_templates WHERE id=? AND deleted_at IS NULL FOR UPDATE');
                $statement->execute([(int) $id]);
                $existing = $statement->fetch();
                if (!$existing) {
                    throw new HttpException(404, 'Cutia Gift Box nu există.');
                }
            }

            $imageId = (new UploadService())->image('image', $name) ?: (int) ($existing['image_id'] ?? 0) ?: null;

            if ($existing) {
                $templateId = (int) $existing['id'];
                $productId = (int) ($existing['product_id'] ?? 0);
                $pdo->prepare('UPDATE gift_box_templates SET image_id=?,name=?,slug=?,description=?,base_price_minor=?,stock_qty=?,min_components=?,max_components=?,rules_json=?,is_active=?,sort_order=?,updated_at=NOW() WHERE id=?')
                    ->execute([$imageId, $name, $slug, $description ?: null, $price, $stock, $min, $max, $rulesJson, $active, $sort, $templateId]);
            } else {
                $pdo->prepare('INSERT INTO gift_box_templates (image_id,name,slug,description,base_price_minor,stock_qty,min_components,max_components,rules_json,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$imageId, $name, $slug, $description ?: null, $price, $stock, $min, $max, $rulesJson, $active, $sort]);
                $templateId = (int) $pdo->lastInsertId();
                $productId = 0;
            }

            $productSlug = 'gift-box-cutie-' . $templateId . '-' . $slug;
            $productSku = 'GBBOX-' . $templateId;
            $variantSku = 'GBBOX-' . $templateId . '-STD';
            if ($productId > 0) {
                $pdo->prepare("UPDATE products SET name=?,slug=?,sku=?,short_description=?,status='active',is_gift_box=1,robots_index=0,include_sitemap=0,deleted_at=NOW(),updated_at=NOW() WHERE id=?")
                    ->execute([$name, $productSlug, $productSku, $description ?: null, $productId]);
            } else {
                $pdo->prepare("INSERT INTO products (primary_category_id,name,slug,sku,short_description,status,is_gift_box,robots_index,include_sitemap,published_at,deleted_at) VALUES (NULL,?,?,?,?, 'active',1,0,0,NOW(),NOW())")
                    ->execute([$name, $productSlug, $productSku, $description ?: null]);
                $productId = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE gift_box_templates SET product_id=? WHERE id=?')->execute([$productId, $templateId]);
            }

            $variant = $pdo->prepare('SELECT id FROM product_variants WHERE product_id=? ORDER BY id LIMIT 1');
            $variant->execute([$productId]);
            $variantId = (int) $variant->fetchColumn();
            if ($variantId) {
                $pdo->prepare('UPDATE product_variants SET sku=?,price_minor=?,stock_qty=?,is_active=1,updated_at=NOW() WHERE id=?')
                    ->execute([$variantSku, $price, $stock, $variantId]);
            } else {
                $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,is_active) VALUES (?,?,?,?,1)')
                    ->execute([$productId, $variantSku, $price, $stock]);
            }

            if ($imageId) {
                $pdo->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$productId]);
                $pdo->prepare('INSERT INTO product_images (product_id,media_id,alt_text,sort_order,is_primary) VALUES (?,?,?,0,1) ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text),is_primary=1')
                    ->execute([$productId, $imageId, $name]);
            }

            $this->audit('gift_box.box.saved', $templateId, ['name' => $name, 'active' => $active]);
            $pdo->commit();
            Session::flash('admin_notice', 'Cutia Gift Box a fost salvată.');
            Response::redirect('/admin/gift-box');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(Request $request, string $id): never
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT * FROM gift_box_templates WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $statement->execute([(int) $id]);
            $box = $statement->fetch();
            if (!$box) {
                throw new HttpException(404, 'Cutia Gift Box nu există.');
            }
            $pdo->prepare('UPDATE gift_box_templates SET is_active=0,deleted_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $id]);
            if (!empty($box['product_id'])) {
                $pdo->prepare("UPDATE products SET status='archived',deleted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int) $box['product_id']]);
                $pdo->prepare('UPDATE product_variants SET is_active=0,updated_at=NOW() WHERE product_id=?')->execute([(int) $box['product_id']]);
            }
            $this->audit('gift_box.box.deleted', (int) $id, ['name' => $box['name']]);
            $pdo->commit();
            Session::flash('admin_notice', 'Cutia Gift Box a fost ștearsă.');
            Response::redirect('/admin/gift-box');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function setting(): array
    {
        $statement = Database::connection()->prepare("SELECT value_json FROM settings WHERE setting_key='gift_box_configurator' LIMIT 1");
        $statement->execute();
        $value = $statement->fetchColumn();
        return $value === false ? ['enabled' => true] : (json_decode((string) $value, true) ?: ['enabled' => true]);
    }

    private function audit(string $action, ?int $targetId, array $metadata): void
    {
        Database::connection()->prepare('INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address,metadata_json) VALUES (?,?,?,?,?,?)')
            ->execute([Auth::id(), $action, 'gift_box', $targetId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($metadata, JSON_UNESCAPED_UNICODE)]);
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $map = ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t'];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
