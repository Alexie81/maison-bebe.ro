<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;

foreach (Database::connection()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    echo $table . PHP_EOL;
}

