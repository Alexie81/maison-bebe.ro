<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use RuntimeException;

final class ZipService
{
    public function create(array $files): string
    {
        if ($files === []) {
            throw new RuntimeException('Arhiva nu poate fi creată fără fișiere.');
        }
        if (count($files) > 5000) {
            throw new RuntimeException('Arhiva conține prea multe fișiere. Restrânge perioada exportată.');
        }

        $body = '';
        $central = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();

        foreach ($files as $name => $contents) {
            $name = str_replace('\\', '/', trim((string) $name));
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                throw new RuntimeException('Arhiva conține o cale internă invalidă.');
            }
            if (!is_string($contents)) {
                throw new RuntimeException('Conținutul arhivei este invalid.');
            }

            $crc = crc32($contents);
            $size = strlen($contents);
            $deflated = gzdeflate($contents, 6);
            $method = $deflated !== false && strlen($deflated) < $size ? 8 : 0;
            $payload = $method === 8 ? $deflated : $contents;
            $compressedSize = strlen($payload);
            $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, $dosTime, $dosDate, $crc, $compressedSize, $size, $nameLength, 0)
                . $name . $payload;
            $body .= $local;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, $dosTime, $dosDate, $crc, $compressedSize, $size, $nameLength, 0, 0, 0, 0, 0, $offset)
                . $name;
            $offset += strlen($local);
        }

        $count = count($files);
        return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
    }

    private function dosDateTime(): array
    {
        $year = max(1980, (int) date('Y'));
        return [
            ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2),
            (($year - 1980) << 9) | ((int) date('m') << 5) | (int) date('d'),
        ];
    }
}
