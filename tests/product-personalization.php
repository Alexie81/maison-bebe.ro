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
        'personalization_option_name' => ['Broderie nume', 'Nume și data botezului/nașterii'],
        'personalization_option_price' => ['25.00', '39.00'],
    ], $pdo);
    $options = $service->forProduct($productId, true, $pdo);
    $assert(count($options) === 2, 'Opțiunile de personalizare nu au fost salvate.');
    $assert((int) $options[1]['price_delta_minor'] === 3900, 'Costul personalizării nu este salvat în bani corect.');

    $selectedOptionIds = [(int) $options[0]['id'], (int) $options[1]['id']];
    $snapshot = $service->withSnapshot($variantId, $selectedOptionIds, 'Sofia Maria', '2025-04-18', [], $pdo);
    $assert($service->unitPrice(12900, $snapshot) === 19300, 'Costurile personalizărilor multiple nu sunt adăugate la prețul produsului.');
    $assert(count((array) ($snapshot['personalization']['options'] ?? [])) === 2, 'Selecția multiplă nu este păstrată în snapshot.');
    $assert(($snapshot['personalization']['child_name'] ?? '') === 'Sofia Maria', 'Numele copilului nu este păstrat în snapshot.');
    $assert(($snapshot['personalization']['birth_date_formatted'] ?? '') === '18.04.2025', 'Data botezului/nașterii nu este formatată corect.');
    $childNameLabel = (string) ($snapshot['personalization']['child_name_label'] ?? 'Numele copilului');
    $assert(str_contains((string) ($snapshot['personalization']['instructions'] ?? ''), $childNameLabel . ': Sofia Maria'), 'Mesajul pentru atelier nu este generat.');

    $futureEventDate = (new DateTimeImmutable('+3 months'))->format('Y-m-d');
    $futureSnapshot = $service->withSnapshot($variantId, (int) $options[1]['id'], 'Sofia Maria', $futureEventDate, [], $pdo);
    $assert(($futureSnapshot['personalization']['birth_date'] ?? '') === $futureEventDate, 'O dată viitoare pentru botez trebuie acceptată.');

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
        'personalization_option_ids' => $selectedOptionIds,
        'personalization_child_name' => 'Sofia Maria',
        'personalization_birth_date' => '2025-04-18',
    ]);
    $totals = $cart->totals();
    $assert((int) $totals['subtotal_minor'] === 19300, 'Totalul coșului nu include toate personalizările.');
    $cartCustomization = json_decode((string) $totals['items'][0]['customization_json'], true) ?: [];
    $assert(($cartCustomization['personalization']['option_name'] ?? '') === 'Broderie nume + Nume și data botezului/nașterii', 'Coșul nu păstrează toate opțiunile alese.');

    $invoiceCustomization = $cartCustomization;
    $invoiceCustomization['optional_variants'] = [['id'=>7001,'name'=>'Body și ștrampi','price_delta_minor'=>9900]];
    $invoiceCustomization['optional_variants_total_minor'] = 9900;
    $invoiceLines = (new InvoiceService())->expandOrderItemForInvoice([
        'name_snapshot' => 'Păturică personalizabilă QA',
        'sku_snapshot' => 'QA-PERS-V-' . $suffix,
        'options_json' => json_encode(['label'=>'6-9 luni, 68-74 cm'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'quantity' => 1,
        'unit_price_minor' => 29200,
        'total_minor' => 29200,
        'customization_json' => json_encode($invoiceCustomization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ], 1.21);
    $assert(count($invoiceLines) === 4, 'Factura trebuie să afișeze produsul, opțiunea suplimentară și cele două personalizări separat.');
    $assert(str_contains($invoiceLines[0]['name'], '6-9 luni, 68-74 cm'), 'Factura nu afișează mărimea produsului.');
    $assert(str_contains($invoiceLines[1]['name'], 'Body și ștrampi'), 'Factura nu afișează opțiunea suplimentară separat.');
    $assert(str_contains($invoiceLines[2]['name'], 'Broderie nume') && str_contains($invoiceLines[3]['name'], 'Nume și data botezului/nașterii'), 'Factura nu desfășoară toate personalizările.');
    $assert(str_contains($invoiceLines[2]['name'], 'Sofia Maria') && str_contains($invoiceLines[2]['name'], '18.04.2025'), 'Serviciul de personalizare nu conține datele pentru atelier.');
    $assert(!empty($invoiceLines[0]['stockable']) && empty($invoiceLines[1]['stockable']) && empty($invoiceLines[2]['stockable']) && empty($invoiceLines[3]['stockable']), 'Numai produsul fizic trebuie să fie legat de ieșirea din stoc.');
    $assert(array_sum(array_map(static fn(array $line): int => $line['total_minor'] + $line['vat_minor'], $invoiceLines)) === 29200, 'Factura desfășurată nu păstrează totalul comenzii.');
    $assert(array_sum(array_column($invoiceLines, 'total_minor')) === (int) round(29200 / 1.21), 'Rotunjirea valorii fără TVA nu este păstrată exact după desfășurare.');

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
