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
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='product_personalization_options'"
        );
        if ((int) $exists->fetchColumn() > 0) return;
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
    }

    public function withSnapshot(
        int $variantId,
        int $optionId,
        string $childName,
        string $birthDate,
        array $customization,
        PDO $pdo
    ): array {
        unset(
            $customization['personalization_option_id'],
            $customization['personalization_child_name'],
            $customization['personalization_birth_date'],
            $customization['personalization'],
            $customization['personalization_total_minor']
        );
        if ($optionId < 1) return $customization;

        $this->ensureSchema($pdo);
        $childName = trim((string) preg_replace('/\s+/u', ' ', $childName));
        if ($childName === '' || mb_strlen($childName) < 2 || mb_strlen($childName) > 120) {
            throw new HttpException(422, 'Completează numele copilului pentru personalizare.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $birthDate) {
            throw new HttpException(422, 'Data nașterii copilului nu este validă.');
        }
        $today = new DateTimeImmutable('today');
        if ($date > $today || $date < new DateTimeImmutable('1900-01-01')) {
            throw new HttpException(422, 'Data nașterii copilului trebuie să fie o dată trecută validă.');
        }

        $statement = $pdo->prepare(
            'SELECT option_row.id,option_row.name,option_row.price_delta_minor '
            . 'FROM product_personalization_options option_row '
            . 'JOIN product_variants variant ON variant.product_id=option_row.product_id '
            . 'WHERE variant.id=? AND option_row.id=? AND option_row.is_active=1 LIMIT 1'
        );
        $statement->execute([$variantId, $optionId]);
        $option = $statement->fetch();
        if (!$option) throw new HttpException(422, 'Opțiunea de personalizare nu mai este disponibilă pentru acest produs.');

        $formattedDate = $date->format('d.m.Y');
        $customization['personalization'] = [
            'option_id' => (int) $option['id'],
            'option_name' => (string) $option['name'],
            'price_delta_minor' => (int) $option['price_delta_minor'],
            'child_name' => $childName,
            'birth_date' => $birthDate,
            'birth_date_formatted' => $formattedDate,
            'instructions' => (string) $option['name'] . ' — Nume copil: ' . $childName . ' · Data nașterii: ' . $formattedDate,
        ];
        $customization['personalization_total_minor'] = (int) $option['price_delta_minor'];
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
