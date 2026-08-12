<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use RuntimeException;

final class NirPdfService
{
    public function generate(int $nirId): array
    {
        $nir = (new NirService())->document($nirId);
        if (!in_array($nir['status'], ['Confirmed', 'PartiallyReceived', 'Reversed'], true)) {
            throw new RuntimeException('PDF-ul NIR poate fi generat numai pentru un document confirmat.');
        }
        $lines = (new NirService())->lines($nirId);
        $company = Database::connection()->query('SELECT * FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1')->fetch() ?: [];
        $company['address'] = json_decode((string) ($company['address_json'] ?? ''), true) ?: [];
        $pages = array_chunk($lines, 13) ?: [[]];
        $streams = [];
        foreach ($pages as $index => $rows) {
            $streams[] = $this->page($nir, $company, $rows, $index + 1, count($pages));
        }
        $pdf = $this->documentPdf($streams);
        $pdo = Database::connection();
        $artifact = $pdo->prepare("SELECT * FROM nir_artifacts WHERE nir_document_id=? AND artifact_type='pdf' ORDER BY id DESC LIMIT 1");
        $artifact->execute([$nirId]);
        $stored = $artifact->fetch();
        $relative = $stored ? (string) $stored['path'] : '/nir/' . date('Y/m', strtotime((string) $nir['confirmed_at'])) . '/nir-' . $nirId . '.pdf';
        $path = BASE_PATH . '/storage' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Directorul PDF NIR nu poate fi creat.');
        }
        if (file_put_contents($path, $pdf, LOCK_EX) === false) {
            throw new RuntimeException('PDF-ul NIR nu a putut fi salvat.');
        }
        $checksum = hash('sha256', $pdf);
        if ($stored) {
            $pdo->prepare('UPDATE nir_artifacts SET sha256=?,size_bytes=?,document_version=document_version+1,generated_at=NOW(),generated_by=? WHERE id=?')
                ->execute([$checksum, strlen($pdf), Auth::id(), $stored['id']]);
            $artifactId = (int) $stored['id'];
        } else {
            $pdo->prepare("INSERT INTO nir_artifacts (nir_document_id,artifact_type,path,mime_type,sha256,size_bytes,generated_by) VALUES (?,'pdf',?,'application/pdf',?,?,?)")
                ->execute([$nirId, $relative, $checksum, strlen($pdf), Auth::id()]);
            $artifactId = (int) $pdo->lastInsertId();
        }
        (new AccountingAuditService())->record('nir.pdf.generated', 'nir_document', $nirId, [], ['artifact_id' => $artifactId, 'sha256' => $checksum]);
        return ['path' => $relative, 'checksum' => $checksum, 'bytes' => strlen($pdf)];
    }

    private function page(array $nir, array $company, array $lines, int $page, int $pages): string
    {
        $dark = [58, 45, 40];
        $brown = [154, 113, 94];
        $cream = [248, 242, 235];
        $danger = [165, 78, 66];
        $isReversal = ($nir['document_kind'] ?? '') === 'reversal';
        $isReversedOriginal = !$isReversal && ($nir['status'] ?? '') === 'Reversed';
        $c = "q\n";
        $this->fill($c, $cream);
        $this->rect($c, 0, 535, 842, 60, true);
        $this->text($c, 34, 568, 15, (string) ($company['legal_name'] ?? 'MAISON BEBE'), true, $dark);
        $this->text($c, 34, 551, 7, 'CUI: ' . ($company['tax_id'] ?? '—') . ' | Reg. Com.: ' . ($company['registration_number'] ?? '—'), false, $dark);
        $this->text($c, 760, 570, 13, 'NOTA DE RECEPTIE SI CONSTATARE DE DIFERENTE', true, $dark, 'right');
        if ($isReversal || $isReversedOriginal) {
            $badge = $isReversal ? 'DOCUMENT DE INVERSARE' : 'NIR INVERSAT';
            $reference = $isReversal
                ? 'NIR initial: ' . ($nir['original_formatted_number'] ?: '#' . (int) $nir['original_nir_id'])
                : (!empty($nir['reversal_formatted_number']) ? 'Document inversare: ' . $nir['reversal_formatted_number'] : 'Document inversat');
            $this->fill($c, $danger);
            $this->rect($c, 620, 546, 188, 15, true);
            $this->text($c, 714, 551, 7, $badge, true, [255, 255, 255], 'center');
            $this->text($c, 808, 536, 7, ($nir['formatted_number'] ?: 'Ciorna') . ' | ' . $reference, false, $danger, 'right');
        } else {
            $this->text($c, 808, 551, 8, 'Cod 14-3-1A | ' . ($nir['formatted_number'] ?: 'Ciorna'), false, $brown, 'right');
        }
        $this->text($c, $isReversal || $isReversedOriginal ? 34 : 808, 538, 7, 'Pagina ' . $page . ' / ' . $pages, false, $dark, $isReversal || $isReversedOriginal ? 'left' : 'right');

        $this->panel($c, 34, 455, 375, 66, $cream, $brown);
        $this->label($c, 46, 505, 'FURNIZOR SI DOCUMENT', $brown);
        $this->text($c, 46, 489, 9, (string) $nir['supplier_name_snapshot'], true, $dark);
        $this->text($c, 46, 475, 7, 'CUI: ' . $nir['supplier_tax_id_snapshot'] . ' | Factura: ' . $nir['invoice_series'] . ' ' . $nir['invoice_number'] . ' din ' . $this->date($nir['invoice_date']), false, $dark);
        $this->text($c, 46, 462, 7, 'Aviz: ' . ($nir['delivery_note_number'] ?: '—') . ' | Moneda: ' . $nir['currency'] . ' | Curs: ' . $nir['exchange_rate'], false, $dark);
        $this->panel($c, 423, 455, 385, 66, [255, 255, 255], $brown);
        $this->label($c, 435, 505, 'RECEPTIE', $brown);
        $this->text($c, 435, 489, 9, 'Data receptiei: ' . $this->date($nir['receipt_date']), true, $dark);
        $this->text($c, 435, 475, 7, 'Gestiune: ' . $nir['warehouse_name'] . ' | Confirmat: ' . $this->dateTime($nir['confirmed_at']), false, $dark);
        $this->text($c, 435, 462, 7, 'Intocmit de: ' . trim((string) $nir['creator_name']) . ' | Confirmat de: ' . trim((string) $nir['confirmer_name']), false, $dark);

        $columns = [
            ['Nr.', 34, 24], ['SKU', 58, 72], ['Produs / varianta', 130, 160], ['UM', 290, 28],
            ['Fact.', 318, 42], ['Recept.', 360, 45], ['Accept.', 405, 45], ['Difer.', 450, 42],
            ['Pret net', 492, 58], ['Disc.', 550, 45], ['TVA', 595, 38], ['Val. neta', 633, 58],
            ['TVA val.', 691, 53], ['Total', 744, 64],
        ];
        $this->fill($c, $brown);
        $this->rect($c, 34, 421, 774, 25, true);
        foreach ($columns as [$name, $x]) {
            $this->text($c, $x + 3, 430, 6, $name, true, [255, 255, 255]);
        }
        $y = 408;
        foreach ($lines as $index => $line) {
            if ($index % 2 === 0) {
                $this->fill($c, [252, 249, 246]);
                $this->rect($c, 34, $y - 21, 774, 28, true);
            }
            $values = [
                (string) ($index + 1 + (($page - 1) * 13)),
                (string) ($line['sku_snapshot'] ?: '—'),
                $this->cut((string) $line['product_name_snapshot'] . (($line['variant_name_snapshot'] ?? '') ? ' / ' . $line['variant_name_snapshot'] : ''), 30),
                (string) $line['unit_of_measure_snapshot'],
                $this->number($line['invoiced_quantity'], 4), $this->number($line['received_quantity'], 4),
                $this->number($line['accepted_quantity'], 4), $this->number($line['difference_quantity'], 4),
                $this->number($line['unit_purchase_price_without_vat'], 2), $this->number($line['discount_value'], 2),
                $this->number($line['vat_rate'], 2) . '%', $this->number($line['value_without_vat'], 2),
                $this->number($line['vat_value'], 2), $this->number($line['total_with_vat'], 2),
            ];
            foreach ($values as $cell => $value) {
                $this->text($c, $columns[$cell][1] + 3, $y - 8, 6, $value, $cell === 1, $dark);
            }
            $this->stroke($c, [225, 215, 207]);
            $this->line($c, 34, $y - 22, 808, $y - 22);
            $y -= 28;
        }

        $this->fill($c, $cream);
        $this->rect($c, 555, 52, 253, 96, true);
        $this->text($c, 670, 127, 8, 'TOTAL FARA TVA', false, $dark, 'right');
        $this->text($c, 796, 127, 9, $this->number($nir['total_without_vat'], 2) . ' ' . $nir['currency'], true, $dark, 'right');
        $this->text($c, 670, 107, 8, 'TVA', false, $dark, 'right');
        $this->text($c, 796, 107, 9, $this->number($nir['vat_total'], 2) . ' ' . $nir['currency'], true, $dark, 'right');
        $this->stroke($c, $brown);
        $this->line($c, 575, 92, 796, 92);
        $this->text($c, 670, 72, 10, 'TOTAL', true, $dark, 'right');
        $this->text($c, 796, 72, 11, $this->number($nir['grand_total'], 2) . ' ' . $nir['currency'], true, $brown, 'right');
        $this->text($c, 34, 132, 7, 'Observatii: ' . $this->cut((string) ($nir['notes'] ?: 'Fara observatii.'), 100), false, $dark);
        if ($isReversal || $isReversedOriginal) {
            $statusLine = $isReversal
                ? 'DOCUMENT DE INVERSARE pentru ' . ($nir['original_formatted_number'] ?: '#' . (int) $nir['original_nir_id'])
                : 'NIR INVERSAT' . (!empty($nir['reversal_formatted_number']) ? ' prin ' . $nir['reversal_formatted_number'] : '');
            $this->text($c, 34, 115, 7, $statusLine . ': ' . $this->cut((string) $nir['reversal_reason'], 90), true, $danger);
        }
        if ($nir['is_late_entered']) {
            $this->text($c, 34, $isReversal || $isReversedOriginal ? 101 : 115, 7, 'DOCUMENT INTRODUS ULTERIOR: ' . $this->cut((string) $nir['late_entry_reason'], 100), true, $danger);
        }
        $creatorName = trim((string) $nir['creator_name']) ?: 'Sistem';
        $confirmerName = trim((string) $nir['confirmer_name']) ?: $creatorName;
        $this->text($c, 34, 78, 7, 'Receptionat de: ' . $creatorName, false, $dark);
        $this->text($c, 240, 78, 7, 'Primit in gestiune de: ' . $creatorName, false, $dark);
        $this->text($c, 34, 58, 7, 'Confirmat de: ' . $confirmerName, false, $dark);
        $this->text($c, 34, 25, 6, 'Document intern generat din datele NIR confirmate. Miscarile rezultate afecteaza exclusiv Stocuri Conta.', false, [115, 105, 100]);
        return $c . "Q\n";
    }

    private function documentPdf(array $streams): string
    {
        $objects = [1 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', 2 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $pageIds = [];
        $next = 3;
        foreach ($streams as $stream) {
            $pageId = $next++;
            $contentId = $next++;
            $pageIds[] = $pageId;
            $objects[$pageId] = '';
            $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "endstream";
        }
        $pagesId = $next++;
        $catalogId = $next++;
        foreach ($pageIds as $index => $pageId) {
            $objects[$pageId] = '<< /Type /Page /Parent ' . $pagesId . ' 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 1 0 R /F2 2 0 R >> >> /Contents ' . ($pageId + 1) . ' 0 R >>';
        }
        $objects[$pagesId] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[$catalogId] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= isset($offsets[$id]) ? sprintf('%010d 00000 n ', $offsets[$id]) . "\n" : "0000000000 00000 f \n";
        }
        return $pdf . "trailer\n<< /Size {$size} /Root {$catalogId} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function date(?string $value): string { return $value ? date('d.m.Y', strtotime($value)) : '—'; }
    private function dateTime(?string $value): string { return $value ? date('d.m.Y H:i', strtotime($value)) : '—'; }
    private function number(mixed $value, int $scale): string { return number_format((float) $value, $scale, ',', '.'); }
    private function cut(string $value, int $length): string { return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '…' : $value; }
    private function color(array $rgb): string { return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255); }
    private function fill(string &$c, array $rgb): void { $c .= $this->color($rgb) . " rg\n"; }
    private function stroke(string &$c, array $rgb): void { $c .= $this->color($rgb) . " RG\n"; }
    private function rect(string &$c, float $x, float $y, float $w, float $h, bool $fill = false): void { $c .= sprintf('%.2F %.2F %.2F %.2F re %s', $x, $y, $w, $h, $fill ? 'f' : 'S') . "\n"; }
    private function line(string &$c, float $x1, float $y1, float $x2, float $y2): void { $c .= sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $y1, $x2, $y2) . "\n"; }
    private function panel(string &$c, float $x, float $y, float $w, float $h, array $fill, array $border): void { $this->fill($c, $fill); $this->rect($c, $x, $y, $w, $h, true); $this->stroke($c, $border); $this->rect($c, $x, $y, $w, $h); }
    private function label(string &$c, float $x, float $y, string $value, array $color): void { $this->text($c, $x, $y, 7, $value, true, $color); }
    private function text(string &$c, float $x, float $y, int $size, string $value, bool $bold, array $color, string $align = 'left'): void
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: preg_replace('/[^\x20-\x7E]/', '', $value) ?: '';
        $estimatedWidth = strlen($value) * $size * ($bold ? .54 : .49);
        if ($align === 'right') $x -= $estimatedWidth;
        elseif ($align === 'center') $x -= $estimatedWidth / 2;
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        $c .= "BT\n" . $this->color($color) . " rg\n" . ($bold ? '/F2' : '/F1') . " {$size} Tf\n1 0 0 1 {$x} {$y} Tm\n({$value}) Tj\nET\n";
    }
}
