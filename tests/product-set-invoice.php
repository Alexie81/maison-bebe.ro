<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Services\InvoiceService;
use MaisonBebe\Services\ProductSetService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $customization = [
        'type' => 'gift_box',
        'role' => 'component',
        'group' => 'GB-QA-SET',
        'template_name' => 'Cutie QA',
        'product_set' => [
            'product_id' => 900,
            'variant_id' => 901,
            'name' => 'Set cadou QA',
            'sku' => 'QA-SET',
            'components' => [
                ['product_id'=>100,'variant_id'=>101,'name'=>'Body QA','variant'=>'Standard','sku'=>'QA-BODY','quantity'=>2,'price_minor'=>1200,'cost_minor'=>600,'track_inventory'=>true,'track_accounting_stock'=>true],
                ['product_id'=>200,'variant_id'=>201,'name'=>'Păturică QA','variant'=>'Standard','sku'=>'QA-BLANKET','quantity'=>1,'price_minor'=>1600,'cost_minor'=>800,'track_inventory'=>true,'track_accounting_stock'=>true],
            ],
        ],
    ];
    $item = [
        'id' => 1,
        'name_snapshot' => 'Set cadou QA',
        'sku_snapshot' => 'QA-SET',
        'quantity' => 2,
        'unit_price_minor' => 2500,
        'total_minor' => 5000,
        'customization_json' => json_encode($customization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ];

    $lines = (new InvoiceService())->expandOrderItemForInvoice($item, 1.21);
    $assert(count($lines) === 2, 'Setul din Gift Box nu a fost defalcat în cele două componente pe factură.');
    $assert(array_column($lines, 'sku') === ['QA-BODY','QA-BLANKET'], 'SKU-urile componentelor nu apar corect pe factură.');
    $assert(array_column($lines, 'quantity') === [4,2], 'Cantitățile componentelor nu țin cont de cantitatea setului.');
    $assert(str_contains($lines[0]['name'], 'din setul Set cadou QA'), 'Linia facturii nu arată setul din care provine componenta.');
    $assert(array_sum(array_map(static fn(array $line): int => $line['total_minor'] + $line['vat_minor'], $lines)) === 5000, 'Defalcarea nu păstrează totalul exact al setului.');

    $targets = (new ProductSetService())->stockTargets(2, $customization);
    $assert(count($targets) === 2, 'Scăderea din stoc nu conține toate componentele setului.');
    $assert(array_column($targets, 'quantity_required') === [4,2], 'Cantitățile scăzute din stoc pentru set sunt incorecte.');

    echo "Product set personalized Gift Box invoice regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Product set personalized Gift Box invoice regression test: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
