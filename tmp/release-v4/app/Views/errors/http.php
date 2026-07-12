<section class="error-page shell section-space">
    <p class="eyebrow">Maison Bébé</p>
    <h1><?= e((string) ($status ?? 500)) ?></h1>
    <p><?= e($title ?? 'Pagina nu este disponibilă momentan.') ?></p>
    <a class="button" href="<?= e(url('/')) ?>">Înapoi acasă</a>
</section>

