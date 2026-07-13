<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use PDO;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return is_numeric($id) ? (int) $id : null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (!self::id()) {
            return null;
        }
        $statement = Database::connection()->prepare('SELECT id, email, first_name, last_name, status, email_verified_at FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([self::id()]);
        self::$user = $statement->fetch() ?: null;
        return self::$user;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        Session::put('user_id', $userId);
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        session_regenerate_id(true);
        Session::forgetPersistentCookie();
        self::$loaded = false;
        self::$user = null;
    }

    public static function hasPermission(string $permission): bool
    {
        if (!self::id()) {
            return false;
        }
        $sql = 'SELECT 1 FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = ? AND p.name IN (?, ?) LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute([self::id(), '*', $permission]);
        return (bool) $statement->fetchColumn();
    }

    public static function isAdmin(): bool
    {
        if (!self::id()) {
            return false;
        }
        $statement = Database::connection()->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name <> 'customer' LIMIT 1");
        $statement->execute([self::id()]);
        return (bool) $statement->fetchColumn();
    }
}

