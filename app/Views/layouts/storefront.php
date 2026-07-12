<?php
$meta = $meta ?? [];
$title = $meta['title'] ?? 'Maison Bébé';
$description = $meta['description'] ?? '';
$canonical = $meta['canonical'] ?? absolute_url('/');
$robots = $meta['robots'] ?? 'index,follow';
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F7F3EE">
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Maison Bébé">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if (!empty($meta['og_image'])): ?><meta property="og:image" content="<?= e($meta['og_image']) ?>"><?php endif; ?>
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('images/maison-bebe-favicon.png?v=20260711-02')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('images/maison-bebe-favicon.png?v=20260711-02')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app3.css?v=20260712-03')) ?>">
    <meta name="csrf-token" content="<?= e(MaisonBebe\Core\Csrf::token()) ?>">
    <?php if (!empty($structuredData)): ?><script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<?php $announcementEnabled=($announcement['enabled']??true) && trim((string)($announcement['text']??''))!== ''; ?>
<body class="<?= $announcementEnabled?'has-announcement':'no-announcement' ?>">
<a class="skip-link" href="#continut">Sari la conținut</a>
<?php if($announcementEnabled): ?><div class="announcement"><?= e((string)$announcement['text']) ?></div><?php endif; ?>
<header class="site-header" data-header>
    <div class="header-inner shell">
        <button class="icon-button menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" data-menu-toggle><span class="menu-icon" aria-hidden="true"><i></i><i></i><i></i></span><span class="sr-only" data-menu-label>Deschide meniul</span></button>
        <nav class="desktop-nav" aria-label="Navigație principală">
            <a href="<?= e(url('/')) ?>">Acasă</a>
            <a href="<?= e(url('/despre-noi')) ?>">Despre noi</a>
            <a href="<?= e(url('/shop')) ?>">Magazin</a>
            <?php if (!empty($hasActiveCollections)): ?><a href="<?= e(url('/#colectii')) ?>">Colecții</a><?php endif; ?>
            <a href="<?= e(url('/gift-box')) ?>">Gift Box</a>
        </nav>
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="Maison Bébé - Acasă">
            <span class="brand-sprig" aria-hidden="true">♧</span>
            <strong>MAISON BÉBÉ</strong><small>PREMIUM BABY BOUTIQUE</small>
        </a>
        <nav class="header-actions" aria-label="Acțiuni cont și cumpărături">
            <button class="icon-button action-search" type="button" data-open-modal="search-modal"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 4 4"/></svg><span class="sr-only">Caută</span></button>
            <a class="icon-button account-link" href="<?= e(url(($authUser ?? null) ? '/cont' : '/cont/autentificare')) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M6.5 19c.5-4 2.3-6 5.5-6s5 2 5.5 6z"/></svg><span class="sr-only">Cont</span></a>
            <a class="icon-button" href="<?= e(url('/favorite')) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 8.5c0 5-8 10-8 10s-8-5-8-10a4.5 4.5 0 0 1 8-2.8 4.5 4.5 0 0 1 8 2.8z"/></svg><span class="counter" data-wishlist-count><?= (int) ($wishlistCount ?? 0) ?: '' ?></span><span class="sr-only">Favorite</span></a>
            <button class="icon-button" type="button" data-open-drawer="cart-drawer"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="6" width="14" height="14" rx="1"/><path d="M9 6V4h6v2"/></svg><span class="counter" data-cart-count><?= (int) ($cartCount ?? 0) ?: '' ?></span><span class="sr-only">Coș</span></button>
        </nav>
    </div>
    <button class="mobile-menu-backdrop" type="button" data-menu-backdrop aria-label="Închide meniul" hidden></button>
    <nav id="mobile-menu" class="mobile-menu" aria-label="Meniu mobil" hidden>
        <a href="<?= e(url('/')) ?>">Acasă</a><a href="<?= e(url('/despre-noi')) ?>">Despre noi</a><a href="<?= e(url('/shop')) ?>">Magazin</a><?php if (!empty($hasActiveCollections)): ?><a href="<?= e(url('/#colectii')) ?>">Colecții</a><?php endif; ?><a href="<?= e(url('/gift-box')) ?>">Gift Box</a><a href="<?= e(url('/atelier')) ?>">Atelier</a><a href="<?= e(url('/contact')) ?>">Contact</a>
    </nav>
</header>

<main id="continut"><?= $content ?></main>

<footer class="site-footer">
    <div class="shell footer-grid">
        <div class="footer-brand"><strong>MAISON BÉBÉ</strong><p>Daruri și obiecte delicate pentru începuturi prețioase.</p></div>
        <div><h2>Magazin</h2><a href="<?= e(url('/shop')) ?>">Toate produsele</a><a href="<?= e(url('/gift-box')) ?>">Gift Box-uri</a><a href="<?= e(url('/favorite')) ?>">Favorite</a></div>
        <div><h2>Ajutor</h2><a href="<?= e(url('/contact')) ?>">Contact</a><a href="<?= e(url('/urmarire-comanda')) ?>">Urmărește comanda</a><a href="<?= e(url('/politici/livrare-si-retur')) ?>">Livrare și retur</a></div>
        <div><h2>Legal</h2><a href="<?= e(url('/politici/termeni-si-conditii')) ?>">Termeni</a><a href="<?= e(url('/politici/confidentialitate')) ?>">Confidențialitate</a><a href="<?= e(url('/politici/cookies')) ?>">Cookies</a></div>
    </div>
    <div class="shell footer-bottom"><span>© <?= date('Y') ?> Maison Bébé</span><span>Creat cu grijă în România</span></div>
</footer>

<div class="modal-layer" id="search-modal" role="dialog" aria-modal="true" aria-labelledby="search-title" hidden>
    <button class="modal-backdrop" type="button" data-close-modal aria-label="Închide căutarea"></button>
    <section class="search-panel modal-panel" tabindex="-1">
        <button class="modal-close" type="button" data-close-modal aria-label="Închide">×</button>
        <p class="eyebrow">Colecția Maison Bébé</p><h2 id="search-title">Caută în colecție</h2>
        <label class="sr-only" for="global-search">Caută produse</label>
        <input id="global-search" type="search" autocomplete="off" placeholder="Începe să scrii..." data-search-input>
        <div class="search-results state-panel" data-search-results aria-live="polite"><p>Scrie cel puțin două caractere.</p></div>
    </section>
</div>

<div class="drawer-layer" id="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" hidden>
    <button class="modal-backdrop" type="button" data-close-drawer aria-label="Închide coșul"></button>
    <aside class="cart-drawer" tabindex="-1"><button class="modal-close" type="button" data-close-drawer aria-label="Închide">×</button><h2 id="drawer-title">Coșul tău</h2><div data-cart-drawer-content class="state-panel"><p>Se încarcă produsele…</p></div></aside>
</div>

<div class="modal-layer" id="cart-added-modal" role="dialog" aria-modal="true" aria-labelledby="cart-added-title" hidden>
    <button class="modal-backdrop" type="button" data-close-modal aria-label="Închide confirmarea"></button>
    <section class="modal-panel cart-added" tabindex="-1"><button class="modal-close" type="button" data-close-modal aria-label="Închide">×</button><div class="success-mark">✓</div><h2 id="cart-added-title">Produsul a fost adăugat în coș</h2><div data-cart-added-product></div><div class="button-row"><button class="button button-outline" data-close-modal>Continuă cumpărăturile</button><a class="button" href="<?= e(url('/cos')) ?>">Vezi coșul</a></div></section>
</div>

<div class="toast-region" aria-live="polite" aria-atomic="true" data-toast-region></div>
<script src="<?= e(asset('js/app.js?v=20260712-03')) ?>" defer></script>
<script src="<?= e(asset('js/commerce.js')) ?>" defer></script>
<script src="<?= e(asset('js/parallax.js')) ?>" defer></script>
<script src="<?= e(asset('js/story-timeline.js')) ?>" defer></script>
</body>
</html>
