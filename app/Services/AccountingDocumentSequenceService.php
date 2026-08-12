<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use PDO;

final class AccountingDocumentSequenceService
{
    public function next(PDO $pdo, string $documentType, string $series, int $fiscalYear): array
    {
        $pdo->prepare(
            'INSERT IGNORE INTO accounting_document_sequences (document_type,series,fiscal_year,last_number) VALUES (?,?,?,0)'
        )->execute([$documentType, $series, $fiscalYear]);
        $statement = $pdo->prepare(
            'SELECT id,last_number,row_version FROM accounting_document_sequences '
            . 'WHERE document_type=? AND series=? AND fiscal_year=? FOR UPDATE'
        );
        $statement->execute([$documentType, $series, $fiscalYear]);
        $sequence = $statement->fetch();
        $number = (int) $sequence['last_number'] + 1;
        $pdo->prepare('UPDATE accounting_document_sequences SET last_number=?,row_version=row_version+1 WHERE id=?')
            ->execute([$number, $sequence['id']]);
        return [
            'series' => $series,
            'number' => $number,
            'formatted' => $series . '-' . $fiscalYear . '-' . str_pad((string) $number, 6, '0', STR_PAD_LEFT),
        ];
    }
}
