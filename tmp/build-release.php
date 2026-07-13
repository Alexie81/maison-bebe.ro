<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root . '/tmp/deploy-stage';
$target = $root . '/tmp/maison-release-current.zip';
if (!is_dir($source)) throw new RuntimeException('Directorul de staging lipsește.');
if (is_file($target)) unlink($target);
$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP-ul nu poate fi creat.');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
    if (!$zip->addFile($file->getPathname(), $relative)) throw new RuntimeException('Fișierul nu poate fi adăugat: ' . $relative);
    $count++;
}
$zip->close();
echo json_encode(['files' => $count, 'bytes' => filesize($target)], JSON_UNESCAPED_SLASHES), PHP_EOL;
