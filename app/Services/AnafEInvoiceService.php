<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DOMDocument;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use RuntimeException;
use Throwable;

final class AnafEInvoiceService
{
    public function queue(int $invoiceId): int
    {
        $pdo = Database::connection();
        $invoice = $pdo->prepare("SELECT id,company_profile_id,status,document_type FROM invoices WHERE id=?");
        $invoice->execute([$invoiceId]);
        $invoice = $invoice->fetch();
        if (!$invoice || $invoice['status'] !== 'issued') {
            throw new RuntimeException('Doar un document fiscal emis poate fi trimis în SPV.');
        }
        $connection = $pdo->prepare(
            "SELECT a.id FROM anaf_connections a JOIN anaf_token_store t ON t.connection_id=a.id "
            . "WHERE a.company_profile_id=? AND a.environment='production' AND a.status='connected' "
            . "ORDER BY a.id DESC LIMIT 1"
        );
        $connection->execute([(int) $invoice['company_profile_id']]);
        $connectionId = (int) $connection->fetchColumn();
        if (!$connectionId) {
            throw new RuntimeException('Conexiunea ANAF de producție nu este autorizată. Conectează certificatul digital în RO e-Factura.');
        }
        $key = hash('sha256', 'anaf-efactura:' . $connectionId . ':' . $invoiceId);
        $pdo->prepare(
            "INSERT INTO efactura_submissions (invoice_id,connection_id,idempotency_key,status,available_at) "
            . "VALUES (?,?,?,'pending',NOW()) ON DUPLICATE KEY UPDATE "
            . "upload_id=IF(status IN ('rejected','requires_attention'),NULL,upload_id),"
            . "available_at=IF(status IN ('rejected','retry','requires_attention'),NOW(),available_at),"
            . "last_error=IF(status IN ('rejected','requires_attention'),NULL,last_error),"
            . "status=IF(status IN ('rejected','requires_attention'),'retry',status)"
        )->execute([$invoiceId, $connectionId, $key]);
        $id = $pdo->prepare('SELECT id FROM efactura_submissions WHERE idempotency_key=?');
        $id->execute([$key]);
        return (int) $id->fetchColumn();
    }

    public function process(int $limit = 5): int
    {
        $pdo = Database::connection();
        $limit = max(1, min(25, $limit));
        $rows = $pdo->query(
            "SELECT s.*,a.environment,a.config_json,i.company_profile_id,i.document_type "
            . "FROM efactura_submissions s JOIN anaf_connections a ON a.id=s.connection_id "
            . "JOIN invoices i ON i.id=s.invoice_id "
            . "WHERE s.status IN ('pending','retry','processing') AND s.available_at<=NOW() "
            . "ORDER BY s.available_at,s.id LIMIT " . $limit
        )->fetchAll();
        $processed = 0;
        foreach ($rows as $row) {
            try {
                $this->processSubmission($row);
                $processed++;
            } catch (Throwable $exception) {
                $attempts = (int) $row['attempts'] + 1;
                $status = $attempts >= 5 ? 'requires_attention' : 'retry';
                $pdo->prepare(
                    'UPDATE efactura_submissions SET status=?,attempts=?,last_error=?,available_at=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id=?'
                )->execute([$status, $attempts, mb_substr($exception->getMessage(), 0, 1000), min(60, 2 ** min(5, $attempts)), $row['id']]);
                $pdo->prepare("UPDATE anaf_connections SET last_error=? WHERE id=?")
                    ->execute([mb_substr($exception->getMessage(), 0, 1000), $row['connection_id']]);
            }
        }
        return $processed;
    }

    public function authorizationUrl(int $connectionId, string $state): string
    {
        $connection = $this->connection($connectionId);
        $credentials = $this->credentials($connection);
        $endpoint = trim((string) env('ANAF_AUTHORIZE_URL', '')) ?: 'https://logincert.anaf.ro/anaf-oauth2/v1/authorize';
        return $endpoint . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $credentials['redirect_uri'],
            'state' => $state,
            'token_content_type' => 'jwt',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(int $connectionId, string $code): void
    {
        $connection = $this->connection($connectionId);
        $credentials = $this->credentials($connection);
        $token = $this->tokenRequest($credentials, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $credentials['redirect_uri'],
            'token_content_type' => 'jwt',
        ]);
        $this->storeToken($connectionId, $token);
    }

    private function processSubmission(array $submission): void
    {
        $pdo = Database::connection();
        $token = $this->accessToken((int) $submission['connection_id']);
        $base = 'https://api.anaf.ro/' . ($submission['environment'] === 'test' ? 'test' : 'prod') . '/FCTEL/rest';
        if (empty($submission['upload_id'])) {
            $document = (new EInvoiceUblService())->generate((int) $submission['invoice_id']);
            $cif = $this->companyCif((int) $submission['company_profile_id']);
            $invoice = $pdo->prepare('SELECT customer_type FROM invoices WHERE id=?');
            $invoice->execute([(int) $submission['invoice_id']]);
            $isB2c = $invoice->fetchColumn() === 'individual';
            $endpoint = $base . '/' . ($isB2c ? 'uploadb2c' : 'upload') . '?' . http_build_query([
                'standard' => (string) ($document['standard'] ?? 'UBL'),
                'cif' => $cif,
            ]);
            $response = $this->request('POST', $endpoint, $token, (string) $document['xml']);
            $xml = $this->xml($response['body']);
            $header = $xml->documentElement;
            $uploadId = trim((string) ($header?->getAttribute('index_incarcare') ?? ''));
            $execution = trim((string) ($header?->getAttribute('ExecutionStatus') ?? ''));
            if ($uploadId === '' || ($execution !== '' && $execution !== '0')) {
                throw new RuntimeException('ANAF a refuzat încărcarea: ' . $this->errorMessage($xml));
            }
            $pdo->prepare(
                "UPDATE efactura_submissions SET upload_id=?,status='processing',attempts=attempts+1,response_json=?,last_error=NULL,available_at=DATE_ADD(NOW(),INTERVAL 2 MINUTE) WHERE id=?"
            )->execute([$uploadId, json_encode(['upload' => $response['body']], JSON_UNESCAPED_UNICODE), $submission['id']]);
            $pdo->prepare('UPDATE anaf_connections SET last_sync_at=NOW(),last_error=NULL WHERE id=?')->execute([$submission['connection_id']]);
            return;
        }

        $endpoint = $base . '/stareMesaj?' . http_build_query(['id_incarcare' => $submission['upload_id']]);
        $response = $this->request('GET', $endpoint, $token);
        $xml = $this->xml($response['body']);
        $header = $xml->documentElement;
        $state = mb_strtolower(trim((string) ($header?->getAttribute('stare') ?? '')));
        if ($state === '' && $this->errorMessage($xml) !== '') {
            throw new RuntimeException('ANAF nu a putut verifica documentul: ' . $this->errorMessage($xml));
        }
        if ($state === 'in prelucrare') {
            $pdo->prepare(
                "UPDATE efactura_submissions SET status='processing',attempts=attempts+1,response_json=?,available_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?"
            )->execute([json_encode(['status' => $response['body']], JSON_UNESCAPED_UNICODE), $submission['id']]);
            return;
        }
        $downloadId = trim((string) ($header?->getAttribute('id_descarcare') ?? ''));
        if ($state === 'nok' || str_contains($state, 'erori')) {
            if ($downloadId !== '') {
                $this->storeAnafResponse((int) $submission['invoice_id'], $downloadId, $base, $token);
            }
            $pdo->prepare(
                "UPDATE efactura_submissions SET status='rejected',attempts=attempts+1,response_json=?,last_error=? WHERE id=?"
            )->execute([
                json_encode(['status' => $response['body'], 'download_id' => $downloadId], JSON_UNESCAPED_UNICODE),
                'Document respins de validarea ANAF. Descarcă răspunsul pentru detalii.',
                $submission['id'],
            ]);
            return;
        }
        if ($state !== 'ok') {
            throw new RuntimeException('Răspuns ANAF necunoscut pentru indexul ' . $submission['upload_id'] . '.');
        }
        if ($downloadId !== '') {
            $this->storeAnafResponse((int) $submission['invoice_id'], $downloadId, $base, $token);
        }
        $pdo->prepare(
            "UPDATE efactura_submissions SET status='accepted',attempts=attempts+1,response_json=?,last_error=NULL WHERE id=?"
        )->execute([json_encode(['status' => $response['body'], 'download_id' => $downloadId], JSON_UNESCAPED_UNICODE), $submission['id']]);
        $pdo->prepare('UPDATE anaf_connections SET last_sync_at=NOW(),last_error=NULL WHERE id=?')->execute([$submission['connection_id']]);
    }

    private function accessToken(int $connectionId): string
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM anaf_token_store WHERE connection_id=?');
        $statement->execute([$connectionId]);
        $token = $statement->fetch();
        if (!$token) throw new RuntimeException('Tokenul OAuth ANAF lipsește. Reconectează certificatul digital.');
        if (strtotime((string) $token['expires_at']) <= time() + 86400) {
            if (empty($token['encrypted_refresh_token'])) throw new RuntimeException('Tokenul ANAF a expirat și nu are refresh token.');
            $connection = $this->connection($connectionId);
            $credentials = $this->credentials($connection);
            $refreshed = $this->tokenRequest($credentials, [
                'grant_type' => 'refresh_token',
                'refresh_token' => Encryptor::decrypt((string) $token['encrypted_refresh_token']),
            ]);
            $this->storeToken($connectionId, $refreshed);
            $statement->execute([$connectionId]);
            $token = $statement->fetch();
        }
        return Encryptor::decrypt((string) $token['encrypted_access_token']);
    }

    private function tokenRequest(array $credentials, array $fields): array
    {
        $endpoint = trim((string) env('ANAF_TOKEN_URL', '')) ?: 'https://logincert.anaf.ro/anaf-oauth2/v1/token';
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $credentials['client_id'] . ':' . $credentials['client_secret'],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($raw === false || $status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
            throw new RuntimeException('Autorizarea OAuth ANAF a eșuat: ' . ($error ?: (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'răspuns invalid')));
        }
        return $decoded;
    }

    private function storeToken(int $connectionId, array $token): void
    {
        $expiresIn = max(60, (int) ($token['expires_in'] ?? 7776000));
        $pdo = Database::connection();
        $existing = $pdo->prepare('SELECT encrypted_refresh_token FROM anaf_token_store WHERE connection_id=?');
        $existing->execute([$connectionId]);
        $existingRefresh = $existing->fetchColumn() ?: null;
        $encryptedRefresh = !empty($token['refresh_token'])
            ? Encryptor::encrypt((string) $token['refresh_token'])
            : $existingRefresh;
        $pdo->prepare(
            'INSERT INTO anaf_token_store (connection_id,encrypted_access_token,encrypted_refresh_token,expires_at,scope) '
            . 'VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND),?) ON DUPLICATE KEY UPDATE '
            . 'encrypted_access_token=VALUES(encrypted_access_token),encrypted_refresh_token=VALUES(encrypted_refresh_token),expires_at=VALUES(expires_at),scope=VALUES(scope)'
        )->execute([$connectionId, Encryptor::encrypt((string) $token['access_token']), $encryptedRefresh, $expiresIn, $token['scope'] ?? null]);
        $pdo->prepare("UPDATE anaf_connections SET status='connected',last_error=NULL,last_sync_at=NOW() WHERE id=?")->execute([$connectionId]);
    }

    private function connection(int $connectionId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM anaf_connections WHERE id=?');
        $statement->execute([$connectionId]);
        $connection = $statement->fetch();
        if (!$connection) throw new RuntimeException('Conexiunea ANAF nu a fost găsită.');
        return $connection;
    }

    private function credentials(array $connection): array
    {
        $config = json_decode((string) ($connection['config_json'] ?? '{}'), true) ?: [];
        $clientId = trim((string) ($config['client_id'] ?? env('ANAF_CLIENT_ID', '')));
        $encryptedSecret = trim((string) ($config['encrypted_client_secret'] ?? ''));
        $clientSecret = $encryptedSecret !== '' ? Encryptor::decrypt($encryptedSecret) : trim((string) env('ANAF_CLIENT_SECRET', ''));
        $redirectUri = trim((string) ($config['redirect_uri'] ?? env('ANAF_REDIRECT_URI', '')));
        if ($redirectUri === '') $redirectUri = absolute_url('/admin/facturare/efactura/callback');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Completează Client ID și Client Secret ANAF înainte de autorizare.');
        }
        return ['client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirectUri];
    }

    private function companyCif(int $companyId): string
    {
        $statement = Database::connection()->prepare('SELECT tax_id FROM company_profiles WHERE id=?');
        $statement->execute([$companyId]);
        $cif = preg_replace('/\D+/', '', (string) $statement->fetchColumn()) ?: '';
        if ($cif === '') throw new RuntimeException('CUI-ul firmei nu este valid pentru transmiterea ANAF.');
        return $cif;
    }

    private function request(string $method, string $url, string $token, ?string $body = null): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/xml', 'Content-Type: text/plain'],
        ]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($raw === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Cererea către ANAF a eșuat (HTTP ' . $status . '): ' . ($error ?: mb_substr((string) $raw, 0, 400)));
        }
        return ['status' => $status, 'body' => (string) $raw];
    }

    private function xml(string $body): DOMDocument
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('ANAF a returnat un răspuns care nu este XML valid.');
        }
        return $dom;
    }

    private function errorMessage(DOMDocument $dom): string
    {
        $messages = [];
        foreach ($dom->getElementsByTagName('Errors') as $node) {
            $message = trim((string) $node->attributes?->getNamedItem('errorMessage')?->nodeValue);
            if ($message !== '') $messages[] = $message;
        }
        return implode('; ', array_unique($messages)) ?: 'răspuns fără detalii';
    }

    private function storeAnafResponse(int $invoiceId, string $downloadId, string $base, string $token): void
    {
        $response = $this->request('GET', $base . '/descarcare?' . http_build_query(['id' => $downloadId]), $token);
        $relative = '/invoices/anaf/' . date('Y/m') . '/anaf-' . $invoiceId . '-' . preg_replace('/\D+/', '', $downloadId) . '.zip';
        $path = BASE_PATH . '/storage' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Directorul răspunsurilor ANAF nu a putut fi creat.');
        }
        if (file_put_contents($path, $response['body'], LOCK_EX) === false) {
            throw new RuntimeException('Răspunsul semnat ANAF nu a putut fi salvat.');
        }
        $hash = hash_file('sha256', $path);
        $exists = Database::connection()->prepare("SELECT id FROM invoice_artifacts WHERE invoice_id=? AND artifact_type='provider_response' AND sha256=?");
        $exists->execute([$invoiceId, $hash]);
        if (!$exists->fetchColumn()) {
            Database::connection()->prepare(
                "INSERT INTO invoice_artifacts (invoice_id,artifact_type,path,mime_type,sha256,size_bytes) VALUES (?,'provider_response',?,'application/zip',?,?)"
            )->execute([$invoiceId, $relative, $hash, filesize($path)]);
        }
    }
}
