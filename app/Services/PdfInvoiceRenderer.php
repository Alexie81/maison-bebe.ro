<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use RuntimeException;

final class PdfInvoiceRenderer
{
    public function render(array $invoice, array $items, string $path): void
    {
        $issuer = json_decode((string) $invoice['issuer_snapshot_json'], true) ?: [];
        $customer = json_decode((string) $invoice['customer_snapshot_json'], true) ?: [];
        $lines = [
            ['FACTURA ' . ($invoice['number'] ?? ''), 18, true],
            ['Data emiterii: ' . ($invoice['issue_date'] ?? date('Y-m-d')), 10, false],
            ['', 10, false],
            ['FURNIZOR', 11, true],
            [(string) ($issuer['legal_name'] ?? ''), 10, false],
            ['CUI: ' . ($issuer['tax_id'] ?? '') . '  Reg. Com.: ' . ($issuer['registration_number'] ?? ''), 9, false],
            ['Email: ' . ($issuer['billing_email'] ?? ''), 9, false],
            ['', 10, false],
            ['CLIENT', 11, true],
            [(string) ($customer['company_name'] ?? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))), 10, false],
            ['Email: ' . ($customer['email'] ?? '') . '  Telefon: ' . ($customer['phone'] ?? ''), 9, false],
            ['CUI/CNP: ' . ($customer['tax_id'] ?? ''), 9, false],
            ['', 10, false],
            ['PRODUSE / SERVICII', 11, true],
        ];
        foreach ($items as $item) {
            $lines[] = [(string) $item['name'] . '  x ' . rtrim(rtrim((string) $item['quantity'], '0'), '.') . '  -  ' . $this->money((int) $item['total_minor']), 9, false];
        }
        $lines[] = ['', 9, false];
        $lines[] = ['Subtotal: ' . $this->money((int) $invoice['subtotal_minor']), 10, false];
        if ((int) $invoice['discount_minor'] > 0) {
            $lines[] = ['Reducere: -' . $this->money((int) $invoice['discount_minor']), 10, false];
        }
        $lines[] = ['TVA: ' . $this->money((int) $invoice['vat_minor']), 10, false];
        $lines[] = ['TOTAL: ' . $this->money((int) $invoice['grand_total_minor']), 14, true];
        $lines[] = ['', 9, false];
        $lines[] = ['Document generat electronic de Maison Bebe.', 8, false];

        $commands = "BT\n";
        $y = 800;
        foreach ($lines as [$text, $size, $bold]) {
            if ($y < 55) {
                break;
            }
            $font = $bold ? '/F2' : '/F1';
            $commands .= $font . ' ' . $size . " Tf\n1 0 0 1 48 " . $y . " Tm\n(" . $this->escape($this->ascii($text)) . ") Tj\n";
            $y -= $size + 8;
        }
        $commands .= "ET\n";
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($commands) . ">>\nstream\n" . $commands . 'endstream',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Directorul pentru facturi nu poate fi creat.');
        }
        if (file_put_contents($path, $pdf, LOCK_EX) === false) {
            throw new RuntimeException('PDF-ul facturii nu a putut fi salvat.');
        }
    }

    private function money(int $minor): string
    {
        return number_format($minor / 100, 2, ',', '.') . ' RON';
    }

    private function ascii(string $value): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: preg_replace('/[^\x20-\x7E]/', '', $value) ?: '';
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
