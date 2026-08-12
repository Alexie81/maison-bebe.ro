<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\GiftBoxService;

$pdo = Database::connection();
$slug = 'qa-box-dimensions-' . strtolower(bin2hex(random_bytes(6)));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO gift_box_templates '
        . '(name,slug,base_price_minor,stock_qty,length_cm,width_cm,height_cm,is_active,sort_order) '
        . 'VALUES (?,?,0,0,NULL,NULL,NULL,0,999999)'
    )->execute(['Cutie test dimensiuni opționale', $slug]);
    $templateId = (int) $pdo->lastInsertId();

    $statement = $pdo->prepare('SELECT length_cm,width_cm,height_cm FROM gift_box_templates WHERE id=?');
    $statement->execute([$templateId]);
    $emptyDimensions = $statement->fetch();
    $assert($emptyDimensions !== false, 'Cutia fără dimensiuni nu a fost salvată.');
    $assert($emptyDimensions['length_cm'] === null, 'Lungimea goală trebuie să rămână opțională.');
    $assert($emptyDimensions['width_cm'] === null, 'Lățimea goală trebuie să rămână opțională.');
    $assert($emptyDimensions['height_cm'] === null, 'Înălțimea goală trebuie să rămână opțională.');

    $pdo->prepare('UPDATE gift_box_templates SET length_cm=?,width_cm=?,height_cm=? WHERE id=?')
        ->execute([27.5, 17, 7.25, $templateId]);

    $template = null;
    foreach ((new GiftBoxService())->templates(false) as $row) {
        if ((int) $row['id'] === $templateId) {
            $template = $row;
            break;
        }
    }
    $assert(is_array($template), 'Cutia nu este disponibilă în serviciul Gift Box.');
    $assert(abs((float) $template['length_cm'] - 27.5) < 0.001, 'Lungimea nu este citită corect.');
    $assert(abs((float) $template['width_cm'] - 17.0) < 0.001, 'Lățimea nu este citită corect.');
    $assert(abs((float) $template['height_cm'] - 7.25) < 0.001, 'Înălțimea nu este citită corect.');

    echo "Gift Box optional dimensions regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Gift Box optional dimensions regression test: FAIL - {$exception->getMessage()}\n");
    exit(1);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

