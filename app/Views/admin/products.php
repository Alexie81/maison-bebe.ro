<?php
$paginationUrl = static function (int $targetPage) use ($query, $perPage): string {
    $params = ['page' => max(1, $targetPage), 'per_page' => $perPage];
    if ($query !== '') $params['q'] = $query;
    return url('/admin/produse?' . http_build_query($params));
};
$rangeStart = $resultCount > 0 ? (($page - 1) * $perPage) + 1 : 0;
$rangeEnd = min($resultCount, $page * $perPage);
?>
<section class="admin-page-head">
    <div>
        <p class="eyebrow">CATALOG</p>
        <h1>Produse</h1>
        <p class="admin-product-limit"><strong><?= (int) $productCount ?></strong> din <?= (int) $productLimit ?> produse</p>
    </div>
    <?php if ($productLimitReached): ?>
        <span class="admin-button is-disabled" aria-disabled="true" title="Limita de 500 de produse a fost atinsă">Limită atinsă</span>
    <?php else: ?>
        <a class="admin-button" href="<?= e(url('/admin/produse/creare')) ?>">Adaugă produs</a>
    <?php endif; ?>
</section>

<section class="admin-product-search-panel" data-product-catalog-search>
    <form method="get" action="<?= e(url('/admin/produse')) ?>" role="search" data-admin-product-search-form data-admin-ignore-dirty>
        <div class="admin-product-search-field">
            <span class="admin-product-search-leading" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4 4"/></svg></span>
            <label class="sr-only" for="admin-product-search-input">Caută în produse</label>
            <input id="admin-product-search-input" type="search" name="q" value="<?= e($query) ?>" placeholder="Caută produse, SKU, categorii, colecții sau variante…" autocomplete="off" spellcheck="false" data-admin-product-search-input aria-autocomplete="list" aria-controls="admin-product-search-results" aria-expanded="false">
            <?php if ($query !== ''): ?><a class="admin-product-search-clear" href="<?= e(url('/admin/produse?per_page='.$perPage)) ?>" aria-label="Șterge căutarea">×</a><?php endif; ?>
            <button class="admin-product-search-submit" type="submit" aria-label="Caută produsele"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4 4"/></svg></button>
        </div>

        <div id="admin-product-search-results" class="admin-product-search-popup" role="listbox" data-admin-product-search-popup hidden>
            <header><div><span class="eyebrow">SUGESTII INTELIGENTE</span><strong data-admin-product-search-heading>Produse potrivite</strong></div><span data-admin-product-search-count></span></header>
            <div class="admin-product-search-results" data-admin-product-search-results>
                <?php foreach ($searchSuggestions as $suggestion): ?>
                    <button type="button" role="option" class="admin-product-search-result" data-admin-product-search-result data-name="<?= e($suggestion['name']) ?>" data-category="<?= e($suggestion['category_name'] ?? '') ?>" data-search="<?= e($suggestion['search_text'] ?? '') ?>" hidden>
                        <img src="<?= e(url($suggestion['image_path'])) ?>" alt="" width="58" height="58" loading="lazy">
                        <span><strong><?= e($suggestion['name']) ?></strong><small><?= e(($suggestion['category_name'] ?: 'Fără categorie') . ' · ' . $suggestion['sku']) ?></small></span>
                        <i><?= money($suggestion['price_minor']) ?></i>
                    </button>
                <?php endforeach; ?>
                <p class="admin-product-search-empty" data-admin-product-search-empty hidden><strong>Niciun produs găsit</strong><span>Încearcă o denumire, o categorie sau un termen mai scurt.</span></p>
            </div>
            <footer><span>Poți căuta și la singular, plural sau fără diacritice.</span><kbd>↑</kbd><kbd>↓</kbd><small>navigare</small><kbd>Enter</kbd><small>selectare</small></footer>
        </div>
    </form>
    <?php if ($query !== ''): ?><p class="admin-product-search-summary"><span><?= (int) $resultCount ?> <?= $resultCount === 1 ? 'rezultat' : 'rezultate' ?></span> pentru <strong>„<?= e($query) ?>”</strong></p><?php endif; ?>
</section>

<section class="admin-panel admin-products-index-panel">
    <div class="admin-table-wrap">
        <table class="admin-table admin-products-table">
            <thead><tr><th>Produs</th><th>SKU</th><th>Categorie</th><th>Preț</th><th>Stoc online</th><th>Stoc efectiv (Conta)</th><th>Status</th><th>Acțiuni</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): $accountingStock = (float) ($item['accounting_stock_qty'] ?? 0); ?>
                <tr>
                    <td><a class="admin-product-cell" href="<?= e(url('/admin/produse/'.$item['id'].'/edit')) ?>"><img src="<?= e(url($item['image_path'])) ?>" alt="" width="48" height="56"><strong><?= e($item['name']) ?></strong></a></td>
                    <td class="admin-product-sku" title="<?= e($item['sku']) ?>"><?= e($item['sku']) ?></td>
                    <td><?= e($item['category_name']) ?></td>
                    <td><?= money($item['price_minor']) ?></td>
                    <td><?php if (!empty($item['has_unlimited_stock'])): ?><span class="stock-display-badge unlimited">Stoc nelimitat</span><?php else: ?><span class="stock-display-badge limited"><?= number_format((float) ($item['online_stock_qty'] ?? 0), 0, ',', '.') ?> buc.</span><?php endif; ?></td>
                    <td><a class="stock-display-badge accounting <?= $accountingStock < 0 ? 'negative' : ($accountingStock > 0 ? 'positive' : 'zero') ?>" href="<?= e(url('/admin/stocuri-conta?q='.rawurlencode((string) ($item['sku'] ?: $item['name'])))) ?>" title="Deschide produsul în Stocuri Conta"><strong><?= number_format($accountingStock, 0, ',', '.') ?> buc.</strong><small>sold contabil</small></a></td>
                    <td><span class="status-pill <?= $item['status'] === 'active' ? 'success' : '' ?>"><?= e($item['status']) ?></span></td>
                    <td class="admin-product-actions"><div class="admin-table-actions"><a class="admin-icon-action" href="<?= e(url('/admin/produse/'.$item['id'].'/edit')) ?>" title="Editează produsul" aria-label="Editează <?= e($item['name']) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg></a><form method="post" action="<?= e(url('/admin/produse/'.$item['id'].'/sterge')) ?>" data-confirm-delete data-confirm-message="Produsul va fi arhivat și eliminat din zona publică."><?= csrf_field() ?><button class="admin-icon-action danger" type="submit" title="Șterge produsul" aria-label="Șterge <?= e($item['name']) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"/></svg></button></form></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr class="admin-product-empty-row"><td colspan="8"><div><span aria-hidden="true">⌕</span><strong>Nu am găsit produse pentru această căutare</strong><p>Încearcă altă denumire, un SKU sau o categorie.</p><a href="<?= e(url('/admin/produse?per_page='.$perPage)) ?>">Afișează toate produsele</a></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="admin-table-pagination" aria-label="Navigare produse">
        <form method="get" action="<?= e(url('/admin/produse')) ?>" data-admin-ignore-dirty>
            <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= e($query) ?>"><?php endif; ?>
            <label for="admin-products-per-page">Rânduri afișate:</label>
            <select id="admin-products-per-page" name="per_page" onchange="this.form.submit()">
                <?php foreach ($allowedPageSizes as $pageSize): ?><option value="<?= $pageSize ?>" <?= $perPage === $pageSize ? 'selected' : '' ?>><?= $pageSize ?></option><?php endforeach; ?>
            </select>
        </form>
        <strong><?= (int) $rangeStart ?>–<?= (int) $rangeEnd ?> din <?= (int) $resultCount ?></strong>
        <nav>
            <?php if ($page > 1): ?><a href="<?= e($paginationUrl(1)) ?>" aria-label="Prima pagină" title="Prima pagină"><svg viewBox="0 0 24 24"><path d="M6 5v14M18 6l-6 6 6 6"/></svg></a><a href="<?= e($paginationUrl($page - 1)) ?>" aria-label="Pagina anterioară" title="Pagina anterioară"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></a><?php else: ?><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 5v14M18 6l-6 6 6 6"/></svg></span><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></span><?php endif; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e($paginationUrl($page + 1)) ?>" aria-label="Pagina următoare" title="Pagina următoare"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></a><a href="<?= e($paginationUrl($totalPages)) ?>" aria-label="Ultima pagină" title="Ultima pagină"><svg viewBox="0 0 24 24"><path d="m6 6 6 6-6 6M18 5v14"/></svg></a><?php else: ?><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></span><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 6 6 6-6 6M18 5v14"/></svg></span><?php endif; ?>
        </nav>
    </footer>
</section>
