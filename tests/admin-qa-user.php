<?php

declare(strict_types=1);

function createAdminQaUser(\PDO $pdo): array
{
    $email = 'qa-admin-' . bin2hex(random_bytes(8)) . '@local.test';
    $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO users (email,password_hash,first_name,last_name,status) "
            . "VALUES (?,?,'Administrator','QA','active')"
        )->execute([$email, $hash]);
        $id = (int) $pdo->lastInsertId();
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name='super_admin' LIMIT 1")->fetchColumn();
        if ($roleId < 1) {
            throw new RuntimeException('Rolul super_admin lipsește.');
        }
        $pdo->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$id, $roleId]);
        $pdo->commit();
        return ['id' => $id, 'email' => $email, 'password_hash' => $hash];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function deleteAdminQaUser(\PDO $pdo, int $userId): void
{
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
    }
}
