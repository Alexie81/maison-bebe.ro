<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class XlsxService
{
    public function export(string $sheetName, array $headers, array $rows, array $metadata = [], array $columnTypes = []): string
    {
        $allRows = [];
        foreach ($metadata as $label => $value) {
            $allRows[] = [(string) $label, (string) $value];
        }
        if ($metadata) {
            $allRows[] = [];
        }
        $allRows[] = array_values($headers);
        foreach ($rows as $row) {
            $allRows[] = array_values($row);
        }
        $metadataRows = count($metadata);
        $headerRow = $metadataRows + ($metadata ? 2 : 1);
        $lastRow = count($allRows);
        $maxColumns = max(1, ...array_map('count', $allRows));
        $widths = array_fill(0, $maxColumns, 10);
        foreach ($allRows as $row) {
            foreach ($row as $columnIndex => $value) {
                $length = mb_strlen((string) $value);
                $widths[$columnIndex] = max($widths[$columnIndex], min(34, $length + 2));
            }
        }
        if ($metadata) $widths[0] = max($widths[0], 22);
        $columnsXml = '<cols>';
        foreach ($widths as $columnIndex => $width) {
            $columnsXml .= '<col min="'.($columnIndex+1).'" max="'.($columnIndex+1).'" width="'.$width.'" customWidth="1"/>';
        }
        $columnsXml .= '</cols>';
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow+1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . $columnsXml . '<sheetData>';
        foreach ($allRows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $height = $excelRow === $headerRow ? 26 : ($excelRow <= $metadataRows ? 22 : 20);
            $sheetXml .= '<row r="' . $excelRow . '" ht="'.$height.'" customHeight="1">';
            foreach ($row as $columnIndex => $value) {
                $ref = $this->columnName($columnIndex + 1) . $excelRow;
                $columnType = (string) ($columnTypes[$columnIndex] ?? '');
                $forceText = $excelRow > $headerRow && $columnType === 'text';
                $numeric = !$forceText && (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value)));
                $style = $excelRow <= $metadataRows
                    ? ($columnIndex === 0 ? 1 : 2)
                    : ($excelRow === $headerRow ? 3 : ($numeric ? ($columnType === 'integer' ? 6 : 5) : 4));
                if ($numeric) {
                    $sheetXml .= '<c r="' . $ref . '" s="'.$style.'" t="n"><v>' . htmlspecialchars((string) $value, ENT_XML1, 'UTF-8') . '</v></c>';
                } else {
                    $sheetXml .= '<c r="' . $ref . '" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                        . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
                }
            }
            $sheetXml .= '</row>';
        }
        $lastColumn = $this->columnName($maxColumns);
        $sheetXml .= '</sheetData>' . ($lastRow >= $headerRow ? '<autoFilter ref="A'.$headerRow.':'.$lastColumn.$lastRow.'"/>' : '') . '</worksheet>';
        $safeName = mb_substr(preg_replace('~[\\\\/?*\[\]:]~u', ' ', $sheetName) ?: 'Export', 0, 31);
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars($safeName, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="10"/><name val="Aptos"/><color rgb="FF3A2D28"/></font><font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF3A2D28"/></font><font><b/><sz val="10"/><name val="Aptos"/><color rgb="FFFFFFFF"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF8F2EB"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF9A715E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFE3D7CF"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];
        return $this->zip($files);
    }

    public function import(string $path): array
    {
        $entries = $this->unzip($path);
        $sheet = $entries['xl/worksheets/sheet1.xml'] ?? null;
        if ($sheet === null) {
            throw new RuntimeException('Fișierul XLSX nu conține prima foaie de calcul.');
        }
        if (preg_match('/<f(?:\s|>)/i', $sheet)) {
            throw new RuntimeException('Importul conține formule. Înlocuiește formulele cu valori înainte de import.');
        }
        $shared = [];
        if (isset($entries['xl/sharedStrings.xml'])) {
            $document = $this->xml($entries['xl/sharedStrings.xml']);
            $xpath = new DOMXPath($document);
            foreach ($xpath->query('//*[local-name()="si"]') ?: [] as $item) {
                $parts = [];
                foreach ((new DOMXPath($document))->query('.//*[local-name()="t"]', $item) ?: [] as $text) {
                    $parts[] = $text->textContent;
                }
                $shared[] = implode('', $parts);
            }
        }
        $document = $this->xml($sheet);
        $xpath = new DOMXPath($document);
        $rows = [];
        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $rowNode) {
            $row = [];
            foreach ($xpath->query('./*[local-name()="c"]', $rowNode) ?: [] as $cell) {
                if (!$cell instanceof DOMElement) continue;
                $reference = $cell->getAttribute('r');
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = $this->columnIndex($match[0] ?? 'A');
                $type = $cell->getAttribute('t');
                $valueNode = $xpath->query('./*[local-name()="v"]', $cell)?->item(0);
                $value = $valueNode?->textContent ?? '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $parts = [];
                    foreach ($xpath->query('.//*[local-name()="t"]', $cell) ?: [] as $text) $parts[] = $text->textContent;
                    $value = implode('', $parts);
                }
                $row[$column] = trim($value);
            }
            if ($row && array_filter($row, static fn(string $value): bool => $value !== '')) {
                $max = max(array_keys($row));
                $rows[] = array_map(static fn($value): string => (string) $value, array_replace(array_fill(0, $max + 1, ''), $row));
            }
        }
        if (!$rows) {
            throw new RuntimeException('Fișierul XLSX nu conține rânduri importabile.');
        }
        return ['headers' => array_shift($rows), 'rows' => $rows];
    }

    private function zip(array $files): string
    {
        $body = '';
        $central = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();
        foreach ($files as $name => $contents) {
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0) . $name . $contents;
            $body .= $local;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($local);
        }
        $count = count($files);
        return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
    }

    private function unzip(string $path): array
    {
        $data = file_get_contents($path);
        if ($data === false) throw new RuntimeException('Fișierul XLSX nu poate fi citit.');
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false) throw new RuntimeException('Arhiva XLSX este invalidă.');
        $end = unpack('vdisk/vstart/ventriesDisk/ventries/VcentralSize/VcentralOffset/vcomment', substr($data, $eocd + 4, 18));
        $offset = (int) $end['centralOffset'];
        $entries = [];
        $totalUncompressed = 0;
        if ((int) $end['entries'] > 2000) throw new RuntimeException('Arhiva XLSX conține prea multe fișiere interne.');
        for ($index = 0; $index < (int) $end['entries']; $index++) {
            if (substr($data, $offset, 4) !== "PK\x01\x02") break;
            $header = unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/VlocalOffset', substr($data, $offset + 4, 42));
            $name = substr($data, $offset + 46, $header['nameLength']);
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) throw new RuntimeException('Arhiva XLSX conține o cale internă nesigură.');
            $totalUncompressed += (int) $header['uncompressed'];
            if ($totalUncompressed > 50 * 1024 * 1024) throw new RuntimeException('Conținutul decomprimat al fișierului XLSX este prea mare.');
            $localOffset = (int) $header['localOffset'];
            $local = unpack('vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength', substr($data, $localOffset + 4, 26));
            $start = $localOffset + 30 + $local['nameLength'] + $local['extraLength'];
            $compressed = substr($data, $start, $header['compressed']);
            $contents = match ((int) $header['method']) {
                0 => $compressed,
                8 => gzinflate($compressed),
                default => false,
            };
            if ($contents === false) throw new RuntimeException('Fișierul XLSX folosește o compresie neacceptată.');
            $entries[str_replace('\\', '/', $name)] = $contents;
            $offset += 46 + $header['nameLength'] + $header['extraLength'] + $header['commentLength'];
        }
        return $entries;
    }

    private function xml(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            throw new RuntimeException('Structura XML din XLSX este invalidă.');
        }
        libxml_use_internal_errors($previous);
        return $document;
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function columnIndex(string $letters): int
    {
        $value = 0;
        foreach (str_split($letters) as $letter) $value = ($value * 26) + (ord($letter) - 64);
        return max(0, $value - 1);
    }

    private function dosDateTime(): array
    {
        $year = max(1980, (int) date('Y'));
        return [
            ((int) date('H') << 11) | ((int) date('i') << 5) | (intdiv((int) date('s'), 2)),
            (($year - 1980) << 9) | ((int) date('m') << 5) | (int) date('d'),
        ];
    }
}
