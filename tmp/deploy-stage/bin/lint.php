<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$failed = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
    exec($command, $output, $code);
    if ($code !== 0) {
        echo implode(PHP_EOL, $output) . PHP_EOL;
        $failed++;
    }
    $output = [];
}
echo $failed === 0 ? "PHP lint: OK\n" : "PHP lint: {$failed} erori\n";
exit($failed === 0 ? 0 : 1);

