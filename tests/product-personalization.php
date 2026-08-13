<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Services\CartService;
use MaisonBebe\Services\InvoiceService;
use MaisonBebe\Services\ProductPersonalizationService;

$pdo = Database::connection();
$suffix = strtoupper(substr(bin2hex(random_bytes(7)), 0, 12));
$productId = $variantId = $cartId = 0;
$failed = false;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $service = new ProductPersonalizationService();
    $service->ensureSchema($pdo);
    $pdo->prepare("INSERT INTO products (name,slug,sku,status) VALUES (?,?,?,'active')")
        ->execute(['Păturică personalizabilă QA', 'paturica-personalizabila-' . strtolower($suffix), 'QA-PERS-P-' . $suffix]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,track_inventory,is_active) VALUES (?,?,12900,10,1,1)')
        ->execute([$productId, 'QA-PERS-V-' . $suffix]);
    $variantId = (int) $pdo->lastInsertId();

    $service->save($productId, [
        'personalization_option_name' => ['Broderie nume', 'Nume și data nașterii'],
        'personalization_option_price' => ['25.00', '39.00'],
    ], $pdo);
    $options = $service->forProduct($productId, true, $pdo);
    $assert(count($options) === 2, 'Opțiunile de personalizare nu au fost salvate.');
    $assert((int) $options[1]['price_delta_minor'] === 3900, 'Costul personalizării nu este salvat în bani corect.');

    $snapshot = $service->withSnapshot($variantId, (int) $options[1]['id'], 'Sofia Maria', '2025-04-18', [], $pdo);
    $assert($service->unitPrice(12900, $snapshot) === 16800, 'Personalizarea nu este adăugată la prețul produsului.');
    $assert(($snapshot['personalization']['child_name'] ?? '') === 'Sofia Maria', 'Numele copilului nu este păstrat în snapshot.');
    $assert(($snapshot['personalization']['birth_date_formatted'] ?? '') === '18.04.2025', 'Data nașterii nu este formatată corect.');
    $assert(str_contains((string) ($snapshot['personalization']['instructions'] ?? ''), 'Nume copil: Sofia Maria'), 'Mesajul pentru atelier nu este generat.');

    try {
        $service->withSnapshot($variantId, (int) $options[0]['id'], '', '2025-04-18', [], $pdo);
        throw new RuntimeException('Personalizarea fără numele copilului a fost acceptată.');
    } catch (HttpException $exception) {
        $assert($exception->status() === 422, 'Eroarea pentru numele lipsă nu este o validare 422.');
    }

    $_COOKIE[CartService::COOKIE] = bin2hex(random_bytes(32));
    $cart = new CartService();
    $cartId = (int) $cart->current()['id'];
    $cart->add($variantId, 1, [
        'personalization_option_id' => (int) $options[1]['id'],
        'personalization_child_name' => 'Sofia Maria',
        'personalization_birth_date' => '2025-04-18',
    ]);
    $totals = $cart->totals();
    $assert((int) $totals['subtotal_minor'] === 16800, 'Totalul coșului nu include personalizarea.');
    $cartCustomization = json_decode((string) $totals['items'][0]['customization_json'], true) ?: [];
    $assert(($cartCustomization['personalization']['option_name'] ?? '') === 'Nume și data nașterii', 'Coșul nu păstrează opțiunea aleasă.');

    $invoiceLines = (new InvoiceService())->expandOrderItemForInvoice([
        'name_snapshot' => 'Păturică personalizabilă QA',
        'sku_snapshot' => 'QA-PERS-V-' . $suffix,
        'quantity' => 1,
        'unit_price_minor' => 16800,
        'total_minor' => 16800,
        'customization_json' => json_encode($cartCustomization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ], 1.21);
    $assert(count($invoiceLines) === 1, 'Produsul personalizat trebuie să rămână o singură poziție pe factură.');
    $assert(str_contains($invoiceLines[0]['name'], 'Nume și data nașterii'), 'Factura nu menționează tipul personalizării.');
    $assert($invoiceLines[0]['total_minor'] + $invoiceLines[0]['vat_minor'] === 16800, 'Factura nu păstrează totalul personalizat.');

    echo "Product personalization regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Product personalization regression test: FAIL - {$exception->getMessage()}\n");
    $failed = true;
} finally {
    if ($cartId) {
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);
        $pdo->prepare('DELETE FROM carts WHERE id=?')->execute([$cartId]);
    }
    if ($productId) $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$productId]);
}

exit($failed ? 1 : 0);
