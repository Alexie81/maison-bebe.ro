<?php
$galleryImages = $product['images'] ?: [['path'=>$product['primary_image'],'alt_text'=>$product['name']]];
$hasRichContent = static fn(?string $html): bool => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '' || (bool) preg_match('/<(img|hr)\b/i', (string) $html);
$renderRichContent = static function (?string $html): string {
    return (string) preg_replace_callback('/(<img\b[^>]*\bsrc=["\'])(\/uploads\/[^"\']+)/i', static fn(array $match): string => $match[1] . url($match[2]), (string) $html);
};
$descriptionContent = $renderRichContent($product['description_html'] ?? '');
$careContent = $renderRichContent($product['care_html'] ?? '');
$shippingContent = $renderRichContent($product['shipping_html'] ?? '');
$giftWrapContent = $renderRichContent($product['gift_wrap_html'] ?? '');
$showCare = trim((string) ($product['material'] ?? '')) !== '' || $hasRichContent($product['care_html'] ?? '');
$showShipping = $hasRichContent($product['shipping_html'] ?? '');
$showGiftWrap = $hasRichContent($product['gift_wrap_html'] ?? '');
?>
<section class="product-page shell section-space-small">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Acasă</a><span>/</span><?php if ($product['category_slug']): ?><a href="<?= e(url('/categorie/'.$product['category_slug'])) ?>"><?= e($product['category_name']) ?></a><span>/</span><?php endif; ?><span><?= e($product['name']) ?></span></nav>

    <div class="product-layout">
        <section class="product-gallery-shell <?= count($galleryImages) > 1 ? 'has-thumbnails' : 'single-image' ?>" data-product-gallery aria-label="Galerie imagini <?= e($product['name']) ?>">
            <?php if (count($galleryImages) > 1): ?>
            <div class="product-thumbnails" aria-label="Miniaturi produs">
                <?php foreach ($galleryImages as $index => $image): ?><button type="button" class="product-thumbnail <?= $index === 0 ? 'active' : '' ?>" data-gallery-thumb="<?= $index ?>" aria-label="Vezi imaginea <?= $index + 1 ?>"><img src="<?= e(url($image['path'])) ?>" alt="" width="76" height="88"></button><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="product-gallery-stage">
                <div class="product-gallery-viewport" data-gallery-viewport tabindex="0">
                    <div class="product-gallery-track" data-gallery-track>
                        <?php foreach ($galleryImages as $index => $image): ?><button type="button" class="product-gallery-slide" data-gallery-slide data-lightbox-src="<?= e(url($image['path'])) ?>" aria-label="Mărește imaginea <?= $index + 1 ?>"><img src="<?= e(url($image['path'])) ?>" alt="<?= e($image['alt_text'] ?: $product['name']) ?>" width="900" height="1050" draggable="false" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>></button><?php endforeach; ?>
                    </div>
                </div>

                <?php if (count($galleryImages) > 1): ?>
                    <button class="gallery-nav gallery-prev" type="button" data-gallery-prev aria-label="Imaginea precedentă"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg></button>
                    <button class="gallery-nav gallery-next" type="button" data-gallery-next aria-label="Imaginea următoare"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div class="product-gallery-dots" aria-label="Selectează imaginea"><?php foreach ($galleryImages as $index => $_): ?><button type="button" class="<?= $index === 0 ? 'active' : '' ?>" data-gallery-dot="<?= $index ?>" aria-label="Imaginea <?= $index + 1 ?>"></button><?php endforeach; ?></div>
                <?php endif; ?>
                <span class="sr-only" data-gallery-status aria-live="polite">Imaginea 1 din <?= count($galleryImages) ?></span>
            </div>
        </section>

        <div class="product-summary">
            <p class="eyebrow"><?= e($product['category_name'] ?: 'Maison Bébé') ?></p>
            <h1><?= e($product['name']) ?></h1>
            <div class="rating" aria-label="5 din 5 stele">★★★★★ <a href="#recenzii"><?= count($product['reviews']) ?> recenzii</a></div>
            <p class="product-price" data-product-price><?= money($product['min_price']) ?></p>
            <p><?= e($product['short_description']) ?></p>

            <form data-add-to-cart-form>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <input type="hidden" name="variant_id" value="<?= count($product['variants']) === 1 ? (int)$product['variants'][0]['id'] : '' ?>" data-variant-id>
                <?php foreach ($product['options'] as $option): ?><fieldset class="variant-options" data-option><legend><?= e($option['name']) ?></legend><div><?php foreach ($option['values'] as $value): ?><button type="button" data-option-value="<?= (int)$value['value_id'] ?>"><?= e($value['value']) ?></button><?php endforeach; ?></div></fieldset><?php endforeach; ?>
                <script type="application/json" data-variants-json><?= json_encode($product['variants'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
                <?php $isFavorite = in_array((int)$product['id'], $wishlistProductIds ?? [], true); ?>
                <div class="purchase-row">
                    <label>Cantitate <input type="number" name="quantity" value="1" min="1" max="10" inputmode="numeric"></label>
                    <button class="button" type="submit">Adaugă în coș</button>
                    <button class="button button-outline wishlist-product<?= $isFavorite ? ' active' : '' ?>" type="button" data-wishlist-product="<?= (int)$product['id'] ?>" aria-label="<?= $isFavorite ? 'Elimină din' : 'Adaugă la' ?> favorite" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">♡</button>
                </div>
                <p class="stock-note" data-stock-note><?= (int)$product['total_stock'] > 0 ? 'În stoc · expediere atentă în 1-2 zile lucrătoare' : 'Indisponibil momentan' ?></p>
            </form>

            <?php if ($showCare || $showShipping || $showGiftWrap): ?><div class="product-accordions">
                <?php if ($showCare): ?><details><summary>Compoziție și îngrijire</summary><div><?php if (trim((string) $product['material']) !== ''): ?><p><strong>Material:</strong> <?= e($product['material']) ?></p><?php endif; ?><?= $careContent ?></div></details><?php endif; ?>
                <?php if ($showShipping): ?><details><summary>Livrare și retur</summary><div><?= $shippingContent ?></div></details><?php endif; ?>
                <?php if ($showGiftWrap): ?><details><summary>Ambalaj cadou</summary><div><?= $giftWrapContent ?></div></details><?php endif; ?>
            </div><?php endif; ?>
        </div>
    </div>
</section>

<?php if ($hasRichContent($product['description_html'] ?? '')): ?><section id="descriere" class="product-rich-description section-space"><div class="shell"><p class="eyebrow">DESCRIEREA PRODUSULUI</p><div class="product-rich-content"><?= $descriptionContent ?></div></div></section><?php endif; ?>

<?php $currentProduct = $product; ?>
<section class="shell section-space"><div class="section-heading"><h2>S-ar putea să îți placă</h2></div><div class="product-grid"><?php foreach ($related as $cardProduct) { $product = $cardProduct; require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div></section>
<?php $product = $currentProduct; ?>

<section id="recenzii" class="reviews-section section-space"><div class="shell"><div class="section-heading"><h2>Recenzii</h2></div><?php if (!$product['reviews']): ?><div class="empty-state compact"><p>Acest produs așteaptă prima poveste de la un client verificat.</p></div><?php else: ?><div class="review-grid"><?php foreach ($product['reviews'] as $review): ?><article><div class="rating">★★★★★</div><h3><?= e($review['title']) ?></h3><p><?= e($review['body']) ?></p><small><?= e($review['author']) ?><?= $review['is_verified_purchase'] ? ' · Achiziție verificată' : '' ?></small></article><?php endforeach; ?></div><?php endif; ?></div></section>

<div class="lightbox" role="dialog" aria-modal="true" aria-label="Galerie produs" hidden data-lightbox>
    <button class="modal-backdrop" type="button" data-lightbox-close aria-label="Închide galeria"></button>
    <section class="lightbox-dialog" tabindex="-1">
        <button class="lightbox-close" type="button" data-lightbox-close aria-label="Închide">×</button>
        <div class="lightbox-stage" data-lightbox-stage>
            <img src="" alt="Imagine produs mărită" draggable="false" data-lightbox-image>
        </div>
        <?php if (count($galleryImages) > 1): ?>
            <button class="lightbox-nav lightbox-prev" type="button" data-lightbox-prev aria-label="Imaginea precedentă"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg></button>
            <button class="lightbox-nav lightbox-next" type="button" data-lightbox-next aria-label="Imaginea următoare"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></button>
        <?php endif; ?>
        <div class="lightbox-tools" aria-label="Control mărire imagine">
            <button type="button" data-lightbox-zoom-out aria-label="Micșorează">−</button>
            <output data-lightbox-zoom aria-live="polite">100%</output>
            <button type="button" data-lightbox-zoom-in aria-label="Mărește">+</button>
            <button class="lightbox-fit" type="button" data-lightbox-reset>Potrivește</button>
        </div>
        <span class="sr-only" data-lightbox-status aria-live="polite"></span>
    </section>
</div>