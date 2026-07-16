<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;

$base = rtrim((string) Env::get('APP_URL', ''), '/');
$pdo = Database::connection();
$run = strtolower(bin2hex(random_bytes(5)));
$ids = [
    'category' => 0,
    'collection' => 0,
    'product' => 0,
    'box' => 0,
    'box_product' => 0,
    'coupon' => 0,
    'post' => 0,
    'staff' => 0,
    'staff_role' => 0,
];

require __DIR__ . '/admin-qa-user.php';
$admin = createAdminQaUser($pdo);
$principalId = (int) $pdo->query(
    "SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id "
    . "JOIN roles r ON r.id=ur.role_id "
    . "WHERE r.name='super_admin' AND u.id<>" . (int) $admin['id'] . " AND u.deleted_at IS NULL "
    . "ORDER BY u.id LIMIT 1"
)->fetchColumn();
$permissionId = (int) $pdo->query("SELECT id FROM permissions WHERE name<>'*' ORDER BY id LIMIT 1")->fetchColumn();
$normalProductId = (int) $pdo->query(
    "SELECT id FROM products WHERE status='active' AND deleted_at IS NULL AND is_gift_box=0 ORDER BY id LIMIT 1"
)->fetchColumn();

$password = 'Codex-CRUD-' . bin2hex(random_bytes(10));
$originalHash = (string) $admin['password_hash'];
$stripeEnabled = (int) $pdo->query("SELECT is_enabled FROM payment_providers WHERE code='stripe'")->fetchColumn();
$pdo->prepare("UPDATE users SET password_hash=?,status='active' WHERE id=?")
    ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
$pdo->exec("UPDATE payment_providers SET is_enabled=0 WHERE code='stripe'");

$cookie = tempnam(sys_get_temp_dir(), 'mb-admin-crud-');
$request = static function (string $method, string $path, array $data = []) use ($base, $cookie): array {
    $headers = [];
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'MaisonBebeAdminCrudAudit/1.0',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    return [$status, is_string($body) ? $body : '', $headers, $error];
};
$csrf = static function (string $html): string {
    if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('Tokenul CSRF nu a fost găsit.');
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};
$redirect = static function (array $response, string $needle): void {
    [$status, $body, $headers, $error] = $response;
    $location = implode(',', $headers['location'] ?? []);
    if (!in_array($status, [302, 303], true) || !str_contains($location, $needle) || $error !== '') {
        throw new RuntimeException('Operație admin eșuată: ' . $status . ' ' . $location . ' ' . $body);
    }
};

try {
    [, $login] = $request('GET', '/cont/autentificare');
    $redirect($request('POST', '/cont/autentificare', [
        '_csrf' => $csrf($login),
        'email' => $admin['email'],
        'password' => $password,
    ]), '/admin');
    [, $categoryForm] = $request('GET', '/admin/categorii/creare');
    $token = $csrf($categoryForm);

    $categorySlug = 'qa-category-' . $run;
    $redirect($request('POST', '/admin/categorii', [
        '_csrf' => $token,
        'name' => 'Categorie QA',
        'slug' => $categorySlug,
        'description' => 'Categorie creată automat pentru audit.',
        'is_active' => '1',
        'show_in_menu' => '1',
        'sort_order' => '999',
    ]), '/admin/categorii/');
    $statement = $pdo->prepare('SELECT id FROM categories WHERE slug=?');
    $statement->execute([$categorySlug]);
    $ids['category'] = (int) $statement->fetchColumn();
    $redirect($request('POST', '/admin/categorii/' . $ids['category'], [
        '_csrf' => $token,
        'name' => 'Categorie QA actualizată',
        'slug' => $categorySlug . '-updated',
        'description' => 'Actualizată.',
        'is_active' => '1',
        'show_in_menu' => '1',
        'sort_order' => '998',
    ]), '/admin/categorii/');
    echo "[OK] Category create/update\n";

    $collectionSlug = 'qa-collection-' . $run;
    $redirect($request('POST', '/admin/colectii', [
        '_csrf' => $token,
        'name' => 'Colecție QA',
        'slug' => $collectionSlug,
        'description' => 'Colecție de audit.',
        'is_active' => '1',
        'sort_order' => '999',
    ]), '/admin/colectii/');
    $statement = $pdo->prepare('SELECT id FROM collections WHERE slug=?');
    $statement->execute([$collectionSlug]);
    $ids['collection'] = (int) $statement->fetchColumn();
    $redirect($request('POST', '/admin/colectii/' . $ids['collection'], [
        '_csrf' => $token,
        'name' => 'Colecție QA actualizată',
        'slug' => $collectionSlug . '-updated',
        'description' => 'Actualizată.',
        'is_active' => '1',
        'sort_order' => '998',
    ]), '/admin/colectii/');
    echo "[OK] Collection create/update\n";

    $productSlug = 'qa-product-' . $run;
    $redirect($request('POST', '/admin/produse', [
        '_csrf' => $token,
        'name' => 'Produs QA',
        'slug' => $productSlug,
        'short_description' => 'Produs temporar pentru test.',
        'description_html' => '<p>Descriere sigură.</p>',
        'status' => 'draft',
        'categories' => [$ids['category']],
        'collections' => [$ids['collection']],
        'primary_category_id' => $ids['category'],
        'variant_price' => ['123.45'],
        'variant_stock' => ['5'],
        'variant_options_json' => ['{}'],
        'image_order_json' => '[]',
        'robots_index' => '1',
        'include_sitemap' => '1',
    ]), '/admin/produse');
    $statement = $pdo->prepare('SELECT id FROM products WHERE slug=?');
    $statement->execute([$productSlug]);
    $ids['product'] = (int) $statement->fetchColumn();
    $variantId = (int) $pdo->query(
        'SELECT id FROM product_variants WHERE product_id=' . $ids['product'] . ' ORDER BY id LIMIT 1'
    )->fetchColumn();
    $redirect($request('POST', '/admin/produse/' . $ids['product'], [
        '_csrf' => $token,
        'name' => 'Produs QA actualizat',
        'slug' => $productSlug . '-updated',
        'short_description' => 'Actualizat.',
        'description_html' => '<p>Conținut actualizat.</p>',
        'status' => 'draft',
        'categories' => [$ids['category']],
        'collections' => [$ids['collection']],
        'primary_category_id' => $ids['category'],
        'variant_id' => [$variantId],
        'variant_price' => ['125.00'],
        'variant_stock' => ['6'],
        'variant_options_json' => ['{}'],
        'image_order_json' => '[]',
        'robots_index' => '1',
        'include_sitemap' => '1',
    ]), '/admin/produse');
    echo "[OK] Product create/update with Stripe isolated\n";

    $boxSlug = 'qa-box-' . $run;
    $redirect($request('POST', '/admin/gift-box/cutii', [
        '_csrf' => $token,
        'name' => 'Cutie QA',
        'slug' => $boxSlug,
        'description' => 'Cutie temporară.',
        'price' => '25',
        'stock_qty' => '3',
        'min_components' => '1',
        'max_components' => '2',
        'sort_order' => '999',
        'is_active' => '1',
        'product_ids' => $normalProductId > 0 ? [$normalProductId] : [],
    ]), '/admin/gift-box');
    $statement = $pdo->prepare('SELECT id,product_id FROM gift_box_templates WHERE slug=?');
    $statement->execute([$boxSlug]);
    $box = $statement->fetch();
    $ids['box'] = (int) ($box['id'] ?? 0);
    $ids['box_product'] = (int) ($box['product_id'] ?? 0);
    $redirect($request('POST', '/admin/gift-box/cutii/' . $ids['box'], [
        '_csrf' => $token,
        'name' => 'Cutie QA actualizată',
        'slug' => $boxSlug . '-updated',
        'description' => 'Actualizată.',
        'price' => '26',
        'stock_qty' => '4',
        'min_components' => '1',
        'max_components' => '3',
        'sort_order' => '998',
        'is_active' => '1',
        'product_ids' => $normalProductId > 0 ? [$normalProductId] : [],
    ]), '/admin/gift-box');
    echo "[OK] Gift Box create/update\n";

    $couponCode = 'ADMINQA' . strtoupper($run);
    $redirect($request('POST', '/admin/cupoane', [
        '_csrf' => $token,
        'code' => $couponCode,
        'discount_type' => 'percent',
        'discount_value' => '15',
        'minimum_order' => '10',
        'max_uses' => '5',
        'max_uses_per_user' => '1',
        'is_active' => '1',
        'eligibility_mode' => 'include',
        'product_ids' => [$ids['product']],
        'category_ids' => [$ids['category']],
        'collection_ids' => [$ids['collection']],
    ]), '/admin/cupoane');
    $statement = $pdo->prepare('SELECT id FROM coupons WHERE code=?');
    $statement->execute([$couponCode]);
    $ids['coupon'] = (int) $statement->fetchColumn();
    $redirect($request('POST', '/admin/cupoane', [
        '_csrf' => $token,
        'coupon_id' => $ids['coupon'],
        'code' => $couponCode,
        'discount_type' => 'fixed',
        'discount_value' => '20',
        'minimum_order' => '50',
        'is_active' => '1',
        'eligibility_mode' => 'exclude',
        'product_ids' => [$ids['product']],
    ]), '/admin/cupoane');
    echo "[OK] Coupon create/update and eligibility\n";

    $postSlug = 'qa-article-' . $run;
    $redirect($request('POST', '/admin/atelier', [
        '_csrf' => $token,
        'title' => 'Articol QA',
        'slug' => $postSlug,
        'excerpt' => 'Articol temporar.',
        'content_html' => '<h2>Test</h2><p>Conținut sigur.</p>',
        'status' => 'draft',
        'robots_index' => '1',
    ]), '/admin/atelier/');
    $statement = $pdo->prepare('SELECT id FROM blog_posts WHERE slug=?');
    $statement->execute([$postSlug]);
    $ids['post'] = (int) $statement->fetchColumn();
    $redirect($request('POST', '/admin/atelier/' . $ids['post'], [
        '_csrf' => $token,
        'title' => 'Articol QA actualizat',
        'slug' => $postSlug . '-updated',
        'excerpt' => 'Actualizat.',
        'content_html' => '<h2>Actualizat</h2><p>Conținut sigur.</p>',
        'status' => 'draft',
        'robots_index' => '1',
    ]), '/admin/atelier/');
    echo "[OK] Article create/update and revision\n";

    $staffEmail = 'qa-staff-' . $run . '@local.test';
    $redirect($request('POST', '/admin/utilizatori', [
        '_csrf' => $token,
        'first_name' => 'Operator',
        'last_name' => 'QA',
        'nickname' => 'Poreclă QA',
        'email' => $staffEmail,
        'password' => 'Staff-QA-' . bin2hex(random_bytes(8)),
        'status' => 'active',
        'permissions' => $permissionId > 0 ? [$permissionId] : [],
    ]), '/admin/utilizatori');
    $statement = $pdo->prepare('SELECT id,nickname FROM users WHERE email=?');
    $statement->execute([$staffEmail]);
    $staff = $statement->fetch();
    $ids['staff'] = (int) ($staff['id'] ?? 0);
    $ids['staff_role'] = (int) $pdo->query(
        "SELECT r.id FROM roles r JOIN user_roles ur ON ur.role_id=r.id "
        . "WHERE ur.user_id=" . $ids['staff'] . " AND r.name LIKE 'staff_user_%' LIMIT 1"
    )->fetchColumn();
    if (($staff['nickname'] ?? '') !== 'Poreclă QA') {
        throw new RuntimeException('Porecla utilizatorului nu a fost salvată.');
    }
    $redirect($request('POST', '/admin/utilizatori/' . $ids['staff'] . '/status', [
        '_csrf' => $token,
    ]), '/admin/utilizatori');
    if ($principalId > 0) {
        [$status] = $request('GET', '/admin/utilizatori/' . $principalId . '/edit');
        if ($status !== 403) {
            throw new RuntimeException('Administratorul principal poate fi editat de alt administrator.');
        }
    }
    echo "[OK] Staff nickname, permissions, status and primary-admin protection\n";
} finally {
    if ($ids['staff'] > 0) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$ids['staff']]);
    }
    if ($ids['staff_role'] > 0) {
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$ids['staff_role']]);
        $pdo->prepare('DELETE FROM roles WHERE id=?')->execute([$ids['staff_role']]);
    }
    if ($ids['post'] > 0) {
        $pdo->prepare('DELETE FROM blog_post_revisions WHERE post_id=?')->execute([$ids['post']]);
        $pdo->prepare('DELETE FROM blog_post_categories WHERE post_id=?')->execute([$ids['post']]);
        $pdo->prepare("DELETE FROM sitemap_events WHERE entity_type='blog_post' AND entity_id=?")->execute([$ids['post']]);
        $pdo->prepare("DELETE FROM url_redirects WHERE entity_type='blog_post' AND entity_id=?")->execute([$ids['post']]);
        $pdo->prepare('DELETE FROM blog_posts WHERE id=?')->execute([$ids['post']]);
    }
    if ($ids['coupon'] > 0) {
        $pdo->prepare('DELETE FROM coupon_products WHERE coupon_id=?')->execute([$ids['coupon']]);
        $pdo->prepare('DELETE FROM coupon_categories WHERE coupon_id=?')->execute([$ids['coupon']]);
        $pdo->prepare('DELETE FROM coupon_collections WHERE coupon_id=?')->execute([$ids['coupon']]);
        $pdo->prepare('DELETE FROM coupons WHERE id=?')->execute([$ids['coupon']]);
    }
    foreach ([$ids['box_product'], $ids['product']] as $productId) {
        if ($productId > 0) {
            $pdo->prepare('DELETE FROM gift_box_templates WHERE product_id=?')->execute([$productId]);
            $pdo->prepare('DELETE FROM collection_products WHERE product_id=?')->execute([$productId]);
            $pdo->prepare('DELETE FROM product_categories WHERE product_id=?')->execute([$productId]);
            $pdo->prepare(
                'DELETE vov FROM variant_option_values vov JOIN product_variants v ON v.id=vov.variant_id WHERE v.product_id=?'
            )->execute([$productId]);
            $pdo->prepare('DELETE FROM product_options WHERE product_id=?')->execute([$productId]);
            $pdo->prepare('DELETE FROM product_variants WHERE product_id=?')->execute([$productId]);
            $pdo->prepare("DELETE FROM sitemap_events WHERE entity_type='product' AND entity_id=?")->execute([$productId]);
            $pdo->prepare("DELETE FROM url_redirects WHERE entity_type='product' AND entity_id=?")->execute([$productId]);
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$productId]);
        }
    }
    if ($ids['box'] > 0) {
        $pdo->prepare('DELETE FROM gift_box_templates WHERE id=?')->execute([$ids['box']]);
    }
    if ($ids['collection'] > 0) {
        $pdo->prepare("DELETE FROM url_redirects WHERE entity_type='collection' AND entity_id=?")->execute([$ids['collection']]);
        $pdo->prepare('DELETE FROM collections WHERE id=?')->execute([$ids['collection']]);
    }
    if ($ids['category'] > 0) {
        $pdo->prepare("DELETE FROM url_redirects WHERE entity_type='category' AND entity_id=?")->execute([$ids['category']]);
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$ids['category']]);
    }
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$originalHash, $admin['id']]);
    $pdo->prepare("UPDATE payment_providers SET is_enabled=? WHERE code='stripe'")->execute([$stripeEnabled]);
    if (is_file($cookie)) {
        unlink($cookie);
    }
    deleteAdminQaUser($pdo, (int) $admin['id']);
}
