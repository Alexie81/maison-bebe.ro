<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;

final class RedirectService
{
    public function handle(string $path): bool
    {
        $statement = Database::connection()->prepare('SELECT id,target_path,http_status FROM url_redirects WHERE source_path=? LIMIT 1');
        $statement->execute([$path]);
        $redirect = $statement->fetch();
        if (!$redirect) {
            return false;
        }
        Database::connection()->prepare('UPDATE url_redirects SET hit_count=hit_count+1,last_hit_at=NOW() WHERE id=?')->execute([$redirect['id']]);
        $status = (int) $redirect['http_status'];
        if ($status === 410 || empty($redirect['target_path'])) {
            http_response_code(410);
            echo view('errors/http', ['status' => 410, 'title' => 'Această pagină a fost eliminată.'], 'layouts/storefront');
            return true;
        }
        header('Location: ' . url((string) $redirect['target_path']), true, in_array($status, [301, 302, 307, 308], true) ? $status : 301);
        exit;
    }
}
