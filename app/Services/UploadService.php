<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;

final class UploadService
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_SIZE = 8 * 1024 * 1024;

    public function image(string $field, string $alt = ''): ?int
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $this->store($_FILES[$field], $alt);
    }

    public function images(string $field, string $alt = '', int $limit = 12): array
    {
        $upload = $_FILES[$field] ?? null;
        if (!$upload || !is_array($upload['name'] ?? null)) {
            return [];
        }
        $ids = [];
        $count = min(count($upload['name']), max(1, $limit));
        for ($index = 0; $index < $count; $index++) {
            $error = (int) ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $ids[] = $this->store([
                'name' => $upload['name'][$index] ?? '',
                'type' => $upload['type'][$index] ?? '',
                'tmp_name' => $upload['tmp_name'][$index] ?? '',
                'error' => $error,
                'size' => $upload['size'][$index] ?? 0,
            ], $alt);
        }
        return $ids;
    }

    private function store(array $file, string $alt): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new HttpException(422, 'Imaginea nu a putut fi încărcată sau depășește 8 MB.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        $info = @getimagesize($temporary);
        if (!$info) {
            throw new HttpException(422, 'Fișierul nu este o imagine validă.');
        }

        // Unele pachete de hosting nu au extensia fileinfo activată. Pentru
        // imagini, MIME-ul raportat de getimagesize() evită o eroare 500.
        $mime = (string) ($info['mime'] ?? '');
        if (class_exists(\finfo::class)) {
            $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
            if (is_string($detectedMime) && $detectedMime !== '') {
                $mime = $detectedMime;
            }
        }
        if (!isset(self::TYPES[$mime])) {
            throw new HttpException(422, 'Formatul imaginii nu este acceptat. Folosește JPG, PNG sau WebP.');
        }
        $name = bin2hex(random_bytes(20)) . '.' . self::TYPES[$mime];
        $relativeDirectory = date('Y/m');
        $directory = BASE_PATH . '/public/uploads/' . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new HttpException(500, 'Directorul de upload nu poate fi creat.');
        }
        $path = $directory . '/' . $name;
        if (!move_uploaded_file($temporary, $path)) {
            throw new HttpException(500, 'Imaginea nu a putut fi salvată.');
        }
        $publicPath = '/uploads/' . $relativeDirectory . '/' . $name;
        $statement = Database::connection()->prepare('INSERT INTO media_assets (path,mime_type,original_name,alt_text,width,height,size_bytes) VALUES (?,?,?,?,?,?,?)');
        $statement->execute([$publicPath, $mime, basename((string) ($file['name'] ?? $name)), $alt, $info[0], $info[1], (int) $file['size']]);
        return (int) Database::connection()->lastInsertId();
    }
}