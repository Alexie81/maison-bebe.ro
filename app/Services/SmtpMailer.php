<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use RuntimeException;

final class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;

    public function test(array $profile): string
    {
        $this->connect($profile);
        $this->command('QUIT', [221]);
        $this->close();
        return 'Conexiunea SMTP și autentificarea au reușit.';
    }

    public function send(array $profile, string $recipient, string $subject, string $html, ?string $text = null): void
    {
        [$html, $inlineImages] = $this->inlineLocalImages($html);
        $this->connect($profile);
        $from = (string) $profile['from_email'];
        $this->command('MAIL FROM:<' . $from . '>', [250]);
        $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
        $this->command('DATA', [354]);

        $boundary = 'mb_' . bin2hex(random_bytes(12));
        $alternativeBoundary = 'mb_alt_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@maison-bebe.ro>',
            'From: ' . $this->header((string) $profile['from_name']) . ' <' . $from . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $this->header($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary="' . $boundary . '"',
        ];
        if (!empty($profile['reply_to_email'])) {
            $headers[] = 'Reply-To: <' . $profile['reply_to_email'] . '>';
        }
        $text ??= trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\nContent-Type: multipart/alternative; boundary=\"" . $alternativeBoundary . "\"\r\n\r\n";
        $message .= '--' . $alternativeBoundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($text) . "\r\n";
        $message .= '--' . $alternativeBoundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($html) . "\r\n";
        $message .= '--' . $alternativeBoundary . "--\r\n";
        foreach ($inlineImages as $image) {
            $message .= '--' . $boundary . "\r\nContent-Type: " . $image['mime'] . "; name=\"" . $image['name'] . "\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\nContent-ID: <" . $image['cid'] . ">\r\nContent-Disposition: inline; filename=\"" . $image['name'] . "\"\r\n\r\n";
            $message .= chunk_split(base64_encode($image['content']), 76, "\r\n");
        }
        $message .= '--' . $boundary . "--\r\n";
        $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
        $this->write($message . ".\r\n");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        $this->close();
    }

    private function inlineLocalImages(string $html): array
    {
        $images = [];
        $seen = [];
        $publicRoot = realpath(BASE_PATH . '/public');
        if ($publicRoot === false) return [$html, $images];
        $html = preg_replace_callback('/(<img\b[^>]*?\bsrc=["\x27])([^"\x27]+)(["\x27])/i', function (array $match) use (&$images, &$seen, $publicRoot): string {
            $path = parse_url(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'), PHP_URL_PATH);
            if (!is_string($path) || $path === '') return $match[0];
            $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
            if ($candidate === false || !is_file($candidate) || !str_starts_with(strtolower($candidate), strtolower($publicRoot . DIRECTORY_SEPARATOR))) return $match[0];
            if (!isset($seen[$candidate])) {
                $mime = function_exists('mime_content_type') ? (string) mime_content_type($candidate) : 'image/jpeg';
                $content = str_starts_with($mime, 'image/') ? file_get_contents($candidate) : false;
                if ($content === false) return $match[0];
                $seen[$candidate] = 'mbimg_' . bin2hex(random_bytes(8)) . '@maison-bebe.ro';
                $images[] = ['cid'=>$seen[$candidate], 'mime'=>$mime, 'name'=>basename($candidate), 'content'=>$content];
            }
            return $match[1] . 'cid:' . $seen[$candidate] . $match[3];
        }, $html) ?? $html;
        return [$html, $images];
    }
    private function connect(array $profile): void
    {
        $host = trim((string) ($profile['smtp_host'] ?? ''));
        $port = (int) ($profile['smtp_port'] ?? 465);
        $encryption = (string) ($profile['smtp_encryption'] ?? 'ssl');
        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $errno = 0;
        $error = '';
        $this->socket = @stream_socket_client($target, $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Conexiune SMTP eșuată: ' . ($error ?: 'eroare ' . $errno));
        }
        stream_set_timeout($this->socket, 20);
        $this->expect([220]);
        $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'maison-bebe.ro'), [250]);
        if ($encryption === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Negocierea TLS SMTP a eșuat.');
            }
            $this->command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'maison-bebe.ro'), [250]);
        }
        $username = (string) ($profile['smtp_username'] ?? '');
        $password = (string) ($profile['password'] ?? '');
        if ($username !== '') {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($username), [334]);
            $this->command(base64_encode($password), [235]);
        }
    }

    private function command(string $command, array $codes): string
    {
        $this->write($command . "\r\n");
        return $this->expect($codes);
    }

    private function write(string $payload): void
    {
        if (!is_resource($this->socket) || fwrite($this->socket, $payload) === false) {
            throw new RuntimeException('Conexiunea SMTP s-a întrerupt.');
        }
    }

    private function expect(array $codes): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Conexiunea SMTP nu este deschisă.');
        }
        $response = '';
        do {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new RuntimeException('Serverul SMTP nu a răspuns.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP ' . $code . ': ' . trim(preg_replace('/\s+/', ' ', $response) ?? $response));
        }
        return $response;
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function header(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', trim($value));
        if (preg_match('/(?:Ã|Â|Ä|È)/u', $value) === 1) {
            $decoded = @mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
            if (is_string($decoded) && $decoded !== '' && mb_check_encoding($decoded, 'UTF-8')) {
                $value = $decoded;
            }
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public function __destruct()
    {
        $this->close();
    }
}
