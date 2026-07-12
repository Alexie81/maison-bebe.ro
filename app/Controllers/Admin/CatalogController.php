<?php
declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HtmlSanitizer;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\NewsletterService;
use MaisonBebe\Services\StripeService;
use MaisonBebe\Services\UploadService;
use PDO;

final class CatalogController
{
    private function admin(string $view,array $data=[]):string{return view($view,$data+['adminUser'=>Auth::user(),'notice'=>Session::flash('admin_notice'),'error'=>Session::flash('admin_error')],'layouts/admin');}
    public function products(Request $request): string
    {
        $pdo = Database::connection();
        $items = $pdo->query("SELECT p.*,c.name category_name,COALESCE(v.price_minor,0) price_minor,COALESCE(v.stock_qty,0) stock_qty,COALESCE(m.path,'/assets/images/packaging-reference.png') image_path FROM products p LEFT JOIN categories c ON c.id=p.primary_category_id LEFT JOIN (SELECT product_id,MIN(price_minor) price_minor,SUM(stock_qty) stock_qty FROM product_variants GROUP BY product_id)v ON v.product_id=p.id LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id WHERE p.deleted_at IS NULL ORDER BY p.updated_at DESC")->fetchAll();
        $productLimit = 500;
        $productCount = count($items);
        $productLimitReached = $productCount >= $productLimit;
        return $this->admin('admin/products', compact('items','productCount','productLimit','productLimitReached'));
    }
    public function editorImage(Request $request): never
    {
        $mediaId = (new UploadService())->image('image', 'Imagine descriere produs');
        if (!$mediaId) {
            throw new HttpException(422, 'Selectează o imagine pentru încărcare.');
        }
        $statement = Database::connection()->prepare('SELECT path,width,height FROM media_assets WHERE id=?');
        $statement->execute([$mediaId]);
        $image = $statement->fetch();
        Response::json(['ok'=>true,'path'=>$image['path'],'url'=>url($image['path']),'width'=>(int)$image['width'],'height'=>(int)$image['height']]);
    }
    public function productForm(Request $request, ?string $id = null): string
    {
        $product = null;
        $variants = [];
        $selected = [];
        $options = [];
        $images = [];
        $pdo = Database::connection();
        if (!$id && (int) $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn() >= 500) {
            Session::flash('admin_error', 'Ai atins limita de 500 de produse. Arhivează sau șterge un produs înainte de a adăuga altul.');
            Response::redirect('/admin/produse');
        }

        if ($id) {
            $statement = $pdo->prepare('SELECT * FROM products WHERE id=? AND deleted_at IS NULL');
            $statement->execute([(int) $id]);
            $product = $statement->fetch();
            if (!$product) {
                throw new HttpException(404, 'Produsul nu există.');
            }

            $variantStatement = $pdo->prepare("SELECT v.*,GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / ') option_label FROM product_variants v LEFT JOIN variant_option_values vov ON vov.variant_id=v.id LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id WHERE v.product_id=? AND v.is_active=1 GROUP BY v.id ORDER BY v.id");
            $variantStatement->execute([(int) $id]);
            $variants = $variantStatement->fetchAll();

            $mapStatement = $pdo->prepare('SELECT vov.variant_id,po.name,ov.value FROM variant_option_values vov JOIN product_option_values ov ON ov.id=vov.option_value_id JOIN product_options po ON po.id=ov.option_id JOIN product_variants v ON v.id=vov.variant_id WHERE v.product_id=? ORDER BY po.sort_order,ov.sort_order');
            $mapStatement->execute([(int) $id]);
            $variantMap = [];
            foreach ($mapStatement->fetchAll() as $mapping) {
                $variantMap[(int) $mapping['variant_id']][(string) $mapping['name']] = (string) $mapping['value'];
            }
            foreach ($variants as &$variant) {
                $variant['options_map'] = $variantMap[(int) $variant['id']] ?? [];
            }
            unset($variant);

            $optionStatement = $pdo->prepare('SELECT po.id option_id,po.name,po.sort_order,ov.id value_id,ov.value,ov.sort_order value_sort FROM product_options po LEFT JOIN product_option_values ov ON ov.option_id=po.id WHERE po.product_id=? ORDER BY po.sort_order,ov.sort_order,ov.id');
            $optionStatement->execute([(int) $id]);
            $grouped = [];
            foreach ($optionStatement->fetchAll() as $row) {
                $key = (int) $row['option_id'];
                $grouped[$key] ??= ['id'=>$key,'name'=>$row['name'],'values'=>[]];
                if ($row['value_id']) {
                    $grouped[$key]['values'][] = $row['value'];
                }
            }
            $options = array_values($grouped);

            $imageStatement = $pdo->prepare('SELECT pi.id,pi.media_id,pi.alt_text,pi.sort_order,pi.is_primary,m.path FROM product_images pi JOIN media_assets m ON m.id=pi.media_id WHERE pi.product_id=? ORDER BY pi.sort_order,pi.id');
            $imageStatement->execute([(int) $id]);
            $images = $imageStatement->fetchAll();

            $categoryStatement = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id=?');
            $categoryStatement->execute([(int) $id]);
            $selected = $categoryStatement->fetchAll(PDO::FETCH_COLUMN);
        }

        $categories = $pdo->query('SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order,name')->fetchAll();
        return $this->admin('admin/product-form', compact('product','variants','selected','categories','options','images'));
    }
    public function saveProduct(Request $request, ?string $id = null): never
    {
        $name = trim((string) $request->input('name', ''));
        $slug = $this->slug((string) $request->input('slug', $name));
        $status = (string) $request->input('status', 'draft');
        $isGiftBox = $request->input('is_gift_box') ? 1 : 0;
        $categories = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('categories', [])))));
        $primary = (int) $request->input('primary_category_id', 0);
        $pdo = Database::connection();
        $newCategoryName=trim((string)$request->input('new_category_name',''));
        if($newCategoryName!==''){$newCategorySlug=$this->slug($newCategoryName);$categoryLookup=$pdo->prepare('SELECT id FROM categories WHERE slug=? AND deleted_at IS NULL LIMIT 1');$categoryLookup->execute([$newCategorySlug]);$newCategoryId=(int)$categoryLookup->fetchColumn();if(!$newCategoryId){$pdo->prepare('INSERT INTO categories (name,slug,description,is_active,is_featured,show_in_menu,sort_order) VALUES (?,?,NULL,1,0,1,999)')->execute([$newCategoryName,$newCategorySlug]);$newCategoryId=(int)$pdo->lastInsertId();}$categories[]=$newCategoryId;$categories=array_values(array_unique($categories));if($request->input('new_category_primary')||$primary===0)$primary=$newCategoryId;}
        if ($name === '' || $slug === '' || !in_array($status, ['draft','active','archived'], true) || !$categories || !in_array($primary, $categories, true)) {throw new HttpException(422, 'Alege cel puțin o categorie și categoria principală sau creează una nouă în acest formular.');}

        if (!$id && (int) $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn() >= 500) {
            throw new HttpException(422, 'Limita maximă de 500 de produse a fost atinsă.');
        }
        (new NewsletterService())->ensureSchema($pdo);
        $pdo->beginTransaction();
        try {
            if ($id) {
                $old = $pdo->prepare('SELECT slug,sku,status FROM products WHERE id=? FOR UPDATE');
                $old->execute([(int) $id]);
                $existing = $old->fetch();
                if (!$existing) {
                    throw new HttpException(404, 'Produsul nu există.');
                }
                $oldSlug = (string) $existing['slug'];
                $sku = (string) $existing['sku'];
                $notifyNewsletter = $status === 'active' && ($existing['status'] ?? '') !== 'active';
                $pdo->prepare('UPDATE products SET primary_category_id=?,name=?,slug=?,material=?,short_description=?,description_html=?,care_html=?,shipping_html=?,gift_wrap_html=?,status=?,is_featured=?,is_gift_box=?,robots_index=?,include_sitemap=?,seo_title=?,seo_description=?,published_at=IF(?=\'active\',COALESCE(published_at,NOW()),published_at),updated_at=NOW() WHERE id=?')
                    ->execute([$primary,$name,$slug,$request->input('material'),$request->input('short_description'),HtmlSanitizer::clean((string) $request->input('description_html','')),HtmlSanitizer::clean((string) $request->input('care_html','')),HtmlSanitizer::clean((string) $request->input('shipping_html','')),HtmlSanitizer::clean((string) $request->input('gift_wrap_html','')),$status,$request->input('is_featured')?1:0,$isGiftBox,$request->input('robots_index')?1:0,$request->input('include_sitemap')?1:0,$request->input('seo_title'),$request->input('seo_description'),$status,(int) $id]);
                if ($oldSlug !== $slug) {
                    $pdo->prepare("INSERT INTO url_redirects (source_path,target_path,http_status,reason,entity_type,entity_id) VALUES (?,?,301,'Schimbare slug produs','product',?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path),http_status=301")
                        ->execute(['/produs/'.$oldSlug,'/produs/'.$slug,(int) $id]);
                }
            } else {
                $sku = $this->uniqueSku($pdo, 'MB-' . strtoupper(substr($slug, 0, 70)));
                $pdo->prepare('INSERT INTO products (primary_category_id,name,slug,sku,material,short_description,description_html,care_html,shipping_html,gift_wrap_html,status,is_featured,is_gift_box,robots_index,include_sitemap,seo_title,seo_description,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?=\'active\',NOW(),NULL))')
                    ->execute([$primary,$name,$slug,$sku,$request->input('material'),$request->input('short_description'),HtmlSanitizer::clean((string) $request->input('description_html','')),HtmlSanitizer::clean((string) $request->input('care_html','')),HtmlSanitizer::clean((string) $request->input('shipping_html','')),HtmlSanitizer::clean((string) $request->input('gift_wrap_html','')),$status,$request->input('is_featured')?1:0,$isGiftBox,$request->input('robots_index')?1:0,$request->input('include_sitemap')?1:0,$request->input('seo_title'),$request->input('seo_description'),$status]);
                $id = (string) $pdo->lastInsertId();
                $notifyNewsletter = $status === 'active';
            }
            $productId = (int) $id;

            $pdo->prepare('DELETE FROM product_categories WHERE product_id=?')->execute([$productId]);
            $categoryInsert = $pdo->prepare('INSERT INTO product_categories (product_id,category_id,is_primary) VALUES (?,?,?)');
            foreach ($categories as $categoryId) {
                $categoryInsert->execute([$productId,$categoryId,$categoryId === $primary ? 1 : 0]);
            }

            $optionNames = (array) $request->input('option_name', []);
            $optionValuesJson = (array) $request->input('option_values_json', []);
            $legacyOptionValues = (array) $request->input('option_values', []);
            $groups = [];
            foreach ($optionNames as $index => $optionName) {
                $optionName = trim((string) $optionName);
                if ($optionName === '') {
                    continue;
                }
                $decodedValues = json_decode((string) ($optionValuesJson[$index] ?? '[]'), true);
                $values = is_array($decodedValues) ? $decodedValues : (preg_split('/\s*[,;\n]\s*/u', trim((string) ($legacyOptionValues[$index] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: []);
                $values = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $values))));
                if (!$values) {
                    throw new HttpException(422, 'Adaugă cel puțin o valoare pentru opțiunea „'.$optionName.'”.');
                }
                $groups[$optionName] = $values;
            }

            $pdo->prepare('DELETE vov FROM variant_option_values vov JOIN product_variants v ON v.id=vov.variant_id WHERE v.product_id=?')->execute([$productId]);
            $pdo->prepare('DELETE FROM product_options WHERE product_id=?')->execute([$productId]);
            $valueIds = [];
            $optionInsert = $pdo->prepare('INSERT INTO product_options (product_id,name,sort_order) VALUES (?,?,?)');
            $valueInsert = $pdo->prepare('INSERT INTO product_option_values (option_id,value,sort_order) VALUES (?,?,?)');
            foreach ($groups as $groupIndex => $values) {
                $groupName = (string) $groupIndex;
                $optionInsert->execute([$productId,$groupName,array_search($groupName,array_keys($groups),true) * 10]);
                $optionId = (int) $pdo->lastInsertId();
                foreach ($values as $valueIndex => $value) {
                    $valueInsert->execute([$optionId,$value,$valueIndex * 10]);
                    $valueIds[mb_strtolower($groupName).'|'.mb_strtolower($value)] = (int) $pdo->lastInsertId();
                }
            }

            $variantIds = (array) $request->input('variant_id', []);
            $prices = (array) $request->input('variant_price', []);
            $stocks = (array) $request->input('variant_stock', []);
            $variantOptions = (array) $request->input('variant_options_json', []);
            if (!$prices) {
                throw new HttpException(422, 'Adaugă cel puțin o variantă cu preț și stoc.');
            }
            $pdo->prepare('UPDATE product_variants SET is_active=0,updated_at=NOW() WHERE product_id=?')->execute([$productId]);
            $mappingInsert = $pdo->prepare('INSERT INTO variant_option_values (variant_id,option_value_id) VALUES (?,?)');
            foreach ($prices as $index => $rawPrice) {
                $price = (int) round(((float) str_replace(',', '.', (string) $rawPrice)) * 100);
                $stock = max(0, (int) ($stocks[$index] ?? 0));
                if ($price < 0) {
                    throw new HttpException(422, 'Prețul variantei nu poate fi negativ.');
                }
                $variantId = (int) ($variantIds[$index] ?? 0);
                if ($variantId) {
                    $variantStatement = $pdo->prepare('SELECT sku FROM product_variants WHERE id=? AND product_id=?');
                    $variantStatement->execute([$variantId,$productId]);
                    $variantSku = $variantStatement->fetchColumn();
                    if (!$variantSku) {
                        throw new HttpException(422, 'Una dintre variante nu aparține acestui produs.');
                    }
                    $pdo->prepare('UPDATE product_variants SET price_minor=?,stock_qty=?,is_active=1,updated_at=NOW() WHERE id=? AND product_id=?')->execute([$price,$stock,$variantId,$productId]);
                } else {
                    $variantSku = $this->uniqueSku($pdo, $sku . '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT));
                    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,is_active) VALUES (?,?,?,?,1)')->execute([$productId,$variantSku,$price,$stock]);
                    $variantId = (int) $pdo->lastInsertId();
                }

                $chosen = json_decode((string) ($variantOptions[$index] ?? '{}'), true) ?: [];
                foreach ($groups as $groupName => $values) {
                    $chosenValue = trim((string) ($chosen[$groupName] ?? ''));
                    $lookup = mb_strtolower($groupName).'|'.mb_strtolower($chosenValue);
                    if ($chosenValue === '' || !isset($valueIds[$lookup])) {
                        throw new HttpException(422, 'Selectează „'.$groupName.'” pentru fiecare variantă.');
                    }
                    $mappingInsert->execute([$variantId,$valueIds[$lookup]]);
                }
            }

            $deleteImageIds = array_values(array_filter(array_map('intval', (array) $request->input('delete_image_ids', []))));
            if ($deleteImageIds) {
                $placeholders = implode(',', array_fill(0, count($deleteImageIds), '?'));
                $pdo->prepare("DELETE FROM product_images WHERE product_id=? AND id IN ({$placeholders})")->execute(array_merge([$productId], $deleteImageIds));
            }
            $uploadedMedia = (new UploadService())->images('images', $name, 12);
            $newImageIds = [];
            $imageInsert = $pdo->prepare('INSERT INTO product_images (product_id,media_id,alt_text,sort_order,is_primary) VALUES (?,?,?,0,0)');
            foreach ($uploadedMedia as $newIndex => $mediaId) {
                $imageInsert->execute([$productId,$mediaId,$name]);
                $newImageIds[$newIndex] = (int) $pdo->lastInsertId();
            }
            $legacyMedia = (new UploadService())->image('image', $name);
            if ($legacyMedia) {
                $imageInsert->execute([$productId,$legacyMedia,$name]);
                $newImageIds[count($newImageIds)] = (int) $pdo->lastInsertId();
            }

            $existingImageStatement = $pdo->prepare('SELECT id FROM product_images WHERE product_id=? ORDER BY sort_order,id');
            $existingImageStatement->execute([$productId]);
            $availableImageIds = array_map('intval', $existingImageStatement->fetchAll(PDO::FETCH_COLUMN));
            $orderTokens = json_decode((string) $request->input('image_order_json', '[]'), true) ?: [];
            $orderedIds = [];
            foreach ($orderTokens as $token) {
                if (preg_match('/^existing:(\d+)$/', (string) $token, $match)) {
                    $candidate = (int) $match[1];
                } elseif (preg_match('/^new:(\d+)$/', (string) $token, $match)) {
                    $candidate = $newImageIds[(int) $match[1]] ?? 0;
                } else {
                    $candidate = 0;
                }
                if ($candidate && in_array($candidate, $availableImageIds, true) && !in_array($candidate, $orderedIds, true)) {
                    $orderedIds[] = $candidate;
                }
            }
            foreach ($availableImageIds as $candidate) {
                if (!in_array($candidate, $orderedIds, true)) {
                    $orderedIds[] = $candidate;
                }
            }
            $orderUpdate = $pdo->prepare('UPDATE product_images SET sort_order=?,is_primary=0 WHERE id=? AND product_id=?');
            foreach ($orderedIds as $sortIndex => $imageId) {
                $orderUpdate->execute([$sortIndex * 10,$imageId,$productId]);
            }
            $primaryToken = (string) $request->input('primary_image_token', '');
            $primaryImageId = 0;
            if (preg_match('/^existing:(\d+)$/', $primaryToken, $match)) {
                $primaryImageId = (int) $match[1];
            } elseif (preg_match('/^new:(\d+)$/', $primaryToken, $match)) {
                $primaryImageId = $newImageIds[(int) $match[1]] ?? 0;
            }
            if (!in_array($primaryImageId, $orderedIds, true)) {
                $primaryImageId = $orderedIds[0] ?? 0;
            }
            if ($primaryImageId) {
                $pdo->prepare('UPDATE product_images SET is_primary=1 WHERE id=? AND product_id=?')->execute([$primaryImageId,$productId]);
            }

            $priceStatement = $pdo->prepare('SELECT MIN(price_minor) FROM product_variants WHERE product_id=? AND is_active=1');
            $priceStatement->execute([$productId]);
            $boxPrice = (int) $priceStatement->fetchColumn();
            $imageStatement = $pdo->prepare('SELECT media_id FROM product_images WHERE product_id=? AND is_primary=1 ORDER BY id DESC LIMIT 1');
            $imageStatement->execute([$productId]);
            $boxImage = (int) $imageStatement->fetchColumn() ?: null;
            if ($isGiftBox) {
                $pdo->prepare("INSERT INTO gift_box_templates (product_id,image_id,name,slug,description,base_price_minor,min_components,max_components,is_active) VALUES (?,?,?,?,?,?,2,6,?) ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),image_id=COALESCE(VALUES(image_id),image_id),name=VALUES(name),description=VALUES(description),base_price_minor=VALUES(base_price_minor),is_active=VALUES(is_active),updated_at=NOW()")
                    ->execute([$productId,$boxImage,$name,$slug,$request->input('short_description'),$boxPrice,$status === 'active' ? 1 : 0]);
            } else {
                $pdo->prepare('UPDATE gift_box_templates SET is_active=0,updated_at=NOW() WHERE product_id=?')->execute([$productId]);
            }

            $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('product',?,?,JSON_OBJECT('slug',?),'pending',NOW())")->execute([$productId,$status === 'active' ? 'published' : 'updated',$slug]);
            $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'product.saved','product',?,?)")->execute([Auth::id(),$productId,$_SERVER['REMOTE_ADDR'] ?? null]);
            if ($notifyNewsletter) (new NewsletterService())->queueProduct($pdo, $productId);
            $pdo->commit();

            try {
                $stripe = (new StripeService())->syncProduct($productId);
                Session::flash('admin_notice', $stripe ? 'Produsul a fost salvat și sincronizat cu Stripe.' : 'Produsul a fost salvat.');
            } catch (\Throwable $stripeException) {
                error_log('Stripe product sync failed for product '.$productId.': '.$stripeException->getMessage());
                Session::flash('admin_error', 'Produsul a fost salvat, dar sincronizarea Stripe a eșuat: '.mb_substr($stripeException->getMessage(),0,180));
            }
            Response::redirect('/admin/produse/'.$productId.'/edit');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
    public function deleteProduct(Request $request,string $id):never
    {
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $statement=$pdo->prepare('SELECT id,name,slug FROM products WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $statement->execute([(int)$id]);
            $product=$statement->fetch();
            if(!$product){throw new HttpException(404,'Produsul nu existÃ„Æ’ sau a fost deja Ãˆâ„¢ters.');}
            $pdo->prepare("UPDATE products SET status='archived',robots_index=0,include_sitemap=0,deleted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$id]);
            $pdo->prepare('UPDATE product_variants SET is_active=0,updated_at=NOW() WHERE product_id=?')->execute([(int)$id]);
            $pdo->prepare("INSERT INTO url_redirects (source_path,target_path,http_status,reason,entity_type,entity_id) VALUES (?,?,301,'Produs Ãˆâ„¢ters din catalog','product',?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path),http_status=301,reason=VALUES(reason)")->execute(['/produs/'.$product['slug'],'/shop',(int)$id]);
            $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('product',?,'deleted',JSON_OBJECT('slug',?),'pending',NOW())")->execute([(int)$id,$product['slug']]);
            $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'product.deleted','product',?,?)")->execute([Auth::id(),(int)$id,$_SERVER['REMOTE_ADDR']??null]);
            $pdo->commit();
            try{(new StripeService())->archiveProduct((int)$id);Session::flash('admin_notice','Produsul a fost arhivat È™i sincronizat cu Stripe.');}catch(\Throwable $stripeException){error_log('Stripe product archive failed for product '.$id.': '.$stripeException->getMessage());Session::flash('admin_error','Produsul a fost arhivat local, dar Stripe nu a putut fi actualizat: '.mb_substr($stripeException->getMessage(),0,180));}
            Response::redirect('/admin/produse');
        }catch(\Throwable $exception){
            if($pdo->inTransaction()){$pdo->rollBack();}
            throw $exception;
        }
    }
    public function categories(Request $request): string
    {
        $items = Database::connection()->query(
            "SELECT c.*,p.name parent_name,m.path image_path,
                    (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id=c.id) product_count,
                    (SELECT COUNT(*) FROM categories child WHERE child.parent_id=c.id AND child.deleted_at IS NULL) child_count
             FROM categories c
             LEFT JOIN categories p ON p.id=c.parent_id
             LEFT JOIN media_assets m ON m.id=c.image_id
             WHERE c.deleted_at IS NULL
             ORDER BY c.is_featured DESC,c.sort_order,c.name"
        )->fetchAll();

        $collections = array_values(array_filter($items, static fn(array $item): bool => (bool) $item['is_featured']));
        $categories = array_values(array_filter($items, static fn(array $item): bool => !(bool) $item['is_featured']));

        return $this->admin('admin/categories', compact('collections', 'categories'));
    }

    public function categoryForm(Request $request, ?string $id = null): string
    {
        $category = null;
        if ($id) {
            $statement = Database::connection()->prepare('SELECT c.*,m.path image_path FROM categories c LEFT JOIN media_assets m ON m.id=c.image_id WHERE c.id=? AND c.deleted_at IS NULL');
            $statement->execute([$id]);
            $category = $statement->fetch();
            if (!$category) {
                throw new HttpException(404, 'Categoria nu există.');
            }
        }
        $parents = Database::connection()->query('SELECT id,name,parent_id FROM categories WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
        $collectionMode = !$id && (string) $request->input('tip', '') === 'colectie';

        return $this->admin('admin/category-form', compact('category', 'parents', 'collectionMode'));
    }

    public function saveCategory(Request $request, ?string $id = null): never
    {
        $name = trim((string) $request->input('name', ''));
        $slug = $this->slug((string) $request->input('slug', $name));
        $parent = (int) $request->input('parent_id', 0) ?: null;
        $featured = $request->input('is_featured') ? 1 : 0;

        if ($name === '' || $slug === '') {
            throw new HttpException(422, 'Numele și slugul sunt obligatorii.');
        }
        if ($id && $parent === (int) $id) {
            throw new HttpException(422, 'Categoria nu poate fi propriul părinte.');
        }

        $pdo = Database::connection();

        $image = (new UploadService())->image('image', $name);
        if ($id) {
            $old = $pdo->prepare('SELECT slug FROM categories WHERE id=? AND deleted_at IS NULL');
            $old->execute([$id]);
            $oldSlug = $old->fetchColumn();
            if (!$oldSlug) {
                throw new HttpException(404, 'Categoria nu există.');
            }

            $pdo->prepare('UPDATE categories SET parent_id=?,image_id=COALESCE(?,image_id),name=?,slug=?,description=?,is_active=?,is_featured=?,show_in_menu=?,sort_order=?,seo_title=?,seo_description=? WHERE id=?')
                ->execute([$parent,$image,$name,$slug,$request->input('description'),$request->input('is_active')?1:0,$featured,$request->input('show_in_menu')?1:0,(int)$request->input('sort_order',0),$request->input('seo_title'),$request->input('seo_description'),$id]);

            if ($oldSlug !== $slug) {
                $pdo->prepare("INSERT INTO url_redirects (source_path,target_path,http_status,reason,entity_type,entity_id) VALUES (?,?,301,'Schimbare slug categorie','category',?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path)")
                    ->execute(['/categorie/'.$oldSlug,'/categorie/'.$slug,$id]);
            }
        } else {
            $pdo->prepare('INSERT INTO categories (parent_id,image_id,name,slug,description,is_active,is_featured,show_in_menu,sort_order,seo_title,seo_description) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$parent,$image,$name,$slug,$request->input('description'),$request->input('is_active')?1:0,$featured,$request->input('show_in_menu')?1:0,(int)$request->input('sort_order',0),$request->input('seo_title'),$request->input('seo_description')]);
            $id = (string) $pdo->lastInsertId();
        }

        Session::flash('admin_notice', $featured ? 'Colecția a fost salvată și actualizată pe website.' : 'Categoria a fost salvată.');
        Response::redirect('/admin/categorii/'.$id.'/edit');
    }

    public function toggleCategory(Request $request, string $id): never
    {
        $active = $request->input('is_active') ? 1 : 0;
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id,name FROM categories WHERE id=? AND deleted_at IS NULL');
        $statement->execute([(int) $id]);
        $category = $statement->fetch();
        if (!$category) {
            throw new HttpException(404, 'Categoria nu există.');
        }

        $pdo->prepare('UPDATE categories SET is_active=?,updated_at=NOW() WHERE id=?')->execute([$active,(int)$id]);
        $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'category.status_changed','category',?,?)")
            ->execute([Auth::id(),(int)$id,$_SERVER['REMOTE_ADDR']??null]);

        if ($request->expectsJson()) {
            Response::json(['ok'=>true,'active'=>(bool)$active,'message'=>$active?'Categoria este vizibilă pe website.':'Categoria a fost ascunsă de pe website.']);
        }

        Session::flash('admin_notice', $active ? 'Categoria este vizibilă pe website.' : 'Categoria a fost ascunsă de pe website.');
        Response::redirect('/admin/categorii');
    }
    public function removeCollection(Request $request, string $id): never
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id,name,is_featured FROM categories WHERE id=? AND deleted_at IS NULL');
        $statement->execute([(int)$id]);
        $category = $statement->fetch();
        if (!$category) {
            throw new HttpException(404, 'Colecția nu există.');
        }

        $pdo->prepare('UPDATE categories SET is_featured=0,updated_at=NOW() WHERE id=?')->execute([(int)$id]);
        $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'collection.removed','category',?,?)")
            ->execute([Auth::id(),(int)$id,$_SERVER['REMOTE_ADDR']??null]);

        Session::flash('admin_notice', 'Colecția a fost eliminată de pe homepage. Categoria și produsele sale au fost păstrate.');
        Response::redirect('/admin/categorii');
    }

    public function deleteCategory(Request $request,string $id):never
    {
        $pdo=Database::connection();
        $statement=$pdo->prepare('SELECT c.id,c.name,c.slug,(SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id=c.id) product_count,(SELECT COUNT(*) FROM categories child WHERE child.parent_id=c.id AND child.deleted_at IS NULL) child_count FROM categories c WHERE c.id=? AND c.deleted_at IS NULL');
        $statement->execute([(int)$id]);
        $category=$statement->fetch();
        if(!$category){throw new HttpException(404,'Categoria nu existÃ„Æ’ sau a fost deja Ãˆâ„¢tearsÃ„Æ’.');}
        if((int)$category['product_count']>0||(int)$category['child_count']>0){throw new HttpException(422,'MutÃ„Æ’ mai ÃƒÂ®ntÃƒÂ¢i produsele Ãˆâ„¢i subcategoriile ÃƒÂ®nainte de Ãˆâ„¢tergere.');}
        $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE categories SET is_active=0,show_in_menu=0,deleted_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$id]);
            $pdo->prepare("INSERT INTO url_redirects (source_path,target_path,http_status,reason,entity_type,entity_id) VALUES (?,?,301,'Categorie Ãˆâ„¢tearsÃ„Æ’ din catalog','category',?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path),http_status=301,reason=VALUES(reason)")->execute(['/categorie/'.$category['slug'],'/shop',(int)$id]);
            $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('category',?,'deleted',JSON_OBJECT('slug',?),'pending',NOW())")->execute([(int)$id,$category['slug']]);
            $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'category.deleted','category',?,?)")->execute([Auth::id(),(int)$id,$_SERVER['REMOTE_ADDR']??null]);
            $pdo->commit();
            Response::redirect('/admin/categorii');
        }catch(\Throwable $exception){if($pdo->inTransaction()){$pdo->rollBack();}throw $exception;}
    }
    private function uniqueSku(PDO $pdo, string $base): string
    {
        $base = trim((string) preg_replace('/[^A-Z0-9-]+/', '-', strtoupper($base)), '-');
        $base = substr($base !== '' ? $base : 'MB-PRODUS', 0, 88);
        $statement = $pdo->prepare('SELECT (EXISTS(SELECT 1 FROM products WHERE sku=?) OR EXISTS(SELECT 1 FROM product_variants WHERE sku=?))');
        for ($counter = 0; $counter < 10000; $counter++) {
            $suffix = $counter === 0 ? '' : '-' . str_pad((string) ($counter + 1), 3, '0', STR_PAD_LEFT);
            $candidate = substr($base, 0, 100 - strlen($suffix)) . $suffix;
            $statement->execute([$candidate,$candidate]);
            if (!(bool) $statement->fetchColumn()) {
                return $candidate;
            }
        }
        throw new HttpException(500, 'Nu a putut fi generat un SKU unic.');
    }
    private function slug(string $value):string{$value=mb_strtolower(trim($value));$map=['Ã„Æ’'=>'a','ÃƒÂ¢'=>'a','ÃƒÂ®'=>'i','Ãˆâ„¢'=>'s','Ã…Å¸'=>'s','Ãˆâ€º'=>'t','Ã…Â£'=>'t'];$value=strtr($value,$map);$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';return trim($value,'-');}
}

