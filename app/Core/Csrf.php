<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function validate(?string $token): bool
    {
        return is_string($token) && hash_equals(self::token(), $token);
    }
}

