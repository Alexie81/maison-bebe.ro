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
        $this->connect($profile);
        $from = (string) $profile['from_email'];
        $this->command('MAIL FROM:<' . $from . '>', [250]);
        $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
        $this->command('DATA', [354]);

        $boundary = 'mb_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@maison-bebe.ro>',
            'From: ' . $this->header((string) $profile['from_name']) . ' <' . $from . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $this->header($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if (!empty($profile['reply_to_email'])) {
            $headers[] = 'Reply-To: <' . $profile['reply_to_email'] . '>';
        }
        $text ??= trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($text) . "\r\n";
        $message .= '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($html) . "\r\n";
        $message .= '--' . $boundary . "--\r\n";
        $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
        $this->write($message . ".\r\n");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        $this->close();
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
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public function __destruct()
    {
        $this->close();
    }
}
