<?php
$isFavorite = in_array((int) $product['id'], $wishlistProductIds ?? [], true);
$isInCart = in_array((int) $product['id'], $cartProductIds ?? [], true);
$canQuickAdd = (int) ($product['variant_count'] ?? 0) === 1 && (int) ($product['default_variant_id'] ?? 0) > 0 && (int) ($product['stock_qty'] ?? 0) >= 0;
$productUrl = url('/produs/' . $product['slug']);
?>
<article class="product-card">
    <div class="product-media">
        <a href="<?= e($productUrl) ?>">
            <img src="<?= e(url($product['image_path'])) ?>" alt="<?= e($product['image_alt'] ?? $product['name']) ?>" width="560" height="680" loading="lazy">
        </a>
        <?php if (!empty($product['is_featured'])): ?><span class="badge">Selecție</span><?php endif; ?>
        <div class="product-card-actions">
            <button type="button" class="wishlist-toggle<?= $isFavorite ? ' active' : '' ?>" aria-label="<?= $isFavorite ? 'Elimină' : 'Adaugă' ?> <?= e($product['name']) ?> <?= $isFavorite ? 'din' : 'la' ?> favorite" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>" data-wishlist-product="<?= (int) $product['id'] ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 8.5c0 5-8 10-8 10s-8-5-8-10a4.5 4.5 0 0 1 8-2.8 4.5 4.5 0 0 1 8 2.8z"/></svg>
            </button>
            <?php if ($canQuickAdd): ?>
                <button type="button" class="quick-cart-toggle<?= $isInCart ? ' active' : '' ?>" data-quick-cart="<?= (int) $product['default_variant_id'] ?>" data-cart-product="<?= (int) $product['id'] ?>" aria-label="<?= $isInCart ? 'Elimină din coș: ' : 'Adaugă în coș: ' ?><?= e($product['name']) ?>" aria-pressed="<?= $isInCart ? 'true' : 'false' ?>">
                    <svg class="quick-cart-icon-add" aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="7" width="14" height="13" rx="1"/><path d="M9 7V5a3 3 0 0 1 6 0v2M12 11v5M9.5 13.5h5"/></svg>
                    <svg class="quick-cart-icon-check" aria-hidden="true" viewBox="0 0 24 24"><path d="m6 12.5 4 4L18.5 8"/></svg>
                </button>
            <?php else: ?>
                <a class="quick-cart-toggle" href="<?= e($productUrl) ?>" aria-label="Alege opțiunile pentru <?= e($product['name']) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="7" width="14" height="13" rx="1"/><path d="M9 7V5a3 3 0 0 1 6 0v2M12 11v5M9.5 13.5h5"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="product-info"><a href="<?= e($productUrl) ?>"><h3><?= e($product['name']) ?></h3></a><div class="price"><?= money((int) $product['price_minor']) ?><?php if (!empty($product['compare_at_price_minor'])): ?><del><?= money((int) $product['compare_at_price_minor']) ?></del><?php endif; ?></div></div>
</article>