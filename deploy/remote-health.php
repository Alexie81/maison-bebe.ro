<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$configPath = dirname(__DIR__) . '/.maison-db-env';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'state' => 'not_configured']);
    exit;
}

$config = [];
foreach (file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $config[$key] = $value;
}

$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) ? $matches[1] : '';
if (!isset($config['REMOTE_HEALTH_TOKEN']) || !hash_equals($config['REMOTE_HEALTH_TOKEN'], $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'state' => 'unauthorized']);
    exit;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_PORT'], $config['DB_DATABASE']),
        $config['DB_USERNAME'],
        $config['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    echo json_encode([
        'ok' => true,
        'php' => PHP_VERSION,
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'mysql' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'tables' => $tableCount,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'state' => 'database_unavailable']);
}

