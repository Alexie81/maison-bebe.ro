<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

final class GoogleAnalyticsService
{
    /**
     * Construiește evenimentul ecommerce GA4 fără date personale.
     * Rambursul este o achiziție la plasarea comenzii; cardul numai după confirmarea Stripe.
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
        $itemRevenueMinor = max(0, $subtotalMinor - $discountMinor);
        $baseMinor = max(1, array_sum(array_map(
            static fn (array $item): int => max(0, (int) ($item['total_minor'] ?? 0)),
            $items
        )));
        $remainingDiscount = $discountMinor;
        $lastIndex = count($items) - 1;
        $analyticsItems = [];

        foreach (array_values($items) as $index => $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $lineMinor = max(0, (int) ($item['total_minor'] ?? 0));
            $lineDiscount = $index === $lastIndex
                ? $remainingDiscount
                : min($remainingDiscount, (int) round($discountMinor * ($lineMinor / $baseMinor)));
            $remainingDiscount -= $lineDiscount;

            $analyticsItem = [
                'item_id' => trim((string) ($item['sku_snapshot'] ?? '')) ?: 'PRODUS-' . (int) ($item['product_id'] ?? 0),
                'item_name' => trim((string) ($item['name_snapshot'] ?? 'Produs')),
                'affiliation' => 'Maison Bébé',
                'index' => $index,
                'price' => $this->amount((int) ($item['unit_price_minor'] ?? 0)),
                'quantity' => $quantity,
            ];
            if ($lineDiscount > 0) {
                $analyticsItem['discount'] = $this->amount((int) round($lineDiscount / $quantity));
            }
            $options = json_decode((string) ($item['options_json'] ?? ''), true);
            $variantLabel = is_array($options) ? trim((string) ($options['label'] ?? '')) : '';
            if ($variantLabel !== '') {
                $analyticsItem['item_variant'] = $variantLabel;
            }
            $analyticsItems[] = $analyticsItem;
        }

        $payload = [
            'transaction_id' => $transactionId,
            'affiliation' => 'Maison Bébé',
            'value' => $this->amount($itemRevenueMinor),
            'tax' => $this->amount((int) ($order['tax_total_minor'] ?? 0)),
            'shipping' => $this->amount((int) ($order['shipping_total_minor'] ?? 0)),
            'currency' => strtoupper((string) ($order['currency'] ?? 'RON')),
            'items' => $analyticsItems,
        ];
        $coupon = trim((string) ($order['coupon_code'] ?? ''));
        if ($coupon !== '') {
            $payload['coupon'] = $coupon;
        }

        return $payload;
    }

    private function amount(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
