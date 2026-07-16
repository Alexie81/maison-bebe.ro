<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;

$base = rtrim((string) Env::get('APP_URL', ''), '/');
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--base=')) {
        $base = rtrim(substr($argument, 7), '/');
    }
}
if (!preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Base URL invalid.\n");
    exit(1);
}

$pdo = Database::connection();
$single = static function (string $sql) use ($pdo): ?string {
    $value = $pdo->query($sql)->fetchColumn();
    return $value === false ? null : (string) $value;
};

$paths = [
    ['/', [200]],
    ['/shop', [200]],
    ['/gift-box', [200]],
    ['/despre-noi', [200]],
    ['/atelier', [200]],
    ['/cos', [200]],
    ['/favorite', [200]],
    ['/checkout', [302, 303]],
    ['/urmarire-comanda', [200]],
    ['/contact', [200]],
    ['/cont/autentificare', [200]],
    ['/cont/inregistrare', [200]],
    ['/cont/resetare-parola', [200]],
    ['/api/search?q=maison', [200]],
    ['/api/cart', [200]],
    ['/sitemap.xml', [200]],
    ['/sitemaps/products.xml', [200]],
    ['/sitemaps/content.xml', [200]],
    ['/robots.txt', [200]],
    ['/admin', [302, 303]],
    ['/admin/produse', [302, 303]],
    ['/pagina-care-nu-exista-codex', [404]],
    ['/.env', [403, 404]],
    ['/composer.json', [403, 404]],
    ['/database/schema.sql', [403, 404]],
];

$dynamic = [
    ['/produs/', $single("SELECT slug FROM products WHERE status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1")],
    ['/categorie/', $single("SELECT slug FROM categories WHERE is_active=1 AND deleted_at IS NULL ORDER BY id LIMIT 1")],
    ['/colectie/', $single("SELECT slug FROM collections WHERE is_active=1 AND deleted_at IS NULL ORDER BY id LIMIT 1")],
    ['/atelier/', $single("SELECT slug FROM blog_posts WHERE status='published' ORDER BY id LIMIT 1")],
    ['/politici/', $single("SELECT slug FROM pages WHERE status='published' ORDER BY id LIMIT 1")],
];
$configuredHost = strtolower((string) parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST));
$targetHost = strtolower((string) parse_url($base, PHP_URL_HOST));
if ($configuredHost === $targetHost) {
    foreach ($dynamic as [$prefix, $slug]) {
        if ($slug !== null && $slug !== '') {
            $paths[] = [$prefix . rawurlencode($slug), [200]];
        }
    }
}

$cookie = tempnam(sys_get_temp_dir(), 'mb-http-');
$failed = false;
$rootHeaders = [];

$request = static function (string $url) use ($cookie): array {
    $headers = [];
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'MaisonBebeLaunchAudit/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8'],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $length = strlen($line);
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
            return $length;
        },
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);
    curl_close($curl);
    return [$status, $type, is_string($body) ? $body : '', $headers, $error];
};

foreach ($paths as [$path, $expected]) {
    [$status, $type, $body, $headers, $error] = $request($base . $path);
    $ok = in_array($status, $expected, true)
        && $error === ''
        && !preg_match('/(?:PHP (?:Warning|Fatal error|Parse error)|Uncaught (?:Error|Exception))/i', $body);
    if ($path === '/') {
        $rootHeaders = $headers;
    }
    if (str_starts_with($path, '/api/') && $status === 200) {
        $ok = $ok && str_contains(strtolower($type), 'application/json') && json_decode($body, true) !== null;
    }
    if (str_ends_with($path, '.xml') && $status === 200) {
        $ok = $ok && str_contains(strtolower($type), 'xml');
    }
    echo '[' . ($ok ? 'OK' : 'FAIL') . '] GET ' . $path . ' -> ' . $status
        . ($error !== '' ? ' (' . $error . ')' : '') . PHP_EOL;
    $failed = $failed || !$ok;
}

$requiredHeaders = [
    'x-content-type-options' => 'nosniff',
    'x-frame-options' => 'sameorigin',
    'referrer-policy' => 'strict-origin-when-cross-origin',
    'content-security-policy' => 'default-src',
];
if (str_starts_with($base, 'https://')) {
    $requiredHeaders['strict-transport-security'] = 'max-age=';
}
foreach ($requiredHeaders as $name => $needle) {
    $values = strtolower(implode(', ', $rootHeaders[$name] ?? []));
    $ok = str_contains($values, strtolower($needle));
    echo '[' . ($ok ? 'OK' : 'FAIL') . '] Header ' . $name . PHP_EOL;
    $failed = $failed || !$ok;
}

if (is_file($cookie)) {
    unlink($cookie);
}

exit($failed ? 1 : 0);
