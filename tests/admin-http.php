<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;

$base = rtrim((string) Env::get('APP_URL', ''), '/');
$pdo = Database::connection();
require __DIR__ . '/admin-qa-user.php';
$admin = createAdminQaUser($pdo);

$password = 'Codex-QA-' . bin2hex(random_bytes(12));
$originalHash = (string) $admin['password_hash'];
$pdo->prepare("UPDATE users SET password_hash=?,status='active' WHERE id=?")
    ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);

$cookie = tempnam(sys_get_temp_dir(), 'mb-admin-');
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
        CURLOPT_USERAGENT => 'MaisonBebeAdminAudit/1.0',
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

$failed = false;
try {
    [$status, $login] = $request('GET', '/cont/autentificare');
    if ($status !== 200) {
        throw new RuntimeException('Pagina de autentificare nu răspunde.');
    }
    [$status, , $headers] = $request('POST', '/cont/autentificare', [
        '_csrf' => $csrf($login),
        'email' => $admin['email'],
        'password' => $password,
    ]);
    $location = implode(',', $headers['location'] ?? []);
    if (!in_array($status, [302, 303], true) || !str_contains($location, '/admin')) {
        throw new RuntimeException('Autentificarea administratorului QA a eșuat.');
    }

    $paths = [
        '/admin',
        '/admin/comenzi',
        '/admin/produse',
        '/admin/produse/creare',
        '/admin/categorii',
        '/admin/categorii/creare',
        '/admin/colectii/creare',
        '/admin/gift-box',
        '/admin/gift-box/cutii/creare',
        '/admin/clienti',
        '/admin/notificari',
        '/admin/cupoane',
        '/admin/cms',
        '/admin/cms/homepage',
        '/admin/expeditii',
        '/admin/atelier',
        '/admin/atelier/creare',
        '/admin/atelier/taxonomii',
        '/admin/atelier/calendar',
        '/admin/seo/indexabilitate',
        '/admin/seo/sitemap',
        '/admin/seo/redirecturi',
        '/admin/seo/search-console',
        '/admin/setari/email',
        '/admin/setari/plati',
        '/admin/setari/plati/stripe',
        '/admin/setari/autentificare',
        '/admin/setari/livrare',
        '/admin/setari/securitate',
        '/admin/facturare',
        '/admin/facturare/firma',
        '/admin/facturare/conectori',
        '/admin/facturare/sabloane',
        '/admin/facturare/sabloane/mapper',
        '/admin/facturare/efactura',
        '/admin/facturi',
        '/admin/utilizatori',
        '/admin/utilizatori/creare',
    ];

    $dynamic = [
        ['/admin/comenzi/', "SELECT id FROM orders ORDER BY id DESC LIMIT 1", ''],
        ['/admin/comenzi/', "SELECT id FROM orders ORDER BY id DESC LIMIT 1", '/awb'],
        ['/admin/produse/', "SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id LIMIT 1", '/edit'],
        ['/admin/produse/', "SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id LIMIT 1", '/seo'],
        ['/admin/categorii/', "SELECT id FROM categories WHERE deleted_at IS NULL ORDER BY id LIMIT 1", '/edit'],
        ['/admin/colectii/', "SELECT id FROM collections WHERE deleted_at IS NULL ORDER BY id LIMIT 1", '/edit'],
        ['/admin/gift-box/cutii/', "SELECT id FROM gift_box_templates ORDER BY id LIMIT 1", '/edit'],
        ['/admin/clienti/', "SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT 1", ''],
        ['/admin/cms/pagini/', "SELECT id FROM pages ORDER BY id LIMIT 1", ''],
        ['/admin/atelier/', "SELECT id FROM blog_posts ORDER BY id LIMIT 1", '/edit'],
        ['/admin/atelier/', "SELECT id FROM blog_posts ORDER BY id LIMIT 1", '/revisions'],
        ['/admin/atelier/', "SELECT id FROM blog_posts ORDER BY id LIMIT 1", '/seo'],
        ['/admin/atelier/', "SELECT id FROM blog_posts ORDER BY id LIMIT 1", '/social'],
        ['/admin/facturi/', "SELECT id FROM invoices ORDER BY id DESC LIMIT 1", ''],
    ];
    foreach ($dynamic as [$prefix, $sql, $suffix]) {
        $id = (int) $pdo->query($sql)->fetchColumn();
        if ($id > 0) {
            $paths[] = $prefix . $id . $suffix;
        }
    }

    foreach ($paths as $path) {
        [$status, $body, , $error] = $request('GET', $path);
        $ok = $status === 200
            && $error === ''
            && !preg_match('/(?:PHP (?:Warning|Fatal error|Parse error)|Uncaught (?:Error|Exception))/i', $body);
        echo '[' . ($ok ? 'OK' : 'FAIL') . '] ' . $path . ' -> ' . $status
            . ($error !== '' ? ' (' . $error . ')' : '') . PHP_EOL;
        $failed = $failed || !$ok;
    }
} finally {
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$originalHash, $admin['id']]);
    if (is_file($cookie)) {
        unlink($cookie);
    }
    deleteAdminQaUser($pdo, (int) $admin['id']);
}

exit($failed ? 1 : 0);
