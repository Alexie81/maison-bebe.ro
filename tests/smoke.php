<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Csrf;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\HtmlSanitizer;
use MaisonBebe\Services\InvoiceService;
use MaisonBebe\Services\ShippingService;

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    try {
        $callback();
        $tests[] = ['name' => $name, 'ok' => true];
    } catch (Throwable $exception) {
        $tests[] = ['name' => $name, 'ok' => false, 'error' => $exception->getMessage()];
    }
};
$assert = static function (bool $condition, string $message = 'Aserțiune eșuată'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test('conexiune DB și schemă', static function () use ($assert): void {
    $tables = (int) Database::connection()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
    $assert($tables >= 95, 'Schema este incompletă.');
});

$test('criptare secret round-trip', static function () use ($assert): void {
    $secret = bin2hex(random_bytes(16));
    $assert(Encryptor::decrypt(Encryptor::encrypt($secret)) === $secret);
});

$test('sanitizare HTML', static function () use ($assert): void {
    $clean = HtmlSanitizer::clean('<p onclick="x()">Text</p><script>alert(1)</script><a href="javascript:x">Rău</a>');
    $assert(!str_contains($clean, '<script') && !str_contains($clean, 'onclick') && !str_contains($clean, 'javascript:'));
});

$test('CSRF valid și token invalid respins', static function () use ($assert): void {
    $token = Csrf::token();
    $assert(Csrf::validate($token) && !Csrf::validate('invalid'));
});

$test('URL absolut fără prefix duplicat', static function () use ($assert): void {
    $url = absolute_url('/shop');
    $assert(!str_contains($url, '/maison-bebe.ro/maison-bebe.ro/'), $url);
});

$test('emitere factură idempotentă', static function () use ($assert): void {
    $orderId = (int) Database::connection()->query('SELECT id FROM orders ORDER BY id LIMIT 1')->fetchColumn();
    if (!$orderId) {
        return;
    }
    $service = new InvoiceService();
    $first = $service->issueForOrder($orderId);
    $second = $service->issueForOrder($orderId);
    $assert($first === $second);
});

$test('generare AWB idempotentă', static function () use ($assert): void {
    $pdo = Database::connection();
    $orderId = (int) $pdo->query('SELECT id FROM orders ORDER BY id LIMIT 1')->fetchColumn();
    $providerId = (int) $pdo->query("SELECT id FROM shipping_providers WHERE code='manual'")->fetchColumn();
    if (!$orderId || !$providerId) {
        return;
    }
    $service = new ShippingService();
    $first = $service->create($orderId, $providerId, 1000, 1);
    $second = $service->create($orderId, $providerId, 1000, 1);
    $assert($first === $second);
});

$failed = array_filter($tests, static fn(array $item): bool => !$item['ok']);
foreach ($tests as $result) {
    echo ($result['ok'] ? '[OK] ' : '[FAIL] ') . $result['name'] . ($result['ok'] ? '' : ': ' . $result['error']) . PHP_EOL;
}
exit($failed ? 1 : 0);
