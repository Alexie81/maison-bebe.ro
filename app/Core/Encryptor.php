<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use RuntimeException;

final class Encryptor
{
    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Criptarea nu a reușit.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $payload = base64_decode($encoded, true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Secret criptat invalid.');
        }
        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $cipher = substr($payload, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Decriptarea nu a reușit.');
        }
        return $plain;
    }

    private static function key(): string
    {
        $decoded = base64_decode((string) Env::get('APP_ENCRYPTION_KEY', ''), true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('APP_ENCRYPTION_KEY trebuie să conțină 32 bytes encodați base64.');
        }
        return $decoded;
    }
}

