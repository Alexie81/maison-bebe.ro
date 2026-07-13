<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use PDO;

final class CouponEligibilityService
{
    /** @return array{eligible:bool,discount_minor:int,eligible_subtotal_minor:int,message:string} */
    public function evaluate(PDO $pdo, array $coupon, array $items, ?int $userId = null): array
    {
        $subtotal = array_sum(array_map(static fn(array $item): int => (int) $item['price_minor'] * (int) $item['quantity'], $items));
        if ($subtotal < (int) ($coupon['minimum_order_minor'] ?? 0)) {
            return $this->denied('Valoarea minimă pentru acest cupon nu a fost atinsă.');
        }

        $totalUses = $pdo->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id=?');
        $totalUses->execute([(int) $coupon['id']]);
        if (!empty($coupon['max_uses']) && (int) $totalUses->fetchColumn() >= (int) $coupon['max_uses']) {
            return $this->denied('Cuponul și-a atins limita totală de utilizări.');
        }
        if ($userId && !empty($coupon['max_uses_per_user'])) {
            $userUses = $pdo->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id=? AND user_id=?');
            $userUses->execute([(int) $coupon['id'], $userId]);
            if ((int) $userUses->fetchColumn() >= (int) $coupon['max_uses_per_user']) {
                return $this->denied('Ai folosit deja acest cupon de numărul maxim de ori.');
            }
        }

        $productRules = $pdo->prepare('SELECT product_id,mode FROM coupon_products WHERE coupon_id=?');
        $productRules->execute([(int) $coupon['id']]);
        $productRules = $productRules->fetchAll();
        $categoryRules = $pdo->prepare('SELECT category_id,mode FROM coupon_categories WHERE coupon_id=?');
        $categoryRules->execute([(int) $coupon['id']]);
        $categoryRules = $categoryRules->fetchAll();

        $includeProducts = $excludeProducts = $includeCategories = $excludeCategories = [];
        foreach ($productRules as $rule) {
            if (($rule['mode'] ?? 'include') === 'exclude') $excludeProducts[] = (int) $rule['product_id'];
            else $includeProducts[] = (int) $rule['product_id'];
        }
        foreach ($categoryRules as $rule) {
            if (($rule['mode'] ?? 'include') === 'exclude') $excludeCategories[] = (int) $rule['category_id'];
            else $includeCategories[] = (int) $rule['category_id'];
        }
        $hasIncludes = $includeProducts || $includeCategories;
        $productIds = array_values(array_unique(array_map(static fn(array $item): int => (int) $item['product_id'], $items)));
        $categoriesByProduct = [];
        if ($productIds && ($includeCategories || $excludeCategories)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $statement = $pdo->prepare("SELECT product_id,category_id FROM product_categories WHERE product_id IN ($placeholders)");
            $statement->execute($productIds);
            foreach ($statement->fetchAll() as $row) $categoriesByProduct[(int) $row['product_id']][] = (int) $row['category_id'];
        }

        $eligibleSubtotal = 0;
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $categories = $categoriesByProduct[$productId] ?? [];
            $included = !$hasIncludes || in_array($productId, $includeProducts, true) || (bool) array_intersect($categories, $includeCategories);
            $excluded = in_array($productId, $excludeProducts, true) || (bool) array_intersect($categories, $excludeCategories);
            if ($included && !$excluded) $eligibleSubtotal += (int) $item['price_minor'] * (int) $item['quantity'];
        }
        if ($eligibleSubtotal <= 0) return $this->denied('Produsele din coș nu sunt eligibile pentru acest cupon.');

        $discount = ($coupon['discount_type'] ?? '') === 'percent'
            ? (int) round($eligibleSubtotal * ((int) $coupon['discount_value'] / 100))
            : min((int) $coupon['discount_value'], $eligibleSubtotal);
        if (!empty($coupon['maximum_discount_minor'])) $discount = min($discount, (int) $coupon['maximum_discount_minor']);
        $discount = max(0, min($discount, $eligibleSubtotal, $subtotal));
        return ['eligible'=>true,'discount_minor'=>$discount,'eligible_subtotal_minor'=>$eligibleSubtotal,'message'=>''];
    }

    private function denied(string $message): array
    {
        return ['eligible'=>false,'discount_minor'=>0,'eligible_subtotal_minor'=>0,'message'=>$message];
    }
}