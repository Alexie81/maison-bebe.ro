<section class="hero home-shell" data-parallax-hero>
    <div class="hero-copy">
        <p class="eyebrow">COLECȚIA 2026</p>
        <h1><?= e($sections['hero']['title'] ?? 'Ales cu grijă pentru cele mai prețioase începuturi.') ?></h1>
        <p>Hăinuțe, accesorii și cadouri premium pentru bebeluși, create pentru momente care devin amintiri.</p>
        <a class="button" href="<?= e(url($sections['hero']['content']['cta_url'] ?? '/shop')) ?>"><?= e($sections['hero']['content']['cta_label'] ?? 'Descoperă colecția') ?></a>
    </div>
    <div class="hero-image" style="--hero-bg:url('<?= e(asset('images/home-hero-v4-original.png')) ?>')"><img src="<?= e(asset('images/home-hero-v4-original.png')) ?>" alt="Bebeluș în ținută crem alături de o jucărie delicată" width="1672" height="941" fetchpriority="high"></div>
</section>

<section class="benefits home-shell"><div class="benefits-grid">
    <div><i class="benefit-badge" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 4.5 12 12 21l7.5-9L12 3z"/><path d="M8.5 12h7"/></svg></i><p><strong>Materiale premium</strong><span>Alese cu grijă</span></p></div>
    <div><i class="benefit-badge" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></i><p><strong>Livrare rapidă</strong><span>2–3 zile lucrătoare</span></p></div>
    <div><i class="benefit-badge" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 9h16v12H4zM3 5h18v4H3zM12 5v16"/><path d="M12 5c-2-4-6-3-5 0h5zm0 0c2-4 6-3 5 0h-5z"/></svg></i><p><strong>Ambalaj cadou</strong><span>Pregătit cu drag</span></p></div>
    <div><i class="benefit-badge" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 8H2l4-4 4 4H7a7 7 0 1 1-1 9"/></svg></i><p><strong>Retur simplu</strong><span>14 zile</span></p></div>
</div></section>

<?php if (!empty($categories)): ?>
<section id="colectii" class="home-shell home-collections" style="--collection-count:<?= count($categories) ?>">
    <div class="section-heading centered"><p class="eyebrow">Explorează</p><h2>Colecțiile noastre</h2></div>
    <div class="collection-rail">
        <?php $categoryImages=['nou-nascut'=>'home-category-newborn-v4.png','0-12-luni'=>'home-category-0-12-v4.png','12-24-luni'=>'home-category-12-24-v4.png','gift-box'=>'giftbox-clean-v4.png','accesorii'=>'packaging-reference.png']; foreach ($categories as $item): $collectionImage=!empty($item['image_path'])?url($item['image_path']):asset('images/'.($categoryImages[$item['slug']]??'brand-board-reference.png')); ?><a class="collection-chip" href="<?= e(url('/colectie/' . $item['slug'])) ?>"><span><img src="<?= e($collectionImage) ?>" alt="<?= e($item['name']) ?>" width="144" height="144" loading="lazy"></span><strong><?= e($item['name']) ?></strong><?php if(!empty($item['description'])): ?><small><?= e($item['description']) ?></small><?php endif; ?></a><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="home-brand-story" aria-labelledby="home-story-title" data-story-timeline>
    <header class="home-story-heading home-shell">
        <p class="eyebrow">FIRUL ÎNCEPUTURILOR</p>
        <h2 id="home-story-title">O poveste care se descoperă pas cu pas.</h2>
        <p>Derulează prin universul Maison Bébé — de la prima alegere până la emoția unui cadou pregătit cu dragoste.</p>
    </header>
    <nav class="home-story-rail home-shell" aria-label="Capitolele poveștii">
        <ol><li class="active" data-story-nav="0"><a href="#story-boutique"><span>01</span><strong>Boutique</strong></a></li><li data-story-nav="1"><a href="#story-selection"><span>02</span><strong>Alegerea</strong></a></li><li data-story-nav="2"><a href="#story-gift"><span>03</span><strong>Cadoul</strong></a></li></ol>
        <div class="home-story-progress" aria-hidden="true"><i></i></div>
    </nav>
    <div class="home-story-chapters">
        <article id="story-boutique" class="home-story-chapter home-shell" data-story-chapter>
            <figure data-story-parallax role="img" aria-label="Detalii de ambalare Maison Bébé" style="--story-bg:url('<?= e(asset('images/packaging-reference.png')) ?>')"></figure>
            <div class="home-story-copy"><span>01 · BOUTIQUE-UL NOSTRU</span><h3>Un loc creat pentru a dărui frumos.</h3><p>Părinții, familia și prietenii găsesc aici cadouri elegante și articole atent selecționate pentru nou-născuți și bebeluși.</p></div>
        </article>
        <article id="story-selection" class="home-story-chapter home-story-chapter-reverse home-shell" data-story-chapter>
            <figure data-story-parallax role="img" aria-label="Bebeluș în universul delicat Maison Bébé" style="--story-bg:url('<?= e(asset('images/home-hero-v4-original.png')) ?>')"></figure>
            <div class="home-story-copy"><span>02 · ALEGEREA NOASTRĂ</span><h3>Calitate și confort, în fiecare detaliu.</h3><p>Alegem produse cu materiale delicate, design rafinat și confort potrivit pentru primele luni de viață.</p></div>
        </article>
        <article id="story-gift" class="home-story-chapter home-shell" data-story-chapter>
            <figure data-story-parallax role="img" aria-label="Gift Box Maison Bébé pregătit cu grijă" style="--story-bg:url('<?= e(asset('images/giftbox-clean-v4.png')) ?>')"></figure>
            <div class="home-story-copy"><span>03 · EXPERIENȚA CADOU</span><h3>Pregătit să devină o amintire.</h3><p>Fiecare comandă este ambalată elegant, astfel încât deschiderea ei să păstreze emoția unui cadou oferit cu dragoste.</p></div>
        </article>
    </div>
    <footer class="home-story-finale home-shell"><span aria-hidden="true">♡</span>
        <p>„Cele mai frumoase începuturi merită cele mai delicate alegeri.”</p>
        <a class="button button-outline" href="<?= e(url('/despre-noi')) ?>">Descoperă povestea noastră</a>
    </footer>
</section>
<aside class="mobile-gift-benefit" aria-label="Ambalaj cadou"><i aria-hidden="true">♧</i><p><strong>Ambalaj cadou</strong><span>Pregătit cu drag</span></p></aside>

<section class="shell section-space">
    <div class="section-heading"><div><p class="eyebrow">Selecții</p><h2>Pentru începuturi prețioase</h2></div><a class="text-link" href="<?= e(url('/shop')) ?>">Vezi toate produsele →</a></div>
    <div class="product-grid"><?php foreach ($products as $product) { require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div>
</section>

<?php if (!empty($hasActiveGiftBox)): ?>
<section class="gift-story home-shell home-gift-story">
    <div class="gift-story-image"><img src="<?= e(asset('images/giftbox-clean-v4.png')) ?>" alt="Gift Box Maison Bébé" width="942" height="756" loading="lazy"></div>
    <div class="gift-story-copy"><p class="eyebrow">GIFT ATELIER</p><h2>Un dar care spune „bun venit” cu delicatețe.</h2><p>Alege o cutie pregătită de noi sau compune un Gift Box cu produsele care spun povestea ta.</p><a class="button" href="<?= e(url('/gift-box')) ?>">Descoperă Gift Box-urile</a></div>
</section>
<?php endif; ?>

<section class="atelier-preview section-space"><div class="shell"><div class="section-heading"><div><p class="eyebrow">Povești pentru începuturi prețioase</p><h2>Din Atelier Maison Bébé</h2></div><a class="text-link" href="<?= e(url('/atelier')) ?>">Intră în Atelier →</a></div><div class="article-grid"><?php foreach ($posts as $post): ?><article class="article-card"><a href="<?= e(url('/atelier/' . $post['slug'])) ?>"><img src="<?= e(url($post['image_path'])) ?>" alt="" width="560" height="360" loading="lazy"><span><?= e($post['category_name'] ?: 'Atelier') ?></span><h3><?= e($post['title']) ?></h3><p><?= e($post['excerpt']) ?></p></a></article><?php endforeach; ?></div></div></section>

<section class="newsletter shell section-space"><div><p class="eyebrow">SCRISORI DIN ATELIER</p><h2>Rămâi aproape de poveștile noastre</h2><p>Ghiduri, inspirație și noutăți trimise rar și cu grijă.</p></div><form class="newsletter-form" method="post" action="<?= e(url('/newsletter/abonare')) ?>" data-newsletter-form><?= csrf_field() ?><label class="sr-only" for="newsletter-email">Email</label><input id="newsletter-email" type="email" name="email" placeholder="adresa@email.ro" required><button class="button" type="submit">Mă abonez</button></form></section>