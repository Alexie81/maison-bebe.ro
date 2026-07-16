<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class GiftBoxService
{
    public function configuratorEnabled(): bool
    {
        $statement = Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=? LIMIT 1');
        $statement->execute(['gift_box_configurator']);
        $value = $statement->fetchColumn();
        if ($value === false) {
            return true;
        }
        $decoded = json_decode((string) $value, true);
        return (bool) ($decoded['enabled'] ?? true);
    }

    public function templates(bool $activeOnly = true): array
    {
        $where = ['t.deleted_at IS NULL'];
        if ($activeOnly) {
            $where[] = 't.is_active=1';
        }

        $sql = "SELECT t.id,t.product_id,t.image_id,t.name,t.slug,t.description,t.base_price_minor,t.stock_qty,t.min_components,t.max_components,t.rules_json,t.is_active,t.sort_order,
                       p.slug product_slug,p.status product_status,v.variant_id,COALESCE(v.price_minor,t.base_price_minor) price_minor,COALESCE(v.stock_qty,t.stock_qty,0) stock_qty,
                       COALESCE(tm.path,pm.path,'/assets/images/giftbox-clean-v4.png') image_path
                FROM gift_box_templates t
                LEFT JOIN products p ON p.id=t.product_id
                LEFT JOIN (
                    SELECT product_id,MIN(id) variant_id,MIN(price_minor) price_minor,SUM(stock_qty) stock_qty
                    FROM product_variants WHERE is_active=1 GROUP BY product_id
                ) v ON v.product_id=p.id
                LEFT JOIN media_assets tm ON tm.id=t.image_id
                LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
                LEFT JOIN media_assets pm ON pm.id=pi.media_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.sort_order,t.id";
        return Database::connection()->query($sql)->fetchAll();
    }

    public function componentsFor(?int $templateId = null): array
    {
        if ($templateId) {
            $template = $this->template($templateId);
            $rules = json_decode((string)($template['rules_json'] ?? ''), true);
            if (is_array($rules) && array_key_exists('catalog_scope', $rules)) {
                return $this->componentsMatchingRules($rules);
            }
            $configured = $this->configuredComponents($templateId);
            if ($configured) {
                return $configured;
            }
        }
        return $this->fallbackComponents();
    }

    private function componentsMatchingRules(array $rules): array
    {
        $components = $this->fallbackComponents();
        $productIds = array_values(array_unique(array_map('intval',(array)($rules['product_ids'] ?? []))));
        $categoryIds = array_values(array_unique(array_map('intval',(array)($rules['category_ids'] ?? []))));
        $collectionIds = array_values(array_unique(array_map('intval',(array)($rules['collection_ids'] ?? []))));
        if (!$productIds && !$categoryIds && !$collectionIds) {
            return $components;
        }
        return array_values(array_filter($components, static function(array $component) use ($productIds,$categoryIds,$collectionIds): bool {
            $categories = array_map('intval',array_filter(explode(',',(string)($component['category_ids'] ?? ''))));
            $collections = array_map('intval',array_filter(explode(',',(string)($component['collection_ids'] ?? ''))));
            return in_array((int)$component['product_id'],$productIds,true)
                || (bool)array_intersect($categories,$categoryIds)
                || (bool)array_intersect($collections,$collectionIds);
        }));
    }

    public function addConfiguredBox(array $payload, CartService $cart): array
    {
        if (!$this->configuratorEnabled()) {
            throw new HttpException(403, 'Personalizarea Gift Box este dezactivată momentan.');
        }

        $templateId = (int) ($payload['template_id'] ?? $payload['template'] ?? 0);
        $template = $this->template($templateId);
        if (!$template || empty($template['variant_id'])) {
            throw new HttpException(422, 'Cutia aleasă nu mai este disponibilă.');
        }
        if ((int) ($template['stock_qty'] ?? 0) < 1) {
            throw new HttpException(422, 'Cutia aleasă nu mai este în stoc.');
        }

        $selected = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['components'] ?? [])))));
        $min = max(0, (int) ($template['min_components'] ?? 1));
        $max = max($min, (int) ($template['max_components'] ?? 6));
        if (count($selected) < $min || count($selected) > $max) {
            throw new HttpException(422, 'Alege între ' . $min . ' și ' . $max . ' produse pentru această cutie.');
        }

        $available = $this->componentsFor((int) $template['id']);
        $availableByVariant = [];
        foreach ($available as $item) {
            $availableByVariant[(int) $item['variant_id']] = $item;
        }

        $components = [];
        foreach ($selected as $variantId) {
            if (!isset($availableByVariant[$variantId])) {
                throw new HttpException(422, 'Un produs ales nu este disponibil pentru această cutie.');
            }
            $component = $availableByVariant[$variantId];
            if ((int) $component['stock_qty'] < 1) {
                throw new HttpException(422, $component['name'] . ' nu mai este în stoc.');
            }
            $components[] = $component;
        }

        $recipient = trim((string) ($payload['recipient_name'] ?? ''));
        $message = mb_substr(trim((string) ($payload['gift_message'] ?? $payload['message'] ?? '')), 0, 500);
        $editGroup = strtoupper(trim((string) ($payload['edit_group'] ?? '')));
        if ($editGroup !== '' && !preg_match('/^GB-[A-F0-9]{8}$/', $editGroup)) {
            throw new HttpException(422, 'Gift Box-ul ales pentru editare nu este valid.');
        }
        if ($editGroup !== '') {
            $cart->removeGiftBoxGroup($editGroup);
        }
        $group = $editGroup !== '' ? $editGroup : 'GB-' . strtoupper(bin2hex(random_bytes(4)));
        $componentSummary = array_map(static fn(array $item): array => [
            'variant_id' => (int) $item['variant_id'],
            'product_id' => (int) $item['product_id'],
            'name' => $item['name'],
            'variant' => $item['variant_label'] ?: 'Standard',
            'price_minor' => (int) $item['price_minor'],
        ], $components);

        $boxCustomization = [
            'type' => 'gift_box',
            'role' => 'box',
            'group' => $group,
            'template_id' => (int) $template['id'],
            'template_name' => $template['name'],
            'recipient_name' => $recipient,
            'gift_message' => $message,
            'components' => $componentSummary,
        ];

        $boxItem = $cart->add((int) $template['variant_id'], 1, $boxCustomization);
        foreach ($components as $component) {
            $cart->add((int) $component['variant_id'], 1, [
                'type' => 'gift_box',
                'role' => 'component',
                'group' => $group,
                'template_id' => (int) $template['id'],
                'template_name' => $template['name'],
                'gift_message' => $message,
            ]);
        }

        $snapshot = (int) ($template['price_minor'] ?? $template['base_price_minor'] ?? 0) + array_sum(array_map(static fn(array $item): int => (int) $item['price_minor'], $components));
        if (!empty($boxItem['item_id'])) {
            Database::connection()->prepare('INSERT INTO gift_box_customizations (template_id,cart_item_id,recipient_name,gift_message,components_json,price_snapshot_minor) VALUES (?,?,?,?,?,?)')
                ->execute([(int) $template['id'], (int) $boxItem['item_id'], $recipient ?: null, $message ?: null, json_encode($componentSummary, JSON_UNESCAPED_UNICODE), $snapshot]);
        }

        return [
            'group' => $group,
            'box' => $boxItem,
            'components' => $componentSummary,
            'cart_count' => $cart->count(),
        ];
    }

    public function template(int $id): ?array
    {
        foreach ($this->templates(true) as $template) {
            if ((int) ($template['id'] ?? 0) === $id) {
                return $template;
            }
        }
        return null;
    }

    private function configuredComponents(int $templateId): array
    {
        $statement = Database::connection()->prepare("SELECT v.id variant_id,p.id product_id,p.name,p.slug,p.short_description,v.price_minor,v.stock_qty,
                   COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_label,
                   COALESCE(m.path,'/assets/images/packaging-reference.png') image_path,
                   COALESCE(c.name,'Selecție') category_name,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT pc.category_id ORDER BY pc.category_id) FROM product_categories pc WHERE pc.product_id=p.id),CAST(c.id AS CHAR),'') category_ids,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT pc_cat.name ORDER BY pc_cat.name SEPARATOR '||') FROM product_categories pc JOIN categories pc_cat ON pc_cat.id=pc.category_id WHERE pc.product_id=p.id),c.name,'Selecție') category_names,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT cp.collection_id ORDER BY cp.collection_id) FROM collection_products cp WHERE cp.product_id=p.id),'') collection_ids,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT col.name ORDER BY col.name SEPARATOR '||') FROM collection_products cp JOIN collections col ON col.id=cp.collection_id AND col.deleted_at IS NULL WHERE cp.product_id=p.id),'') collection_names,
                   gbc.sort_order
            FROM gift_box_components gbc
            JOIN product_variants v ON v.id=gbc.variant_id AND v.is_active=1
            JOIN products p ON p.id=v.product_id AND p.status='active' AND p.deleted_at IS NULL
            LEFT JOIN categories c ON c.id=gbc.category_id
            LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
            LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
            LEFT JOIN product_options po ON po.id=ov.option_id
            LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
            LEFT JOIN media_assets m ON m.id=pi.media_id
            WHERE gbc.template_id=? AND v.stock_qty>0
            GROUP BY v.id
            ORDER BY gbc.sort_order,p.name");
        $statement->execute([$templateId]);
        return $statement->fetchAll();
    }

    private function fallbackComponents(): array
    {
        return Database::connection()->query("SELECT v.id variant_id,p.id product_id,p.name,p.slug,p.short_description,v.price_minor,v.stock_qty,
                   COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_label,
                   COALESCE(m.path,'/assets/images/packaging-reference.png') image_path,
                   COALESCE(c.name,'Selecție') category_name,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT pc.category_id ORDER BY pc.category_id) FROM product_categories pc WHERE pc.product_id=p.id),CAST(c.id AS CHAR),'') category_ids,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT pc_cat.name ORDER BY pc_cat.name SEPARATOR '||') FROM product_categories pc JOIN categories pc_cat ON pc_cat.id=pc.category_id WHERE pc.product_id=p.id),c.name,'Selecție') category_names,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT cp.collection_id ORDER BY cp.collection_id) FROM collection_products cp WHERE cp.product_id=p.id),'') collection_ids,
                   COALESCE((SELECT GROUP_CONCAT(DISTINCT col.name ORDER BY col.name SEPARATOR '||') FROM collection_products cp JOIN collections col ON col.id=cp.collection_id AND col.deleted_at IS NULL WHERE cp.product_id=p.id),'') collection_names
            FROM product_variants v
            JOIN products p ON p.id=v.product_id AND p.status='active' AND p.deleted_at IS NULL
            LEFT JOIN categories c ON c.id=p.primary_category_id
            LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
            LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
            LEFT JOIN product_options po ON po.id=ov.option_id
            LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
            LEFT JOIN media_assets m ON m.id=pi.media_id
            WHERE v.is_active=1 AND v.stock_qty>0 AND p.is_gift_box=0
              AND NOT EXISTS (SELECT 1 FROM product_categories pc JOIN categories gc ON gc.id=pc.category_id WHERE pc.product_id=p.id AND gc.slug='gift-box')
            GROUP BY v.id
            ORDER BY p.is_featured DESC,p.name")->fetchAll();
    }
}
