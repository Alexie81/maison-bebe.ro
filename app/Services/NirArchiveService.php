<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use MaisonBebe\Core\Database;
use RuntimeException;

final class NirArchiveService
{
    private const MAX_ARCHIVE_BYTES = 250 * 1024 * 1024;

    public function exportPeriod(string $from, string $to, bool $includeNirs = true, ?string $stockXlsx = null, bool $audit = true): array
    {
        [$start, $end] = $this->validatedPeriod($from, $to);
        if (!$includeNirs && $stockXlsx === null) {
            throw new RuntimeException('Selectează NIR-urile, stocurile sau ambele.');
        }

        $files = [];
        $bytes = 0;
        $documents = [];
        if ($includeNirs) {
            $statement = Database::connection()->prepare(
                "SELECT id,formatted_number,receipt_date FROM nir_documents
                 WHERE receipt_date BETWEEN ? AND ? AND status IN ('Confirmed','PartiallyReceived','Reversed')
                 ORDER BY receipt_date,number,id LIMIT 501"
            );
            $statement->execute([$start->format('Y-m-d'), $end->format('Y-m-d')]);
            $documents = $statement->fetchAll();
            if (!$documents && $stockXlsx === null) {
                throw new RuntimeException('Nu există NIR-uri confirmate în perioada selectată.');
            }
            if (count($documents) > 500) {
                throw new RuntimeException('Perioada conține peste 500 de NIR-uri. Selectează un interval mai scurt.');
            }
            foreach ($documents as $document) {
                $nirId = (int) $document['id'];
                $base = $this->safeName((string) ($document['formatted_number'] ?: 'nir-' . $nirId));
                $folder = 'NIR-uri/' . $base . '/';
                $pdf = (new NirPdfService())->generate($nirId);
                $pdfPath = BASE_PATH . '/storage' . $pdf['path'];
                $pdfBinary = is_file($pdfPath) ? file_get_contents($pdfPath) : false;
                if ($pdfBinary === false) {
                    throw new RuntimeException('PDF-ul pentru ' . $base . ' nu a putut fi citit.');
                }
                $files[$folder . $base . '.pdf'] = $pdfBinary;
                $xlsx = (new NirXlsxService())->generate($nirId);
                $files[$folder . $base . '.xlsx'] = $xlsx['binary'];
                $bytes += strlen($pdfBinary) + strlen($xlsx['binary']);
                $bytes += $this->appendSourceDocuments($files, $folder, $nirId, $base);
                if ($bytes > self::MAX_ARCHIVE_BYTES) {
                    throw new RuntimeException('Arhiva depășește 250 MB. Selectează o perioadă mai scurtă.');
                }
            }
        }

        if ($stockXlsx !== null) {
            $files['Stocuri/Stocuri-contabile-' . $from . '-' . $to . '.xlsx'] = $stockXlsx;
            $bytes += strlen($stockXlsx);
        }
        $files['CITESTE.txt'] = "Arhivă contabilă Maison Bébé\r\nPerioada: {$from} - {$to}\r\nNIR-uri: " . ($includeNirs ? count($documents) : 'neincluse') . "\r\nStocuri: " . ($stockXlsx !== null ? 'incluse' : 'neincluse') . "\r\nFiecare folder NIR conține PDF-ul, XLSX-ul și documentele furnizorului disponibile.\r\n";
        $binary = $this->zip($files);

        if ($audit) {
            (new AccountingAuditService())->record('nir.archive.exported', 'nir_export', null, [], [
                'from' => $from,
                'to' => $to,
                'document_count' => count($documents),
                'includes_nirs' => $includeNirs,
                'includes_stock' => $stockXlsx !== null,
                'sha256' => hash('sha256', $binary),
            ]);
        }

        return ['binary' => $binary, 'filename' => 'documente-contabile-' . $from . '-' . $to . '.zip', 'count' => count($documents)];
    }

    private function appendSourceDocuments(array &$files, string $folder, int $nirId, string $base): int
    {
        $statement = Database::connection()->prepare(
            "SELECT * FROM nir_artifacts
             WHERE nir_document_id=? AND artifact_type IN ('source_pdf','source_xlsx','source_xml','source_image','delivery_note')
             ORDER BY CASE artifact_type WHEN 'source_pdf' THEN 1 WHEN 'source_image' THEN 2 WHEN 'source_xlsx' THEN 3 WHEN 'source_xml' THEN 4 ELSE 5 END,id"
        );
        $statement->execute([$nirId]);
        $page = 1;
        $bytes = 0;
        foreach ($statement->fetchAll() as $attachment) {
            $path = BASE_PATH . '/storage' . $attachment['path'];
            $contents = is_file($path) ? file_get_contents($path) : false;
            if ($contents === false) {
                $missingName = trim((string) ($attachment['original_filename'] ?? '')) ?: basename((string) $attachment['path']);
                $files[$folder . 'ATENTIE-document-lipsa-' . str_pad((string) $page++, 2, '0', STR_PAD_LEFT) . '.txt'] =
                    "Documentul-sursă „{$missingName}” era înregistrat, dar fișierul nu mai există în arhiva serverului. Reatașați-l din pagina NIR {$base}.\r\n";
                continue;
            }
            $original = trim((string) ($attachment['original_filename'] ?? '')) ?: basename((string) $attachment['path']);
            $attachmentFolder = $attachment['artifact_type'] === 'delivery_note' ? 'Aviz/' : 'Factura-furnizor/';
            $files[$folder . $attachmentFolder . str_pad((string) $page++, 2, '0', STR_PAD_LEFT) . '-' . $this->safeName($original, true)] = $contents;
            $bytes += strlen($contents);
        }
        return $bytes;
    }

    private function validatedPeriod(string $from, string $to): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$start || $start->format('Y-m-d') !== $from || !$end || $end->format('Y-m-d') !== $to) throw new RuntimeException('Selectează o perioadă calendaristică validă.');
        if ($start > $end) throw new RuntimeException('Data de început trebuie să fie înaintea datei finale.');
        if ($end > new DateTimeImmutable('today')) throw new RuntimeException('Perioada nu poate include zile viitoare.');
        if ((int) $start->diff($end)->format('%a') > 366) throw new RuntimeException('Selectează o perioadă de cel mult 366 de zile.');
        return [$start, $end];
    }

    private function safeName(string $name, bool $keepExtension = false): string
    {
        $extension = $keepExtension ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';
        $stem = $keepExtension ? pathinfo($name, PATHINFO_FILENAME) : $name;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $stem) ?: $stem;
        $ascii = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $ascii), '-.');
        $ascii = $ascii !== '' ? substr($ascii, 0, 160) : 'document';
        return $keepExtension && $extension !== '' ? $ascii . '.' . preg_replace('/[^a-z0-9]+/', '', $extension) : $ascii;
    }

    private function zip(array $files): string
    {
        return (new ZipService())->create($files);
    }
}
