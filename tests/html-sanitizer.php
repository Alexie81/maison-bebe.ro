<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\HtmlSanitizer;

set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});

try {
    $clean = HtmlSanitizer::clean(
        '<p onclick="x()">Text</p><script>alert(1)</script>'
        . '<a href="javascript:x">Rău</a><a href="#detalii">Detalii</a>'
    );
} finally {
    restore_error_handler();
}

if (
    str_contains($clean, '<script')
    || str_contains($clean, 'onclick')
    || str_contains($clean, 'javascript:')
    || !str_contains($clean, 'href="#detalii"')
) {
    fwrite(STDERR, "HtmlSanitizer regression test failed.\n");
    exit(1);
}

fwrite(STDOUT, "HtmlSanitizer regression test: OK\n");
