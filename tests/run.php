<?php

declare(strict_types=1);

$tests = [
    __DIR__ . '/smoke.php',
    __DIR__ . '/html-sanitizer.php',
    __DIR__ . '/stripe-webhook.php',
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
