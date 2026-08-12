<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Env;
use RuntimeException;

final class GoogleMerchantClient
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function isEnabled(): bool
    {
        return Env::bool('GOOGLE_MERCHANT_ENABLED', false)
            && $this->accountId(false) !== null
            && $this->dataSourceId(false) !== null
            && is_file($this->credentialsPath(false) ?? '');
    }

    public function accountId(bool $required = true): ?string
    {
        return $this->digitsSetting('GOOGLE_MERCHANT_ACCOUNT_ID', $required);
    }

    public function dataSourceId(bool $required = true): ?string
    {
        return $this->digitsSetting('GOOGLE_MERCHANT_DATA_SOURCE_ID', $required);
    }

    public function dataSourceName(): string
    {
        return 'accounts/' . $this->accountId() . '/dataSources/' . $this->dataSourceId();
    }

    public function dataSource(): array
    {
        return $this->request('GET', '/datasources/v1/' . $this->dataSourceName());
    }

    public function account(): array
    {
        return $this->request('GET', '/accounts/v1/accounts/' . $this->accountId());
    }

    public function insertProduct(array $productInput): array
    {
        return $this->request(
            'POST',
            '/products/v1/accounts/' . $this->accountId() . '/productInputs:insert',
            $productInput,
            ['dataSource' => $this->dataSourceName()]
        );
    }

    public function deleteProduct(string $offerId): void
    {
        $productId = $this->contentLanguage() . '~' . $this->feedLabel() . '~' . $offerId;
        $encodedProductId = rtrim(strtr(base64_encode($productId), '+/', '-_'), '=');
        $this->request(
            'DELETE',
            '/products/v1/accounts/' . $this->accountId() . '/productInputs/' . $encodedProductId,
            null,
            ['dataSource' => $this->dataSourceName()],
            [404]
        );
    }

    public function contentLanguage(): string
    {
        $value = strtolower(trim((string) Env::get('GOOGLE_MERCHANT_LANGUAGE', 'ro')));
        return preg_match('/^[a-z]{2,3}$/', $value) ? $value : 'ro';
    }

    public function feedLabel(): string
    {
        $value = strtoupper(trim((string) Env::get('GOOGLE_MERCHANT_FEED_LABEL', 'RO')));
        return preg_match('/^[A-Z0-9_-]{1,20}$/', $value) ? $value : 'RO';
    }

    private function request(string $method, string $path, mixed $body = null, array $query = [], array $allowedStatuses = []): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Sincronizarea Google Merchant nu este configurată sau activată.');
        }
        $url = 'https://merchantapi.googleapis.com' . $path;
        if ($query) $url .= '?' . http_build_query($query);
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Conexiunea Google Merchant nu a putut fi inițializată.');
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token(),
        ];
        if ($body !== null) $headers[] = 'Content-Type: application/json; charset=utf-8';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        if ($raw === false) throw new RuntimeException('Google Merchant nu răspunde: ' . $curlError);
        $decoded = $raw === '' ? [] : json_decode((string) $raw, true);
        if (!is_array($decoded)) throw new RuntimeException('Google Merchant a returnat un răspuns invalid.');
        if ($status >= 400 && !in_array($status, $allowedStatuses, true)) {
            $message = (string) ($decoded['error']['message'] ?? 'Eroare Google Merchant API.');
            throw new RuntimeException('Google Merchant (' . $status . '): ' . mb_substr($message, 0, 850));
        }
        return $decoded;
    }

    private function token(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) return $this->accessToken;
        $credentialsPath = $this->credentialsPath();
        $credentials = json_decode((string) file_get_contents($credentialsPath), true, flags: JSON_THROW_ON_ERROR);
        foreach (['client_email', 'private_key'] as $field) {
            if (trim((string) ($credentials[$field] ?? '')) === '') throw new RuntimeException('Fișierul Google Merchant nu conține ' . $field . '.');
        }
        $tokenUri = trim((string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
        if (!str_starts_with($tokenUri, 'https://')) throw new RuntimeException('Adresa OAuth Google este invalidă.');
        $now = time();
        $encode = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/content',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $unsigned = $header . '.' . $claims;
        if (!openssl_sign($unsigned, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Semnarea solicitării Google Merchant a eșuat.');
        }
        $assertion = $unsigned . '.' . $encode($signature);
        $curl = curl_init($tokenUri);
        if ($curl === false) throw new RuntimeException('Autentificarea Google nu a putut fi inițializată.');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        if ($raw === false) throw new RuntimeException('Autentificarea Google nu răspunde: ' . $curlError);
        $response = json_decode((string) $raw, true);
        if ($status >= 400 || !is_array($response) || empty($response['access_token'])) {
            throw new RuntimeException('Autentificarea Google Merchant a eșuat: ' . mb_substr((string) ($response['error_description'] ?? $response['error'] ?? 'răspuns invalid'), 0, 600));
        }
        $this->accessToken = (string) $response['access_token'];
        $this->accessTokenExpiresAt = $now + max(300, (int) ($response['expires_in'] ?? 3600));
        return $this->accessToken;
    }

    private function credentialsPath(bool $required = true): ?string
    {
        $configured = trim((string) Env::get('GOOGLE_MERCHANT_CREDENTIALS_PATH', 'storage/private_uploads/google-merchant-service-account.json'));
        if ($configured === '') {
            if ($required) throw new RuntimeException('Calea credentialelor Google Merchant lipsește.');
            return null;
        }
        $isAbsolute = str_starts_with($configured, '/') || str_starts_with($configured, '\\')
            || (strlen($configured) > 2 && ctype_alpha($configured[0]) && $configured[1] === ':');
        $path = $isAbsolute ? $configured : BASE_PATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured);
        if ($required && !is_file($path)) throw new RuntimeException('Fișierul de autentificare Google Merchant nu a fost găsit.');
        return $path;
    }

    private function digitsSetting(string $key, bool $required): ?string
    {
        $value = trim((string) Env::get($key, ''));
        if ($value !== '' && ctype_digit($value)) return $value;
        if ($required) throw new RuntimeException('Configurația ' . $key . ' lipsește sau este invalidă.');
        return null;
    }
}
