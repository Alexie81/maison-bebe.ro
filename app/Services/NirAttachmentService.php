<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;

final class NirAttachmentService
{
    private const MAX_BYTES = 12 * 1024 * 1024;

    private const TYPES = [
        'source_pdf' => ['pdf' => ['application/pdf']],
        'source_xlsx' => ['xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream']],
        'source_xml' => ['xml' => ['application/xml', 'text/xml', 'text/plain']],
        'source_image' => [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ],
        'delivery_note' => ['pdf' => ['application/pdf']],
    ];

    public function storeUploaded(int $nirId, array $file, string $artifactType): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new HttpException(422, 'Fișierul încărcat nu este valid.');
        }

        return $this->storeFromPath(
            $nirId,
            (string) $file['tmp_name'],
            (string) ($file['name'] ?? 'document'),
            $artifactType,
            true
        );
    }

    public function storeFromPath(int $nirId, string $sourcePath, string $originalName, string $artifactType, bool $move = false): array
    {
        if (!isset(self::TYPES[$artifactType]) || !is_file($sourcePath)) {
            throw new HttpException(422, 'Tipul documentului atașat nu este acceptat.');
        }
        $size = filesize($sourcePath);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new HttpException(422, 'Atașamentul este gol sau depășește limita de 12 MB.');
        }

        $safeOriginal = $this->safeOriginalName($originalName);
        $extension = strtolower(pathinfo($safeOriginal, PATHINFO_EXTENSION));
        $allowedMimes = self::TYPES[$artifactType][$extension] ?? null;
        if ($allowedMimes === null) {
            throw new HttpException(422, 'Extensia fișierului nu este acceptată pentru acest document.');
        }
        $detectedMime = $this->detectMime($sourcePath, $extension, $artifactType);
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new HttpException(422, 'Conținutul fișierului nu corespunde extensiei selectate.');
        }
        if ($artifactType === 'source_image' && @getimagesize($sourcePath) === false) {
            throw new HttpException(422, 'Imaginea facturii nu a putut fi validată.');
        }

        $hash = hash_file('sha256', $sourcePath);
        $pdo = Database::connection();
        $duplicate = $pdo->prepare('SELECT * FROM nir_artifacts WHERE nir_document_id=? AND artifact_type=? AND sha256=? LIMIT 1');
        $duplicate->execute([$nirId, $artifactType, $hash]);
        if ($existing = $duplicate->fetch()) {
            return $existing;
        }

        $relative = '/nir/source/' . date('Y/m') . '/' . $nirId . '-' . $artifactType . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = BASE_PATH . '/storage' . $relative;
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
            throw new HttpException(500, 'Directorul atașamentelor nu poate fi creat.');
        }
        $stored = $move ? move_uploaded_file($sourcePath, $target) : copy($sourcePath, $target);
        if (!$stored) {
            throw new HttpException(500, 'Atașamentul nu a putut fi salvat.');
        }

        $pdo->prepare('INSERT INTO nir_artifacts (nir_document_id,artifact_type,original_filename,path,mime_type,sha256,size_bytes,generated_by) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$nirId, $artifactType, $safeOriginal, $relative, $detectedMime, $hash, $size, Auth::id()]);
        $artifactId = (int) $pdo->lastInsertId();
        (new AccountingAuditService())->record(
            'nir.source_attachment.saved',
            'nir_document',
            $nirId,
            [],
            ['artifact_id' => $artifactId, 'type' => $artifactType, 'filename' => $safeOriginal, 'sha256' => $hash]
        );

        $statement = $pdo->prepare('SELECT * FROM nir_artifacts WHERE id=?');
        $statement->execute([$artifactId]);
        return $statement->fetch() ?: [];
    }

    private function safeOriginalName(string $name): string
    {
        $name = trim(str_replace(["\0", '/', '\\'], '', basename($name)));
        if ($name === '') {
            $name = 'document';
        }
        return mb_substr($name, 0, 255, 'UTF-8');
    }

    private function detectMime(string $path, string $extension, string $artifactType): string
    {
        if ($artifactType === 'source_image') {
            $image = @getimagesize($path);
            if ($image === false || empty($image['mime'])) {
                throw new HttpException(422, 'Imaginea facturii nu a putut fi validată.');
            }
            return (string) $image['mime'];
        }

        if (class_exists(\finfo::class)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $prefix = file_get_contents($path, false, null, 0, 12);
        return match ($extension) {
            'pdf' => str_starts_with((string) $prefix, '%PDF-') ? 'application/pdf' : 'application/octet-stream',
            'xlsx' => str_starts_with((string) $prefix, "PK\x03\x04") ? 'application/zip' : 'application/octet-stream',
            'xml' => $this->looksLikeXml($path) ? 'application/xml' : 'application/octet-stream',
            default => 'application/octet-stream',
        };
    }

    private function looksLikeXml(string $path): bool
    {
        $contents = file_get_contents($path, false, null, 0, 4096);
        if (!is_string($contents)) return false;
        $contents = ltrim($contents, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
        return str_starts_with($contents, '<?xml') || str_starts_with($contents, '<');
    }
}
