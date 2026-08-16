<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class ProductPersonalizationService
{
    public function ensureSchema(?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $tables = $pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() "
            . "AND table_name IN ('product_personalization_options','product_personalization_settings')"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('product_personalization_options', $tables, true)) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS product_personalization_options ('
                . 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . 'product_id BIGINT UNSIGNED NOT NULL,'
                . 'name VARCHAR(190) NOT NULL,'
                . 'price_delta_minor INT UNSIGNED NOT NULL DEFAULT 0,'
                . 'is_active TINYINT(1) NOT NULL DEFAULT 1,'
                . 'sort_order INT NOT NULL DEFAULT 0,'
                . 'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . 'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . 'KEY idx_personalization_product (product_id,is_active,sort_order),'
                . 'CONSTRAINT fk_personalization_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
        if (!in_array('product_personalization_settings', $tables, true)) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS product_personalization_settings ('
                . 'product_id BIGINT UNSIGNED PRIMARY KEY,'
                . "child_name_label VARCHAR(120) NOT NULL DEFAULT 'Numele copilului',"
                . "event_date_label VARCHAR(120) NOT NULL DEFAULT 'Data botezului/nașterii',"
                . 'helper_text VARCHAR(255) NOT NULL,'
                . 'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . 'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . 'CONSTRAINT fk_personalization_settings_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    public function forProduct(int $productId, bool $activeOnly = false, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $this->ensureSchema($pdo);
        $sql = 'SELECT * FROM product_personalization_options WHERE product_id=?';
        if ($activeOnly) $sql .= ' AND is_active=1';
        $sql .= ' ORDER BY sort_order,id';
        $statement = $pdo->prepare($sql);
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    public function defaultSettings(bool $eventDate = false): array
    {
        $dateLabel = $eventDate ? 'Data evenimentului' : 'Data botezului/nașterii';
        return [
            'child_name_label' => 'Numele copilului',
            'event_date_label' => $dateLabel,
            'helper_text' => 'Numele copilului și ' . mb_strtolower($dateLabel, 'UTF-8') . ' se completează o singură dată mai jos',
        ];
    }

    public function settingsForProduct(int $productId, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $this->ensureSchema($pdo);
        $statement = $pdo->prepare('SELECT child_name_label,event_date_label,helper_text FROM product_personalization_settings WHERE product_id=? LIMIT 1');
        $statement->execute([$productId]);
        $stored = $statement->fetch();
        if ($stored) {
            return [
                'child_name_label' => (string) $stored['child_name_label'],
                'event_date_label' => (string) $stored['event_date_label'],
                'helper_text' => (string) $stored['helper_text'],
            ];
        }

        $identity = $pdo->prepare(
            "SELECT CONCAT_WS(' ',p.name,p.slug,COALESCE(GROUP_CONCAT(DISTINCT c.name SEPARATOR ' '),'')) "
            . 'FROM products p '
            . 'LEFT JOIN product_categories pc ON pc.product_id=p.id '
            . 'LEFT JOIN categories c ON c.id=pc.category_id AND c.deleted_at IS NULL '
            . 'WHERE p.id=? GROUP BY p.id'
        );
        $identity->execute([$productId]);
        return $this->defaultSettings($this->isTrayIdentity((string) $identity->fetchColumn()));
    }

    public function save(int $productId, array $input, PDO $pdo): void
    {
        $this->ensureSchema($pdo);
        $ids = (array) ($input['personalization_option_id'] ?? []);
        $names = (array) ($input['personalization_option_name'] ?? []);
        $prices = (array) ($input['personalization_option_price'] ?? []);
        $kept = [];
        $upsert = $pdo->prepare(
            'INSERT INTO product_personalization_options (id,product_id,name,price_delta_minor,is_active,sort_order) VALUES (?,?,?,?,1,?) '
            . 'ON DUPLICATE KEY UPDATE name=VALUES(name),price_delta_minor=VALUES(price_delta_minor),is_active=1,sort_order=VALUES(sort_order),updated_at=NOW()'
        );
        foreach ($names as $index => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') continue;
            $price = (int) round((float) str_replace(',', '.', (string) ($prices[$index] ?? '0')) * 100);
            if ($price < 0) throw new HttpException(422, 'Prețul personalizării nu poate fi negativ.');
            $id = max(0, (int) ($ids[$index] ?? 0));
            if ($id > 0) {
                $owner = $pdo->prepare('SELECT product_id FROM product_personalization_options WHERE id=?');
                $owner->execute([$id]);
                if ((int) $owner->fetchColumn() !== $productId) {
                    throw new HttpException(422, 'Opțiunea de personalizare nu aparține acestui produs.');
                }
            } else {
                $id = null;
            }
            $upsert->execute([$id, $productId, mb_substr($name, 0, 190), $price, $index * 10]);
            $kept[] = $id ?: (int) $pdo->lastInsertId();
        }
        if ($kept) {
            $placeholders = implode(',', array_fill(0, count($kept), '?'));
            $pdo->prepare("DELETE FROM product_personalization_options WHERE product_id=? AND id NOT IN ({$placeholders})")
                ->execute([$productId, ...$kept]);
        } else {
            $pdo->prepare('DELETE FROM product_personalization_options WHERE product_id=?')->execute([$productId]);
        }
        $this->saveSettings($productId, $input, $pdo);
    }

    private function saveSettings(int $productId, array $input, PDO $pdo): void
    {
        if (!array_key_exists('personalization_child_name_label', $input)
            && !array_key_exists('personalization_event_date_label', $input)
            && !array_key_exists('personalization_helper_text', $input)) {
            return;
        }
        $defaults = $this->settingsForProduct($productId, $pdo);
        $childNameLabel = trim((string) ($input['personalization_child_name_label'] ?? '')) ?: $defaults['child_name_label'];
        $eventDateLabel = trim((string) ($input['personalization_event_date_label'] ?? '')) ?: $defaults['event_date_label'];
        $helperText = trim((string) ($input['personalization_helper_text'] ?? ''))
            ?: $childNameLabel . ' și ' . mb_strtolower($eventDateLabel, 'UTF-8') . ' se completează o singură dată mai jos';
        $pdo->prepare(
            'INSERT INTO product_personalization_settings (product_id,child_name_label,event_date_label,helper_text) VALUES (?,?,?,?) '
            . 'ON DUPLICATE KEY UPDATE child_name_label=VALUES(child_name_label),event_date_label=VALUES(event_date_label),helper_text=VALUES(helper_text),updated_at=NOW()'
        )->execute([
            $productId,
            mb_substr($childNameLabel, 0, 120),
            mb_substr($eventDateLabel, 0, 120),
            mb_substr($helperText, 0, 255),
        ]);
    }

    private function isTrayIdentity(string $identity): bool
    {
        $identity = mb_strtolower($identity, 'UTF-8');
        $identity = strtr($identity, ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t']);
        $identity = preg_replace('/[^a-z0-9]+/u', ' ', $identity) ?? '';
        return preg_match('/\b(?:tavita|tavite)\b/u', $identity) === 1;
    }

    public function withSnapshot(
        int $variantId,
        int|array $optionIds,
        string $childName,
        string $birthDate,
        array $customization,
        PDO $pdo
    ): array {
        $optionIds = is_array($optionIds) ? $optionIds : [$optionIds];
        $optionIds = array_slice(array_values(array_unique(array_filter(array_map('intval', $optionIds), static fn (int $id): bool => $id > 0))), 0, 30);
        unset(
            $customization['personalization_option_id'],
            $customization['personalization_option_ids'],
            $customization['personalization_child_name'],
            $customization['personalization_birth_date'],
            $customization['personalization'],
            $customization['personalization_total_minor']
        );
        if (!$optionIds) return $customization;

        $this->ensureSchema($pdo);
        $productStatement = $pdo->prepare('SELECT product_id FROM product_variants WHERE id=? AND is_active=1 LIMIT 1');
        $productStatement->execute([$variantId]);
        $productId = (int) $productStatement->fetchColumn();
        if ($productId <= 0) {
            throw new HttpException(422, 'Varianta produsului nu mai este disponibilă.');
        }
        $settings = $this->settingsForProduct($productId, $pdo);
        $childNameLabel = (string) $settings['child_name_label'];
        $eventDateLabel = (string) $settings['event_date_label'];
        $childName = trim((string) preg_replace('/\s+/u', ' ', $childName));
        if ($childName === '' || mb_strlen($childName) < 2 || mb_strlen($childName) > 120) {
            throw new HttpException(422, 'Completează câmpul „' . $childNameLabel . '” pentru personalizare.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $birthDate) {
            throw new HttpException(422, 'Câmpul „' . $eventDateLabel . '” nu conține o dată validă.');
        }
        if ($date < new DateTimeImmutable('1900-01-01')) {
            throw new HttpException(422, 'Câmpul „' . $eventDateLabel . '” trebuie să fie ulterior datei de 01.01.1900.');
        }

        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $statement = $pdo->prepare(
            'SELECT option_row.id,option_row.name,option_row.price_delta_minor '
            . 'FROM product_personalization_options option_row '
            . 'JOIN product_variants variant ON variant.product_id=option_row.product_id '
            . "WHERE variant.id=? AND option_row.id IN ({$placeholders}) AND option_row.is_active=1 "
            . 'ORDER BY option_row.sort_order,option_row.id'
        );
        $statement->execute([$variantId, ...$optionIds]);
        $optionRows = $statement->fetchAll();
        if (count($optionRows) !== count($optionIds)) {
            throw new HttpException(422, 'Una dintre opțiunile de personalizare nu mai este disponibilă pentru acest produs.');
        }

        $formattedDate = $date->format('d.m.Y');
        $options = array_map(static fn (array $option): array => [
            'option_id' => (int) $option['id'],
            'option_name' => (string) $option['name'],
            'price_delta_minor' => (int) $option['price_delta_minor'],
        ], $optionRows);
        $optionNames = array_column($options, 'option_name');
        $totalPrice = array_sum(array_column($options, 'price_delta_minor'));
        $customization['personalization'] = [
            'option_id' => (int) $options[0]['option_id'],
            'option_ids' => array_column($options, 'option_id'),
            'options' => $options,
            'option_name' => implode(' + ', $optionNames),
            'price_delta_minor' => $totalPrice,
            'child_name' => $childName,
            'child_name_label' => $childNameLabel,
            'birth_date' => $birthDate,
            'birth_date_formatted' => $formattedDate,
            'event_date' => $birthDate,
            'event_date_formatted' => $formattedDate,
            'event_date_label' => $eventDateLabel,
            'instructions' => implode(', ', $optionNames) . ' — ' . $childNameLabel . ': ' . $childName . ' · ' . $eventDateLabel . ': ' . $formattedDate,
        ];
        $customization['personalization_total_minor'] = $totalPrice;
        return $customization;
    }

    public function unitPrice(int $basePriceMinor, array $customization): int
    {
        return max(0, $basePriceMinor + (int) ($customization['personalization_total_minor'] ?? 0));
    }

    public function label(array $customization): string
    {
        return trim((string) ($customization['personalization']['option_name'] ?? ''));
    }
}
