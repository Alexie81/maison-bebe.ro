<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $rememberDays = max(1, (int) Env::get('REMEMBER_SESSION_DAYS', '30'));
        ini_set('session.gc_maxlifetime', (string) ($rememberDays * 86400));
        ini_set('session.use_strict_mode', '1');
        session_name((string) Env::get('SESSION_NAME', 'maison_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::bool('SESSION_SECURE', false) && self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if (func_num_args() === 2) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $result = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $result;
    }

    public static function remember(int $days = 30): void
    {
        $days = max(1, min($days, 90));
        setcookie(session_name(), session_id(), [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => Env::bool('SESSION_SECURE', false) && self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function forgetPersistentCookie(): void
    {
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => Env::bool('SESSION_SECURE', false) && self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

