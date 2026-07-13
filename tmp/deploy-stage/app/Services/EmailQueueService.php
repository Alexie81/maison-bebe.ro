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
        if (!$profile && !in_array($purpose, ['general', 'recovery'], true)) {
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
            $template === 'password_reset' => 'recovery',
            str_starts_with($template, 'newsletter_') => 'marketing',
            in_array($template, ['email_verification', 'welcome'], true) => 'account',
            str_contains($template, 'order') => 'orders',
            default => 'general',
        };
    }

    private function render(string $template, array $data, string $subject): string
    {
        $order = htmlspecialchars((string) ($data['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $firstName = htmlspecialchars(trim((string) ($data['first_name'] ?? '')), ENT_QUOTES, 'UTF-8');
        $marketingTitle = htmlspecialchars((string) ($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $marketingExcerpt = htmlspecialchars((string) ($data['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8');
        $marketingUrl = htmlspecialchars((string) ($data['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
        $marketingImage = htmlspecialchars((string) ($data['image_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $marketingCard = ($marketingImage !== '' ? '<img src="' . $marketingImage . '" alt="" style="display:block;width:100%;height:auto;border-radius:14px;margin:0 0 22px">' : '') . '<h1 style="font-family:Georgia,serif">' . $marketingTitle . '</h1><p>' . $marketingExcerpt . '</p><p style="margin-top:24px"><a href="' . $marketingUrl . '" style="display:inline-block;background:#94735f;color:#fff;text-decoration:none;padding:13px 19px;border-radius:4px;font-weight:bold">Descoperă acum</a></p>';
        $body = match ($template) {
            'new_order_admin' => '<h1>Comandă nouă ' . $order . '</h1><p>A fost înregistrată o comandă nouă. Valoare: <strong>' . number_format(((int) ($data['total_minor'] ?? 0)) / 100, 2, ',', '.') . ' lei</strong>.</p><p><a href="' . htmlspecialchars((string) ($data['admin_url'] ?? absolute_url('/admin/comenzi')), ENT_QUOTES, 'UTF-8') . '">Deschide comanda în admin</a></p>',
            'order_confirmation_customer' => $this->orderConfirmation($data, $order, $firstName),
            'order_status' => $this->statusEmail($data),
            'password_reset' => '<h1>Resetare parolă</h1><p><a href="' . htmlspecialchars((string) ($data['reset_url'] ?? '#'), ENT_QUOTES, 'UTF-8') . '">Alege o parolă nouă</a>. Linkul expiră în 60 de minute.</p>',
            'contact_admin' => '<h1>Mesaj nou din formularul de contact</h1><p><strong>' . htmlspecialchars((string) ($data['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong> &lt;' . htmlspecialchars((string) ($data['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '&gt;</p><p>' . nl2br(htmlspecialchars((string) ($data['message'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>',
            'newsletter_product' => $marketingCard,
            'newsletter_article' => $marketingCard,
            'invoice_customer' => $this->invoiceEmail($data, $order),
            default => '<h1>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars((string) ($data['message'] ?? 'Mesaj automat Maison Bébé.'), ENT_QUOTES, 'UTF-8') . '</p>',
        };
        $unsubscribe = trim((string) ($data['unsubscribe_url'] ?? ''));
        $footer = '<p style="color:#766d67;font-size:12px;line-height:1.6">Acesta este un mesaj automat trimis de maison-bebe.ro.</p>';
        if ($unsubscribe !== '') {
            $footer .= '<p style="font-size:12px"><a href="' . htmlspecialchars($unsubscribe, ENT_QUOTES, 'UTF-8') . '" style="color:#766d67;text-decoration:underline">Dezabonează-mă de la mesajele de marketing</a></p>';
        }
        return '<!doctype html><html lang="ro"><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"></head><body style="margin:0;background:#f7f3ee;color:#25211f;font:16px Arial,sans-serif"><div style="max-width:620px;margin:auto;padding:16px 12px"><p style="margin:0 0 12px;letter-spacing:.18em;font-size:12px">MAISON BÉBÉ</p><div style="background:#fff;border:1px solid #e9dfd5;border-radius:14px;padding:22px 18px">' . $body . '</div>' . $footer . '</div></body></html>';
    }

    private function statusEmail(array $data): string
    {
        $number=htmlspecialchars((string)($data['order_number']??''),ENT_QUOTES,'UTF-8');
        $label=htmlspecialchars((string)($data['status_label']??$data['status']??'Actualizată'),ENT_QUOTES,'UTF-8');
        $message=htmlspecialchars((string)($data['message']??''),ENT_QUOTES,'UTF-8');
        $url=htmlspecialchars((string)($data['tracking_url']??absolute_url('/urmarire-comanda')),ENT_QUOTES,'UTF-8');
        return '<p style="margin:0 0 8px;color:#9a725d;font-size:11px;font-weight:bold;letter-spacing:.14em">COMANDA '.$number.'</p><h1 style="margin:0 0 16px;font-family:Georgia,serif;font-size:29px;line-height:1.15;color:#3d2e27">Comanda ta avansează</h1><div style="margin:0 0 18px;padding:15px 16px;border-left:4px solid #94735f;background:#f7f0e9"><small style="display:block;margin-bottom:4px;color:#8b766a;font-size:10px;letter-spacing:.12em">STATUS NOU</small><strong style="font-size:17px;color:#3d2e27">'.$label.'</strong></div><p style="margin:0;color:#675b54;line-height:1.65">'.$message.'</p><p style="margin:24px 0 0;text-align:center"><a href="'.$url.'" style="display:inline-block;background:#94735f;color:#fff;text-decoration:none;padding:14px 20px;border-radius:4px;font-size:12px;font-weight:bold;letter-spacing:.06em">URMĂREȘTE COMANDA</a></p>';
    }

    private function invoiceEmail(array $data,string $order): string
    {
        $number=htmlspecialchars((string)($data['invoice_number']??''),ENT_QUOTES,'UTF-8');
        $url=htmlspecialchars((string)($data['invoice_url']??'#'),ENT_QUOTES,'UTF-8');
        return '<p style="margin:0 0 8px;color:#9a725d;font-size:11px;font-weight:bold;letter-spacing:.14em">DOCUMENT FISCAL</p><h1 style="margin:0 0 13px;font-family:Georgia,serif;font-size:29px;line-height:1.15;color:#3d2e27">Factura ta este pregătită</h1><p style="margin:0 0 20px;color:#675b54;line-height:1.65">Am emis factura <strong>'.$number.'</strong> pentru comanda <strong>'.$order.'</strong>. Documentul poate fi deschis și salvat de pe orice dispozitiv.</p><div style="padding:15px 16px;background:#f7f0e9;border:1px solid #e7d9cd"><small style="display:block;color:#8b766a;font-size:10px;letter-spacing:.12em">FACTURĂ</small><strong style="display:block;margin-top:4px;font-family:Georgia,serif;font-size:20px">'.$number.'</strong></div><p style="margin:24px 0 0;text-align:center"><a href="'.$url.'" style="display:inline-block;background:#94735f;color:#fff;text-decoration:none;padding:14px 22px;border-radius:4px;font-size:12px;font-weight:bold;letter-spacing:.06em">DESCHIDE FACTURA</a></p>';
    }
    private function orderConfirmation(array $data, string $order, string $firstName): string
    {
        $money = static fn (int $minor): string => number_format($minor / 100, 2, ',', '.') . ' lei';
        $itemsHtml = '';

        foreach ((array) ($data['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = htmlspecialchars((string) ($item['name'] ?? 'Produs Maison Bébé'), ENT_QUOTES, 'UTF-8');
            $sku = htmlspecialchars((string) ($item['sku'] ?? ''), ENT_QUOTES, 'UTF-8');
            $options = htmlspecialchars((string) ($item['options'] ?? ''), ENT_QUOTES, 'UTF-8');
            $image = htmlspecialchars((string) ($item['image_url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $total = (int) ($item['total_minor'] ?? ((int) ($item['unit_price_minor'] ?? 0) * $quantity));
            $details = array_filter([$options, $sku !== '' ? 'SKU: ' . $sku : '']);

            $imageCell = $image !== ''
                ? '<img src="' . $image . '" width="72" height="72" alt="' . $name . '" style="display:block;width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid #eadfd4">'
                : '<div style="width:72px;height:72px;border-radius:10px;background:#f3ede6"></div>';

            $itemsHtml .= '<tr>'
                . '<td width="82" style="width:82px;padding:12px 10px 12px 0;border-bottom:1px solid #eee4da;vertical-align:top">' . $imageCell . '</td>'
                . '<td style="padding:12px 0;border-bottom:1px solid #eee4da;vertical-align:middle;word-break:break-word">'
                . '<p style="margin:0 0 7px;font-family:Georgia,serif;font-size:16px;line-height:1.25;color:#352923">' . $name . '</p>'
                . ($details ? '<p style="margin:0;color:#85766c;font-size:12px;line-height:1.55">' . implode(' · ', $details) . '</p>' : '')
                . '<p style="margin:5px 0 0;color:#6f625a;font-size:12px">Cantitate: ' . $quantity . '</p>'
                . '<p style="margin:7px 0 0;font-size:14px;font-weight:bold;color:#594337">' . $money($total) . '</p>'
                . '</td>'
                . '</tr>';
        }

        $subtotal = (int) ($data['subtotal_minor'] ?? $data['total_minor'] ?? 0);
        $discount = max(0, (int) ($data['discount_minor'] ?? 0));
        $shipping = max(0, (int) ($data['shipping_minor'] ?? 0));
        $total = (int) ($data['total_minor'] ?? ($subtotal - $discount + $shipping));
        $trackingUrl = htmlspecialchars((string) ($data['tracking_url'] ?? ''), ENT_QUOTES, 'UTF-8');

        $totals = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;font-size:14px">'
            . '<tr><td style="padding:5px 0;color:#796c64">Subtotal</td><td style="padding:5px 0;text-align:right">' . $money($subtotal) . '</td></tr>'
            . ($discount > 0 ? '<tr><td style="padding:5px 0;color:#796c64">Reducere</td><td style="padding:5px 0;text-align:right;color:#53745f">−' . $money($discount) . '</td></tr>' : '')
            . '<tr><td style="padding:5px 0;color:#796c64">Livrare</td><td style="padding:5px 0;text-align:right">' . ($shipping > 0 ? $money($shipping) : 'Gratuită') . '</td></tr>'
            . '<tr><td style="padding:13px 0 0;border-top:1px solid #dfd1c5;font-family:Georgia,serif;font-size:19px">Total</td><td style="padding:13px 0 0;border-top:1px solid #dfd1c5;text-align:right;font-family:Georgia,serif;font-size:19px;font-weight:bold">' . $money($total) . '</td></tr>'
            . '</table>';

        return ($firstName !== '' ? '<p style="font-size:16px;margin:0 0 12px">Bună, <strong>' . $firstName . '</strong>,</p>' : '')
            . '<h1 style="margin:0 0 12px;font-family:Georgia,serif;font-size:31px;line-height:1.15;color:#3d2e27">Îți mulțumim pentru comandă</h1>'
            . '<p style="margin:0 0 22px;line-height:1.6;color:#675b54">Am primit comanda <strong>' . $order . '</strong> și te vom ține la curent cu fiecare etapă.</p>'
            . ($itemsHtml !== '' ? '<p style="margin:0 0 4px;color:#9a725d;font-size:11px;font-weight:bold;letter-spacing:.14em;text-transform:uppercase">Produsele comandate</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $itemsHtml . '</table>' : '')
            . $totals
            . ($trackingUrl !== '' ? '<p style="margin:26px 0 0;text-align:center"><a href="' . $trackingUrl . '" style="display:inline-block;background:#94735f;color:#fff;text-decoration:none;padding:14px 22px;border-radius:4px;font-size:13px;font-weight:bold;letter-spacing:.06em">URMĂREȘTE COMANDA</a></p>' : '');
    }}
