<article class="product-card">
    <div class="product-media">
        <a href="<?= e(url('/produs/' . $product['slug'])) ?>">
            <img src="<?= e(url($product['image_path'])) ?>" alt="<?= e($product['image_alt'] ?? $product['name']) ?>" width="560" height="680" loading="lazy">
        </a>
        <?php if (!empty($product['is_featured'])): ?><span class="badge">Selecție</span><?php endif; ?>
        <button type="button" class="wishlist-toggle" aria-label="Adaugă <?= e($product['name']) ?> la favorite" data-wishlist-product="<?= (int) $product['id'] ?>">♡</button>
    </div>
    <div class="product-info"><a href="<?= e(url('/produs/' . $product['slug'])) ?>"><h3><?= e($product['name']) ?></h3></a><div class="price"><?= money($product['price_minor']) ?><?php if (!empty($product['compare_at_price_minor'])): ?><del><?= money($product['compare_at_price_minor']) ?></del><?php endif; ?></div></div>
</article>

