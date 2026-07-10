<?php if (($pageType ?? '') === 'about'): ?>
<section class="about-hero-v4 home-shell">
    <div class="about-hero-media" style="--about-hero:url('<?= e(asset('images/home-hero-v4-original.png')) ?>')"><img src="<?= e(asset('images/home-hero-v4-original.png')) ?>" alt="Universul delicat Maison Bébé" width="1672" height="941" fetchpriority="high"></div>
    <div class="about-hero-copy"><p class="eyebrow">POVESTEA NOASTRĂ</p><h1><?= e($page['title'] ?? 'Despre Maison Bébé') ?></h1><p>Un boutique premium creat pentru cadouri elegante, alegeri delicate și începuturi care merită sărbătorite.</p><a class="button" href="<?= e(url('/shop')) ?>">Descoperă colecția</a></div>
</section>
<section class="about-manifesto home-shell"><p class="eyebrow">MAISON BÉBÉ</p><blockquote>Credem că cele mai frumoase începuturi merită cele mai delicate alegeri.</blockquote><span aria-hidden="true">♡</span></section>
<section class="about-values home-shell" aria-label="Valorile Maison Bébé">
    <article><i aria-hidden="true">01</i><h2>Ales cu grijă</h2><p>Calitate, confort și materiale delicate pentru primele luni de viață.</p></article>
    <article><i aria-hidden="true">02</i><h2>Pregătit cu drag</h2><p>Fiecare produs este așezat atent, într-un ambalaj elegant și memorabil.</p></article>
    <article><i aria-hidden="true">03</i><h2>Dăruit cu emoție</h2><p>Transformăm fiecare comandă într-un moment frumos de oferit și de păstrat.</p></article>
</section>
<section class="about-editorial home-shell">
    <figure style="--about-story:url('<?= e(asset('images/packaging-reference.png')) ?>')"><img src="<?= e(asset('images/packaging-reference.png')) ?>" alt="Ambalaj premium Maison Bébé" width="900" height="900" loading="lazy"></figure>
    <article class="prose"><?= $page['content_html'] ?? '' ?></article>
</section>
<section class="about-finale"><div class="home-shell"><p class="eyebrow">PENTRU FIECARE NOU ÎNCEPUT</p><h2>Un dar ales cu inimă.</h2><p>Descoperă Gift Box-uri, hăinuțe și accesorii pregătite să devină amintiri.</p><div class="button-row"><a class="button" href="<?= e(url('/gift-box')) ?>">Alege un Gift Box</a><a class="button button-outline" href="<?= e(url('/atelier')) ?>">Povești din Atelier</a></div></div></section>
<?php else: ?>
<section class="page-hero shell section-space-small"><p class="eyebrow">Maison Bébé</p><h1><?= e($page['title'] ?? 'Maison Bébé') ?></h1></section>
<section class="legal-layout shell section-space-small"><aside><a href="<?= e(url('/politici/livrare-si-retur')) ?>">Livrare și retur</a><a href="<?= e(url('/politici/termeni-si-conditii')) ?>">Termeni</a><a href="<?= e(url('/politici/confidentialitate')) ?>">Confidențialitate</a><a href="<?= e(url('/politici/cookies')) ?>">Cookies</a></aside><article class="prose"><?= $page['content_html'] ?? '' ?></article></section>
<?php endif; ?>