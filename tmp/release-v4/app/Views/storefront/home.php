<section class="hero home-shell" data-parallax-hero>
    <div class="hero-copy">
        <p class="eyebrow">COLECȚIA 2026</p>
        <h1><?= e($sections['hero']['title'] ?? 'Ales cu grijă pentru cele mai prețioase începuturi.') ?></h1>
        <p>Hăinuțe, accesorii și cadouri premium pentru bebeluși, create pentru momente care devin amintiri.</p>
        <a class="button" href="<?= e(url($sections['hero']['content']['cta_url'] ?? '/shop')) ?>"><?= e($sections['hero']['content']['cta_label'] ?? 'Descoperă colecția') ?></a>
    </div>
    <div class="hero-image" style="--hero-bg:url('<?= e(asset('images/home-hero-v4-original.png')) ?>')"><img src="<?= e(asset('images/home-hero-v4-original.png')) ?>" alt="Bebeluș în ținută crem alături de o jucărie delicată" width="1672" height="941" fetchpriority="high"></div>
</section>

<section class="benefits home-shell"><div class="benefits-grid"><div><i aria-hidden="true">◇</i><p><strong>Materiale premium</strong><span>Alese cu grijă</span></p></div><div><i aria-hidden="true">⌁</i><p><strong>Livrare rapidă</strong><span>2–3 zile lucrătoare</span></p></div><div><i aria-hidden="true">♧</i><p><strong>Ambalaj cadou</strong><span>Pregătit cu drag</span></p></div><div><i aria-hidden="true">↶</i><p><strong>Retur simplu</strong><span>14 zile</span></p></div></div></section>

<section id="colectii" class="home-shell home-collections">
    <div class="section-heading centered"><p class="eyebrow">Explorează</p><h2>Colecțiile noastre</h2></div>
    <div class="collection-rail">
        <?php $categoryImages=['nou-nascut'=>'home-category-newborn-v4.png','0-12-luni'=>'home-category-0-12-v4.png','12-24-luni'=>'home-category-12-24-v4.png','gift-box'=>'giftbox-clean-v4.png','accesorii'=>'packaging-reference.png']; foreach ($categories as $item): ?><a class="collection-chip" href="<?= e(url('/categorie/' . $item['slug'])) ?>"><span><img src="<?= e(asset('images/'.($categoryImages[$item['slug']]??'brand-board-reference.png'))) ?>" alt="" width="144" height="144" loading="lazy"></span><strong><?= e($item['name']) ?></strong></a><?php endforeach; ?>
    </div>
</section>
<aside class="mobile-gift-benefit" aria-label="Ambalaj cadou"><i aria-hidden="true">♧</i><p><strong>Ambalaj cadou</strong><span>Pregătit cu drag</span></p></aside>

<section class="shell section-space">
    <div class="section-heading"><div><p class="eyebrow">Selecții</p><h2>Pentru începuturi prețioase</h2></div><a class="text-link" href="<?= e(url('/shop')) ?>">Vezi toate produsele →</a></div>
    <div class="product-grid"><?php foreach ($products as $product) { require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div>
</section>

<section class="gift-story home-shell home-gift-story">
    <div class="gift-story-image"><img src="<?= e(asset('images/giftbox-clean-v4.png')) ?>" alt="Gift Box Maison Bébé" width="942" height="756" loading="lazy"></div>
    <div class="gift-story-copy"><p class="eyebrow">GIFT ATELIER</p><h2>Un dar care spune „bun venit” cu delicatețe.</h2><p>Alege o cutie pregătită de noi sau compune un Gift Box cu produsele care spun povestea ta.</p><a class="button" href="<?= e(url('/gift-box')) ?>">Descoperă Gift Box-urile</a></div>
</section>

<section class="atelier-preview section-space"><div class="shell"><div class="section-heading"><div><p class="eyebrow">Povești pentru începuturi prețioase</p><h2>Din Atelier Maison Bébé</h2></div><a class="text-link" href="<?= e(url('/atelier')) ?>">Intră în Atelier →</a></div><div class="article-grid"><?php foreach ($posts as $post): ?><article class="article-card"><a href="<?= e(url('/atelier/' . $post['slug'])) ?>"><img src="<?= e(url($post['image_path'])) ?>" alt="" width="560" height="360" loading="lazy"><span><?= e($post['category_name'] ?: 'Atelier') ?></span><h3><?= e($post['title']) ?></h3><p><?= e($post['excerpt']) ?></p></a></article><?php endforeach; ?></div></div></section>

<section class="newsletter shell section-space"><div><p class="eyebrow">SCRISORI DIN ATELIER</p><h2>Rămâi aproape de poveștile noastre</h2><p>Ghiduri, inspirație și noutăți trimise rar și cu grijă.</p></div><form class="newsletter-form" data-newsletter-form><label class="sr-only" for="newsletter-email">Email</label><input id="newsletter-email" type="email" name="email" placeholder="adresa@email.ro" required><button class="button" type="submit">Mă abonez</button></form></section>
