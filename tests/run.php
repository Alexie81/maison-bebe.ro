<?php

declare(strict_types=1);

$tests = [
    __DIR__ . '/smoke.php',
    __DIR__ . '/html-sanitizer.php',
    __DIR__ . '/stripe-webhook.php',
    __DIR__ . '/google-merchant.php',
    __DIR__ . '/google-analytics.php',
    __DIR__ . '/accounting-stock.php',
    __DIR__ . '/accounting-product-scope.php',
    __DIR__ . '/cod-payment.php',
    __DIR__ . '/gift-box-accounting-stock.php',
    __DIR__ . '/gift-box-dimensions.php',
    __DIR__ . '/product-set-invoice.php',
    __DIR__ . '/product-set-mixed-sales-invoice.php',
    __DIR__ . '/product-optional-variants.php',
    __DIR__ . '/product-personalization.php',
    __DIR__ . '/product-gift-box-option.php',
    __DIR__ . '/invoice-accounting-export.php',
    __DIR__ . '/invoice-storno.php',
];

$failed = false;
foreach ($tests as $test) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test);
    passthru($command, $status);
    if ($status !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
