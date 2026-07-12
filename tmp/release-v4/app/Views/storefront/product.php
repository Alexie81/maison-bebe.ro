<section class="product-page shell section-space-small">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Acasă</a><span>/</span><?php if ($product['category_slug']): ?><a href="<?= e(url('/categorie/' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a><span>/</span><?php endif; ?><span><?= e($product['name']) ?></span></nav>
    <div class="product-layout">
        <div class="product-gallery" data-gallery><?php foreach ($product['images'] ?: [['path'=>$product['primary_image'],'alt_text'=>$product['name']]] as $index => $image): ?><button type="button" class="gallery-item" data-lightbox-src="<?= e(url($image['path'])) ?>"><img src="<?= e(url($image['path'])) ?>" alt="<?= e($image['alt_text'] ?: $product['name']) ?>" width="720" height="840" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>></button><?php endforeach; ?></div>
        <div class="product-summary">
            <p class="eyebrow"><?= e($product['category_name'] ?: 'Maison Bébé') ?></p><h1><?= e($product['name']) ?></h1>
            <div class="rating" aria-label="5 din 5 stele">★★★★★ <a href="#recenzii"><?= count($product['reviews']) ?> recenzii</a></div>
            <p class="product-price" data-product-price><?= money($product['min_price']) ?></p><p><?= e($product['short_description']) ?></p>
            <form data-add-to-cart-form>
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="variant_id" value="<?= count($product['variants']) === 1 ? (int) $product['variants'][0]['id'] : '' ?>" data-variant-id>
                <?php foreach ($product['options'] as $option): ?><fieldset class="variant-options" data-option><legend><?= e($option['name']) ?></legend><div><?php foreach ($option['values'] as $value): ?><button type="button" data-option-value="<?= (int) $value['value_id'] ?>"><?= e($value['value']) ?></button><?php endforeach; ?></div></fieldset><?php endforeach; ?>
                <script type="application/json" data-variants-json><?= json_encode($product['variants'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
                <div class="purchase-row"><label>Cantitate <input type="number" name="quantity" value="1" min="1" max="10"></label><button class="button" type="submit">Adaugă în coș</button><button class="button button-outline wishlist-product" type="button" data-wishlist-product="<?= (int) $product['id'] ?>" aria-label="Adaugă la favorite">♡</button></div>
                <p class="stock-note" data-stock-note><?= (int) $product['total_stock'] > 0 ? 'În stoc · expediere atentă în 1-2 zile lucrătoare' : 'Indisponibil momentan' ?></p>
            </form>
            <div class="product-accordions"><details open><summary>Descriere</summary><div><?= $product['description_html'] ?></div></details><details><summary>Compoziție și îngrijire</summary><div><p><?= e($product['material']) ?></p><?= $product['care_html'] ?></div></details><details><summary>Livrare și retur</summary><div><p>Livrare prin curier și retur conform politicii publicate.</p></div></details><details><summary>Ambalaj cadou</summary><div><p>Poți adăuga un mesaj cadou la checkout.</p></div></details></div>
        </div>
    </div>
</section>
<?php $currentProduct = $product; ?>
<section class="shell section-space"><div class="section-heading"><h2>S-ar putea să îți placă</h2></div><div class="product-grid"><?php foreach ($related as $cardProduct) { $product = $cardProduct; require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div></section>
<?php $product = $currentProduct; ?>
<section id="recenzii" class="reviews-section section-space"><div class="shell"><div class="section-heading"><h2>Recenzii</h2></div><?php if (!$product['reviews']): ?><div class="empty-state compact"><p>Acest produs așteaptă prima poveste de la un client verificat.</p></div><?php else: ?><div class="review-grid"><?php foreach ($product['reviews'] as $review): ?><article><div class="rating">★★★★★</div><h3><?= e($review['title']) ?></h3><p><?= e($review['body']) ?></p><small><?= e($review['author']) ?><?= $review['is_verified_purchase'] ? ' · Achiziție verificată' : '' ?></small></article><?php endforeach; ?></div><?php endif; ?></div></section>
<div class="lightbox" role="dialog" aria-modal="true" aria-label="Galerie produs" hidden data-lightbox><button class="modal-backdrop" type="button" data-lightbox-close></button><button class="modal-close" type="button" data-lightbox-close aria-label="Închide">×</button><img src="" alt="Imagine produs mărită" data-lightbox-image></div>

