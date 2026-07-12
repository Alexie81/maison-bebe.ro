<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use RuntimeException;
use Throwable;

final class EmailQueueService
{
    public function process(int $limit = 20): array
    {
        $pdo = Database::connection();
        $metrics = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        for ($i = 0; $i < $limit; $i++) {
            $pdo->beginTransaction();
            $row = $pdo->query("SELECT * FROM email_queue WHERE status IN ('pending','failed') AND next_attempt_at<=NOW() AND attempts<5 ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
            if (!$row) {
                $pdo->commit();
                break;
            }
            $pdo->prepare("UPDATE email_queue SET status='sending',attempts=attempts+1 WHERE id=?")->execute([$row['id']]);
            $pdo->commit();
            try {
                $purpose = $this->purpose((string) $row['template_key']);
                $profile = $this->profile($purpose);
                $payload = json_decode((string) $row['payload_json'], true) ?: [];
                $html = $this->render((string) $row['template_key'], $payload, (string) $row['subject']);
                (new SmtpMailer())->send($profile, (string) $row['recipient'], (string) $row['subject'], $html);
                $pdo->prepare("UPDATE email_queue SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=?")->execute([$row['id']]);
                $metrics['sent']++;
            } catch (Throwable $exception) {
                $attempts = (int) $row['attempts'] + 1;
                $status = $attempts >= 5 ? 'requires_attention' : 'failed';
                $delay = min(3600, 60 * (2 ** max(0, $attempts - 1)));
                $pdo->prepare('UPDATE email_queue SET status=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=? WHERE id=?')->execute([$status, $delay, mb_substr($exception->getMessage(), 0, 1000), $row['id']]);
                $metrics['failed']++;
            }
        }
        return $metrics;
    }

    public function profile(string $purpose): array
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare("SELECT * FROM email_senders WHERE purpose=? AND is_active=1 LIMIT 1");
        $statement->execute([$purpose]);
        $profile = $statement->fetch();
        if (!$profile && $purpose !== 'general') {
            $statement->execute(['general']);
            $profile = $statement->fetch();
        }
        if (!$profile) {
            throw new RuntimeException('Nu există un profil SMTP activ pentru ' . $purpose . '.');
        }
        $profile['password'] = !empty($profile['encrypted_password']) ? Encryptor::decrypt((string) $profile['encrypted_password']) : '';
        return $profile;
    }

    private function purpose(string $template): string
    {
        return match (true) {
            str_starts_with($template, 'invoice'), str_starts_with($template, 'efactura') => 'invoices',
            in_array($template, ['password_reset', 'email_verification', 'welcome'], true) => 'account',
            str_contains($template, 'order') => 'orders',
            default => 'general',
        };
    }

    private function render(string $template, array $data, string $subject): string
    {
        $order = htmlspecialchars((string) ($data['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $body = match ($template) {
            'new_order_admin' => '<h1>Comandă nouă ' . $order . '</h1><p>A fost înregistrată o comandă nouă. Valoare: <strong>' . number_format(((int) ($data['total_minor'] ?? 0)) / 100, 2, ',', '.') . ' lei</strong>.</p><p><a href="' . htmlspecialchars((string) ($data['admin_url'] ?? absolute_url('/admin/comenzi')), ENT_QUOTES, 'UTF-8') . '">Deschide comanda în admin</a></p>',
            'order_confirmation_customer' => '<h1>Îți mulțumim pentru comandă</h1><p>Am primit comanda <strong>' . $order . '</strong> și te vom ține la curent cu fiecare etapă.</p>',
            'order_status' => '<h1>Comanda ta a fost actualizată</h1><p>' . htmlspecialchars((string) ($data['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>',
            'password_reset' => '<h1>Resetare parolă</h1><p><a href="' . htmlspecialchars((string) ($data['reset_url'] ?? '#'), ENT_QUOTES, 'UTF-8') . '">Alege o parolă nouă</a>. Linkul expiră în 60 de minute.</p>',
            'contact_admin' => '<h1>Mesaj nou din formularul de contact</h1><p><strong>' . htmlspecialchars((string) ($data['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong> &lt;' . htmlspecialchars((string) ($data['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '&gt;</p><p>' . nl2br(htmlspecialchars((string) ($data['message'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>',
            'invoice_customer' => '<h1>Factura ta Maison Bébé</h1><p>Factura <strong>' . htmlspecialchars((string) ($data['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong> pentru comanda ' . $order . ' a fost emisă.</p><p><a href="' . htmlspecialchars((string) ($data['invoice_url'] ?? '#'), ENT_QUOTES, 'UTF-8') . '">Vezi factura</a></p>',
            default => '<h1>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars((string) ($data['message'] ?? 'Mesaj automat Maison Bébé.'), ENT_QUOTES, 'UTF-8') . '</p>',
        };
        return '<!doctype html><html lang="ro"><body style="margin:0;background:#f7f3ee;color:#25211f;font:16px Arial,sans-serif"><div style="max-width:620px;margin:auto;padding:32px"><p style="letter-spacing:.18em;font-size:12px">MAISON BÉBÉ</p><div style="background:#fff;border:1px solid #e9dfd5;border-radius:18px;padding:32px">' . $body . '</div><p style="color:#766d67;font-size:12px">Acesta este un mesaj automat trimis de maison-bebe.ro.</p></div></body></html>';
    }
}
