<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? 'Admin Maison Bébé') ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('images/maison-bebe-favicon.png?v=20260711-02')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/admin.css?v=20260716-staff-nickname-1')) ?>">
    <meta name="csrf-token" content="<?= e(MaisonBebe\Core\Csrf::token()) ?>">
</head>
<body class="admin-body">
<script>try{if(matchMedia('(min-width:1051px)').matches&&localStorage.getItem('maison_admin_sidebar_collapsed')==='1')document.body.classList.add('admin-sidebar-collapsed')}catch(e){}</script>
<a class="admin-skip-link" href="#admin-content">Sari la conținut</a>
<button class="admin-menu-toggle" type="button" data-admin-menu aria-expanded="false" aria-controls="admin-sidebar" aria-label="Deschide meniul de administrare"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8.5h14"/><path d="M5 15.5h9"/></svg><b>Meniu</b></button>
<aside id="admin-sidebar" class="admin-sidebar">
    <button class="admin-sidebar-collapse" type="button" data-admin-sidebar-collapse aria-expanded="true" aria-label="Restrânge bara laterală" title="Restrânge bara laterală">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16"/><path class="admin-collapse-arrow" d="m15 9-3 3 3 3"/></svg>
    </button>
    <a class="admin-brand" href="<?= e(url('/admin')) ?>" aria-label="Maison Bébé — administrare magazin">
        <span class="admin-brand-mark" aria-hidden="true"><img src="<?= e(asset('images/logo-reference.png')) ?>" alt=""></span>
        <small>ADMINISTRARE MAGAZIN</small>
    </a>
    <nav aria-label="Administrare magazin">
        <?php if (MaisonBebe\Core\Auth::hasPermission('dashboard.view') || MaisonBebe\Core\Auth::hasPermission('orders.view') || MaisonBebe\Core\Auth::hasPermission('shipping.manage')): ?><span>Activitate zilnică</span><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('dashboard.view')): ?><a href="<?= e(url('/admin')) ?>">Prezentare generală</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('orders.view')): ?><a href="<?= e(url('/admin/comenzi')) ?>">Comenzi</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('dashboard.view')): ?><a href="<?= e(url('/admin/notificari')) ?>">Notificări <b data-admin-notification-count hidden></b></a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('shipping.manage')): ?><a href="<?= e(url('/admin/expeditii')) ?>">Expediții</a><?php endif; ?>

        <?php if (MaisonBebe\Core\Auth::hasPermission('products.view') || MaisonBebe\Core\Auth::hasPermission('categories.manage') || MaisonBebe\Core\Auth::hasPermission('customers.view')): ?><span>Produse și clienți</span><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('products.view')): ?><a href="<?= e(url('/admin/produse')) ?>">Produse</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('categories.manage')): ?><a href="<?= e(url('/admin/categorii')) ?>">Categorii &amp; Colecții</a><a href="<?= e(url('/admin/gift-box')) ?>">Gift Box și cutii</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('customers.view')): ?><a href="<?= e(url('/admin/clienti')) ?>">Clienți</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('products.update')): ?><a href="<?= e(url('/admin/cupoane')) ?>">Cupoane</a><?php endif; ?>

        <?php if (MaisonBebe\Core\Auth::hasPermission('cms.manage') || MaisonBebe\Core\Auth::hasPermission('atelier.manage')): ?><span>Conținut</span><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('cms.manage')): ?><a href="<?= e(url('/admin/cms')) ?>">Pagini și texte</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('atelier.manage')): ?><a href="<?= e(url('/admin/atelier')) ?>">Atelier</a><?php endif; ?>

        <?php if (MaisonBebe\Core\Auth::hasPermission('billing.view')): ?><span>Bani și documente</span><a href="<?= e(url('/admin/facturare')) ?>">Configurare facturi</a><a href="<?= e(url('/admin/facturi')) ?>">Facturi emise</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('seo.manage') || MaisonBebe\Core\Auth::hasPermission('settings.manage')): ?><span>Setări magazin</span><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('seo.manage')): ?><a href="<?= e(url('/admin/seo/indexabilitate')) ?>">SEO</a><?php endif; ?>
        <?php if (MaisonBebe\Core\Auth::hasPermission('settings.manage')): ?><a href="<?= e(url('/admin/setari/email')) ?>">Email</a><a href="<?= e(url('/admin/setari/plati')) ?>">Plăți</a><a href="<?= e(url('/admin/setari/livrare')) ?>">Livrare</a><a href="<?= e(url('/admin/setari/autentificare')) ?>">Autentificare</a><a href="<?= e(url('/admin/utilizatori')) ?>">Utilizatori &amp; acces</a><?php endif; ?>
        <a href="<?= e(url('/admin/setari/securitate')) ?>">Securitate cont</a>
    </nav>
</aside>

<div class="admin-app">
    <header class="admin-topbar">
        <div class="admin-topbar-title"><p><small>Maison Bébé / Admin</small><strong>Administrare magazin</strong></p></div>
        <div class="admin-topbar-actions">
            <?php if (MaisonBebe\Core\Auth::hasPermission('products.create')): ?><a class="admin-button admin-quick-add" href="<?= e(url('/admin/produse/creare')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><b>Produs nou</b></a><?php endif; ?>
            <button type="button" class="browser-notify-button" data-enable-browser-notifications title="Activează notificările în browser"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span>Notificări</span></button>
            <a class="admin-store-link" href="<?= e(url('/')) ?>" target="_blank"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M19 13v6H5V5h6"/></svg><span>Vezi magazinul</span></a>
            <span class="admin-user-chip"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/></svg><span><?= e(trim((string) ($adminUser['nickname'] ?? '')) ?: trim(($adminUser['first_name'] ?? 'Admin').' '.($adminUser['last_name'] ?? ''))) ?></span></span>
            <details class="admin-help-menu"><summary aria-label="Ajutor">?</summary><div><strong>Ai nevoie de ajutor?</strong><p>Deschide ghidul pas cu pas pentru configurarea completă a magazinului.</p><a href="<?= e(url('/admin?ghid=1')) ?>">Deschide ghidul de lansare →</a></div></details>
        </div>
    </header>
    <main id="admin-content" class="admin-main"><?= $content ?></main>
    <div class="admin-page-loader" data-admin-page-loader hidden aria-hidden="true" aria-live="polite">
        <div class="admin-page-loader-card" role="status"><span>Un moment</span><b data-admin-loading-dots>.</b></div>
    </div>
</div>

<div class="admin-order-popup" role="dialog" aria-modal="false" aria-live="polite" hidden data-order-popup><button type="button" data-close-order-popup>×</button><p class="eyebrow">COMANDĂ NOUĂ PRIMITĂ</p><h2 data-popup-title></h2><p data-popup-body></p><a class="admin-button" data-popup-link>Vezi comanda</a></div>
<div class="admin-confirm-modal" data-confirm-modal hidden><button class="admin-confirm-backdrop" type="button" data-confirm-cancel aria-label="Anulează ștergerea"></button><section class="admin-confirm-card" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title"><span class="admin-confirm-icon" aria-hidden="true">!</span><p class="eyebrow">CONFIRMARE</p><h2 id="admin-confirm-title">Ștergi acest element?</h2><p data-confirm-message>Elementul va fi arhivat și eliminat din zona publică.</p><div class="button-row"><button class="admin-button secondary" type="button" data-confirm-cancel>Anulează</button><button class="admin-button danger" type="button" data-confirm-accept>Da, șterge</button></div></section></div>
<div class="admin-result-modal" data-admin-result-modal hidden>
    <button class="admin-result-backdrop" type="button" data-admin-result-close aria-label="Închide mesajul"></button>
    <section class="admin-result-card" role="alertdialog" aria-modal="true" aria-labelledby="admin-result-title">
        <span class="admin-result-icon" data-admin-result-icon aria-hidden="true">✓</span>
        <p class="eyebrow" data-admin-result-eyebrow>SALVAT CU SUCCES</p>
        <h2 id="admin-result-title" data-admin-result-title>Modificările au fost salvate.</h2>
        <p data-admin-result-message>Lista a fost actualizată și poți vedea toate elementele.</p>
        <button class="admin-button" type="button" data-admin-result-close>Continuă</button>
    </section>
</div>
<div class="toast-region" aria-live="polite" data-toast-region></div>
<script>window.APP_BASE_PATH=<?= json_encode((string) env('APP_BASE_PATH', '')) ?>;</script>
<script src="<?= e(asset('js/admin.js?v=20260716-variants-modern-1')) ?>" defer></script>
</body>
</html>
