<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/public/assets/js/app.js';
$content = file_get_contents($path);
if ($content === false) {
    throw new RuntimeException('app.js nu poate fi citit.');
}

if (!str_contains($content, 'detectedBasePath')) {
    $needle = "  'use strict';";
    $insertion = <<<'JS'
  'use strict';
  const detectedBasePath = (() => {
    const src = document.currentScript?.src || '';
    try {
      const path = new URL(src, location.href).pathname;
      return path.includes('/assets/') ? path.split('/assets/')[0] : '';
    } catch { return ''; }
  })();
  window.APP_BASE_PATH = window.APP_BASE_PATH || detectedBasePath;
JS;
    $content = str_replace($needle, $insertion, $content);
}

$cartAdd = <<<'JS'
fetch(`${window.APP_BASE_PATH || ''}/api/cart/items`
JS;
$wishlist = <<<'JS'
fetch(`${window.APP_BASE_PATH || ''}/api/wishlist/toggle`
JS;
$cart = <<<'JS'
fetch(`${window.APP_BASE_PATH || ''}/api/cart`
JS;

$content = str_replace("fetch('/api/cart/items'", $cartAdd, $content);
$content = str_replace("fetch('/api/wishlist/toggle'", $wishlist, $content);
$content = str_replace("fetch('/api/cart'", $cart, $content);

file_put_contents($path, $content, LOCK_EX);
echo "app.js base path actualizat.\n";

