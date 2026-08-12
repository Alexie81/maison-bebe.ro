<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;

final class GoogleAnalyticsService
{
    public const CURRENCY = 'RON';
    public const AFFILIATION = 'Maison Bébé';

    public function productItem(
        array $product,
        int $index = 0,
        ?string $listId = null,
        ?string $listName = null,
        ?array $variant = null
    ): array {
        $variantCount = (int) ($product['variant_count'] ?? count((array) ($product['variants'] ?? [])));
        $variantSku = trim((string) ($variant['sku'] ?? ''));
        if ($variantSku === '' && $variantCount === 1) {
            $variantSku = trim((string) ($product['default_variant_sku'] ?? ($product['variants'][0]['sku'] ?? '')));
        }
        $productSku = trim((string) ($product['sku'] ?? ''));
        $item = [
            'item_id' => $variantSku !== '' ? $variantSku : ($productSku !== '' ? $productSku : 'PRODUS-' . (int) ($product['id'] ?? 0)),
            'item_name' => trim((string) ($product['name'] ?? 'Produs')),
            'affiliation' => self::AFFILIATION,
            'item_brand' => trim((string) ($product['brand'] ?? '')) ?: self::AFFILIATION,
            'index' => max(0, $index),
            'price' => $this->amount((int) ($variant['price_minor'] ?? $product['price_minor'] ?? $product['min_price'] ?? 0)),
            'quantity' => 1,
            'google_business_vertical' => 'retail',
        ];
        $category = trim((string) ($product['category_name'] ?? ''));
        if ($category !== '') {
            $item['item_category'] = $category;
        }
        $variantLabel = trim((string) ($variant['option_label'] ?? ''));
        if ($variantLabel !== '' && mb_strtolower($variantLabel) !== 'standard') {
            $item['item_variant'] = $variantLabel;
        }
        if ($listId !== null && $listId !== '') {
            $item['item_list_id'] = $listId;
        }
        if ($listName !== null && $listName !== '') {
            $item['item_list_name'] = $listName;
        }
        return $item;
    }

    public function productList(array $products, string $listId, string $listName): array
    {
        $items = [];
        foreach (array_values($products) as $index => $product) {
            $items[] = $this->productItem($product, $index, $listId, $listName);
        }
        return [
            'item_list_id' => $listId,
            'item_list_name' => $listName,
            'items' => $items,
        ];
    }

    public function productItemById(int $productId): ?array
    {
        if ($productId < 1) {
            return null;
        }
        $statement = Database::connection()->prepare(
            "SELECT p.id,p.name,p.sku,p.brand,c.name category_name,
                    COALESCE(v.price_minor,0) price_minor,v.default_variant_sku,v.variant_count
             FROM products p
             LEFT JOIN categories c ON c.id=p.primary_category_id
             LEFT JOIN (
                SELECT pv.product_id,MIN(pv.price_minor) price_minor,COUNT(*) variant_count,
                       SUBSTRING_INDEX(GROUP_CONCAT(pv.sku ORDER BY pv.id SEPARATOR '||'),'||',1) default_variant_sku
                FROM product_variants pv WHERE pv.is_active=1 GROUP BY pv.product_id
             ) v ON v.product_id=p.id
             WHERE p.id=? AND p.deleted_at IS NULL LIMIT 1"
        );
        $statement->execute([$productId]);
        $product = $statement->fetch();
        return $product ? $this->productItem($product) : null;
    }

    public function cartItem(array $item, int $index = 0, ?int $quantity = null): array
    {
        $analyticsItem = [
            'item_id' => trim((string) ($item['sku'] ?? $item['product_sku'] ?? '')) ?: 'PRODUS-' . (int) ($item['product_id'] ?? 0),
            'item_name' => trim((string) ($item['name'] ?? 'Produs')),
            'affiliation' => self::AFFILIATION,
            'item_brand' => trim((string) ($item['brand'] ?? '')) ?: self::AFFILIATION,
            'index' => max(0, $index),
            'price' => $this->amount((int) ($item['price_minor'] ?? 0)),
            'quantity' => max(1, $quantity ?? (int) ($item['quantity'] ?? 1)),
            'google_business_vertical' => 'retail',
        ];
        $category = trim((string) ($item['category_name'] ?? ''));
        if ($category !== '') {
            $analyticsItem['item_category'] = $category;
        }
        $variant = trim((string) ($item['variant_label'] ?? ''));
        if ($variant !== '' && mb_strtolower($variant) !== 'standard') {
            $analyticsItem['item_variant'] = $variant;
        }
        return $analyticsItem;
    }

    public function cartPayload(array $totals): array
    {
        $rawItems = array_values((array) ($totals['items'] ?? []));
        $discountMinor = max(0, (int) ($totals['discount_minor'] ?? 0));
        $items = $this->cartItemsWithDiscount($rawItems, $discountMinor);
        $subtotalMinor = (int) ($totals['subtotal_minor'] ?? array_sum(array_map(
            static fn (array $item): int => (int) ($item['price_minor'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1)),
            $rawItems
        )));
        $payload = [
            'currency' => self::CURRENCY,
            'value' => $this->amount(max(0, $subtotalMinor - $discountMinor)),
            'items' => $items,
        ];
        $coupon = trim((string) ($totals['coupon']['code'] ?? ''));
        if ($coupon !== '') {
            $payload['coupon'] = $coupon;
        }
        return $payload;
    }

    public function cartMutationPayload(array $items, array $quantityOverrides = []): array
    {
        $analyticsItems = [];
        $valueMinor = 0;
        foreach (array_values($items) as $index => $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $quantity = max(1, (int) ($quantityOverrides[$itemId] ?? $item['quantity'] ?? 1));
            $analyticsItems[] = $this->cartItem($item, $index, $quantity);
            $valueMinor += (int) ($item['price_minor'] ?? 0) * $quantity;
        }
        return [
            'currency' => self::CURRENCY,
            'value' => $this->amount($valueMinor),
            'items' => $analyticsItems,
        ];
    }

    /**
     * Rambursul este considerat conversie când comanda este plasată; plata Stripe
     * numai după confirmarea furnizorului. ID-ul unic permite deduplicarea GA4.
     */
    public function purchase(array $order, array $items): ?array
    {
        $paymentMethod = strtolower((string) ($order['payment_method'] ?? ''));
        $paymentStatus = strtolower((string) ($order['payment_status'] ?? ''));
        $orderStatus = strtolower((string) ($order['order_status'] ?? ''));

        if ($paymentMethod === 'stripe' && $paymentStatus !== 'paid') {
            return null;
        }
        if (in_array($orderStatus, ['cancelled', 'returned', 'refunded'], true)) {
            return null;
        }

        $transactionId = trim((string) ($order['order_number'] ?? ''));
        if ($transactionId === '' || $items === []) {
            return null;
        }

        $subtotalMinor = (int) ($order['subtotal_minor'] ?? array_sum(array_map(
            static fn (array $item): int => (int) ($item['total_minor'] ?? 0),
            $items
        )));
        $discountMinor = max(0, (int) ($order['discount_total_minor'] ?? 0));
        $payload = [
            'transaction_id' => $transactionId,
            'affiliation' => self::AFFILIATION,
            'value' => $this->amount(max(0, $subtotalMinor - $discountMinor)),
            'tax' => $this->amount((int) ($order['tax_total_minor'] ?? 0)),
            'shipping' => $this->amount((int) ($order['shipping_total_minor'] ?? 0)),
            'currency' => strtoupper((string) ($order['currency'] ?? self::CURRENCY)),
            'items' => $this->orderItemsWithDiscount($items, $discountMinor),
        ];
        $coupon = trim((string) ($order['coupon_code'] ?? ''));
        if ($coupon !== '') {
            $payload['coupon'] = $coupon;
        }
        return $payload;
    }

    public function refund(array $order, array $items): ?array
    {
        $transactionId = trim((string) ($order['order_number'] ?? ''));
        $paymentMethod = strtolower((string) ($order['payment_method'] ?? ''));
        $paymentStatus = strtolower((string) ($order['payment_status'] ?? ''));
        if ($transactionId === '' || $items === [] || ($paymentMethod === 'stripe' && $paymentStatus !== 'paid')) {
            return null;
        }
        $subtotalMinor = (int) ($order['subtotal_minor'] ?? array_sum(array_map(
            static fn (array $item): int => (int) ($item['total_minor'] ?? 0),
            $items
        )));
        $discountMinor = max(0, (int) ($order['discount_total_minor'] ?? 0));
        $payload = [
            'transaction_id' => $transactionId,
            'value' => $this->amount(max(0, $subtotalMinor - $discountMinor)),
            'tax' => $this->amount((int) ($order['tax_total_minor'] ?? 0)),
            'shipping' => $this->amount((int) ($order['shipping_total_minor'] ?? 0)),
            'currency' => strtoupper((string) ($order['currency'] ?? self::CURRENCY)),
            'items' => $this->orderItemsWithDiscount($items, $discountMinor),
        ];
        $coupon = trim((string) ($order['coupon_code'] ?? ''));
        if ($coupon !== '') {
            $payload['coupon'] = $coupon;
        }
        return $payload;
    }

    private function cartItemsWithDiscount(array $items, int $discountMinor): array
    {
        $lineValues = array_map(
            static fn (array $item): int => max(0, (int) ($item['price_minor'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1))),
            $items
        );
        $discounts = $this->allocateDiscount($lineValues, $discountMinor);
        $analyticsItems = [];
        foreach ($items as $index => $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $analyticsItem = $this->cartItem($item, $index, $quantity);
            if (($discounts[$index] ?? 0) > 0) {
                $analyticsItem['discount'] = $this->amount((int) round($discounts[$index] / $quantity));
            }
            $analyticsItems[] = $analyticsItem;
        }
        return $analyticsItems;
    }

    private function orderItemsWithDiscount(array $items, int $discountMinor): array
    {
        $lineValues = array_map(
            static fn (array $item): int => max(0, (int) ($item['total_minor'] ?? 0)),
            $items
        );
        $discounts = $this->allocateDiscount($lineValues, $discountMinor);
        $analyticsItems = [];
        foreach (array_values($items) as $index => $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $analyticsItem = [
                'item_id' => trim((string) ($item['sku_snapshot'] ?? '')) ?: 'PRODUS-' . (int) ($item['product_id'] ?? 0),
                'item_name' => trim((string) ($item['name_snapshot'] ?? 'Produs')),
                'affiliation' => self::AFFILIATION,
                'index' => $index,
                'price' => $this->amount((int) ($item['unit_price_minor'] ?? 0)),
                'quantity' => $quantity,
                'google_business_vertical' => 'retail',
            ];
            if (($discounts[$index] ?? 0) > 0) {
                $analyticsItem['discount'] = $this->amount((int) round($discounts[$index] / $quantity));
            }
            $options = json_decode((string) ($item['options_json'] ?? ''), true);
            $variant = is_array($options) ? trim((string) ($options['label'] ?? '')) : '';
            if ($variant !== '' && mb_strtolower($variant) !== 'standard') {
                $analyticsItem['item_variant'] = $variant;
            }
            $analyticsItems[] = $analyticsItem;
        }
        return $analyticsItems;
    }

    private function allocateDiscount(array $lineValues, int $discountMinor): array
    {
        $discountMinor = max(0, $discountMinor);
        $baseMinor = max(1, array_sum($lineValues));
        $remaining = min($discountMinor, $baseMinor);
        $lastIndex = count($lineValues) - 1;
        $allocated = [];
        foreach ($lineValues as $index => $lineMinor) {
            $lineDiscount = $index === $lastIndex
                ? $remaining
                : min($remaining, (int) round($discountMinor * ($lineMinor / $baseMinor)));
            $allocated[$index] = $lineDiscount;
            $remaining -= $lineDiscount;
        }
        return $allocated;
    }

    private function amount(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
