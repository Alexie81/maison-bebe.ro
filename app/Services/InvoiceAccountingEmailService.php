<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use RuntimeException;

final class InvoiceAccountingEmailService
{
    private const MAX_EMAIL_ARCHIVE_BYTES = 18 * 1024 * 1024;

    public function send(string $from, string $to, string $recipient, string $subject, string $message): array
    {
        $recipient = mb_strtolower(trim($recipient));
        $period = date('d.m.Y', strtotime($from)) . ' – ' . date('d.m.Y', strtotime($to));
        $contents = 'registrul XLSX și fișierele RO e-Factura ale documentelor emise';
        $subject = trim(strtr($subject, ['{PERIOADA}' => $period, '{CONTINUT}' => $contents]));
        $message = trim(strtr($message, ['{PERIOADA}' => $period, '{CONTINUT}' => $contents]));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresa firmei de contabilitate nu este validă.');
        }
        if ($subject === '' || mb_strlen($subject) > 190) {
            throw new RuntimeException('Completează un subiect de cel mult 190 de caractere.');
        }
        if ($message === '' || mb_strlen($message) > 5000) {
            throw new RuntimeException('Completează un mesaj de cel mult 5.000 de caractere.');
        }

        $archive = (new InvoiceAccountingExportService())->exportPeriod($from, $to);
        if (strlen($archive['binary']) > self::MAX_EMAIL_ARCHIVE_BYTES) {
            throw new RuntimeException('Pachetul facturilor depășește 18 MB. Selectează o perioadă mai scurtă pentru trimiterea prin email.');
        }

        $html = '<!doctype html><html lang="ro"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;background:#f7f3ee;color:#352923;font:15px Arial,sans-serif">'
            . '<div style="max-width:640px;margin:auto;padding:20px 12px"><p style="letter-spacing:.18em;font-size:11px;color:#8a6b5a">MAISON BÉBÉ</p>'
            . '<div style="background:#fff;border:1px solid #e7dbd0;border-radius:16px;padding:24px">'
            . '<h1 style="margin:0 0 14px;font:500 27px Georgia,serif">Facturi pentru contabilitate</h1>'
            . '<p style="margin:0 0 6px;color:#786a62">Perioada: <strong>' . htmlspecialchars($period, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p style="margin:0 0 18px;color:#786a62">Conținut: <strong>' . htmlspecialchars($contents, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<div style="line-height:1.7">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</div>'
            . '<p style="margin:22px 0 0;padding:14px;background:#f7f0e9;border-radius:10px">Pachetul ZIP conține registrul XLSX și fișierele RO e-Factura din interval.</p>'
            . '</div></div></body></html>';

        $profile = (new EmailQueueService())->profile('invoices');
        $plainMessage = "Perioada: {$period}\nConținut: {$contents}\n\n{$message}";
        (new SmtpMailer())->send($profile, $recipient, $subject, $html, $plainMessage, [[
            'name' => $archive['filename'],
            'mime' => 'application/zip',
            'content' => $archive['binary'],
        ]]);

        Database::connection()->prepare(
            'INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address,metadata_json) VALUES (?,?,?,?,?,?)'
        )->execute([
            Auth::id(), 'invoices.accounting_bundle.emailed', 'invoice_export', null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            json_encode([
                'recipient' => $recipient,
                'from' => $from,
                'to' => $to,
                'invoice_count' => $archive['invoice_count'],
                'line_count' => $archive['line_count'],
                'sha256' => hash('sha256', $archive['binary']),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ['recipient' => $recipient, 'invoice_count' => $archive['invoice_count']];
    }
}
