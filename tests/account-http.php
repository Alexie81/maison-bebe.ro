<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;

$base = rtrim((string) Env::get('APP_URL', ''), '/');
$pdo = Database::connection();
$email = 'qa-customer-' . bin2hex(random_bytes(5)) . '@local.test';
$password = 'Client-QA-' . bin2hex(random_bytes(8));
$cookie = tempnam(sys_get_temp_dir(), 'mb-account-');

$request = static function (string $method, string $path, array $data = []) use ($base, $cookie): array {
    $headers = [];
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'MaisonBebeAccountAudit/1.0',
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
$assertRedirect = static function (array $response, string $needle): void {
    [$status, , $headers] = $response;
    $location = implode(',', $headers['location'] ?? []);
    if (!in_array($status, [302, 303], true) || !str_contains($location, $needle)) {
        throw new RuntimeException('Redirect neașteptat: ' . $status . ' ' . $location);
    }
};

$userId = 0;
try {
    [$status, , $googleHeaders] = $request('GET', '/auth/google');
    $googleLocation = implode(',', $googleHeaders['location'] ?? []);
    if (!in_array($status, [302, 303], true) || !str_starts_with($googleLocation, 'https://accounts.google.com/')) {
        throw new RuntimeException('Pornirea Google OAuth a eșuat.');
    }
    echo "[OK] Google OAuth redirect\n";

    [$status, $register] = $request('GET', '/cont/inregistrare');
    if ($status !== 200) {
        throw new RuntimeException('Pagina de înregistrare nu răspunde.');
    }
    $assertRedirect($request('POST', '/cont/inregistrare', [
        '_csrf' => $csrf($register),
        'first_name' => 'Client',
        'last_name' => 'QA',
        'email' => $email,
        'password' => $password,
    ]), '/cont');

    $statement = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $statement->execute([$email]);
    $userId = (int) $statement->fetchColumn();
    if ($userId < 1) {
        throw new RuntimeException('Contul QA nu a fost creat.');
    }
    echo "[OK] Customer registration\n";

    foreach (['/cont', '/cont/comenzi', '/cont/date-personale', '/cont/cupoane', '/cont/adrese'] as $path) {
        [$status, $body, , $error] = $request('GET', $path);
        if (
            $status !== 200
            || $error !== ''
            || preg_match('/(?:PHP (?:Warning|Fatal error|Parse error)|Uncaught (?:Error|Exception))/i', $body)
        ) {
            throw new RuntimeException($path . ' a eșuat cu status ' . $status . '.');
        }
        echo '[OK] ' . $path . PHP_EOL;
    }

    [, $profile] = $request('GET', '/cont/date-personale');
    $assertRedirect($request('POST', '/cont/date-personale', [
        '_csrf' => $csrf($profile),
        'first_name' => 'Clientă',
        'last_name' => 'QA Actualizat',
        'phone' => '+40 700 123 456',
        'email' => $email,
    ]), '/cont/date-personale');
    $assertRedirect($request('POST', '/cont/preferinte-email', [
        '_csrf' => $csrf($profile),
        'product_updates' => '1',
        'article_updates' => '1',
    ]), '/cont/date-personale');
    $profileRow = $pdo->prepare('SELECT first_name,last_name,phone FROM users WHERE id=?');
    $profileRow->execute([$userId]);
    $profileState = $profileRow->fetch();
    if (($profileState['first_name'] ?? '') !== 'Clientă' || ($profileState['phone'] ?? '') !== '+40 700 123 456') {
        throw new RuntimeException('Actualizarea profilului nu a fost salvată.');
    }
    echo "[OK] Profile and email preferences\n";

    [, $addresses] = $request('GET', '/cont/adrese');
    $assertRedirect($request('POST', '/cont/adrese', [
        '_csrf' => $csrf($addresses),
        'name' => 'Acasă QA',
        'contact_first_name' => 'Clientă',
        'contact_last_name' => 'QA',
        'line1' => 'Strada Testului 10',
        'line2' => 'Apartament 2',
        'city' => 'București',
        'county' => 'București',
        'postal_code' => '010101',
        'phone' => '+40 700 123 456',
        'is_default' => '1',
    ]), '/cont/adrese');
    $address = $pdo->prepare('SELECT id FROM user_addresses WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $address->execute([$userId]);
    $addressId = (int) $address->fetchColumn();
    if ($addressId < 1) {
        throw new RuntimeException('Adresa QA nu a fost creată.');
    }
    [, $addresses] = $request('GET', '/cont/adrese');
    $assertRedirect($request('POST', '/cont/adrese/' . $addressId, [
        '_csrf' => $csrf($addresses),
        'name' => 'Acasă QA actualizată',
        'contact_first_name' => 'Clientă',
        'contact_last_name' => 'QA',
        'line1' => 'Strada Testului 12',
        'city' => 'București',
        'county' => 'București',
        'postal_code' => '010102',
        'phone' => '+40 700 123 456',
        'is_default' => '1',
    ]), '/cont/adrese');
    echo "[OK] Address create and update\n";

    [, $account] = $request('GET', '/cont');
    $assertRedirect($request('POST', '/cont/deconectare', ['_csrf' => $csrf($account)]), '/');
    [$status, , $headers] = $request('GET', '/cont');
    if (!in_array($status, [302, 303], true) || !str_contains(implode(',', $headers['location'] ?? []), '/cont/autentificare')) {
        throw new RuntimeException('Protecția contului după logout a eșuat.');
    }
    echo "[OK] Logout and account protection\n";
} finally {
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM newsletter_subscribers WHERE user_id=?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
    }
    if (is_file($cookie)) {
        unlink($cookie);
    }
}
