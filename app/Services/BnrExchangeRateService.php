<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use RuntimeException;

final class BnrExchangeRateService
{
    private const OFFICIAL_URL = 'https://www.bnr.ro/nbrfxrates.xml';
    private const MIRROR_URL = 'https://cursbnr.servicii-informatice.ro/api_public.php';
    private const GLOBAL_URL = 'https://open.er-api.com/v6/latest/';

    public function latest(string $currency): array
    {
        $currency = strtoupper(trim($currency));
        // "TL" is still printed on some Turkish invoices; normalize it to the
        // current ISO 4217 code before validation and rate lookup.
        if ($currency === 'TL') {
            $currency = 'TRY';
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException('Moneda selectată nu este validă.');
        }
        if ($currency === 'RON') {
            return ['currency'=>'RON','rate'=>'1.000000','date'=>date('Y-m-d'),'source'=>'RON','stale'=>false];
        }

        $cached = $this->cache();
        if (($cached['date'] ?? '') === date('Y-m-d') && isset($cached['rates'][$currency])) {
            return $this->result($currency, $cached, false);
        }

        try {
            $fresh = $this->officialRates() ?: $this->mirrorRates();
            if ($fresh && !empty($fresh['rates'])) {
                $this->writeCache($fresh);
                if (isset($fresh['rates'][$currency])) {
                    return $this->result($currency, $fresh, false);
                }
            }
        } catch (\Throwable) {
            // A previously saved BNR rate is safer than replacing the field with zero.
        }

        if (isset($cached['rates'][$currency])) {
            return $this->result($currency, $cached, true);
        }
        try {
            return $this->globalRate($currency);
        } catch (\Throwable) {
            throw new RuntimeException('Cursul valutar pentru ' . $currency . ' nu este disponibil momentan. Încearcă din nou peste câteva minute.');
        }
    }

    private function officialRates(): ?array
    {
        $body = $this->download(self::OFFICIAL_URL, 'application/xml,text/xml;q=0.9,*/*;q=0.5');
        if (!str_contains($body, '<Rate') || !str_contains($body, '<Cube')) {
            return null;
        }
        $xml = @simplexml_load_string($body);
        if (!$xml) {
            return null;
        }
        $cubes = $xml->xpath('//*[local-name()="Cube"]') ?: [];
        $cube = end($cubes);
        if (!$cube) {
            return null;
        }
        $rates = [];
        foreach ($cube->children() as $node) {
            if ($node->getName() !== 'Rate') continue;
            $attributes = $node->attributes();
            $code = strtoupper((string)($attributes['currency'] ?? ''));
            $multiplier = max(1, (int)($attributes['multiplier'] ?? 1));
            if (preg_match('/^[A-Z]{3}$/', $code)) $rates[$code] = (float)$node / $multiplier;
        }
        return ['date'=>(string)($cube['date'] ?? date('Y-m-d')),'source'=>'BNR · flux XML oficial','rates'=>$rates];
    }

    private function mirrorRates(): ?array
    {
        $decoded = json_decode($this->download(self::MIRROR_URL, 'application/json'), true);
        if (!is_array($decoded) || empty($decoded['rates']) || empty($decoded['data'])) {
            return null;
        }
        $rates = [];
        foreach ($decoded['rates'] as $code => $row) {
            if (!is_array($row) || !preg_match('/^[A-Z]{3}$/', (string)$code)) continue;
            $multiplier = max(1, (int)($row['multiplicator'] ?? 1));
            $rates[(string)$code] = (float)($row['valoare'] ?? 0) / $multiplier;
        }
        return ['date'=>(string)$decoded['data'],'source'=>'BNR · flux public de rezervă','rates'=>$rates];
    }

    private function download(string $url, string $accept): string
    {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Conexiunea pentru cursul valutar nu a putut fi inițializată.');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'MaisonBebe-Accounting/1.0',CURLOPT_HTTPHEADER=>['Accept: '.$accept]]);
        $body = curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('Sursa cursului valutar nu a răspuns corect.');
        return $body;
    }

    private function cache(): array
    {
        $path=$this->cachePath();if(!is_file($path))return [];
        $decoded=json_decode((string)file_get_contents($path),true);return is_array($decoded)?$decoded:[];
    }

    private function writeCache(array $rates): void
    {
        $path=$this->cachePath();$directory=dirname($path);
        if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))return;
        file_put_contents($path,json_encode($rates,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    }

    private function cachePath(): string
    {
        return BASE_PATH.'/storage/cache/bnr-rates.json';
    }

    private function globalRate(string $currency): array
    {
        $path = BASE_PATH . '/storage/cache/global-rate-' . strtolower($currency) . '.json';
        $cached = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $cached = is_array($decoded) ? $decoded : [];
            if ((int) ($cached['cached_at'] ?? 0) > time() - 86400 && (float) ($cached['rate'] ?? 0) > 0) {
                return $this->globalResult($currency, $cached, false);
            }
        }

        $decoded = json_decode($this->download(self::GLOBAL_URL . rawurlencode($currency), 'application/json'), true);
        $rate = (float) ($decoded['rates']['RON'] ?? 0);
        if (!is_array($decoded) || ($decoded['result'] ?? '') !== 'success' || $rate <= 0) {
            if ((float) ($cached['rate'] ?? 0) > 0) return $this->globalResult($currency, $cached, true);
            throw new RuntimeException('Sursa extinsă nu publică această monedă.');
        }
        $timestamp = (int) ($decoded['time_last_update_unix'] ?? time());
        $data = ['rate'=>$rate,'date'=>date('Y-m-d',$timestamp),'cached_at'=>time()];
        $directory = dirname($path);
        if ((!is_dir($directory) && mkdir($directory, 0750, true)) || is_dir($directory)) {
            file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        return $this->globalResult($currency, $data, false);
    }

    private function globalResult(string $currency,array $data,bool $stale):array
    {
        return ['currency'=>$currency,'rate'=>number_format((float)$data['rate'],6,'.',''),'date'=>(string)$data['date'],'source'=>'ExchangeRate-API · curs zilnic','stale'=>$stale];
    }

    private function result(string $currency,array $data,bool $stale):array
    {
        return ['currency'=>$currency,'rate'=>number_format((float)$data['rates'][$currency],6,'.',''),'date'=>(string)$data['date'],'source'=>(string)($data['source']??'BNR'),'stale'=>$stale];
    }
}
