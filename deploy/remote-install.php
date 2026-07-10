<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'state' => 'method_not_allowed']);
    exit;
}

$home = dirname(__DIR__);
$configPath = $home . '/.maison-db-env';
$installPath = $home . '/.maison-install';
$lockPath = $home . '/.maison-install.lock';

function parseConfig(string $path): array
{
    $result = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $result[$key] = $value;
    }
    return $result;
}

function splitSql(string $sql): array
{
    $statements = []; $buffer = ''; $quote = null; $escaped = false; $length = strlen($sql);
    for ($index = 0; $index < $length; $index++) {
        $char = $sql[$index]; $next = $index + 1 < $length ? $sql[$index + 1] : '';
        if ($quote === null && $char === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
            while ($index < $length && $sql[$index] !== "\n") { $index++; } $buffer .= "\n"; continue;
        }
        if ($quote === null && $char === '#') {
            while ($index < $length && $sql[$index] !== "\n") { $index++; } $buffer .= "\n"; continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $index += 2; while ($index + 1 < $length && !($sql[$index] === '*' && $sql[$index + 1] === '/')) { $index++; } $index++; continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\') { $escaped = true; continue; }
            if ($char === $quote) { if ($next === $quote && $quote !== '`') { $buffer .= $next; $index++; } else { $quote = null; } }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; $buffer .= $char; continue; }
        if ($char === ';') { if (($trimmed = trim($buffer)) !== '') { $statements[] = $trimmed; } $buffer = ''; continue; }
        $buffer .= $char;
    }
    if (($trimmed = trim($buffer)) !== '') { $statements[] = $trimmed; }
    return $statements;
}

function runSqlFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) { throw new RuntimeException('Fișier SQL absent.'); }
    foreach (splitSql($sql) as $statement) { $pdo->exec($statement); }
}

if (!is_file($configPath)) {
    http_response_code(503); echo json_encode(['ok' => false, 'state' => 'not_configured']); exit;
}
$config = parseConfig($configPath);
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) ? $matches[1] : '';
if (!isset($config['REMOTE_HEALTH_TOKEN']) || !hash_equals($config['REMOTE_HEALTH_TOKEN'], $token)) {
    http_response_code(401); echo json_encode(['ok' => false, 'state' => 'unauthorized']); exit;
}
if (is_file($lockPath)) {
    http_response_code(409); echo json_encode(['ok' => false, 'state' => 'already_installed']); exit;
}

try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_PORT'], $config['DB_DATABASE']), $config['DB_USERNAME'], $config['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $hasSchema = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn();
    if (!$hasSchema) { runSqlFile($pdo, $installPath . '/schema.sql'); }
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(190) NOT NULL UNIQUE, batch INT UNSIGNED NOT NULL, executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
    foreach (glob($installPath . '/migrations/*.sql') ?: [] as $migration) {
        $name = basename($migration); $check = $pdo->prepare('SELECT 1 FROM migrations WHERE migration = ?'); $check->execute([$name]);
        if (!$check->fetchColumn()) { runSqlFile($pdo, $migration); $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)')->execute([$name, $batch]); }
    }
    runSqlFile($pdo, $installPath . '/seed.sql');
    if (!empty($config['SMTP_PASSWORD'])) {
        $key = base64_decode($config['APP_ENCRYPTION_KEY'], true); $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($config['SMTP_PASSWORD'], 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag); $encrypted = base64_encode($iv . $tag . $cipher);
        $sql = "INSERT INTO email_senders (purpose,from_email,from_name,reply_to_email,smtp_host,smtp_port,smtp_encryption,smtp_username,encrypted_password,is_active) VALUES (?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE from_email=VALUES(from_email),from_name=VALUES(from_name),reply_to_email=VALUES(reply_to_email),smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),smtp_username=VALUES(smtp_username),encrypted_password=VALUES(encrypted_password),is_active=1";
        $statement = $pdo->prepare($sql);
        foreach (['orders','invoices','account','general'] as $purpose) {
            $statement->execute([$purpose,$config['MAIL_FROM_ADDRESS'],$config['MAIL_FROM_NAME'],$config['MAIL_FROM_ADDRESS'],$config['SMTP_HOST'],(int)$config['SMTP_PORT'],$config['SMTP_ENCRYPTION'],$config['SMTP_USERNAME'],$encrypted]);
        }
    }
    file_put_contents($lockPath, gmdate('c') . PHP_EOL, LOCK_EX);
    $tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    echo json_encode(['ok' => true, 'state' => 'installed', 'tables' => $tables], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500); error_log($exception->__toString()); echo json_encode(['ok' => false, 'state' => 'install_failed']);
}

