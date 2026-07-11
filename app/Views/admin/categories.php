<?php
$renderRows = static function (array $rows): void {
    foreach ($rows as $item):
        $image = $item['image_path'] ?: '/assets/images/packaging-reference.png';
        $isCollection = !empty($item['is_featured']);
        $hasDependencies = (int)$item['product_count'] > 0 || (int)$item['child_count'] > 0;
        $removePath = $isCollection
            ? '/admin/categorii/'.$item['id'].'/colectie/sterge'
            : '/admin/categorii/'.$item['id'].'/sterge';
        $removeMessage = $isCollection
            ? 'Colecția va fi eliminată de pe homepage, dar categoria și produsele asociate vor fi păstrate.'
            : 'Categoria va fi arhivată și eliminată din zona publică.';
?>
<tr data-category-row>
    <td>
        <a class="admin-category-cell" href="<?= e(url('/admin/categorii/'.$item['id'].'/edit')) ?>">
            <img src="<?= e(url($image)) ?>" alt="" width="58" height="58">
            <span><strong><?= e($item['name']) ?></strong><small><?= e($item['description'] ?: 'Fără descriere') ?></small></span>
        </a>
    </td>
    <td><?= e($item['slug']) ?></td>
    <td><?= e($item['parent_name'] ?? '—') ?></td>
    <td><?= (int)$item['product_count'] ?></td>
    <td><?= (int)$item['sort_order'] ?></td>
    <td>
        <form class="admin-category-toggle" method="post" action="<?= e(url('/admin/categorii/'.$item['id'].'/status')) ?>" data-category-toggle>
            <?= csrf_field() ?>
            <input type="hidden" name="is_active" value="0">
            <label class="admin-switch" title="Afișare pe website">
                <input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?> data-category-status aria-label="Afișează <?= e($item['name']) ?> pe website">
                <span aria-hidden="true"></span>
            </label>
            <small data-category-status-label><?= $item['is_active'] ? 'Vizibilă' : 'Ascunsă' ?></small>
        </form>
    </td>
    <td>
        <div class="admin-table-actions">
            <a class="admin-icon-action" href="<?= e(url('/admin/categorii/'.$item['id'].'/edit')) ?>" title="Editează <?= $isCollection ? 'colecția' : 'categoria' ?>" aria-label="Editează <?= e($item['name']) ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
            </a>
            <form method="post" action="<?= e(url($removePath)) ?>" data-confirm-delete data-confirm-message="<?= e($removeMessage) ?>">
                <?= csrf_field() ?>
                <button class="admin-icon-action danger" type="submit"
                    title="<?= !$isCollection && $hasDependencies ? 'Mută întâi produsele și subcategoriile' : ($isCollection ? 'Elimină colecția' : 'Șterge categoria') ?>"
                    aria-label="<?= $isCollection ? 'Elimină colecția' : 'Șterge categoria' ?> <?= e($item['name']) ?>"
                    <?= !$isCollection && $hasDependencies ? 'disabled' : '' ?>>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"/></svg>
                </button>
            </form>
        </div>
    </td>
</tr>
<?php
    endforeach;
};
$visibleCollections = count(array_filter($collections, static fn(array $item): bool => (bool)$item['is_active']));
?>
<section class="admin-page-head">
    <div><p class="eyebrow">CATALOG</p><h1>Categorii și colecții</h1></div>
    <div class="button-row">
        <a class="admin-button secondary" href="<?= e(url('/admin/categorii/creare')) ?>">Categorie nouă</a>
        <a class="admin-button" href="<?= e(url('/admin/categorii/creare?tip=colectie')) ?>">Colecție nouă</a>
    </div>
</section>

<section class="admin-panel admin-collection-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">PAGINA PRINCIPALĂ</p>
            <div class="admin-title-with-tooltip">
                <h2>Colecții</h2>
                <span class="admin-tooltip" tabindex="0" aria-describedby="collections-tooltip">i
                    <span id="collections-tooltip" role="tooltip">Se recomandă să păstrezi 4–5 colecții active pentru un aspect uniform și echilibrat al website-ului.</span>
                </span>
            </div>
            <p>Categoriile evidențiate în secțiunea „Colecțiile noastre”. Imaginea, titlul, textul, ordinea și vizibilitatea se actualizează imediat pe website.</p>
        </div>
        <span class="status-pill accent"><?= count($collections) ?> configurate · <?= $visibleCollections ?> vizibile</span>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table admin-category-table">
            <thead><tr><th>Colecție</th><th>Slug</th><th>Părinte</th><th>Produse</th><th>Ordine</th><th>Pe website</th><th>Acțiuni</th></tr></thead>
            <tbody>
                <?php if ($collections): $renderRows($collections); else: ?><tr><td colspan="7" class="admin-empty">Nu există colecții configurate. Folosește butonul „Colecție nouă”.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <div class="panel-head">
        <div><p class="eyebrow">STRUCTURA MAGAZINULUI</p><h2>Categorii</h2><p>Restul categoriilor folosite în filtre și pentru organizarea produselor.</p></div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table admin-category-table">
            <thead><tr><th>Categorie</th><th>Slug</th><th>Părinte</th><th>Produse</th><th>Ordine</th><th>Pe website</th><th>Acțiuni</th></tr></thead>
            <tbody>
                <?php if ($categories): $renderRows($categories); else: ?><tr><td colspan="7" class="admin-empty">Nu există alte categorii.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>