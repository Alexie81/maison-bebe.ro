<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;

final class InvoiceController
{
    public function download(Request $request, string $hash): never
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new HttpException(404, 'Factura nu a fost găsită.');
        }
        $statement = Database::connection()->prepare("SELECT a.path,i.number FROM invoices i JOIN invoice_artifacts a ON a.invoice_id=i.id AND a.artifact_type='pdf' WHERE i.document_hash=? AND i.status='issued' ORDER BY a.id DESC LIMIT 1");
        $statement->execute([$hash]);
        $row = $statement->fetch();
        $path = $row ? BASE_PATH . '/storage' . $row['path'] : '';
        if (!$row || !is_file($path)) {
            throw new HttpException(404, 'Factura nu a fost găsită.');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="factura-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $row['number']) . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }
}
