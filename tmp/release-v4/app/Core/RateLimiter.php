<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

final class RateLimiter
{
    public static function hit(string $key, int $limit, int $seconds): bool
    {
        $hash = hash('sha256', $key);
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rate_limits WHERE expires_at < NOW()')->execute();
        $statement = $pdo->prepare('SELECT attempts FROM rate_limits WHERE rate_key = ? FOR UPDATE');
        return Database::transaction(static function () use ($pdo, $statement, $hash, $limit, $seconds): bool {
            $statement->execute([$hash]);
            $attempts = $statement->fetchColumn();
            if ($attempts === false) {
                $insert = $pdo->prepare('INSERT INTO rate_limits (rate_key, attempts, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))');
                $insert->execute([$hash, $seconds]);
                return true;
            }
            if ((int) $attempts >= $limit) {
                return false;
            }
            $pdo->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE rate_key = ?')->execute([$hash]);
            return true;
        });
    }
}

