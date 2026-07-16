<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;

$base = rtrim((string) Env::get('APP_URL', ''), '/');
$pdo = Database::connection();
$email = 'qa-forms-' . bin2hex(random_bytes(6)) . '@local.test';
$subject = 'Mesaj QA ' . bin2hex(random_bytes(5));
$subscriberId = 0;
$contactId = 0;
$queueId = 0;
$maxContact = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM contact_messages')->fetchColumn();
$maxQueue = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM email_queue')->fetchColumn();

$cookie = tempnam(sys_get_temp_dir(), 'mb-forms-');
$request = static function (string $method, string $path, array $data = [], array $extraHeaders = []) use ($base, $cookie): array {
    $headers = [];
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'MaisonBebeFormsAudit/1.0',
        CURLOPT_HTTPHEADER => $extraHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    return [$status, is_string($body) ? $body : '', $headers, $error];
};
$csrf = static function (string $html): string {
    if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('Tokenul CSRF nu a fost găsit.');
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};

try {
    [$status, $home] = $request('GET', '/');
    if ($status !== 200) {
        throw new RuntimeException('Homepage indisponibil.');
    }
    [$invalidStatus] = $request('POST', '/newsletter/abonare', [
        'email' => $email,
    ], ['Accept: application/json']);
    if ($invalidStatus !== 403) {
        throw new RuntimeException('Newsletterul acceptă cereri fără CSRF.');
    }
    [$status, $body, , $error] = $request('POST', '/newsletter/abonare', [
        '_csrf' => $csrf($home),
        'email' => $email,
    ], ['Accept: application/json']);
    $payload = json_decode($body, true);
    if ($status !== 200 || $error !== '' || !is_array($payload) || empty($payload['ok'])) {
        throw new RuntimeException('Abonarea newsletter a eșuat: ' . $status . ' ' . $body . ' ' . $error);
    }
    $statement = $pdo->prepare('SELECT id,unsubscribe_token,status FROM newsletter_subscribers WHERE email=?');
    $statement->execute([$email]);
    $subscriber = $statement->fetch();
    $subscriberId = (int) ($subscriber['id'] ?? 0);
    if ($subscriberId < 1 || ($subscriber['status'] ?? '') !== 'active') {
        throw new RuntimeException('Abonarea newsletter nu a fost salvată.');
    }
    [$status] = $request('GET', '/newsletter/dezabonare/' . $subscriber['unsubscribe_token']);
    $statement = $pdo->prepare('SELECT status FROM newsletter_subscribers WHERE id=?');
    $statement->execute([$subscriberId]);
    if ($status !== 200 || $statement->fetchColumn() !== 'unsubscribed') {
        throw new RuntimeException('Dezabonarea newsletter a eșuat.');
    }
    echo "[OK] Newsletter subscribe, CSRF and unsubscribe\n";

    [$status, $contact] = $request('GET', '/contact');
    if ($status !== 200) {
        throw new RuntimeException('Pagina de contact este indisponibilă.');
    }
    [$invalidStatus] = $request('POST', '/contact', [
        'name' => 'Client QA',
        'email' => $email,
        'subject' => $subject,
        'message' => 'Mesaj automat de audit care nu trebuie procesat.',
    ]);
    if ($invalidStatus !== 403) {
        throw new RuntimeException('Formularul de contact acceptă cereri fără CSRF.');
    }
    [$status, , $headers, $error] = $request('POST', '/contact', [
        '_csrf' => $csrf($contact),
        'name' => 'Client QA',
        'email' => $email,
        'phone' => '+40 700 000 000',
        'subject' => $subject,
        'message' => 'Mesaj automat de audit. Înregistrarea și emailul din coadă vor fi șterse imediat.',
        'website' => '',
    ]);
    $location = implode(',', $headers['location'] ?? []);
    if (!in_array($status, [302, 303], true) || !str_contains($location, '/contact') || $error !== '') {
        throw new RuntimeException('Trimiterea formularului de contact a eșuat.');
    }
    $statement = $pdo->prepare('SELECT id FROM contact_messages WHERE id>? AND email=? AND subject=? ORDER BY id DESC LIMIT 1');
    $statement->execute([$maxContact, $email, $subject]);
    $contactId = (int) $statement->fetchColumn();
    $statement = $pdo->prepare("SELECT id FROM email_queue WHERE id>? AND template_key='contact_admin' AND subject=? ORDER BY id DESC LIMIT 1");
    $statement->execute([$maxQueue, 'Mesaj nou din website: ' . $subject]);
    $queueId = (int) $statement->fetchColumn();
    if ($contactId < 1 || $queueId < 1) {
        throw new RuntimeException('Mesajul sau notificarea internă de contact nu a fost creată.');
    }
    echo "[OK] Contact form, CSRF, persistence and email queue\n";
} finally {
    if ($queueId > 0) {
        $pdo->prepare('DELETE FROM email_queue WHERE id=?')->execute([$queueId]);
    }
    if ($contactId > 0) {
        $pdo->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$contactId]);
    }
    if ($subscriberId > 0) {
        $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id=?')->execute([$subscriberId]);
    }
    if (is_file($cookie)) {
        unlink($cookie);
    }
}
