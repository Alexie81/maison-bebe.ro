<?php
declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$pdo = MaisonBebe\Core\Database::connection();
echo 'Database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'MySQL: ' . $pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;

