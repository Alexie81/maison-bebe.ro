<section class="admin-page-head payment-page-head">
    <div>
        <p class="eyebrow">ÎNCASAREA COMENZILOR</p>
        <h1>Metode de plată</h1>
        <p>Alegi simplu cum pot plăti clienții. Schimbarea apare imediat în pagina de finalizare a comenzii.</p>
    </div>
</section>

<?php if ($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="payment-simple-grid" aria-label="Metode de plată disponibile">
<?php foreach ($items as $item):
    $card = $item['code'] === 'stripe';
    $enabled = (bool) $item['is_enabled'];
?>
    <article class="payment-simple-card <?= $enabled ? 'is-enabled' : 'is-disabled' ?>">
        <div class="payment-simple-icon" aria-hidden="true">
            <?php if ($card): ?>
                <svg viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M2.5 10h19M7 15h4"/></svg>
            <?php else: ?>
                <svg viewBox="0 0 24 24"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>
            <?php endif; ?>
        </div>
        <div class="payment-simple-copy">
            <span class="payment-state <?= $enabled ? 'is-on' : '' ?>"><?= $enabled ? 'Activă în magazin' : 'Dezactivată' ?></span>
            <h2><?= $card ? 'Plată cu cardul' : 'Plată ramburs' ?></h2>
            <p><?= $card ? 'Clientul plătește online, înainte de confirmarea comenzii.' : 'Clientul plătește curierului atunci când primește coletul.' ?></p>
        </div>
        <form method="post" action="<?= e(url('/admin/setari/plati/'.$item['code'].'/toggle')) ?>" class="payment-toggle-form payment-simple-toggle">
            <?= csrf_field() ?>
            <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
            <button type="submit" class="payment-toggle-button <?= $enabled ? 'is-on' : '' ?>" aria-label="<?= $enabled ? 'Dezactivează' : 'Activează' ?> <?= $card ? 'plata cu cardul' : 'plata ramburs' ?>">
                <span aria-hidden="true"><i></i></span>
                <b><?= $enabled ? 'Dezactivează' : 'Activează' ?></b>
            </button>
        </form>
    </article>
<?php endforeach; ?>
</section>

<?php if (!array_filter($items, static fn(array $item): bool => (bool) $item['is_enabled'])): ?>
    <div class="payment-method-warning"><strong>Nicio metodă nu este activă.</strong><span>Clienții nu pot finaliza comenzi până nu activezi cel puțin una.</span></div>
<?php endif; ?>
