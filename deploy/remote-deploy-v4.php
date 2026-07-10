<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$configPath = dirname(__DIR__) . '/.maison-db-env';
$config = [];
foreach (is_file($configPath) ? (file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
    if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $config[$key] = $value;
    }
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) ? $matches[1] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($config['REMOTE_HEALTH_TOKEN']) || !hash_equals($config['REMOTE_HEALTH_TOKEN'], $token)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$archivePath = dirname(__DIR__) . '/maison-release-v4.zip';
$root = __DIR__;
try {
    if (!class_exists(ZipArchive::class) || !is_file($archivePath)) {
        throw new RuntimeException('Arhiva sau extensia ZIP lipsește.');
    }
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Arhiva nu poate fi deschisă.');
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
            $zip->close();
            throw new RuntimeException('Arhiva conține o cale invalidă.');
        }
    }
    if (!$zip->extractTo($root)) {
        $zip->close();
        throw new RuntimeException('Fișierele nu au putut fi extrase.');
    }
    $files = $zip->numFiles;
    $zip->close();

    foreach (['storage','storage/cache','storage/logs','storage/locks','storage/private_uploads','storage/invoices','public/uploads'] as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
        chmod($path, str_starts_with($directory, 'public/') ? 0755 : 0750);
    }

    require $root . '/bootstrap.php';
    $pdo = MaisonBebe\Core\Database::connection();
    $pdo->beginTransaction();
    $email = 'admin@maison-bebe.ro';
    $passwordHash = '$2y$10$FCkZewavjzrF8tOLqqizcuJfAnbLKr85nnbiM0NkudwArDi872XHy';
    $statement = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1 FOR UPDATE');
    $statement->execute([$email]);
    $userId = (int) $statement->fetchColumn();
    if ($userId) {
        $pdo->prepare("UPDATE users SET password_hash=?,first_name='Administrator',last_name='Maison Bébé',status='active',deleted_at=NULL WHERE id=?")->execute([$passwordHash, $userId]);
    } else {
        $pdo->prepare("INSERT INTO users (email,password_hash,first_name,last_name,status,email_verified_at) VALUES (?,?,'Administrator','Maison Bébé','active',NOW())")->execute([$email, $passwordHash]);
        $userId = (int) $pdo->lastInsertId();
    }
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name='super_admin' LIMIT 1")->fetchColumn();
    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId, $roleId]);
    $pdo->prepare("UPDATE email_senders SET from_name='Maison Bébé',from_email='comenzi@maison-bebe.ro',reply_to_email='comenzi@maison-bebe.ro',smtp_host='mail.maison-bebe.ro',smtp_port=465,smtp_encryption='ssl',smtp_username='comenzi@maison-bebe.ro',is_active=1")->execute();
    $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address,metadata_json) VALUES (?,'deployment.completed','application',1,?,?)")->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode(['release' => 'v4', 'files' => $files])]);
    $pdo->commit();

    @unlink($archivePath);
    @unlink($root . '/remote-health.php');
    @unlink(__FILE__);
    echo json_encode(['ok' => true, 'release' => 'v4', 'files' => $files, 'tables' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
