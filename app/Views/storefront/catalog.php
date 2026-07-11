<?php
$resetUrl = isset($category)
    ? '/categorie/' . $category['slug']
    : (isset($collection) ? '/colectie/' . $collection['slug'] : '/shop');
$pages = max(1, (int) ceil((int) $catalog['total'] / 12));
$currentPage = min(max(1, (int) $page), $pages);
$windowStart = max(1, $currentPage - 2);
$windowEnd = min($pages, $windowStart + 4);
$windowStart = max(1, $windowEnd - 4);
$activeFilterCount = (int) !empty($filters['category']) + (int) !empty($filters['collection']) + (int) !empty($filters['material']) + (int) !empty($filters['stock']) + (int) ($filters['min_price'] !== null) + (int) ($filters['max_price'] !== null);
?>
<section class="catalog-hero <?= !empty($heroImage) ? 'catalog-hero-with-image' : '' ?>">
    <?php if (!empty($heroImage)): ?><div class="catalog-hero-media"><img src="<?= e(url($heroImage)) ?>" alt="<?= e($heading) ?>" width="1600" height="760"></div><?php endif; ?>
    <div class="catalog-hero-copy shell">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Acasă</a><span>/</span><span><?= e($heading) ?></span></nav>
        <h1><?= e($heading) ?></h1>
        <?php if ($description): ?><p><?= e($description) ?></p><?php endif; ?>
    </div>
</section>

<section class="shell catalog-layout section-space-small">
    <div class="catalog-mobile-actions">
        <button type="button" class="filter-trigger button button-outline" data-filter-toggle aria-controls="catalog-filters" aria-expanded="false">
            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            Filtre<?= $activeFilterCount ? ' (' . $activeFilterCount . ')' : '' ?>
        </button>
    </div>

    <aside id="catalog-filters" class="catalog-filters" aria-label="Filtre produse">
        <form method="get" action="<?= e(url($resetUrl)) ?>">
            <?php if (!empty($filters['query'])): ?><input type="hidden" name="q" value="<?= e($filters['query']) ?>"><?php endif; ?>
            <?php if (!empty($filters['sort'])): ?><input type="hidden" name="sort" value="<?= e($filters['sort']) ?>"><?php endif; ?>
            <div class="filter-head"><div><span class="eyebrow">RAFINARE</span><h2>Filtre</h2></div><button type="button" data-filter-toggle aria-label="Închide filtrele">×</button></div>

            <?php if (!isset($category) && $categories): ?>
            <fieldset><legend>Categorii</legend>
                <?php foreach ($categories as $item): ?><label><input type="radio" name="categorie" value="<?= e($item['slug']) ?>" <?= ($filters['category'] ?? '') === $item['slug'] ? 'checked' : '' ?>><span class="filter-label-text"><?= e($item['name']) ?></span><span class="filter-count"><?= (int) $item['product_count'] ?></span></label><?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <?php if (!isset($collection) && $collections): ?>
            <fieldset><legend>Colecții</legend>
                <?php foreach ($collections as $item): ?><label><input type="radio" name="colectie" value="<?= e($item['slug']) ?>" <?= ($filters['collection'] ?? '') === $item['slug'] ? 'checked' : '' ?>><span class="filter-label-text"><?= e($item['name']) ?></span></label><?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <?php if ($materials): ?>
            <fieldset><legend>Material</legend>
                <?php foreach ($materials as $material): ?><label><input type="radio" name="material" value="<?= e($material) ?>" <?= ($filters['material'] ?? '') === $material ? 'checked' : '' ?>><span class="filter-label-text"><?= e($material) ?></span></label><?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <fieldset><legend>Disponibilitate</legend><label><input type="checkbox" name="stoc" value="disponibil" <?= !empty($filters['stock']) ? 'checked' : '' ?>><span class="filter-label-text">În stoc</span></label></fieldset>
            <fieldset><legend>Preț</legend><div class="price-fields">
                <label><span>De la (lei)</span><input type="number" name="pret_min" min="0" step="1" inputmode="numeric" value="<?= e($filters['min_price'] !== null ? (string) ($filters['min_price'] / 100) : '') ?>"></label>
                <label><span>Până la (lei)</span><input type="number" name="pret_max" min="0" step="1" inputmode="numeric" value="<?= e($filters['max_price'] !== null ? (string) ($filters['max_price'] / 100) : '') ?>"></label>
            </div></fieldset>
            <div class="filter-actions"><button class="button" type="submit">Arată <?= (int) $catalog['total'] ?> produse</button><a class="text-link" href="<?= e(url($resetUrl)) ?>">Resetează filtrele</a></div>
        </form>
    </aside>
    <button type="button" class="catalog-filter-backdrop" data-filter-toggle aria-label="Închide filtrele" hidden></button>

    <div class="catalog-content">
        <form class="catalog-search" method="get" action="<?= e(url($resetUrl)) ?>" role="search">
            <?php foreach ($_GET as $key => $value): if (!in_array($key, ['q', 'page'], true) && is_scalar($value)): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endif; endforeach; ?>
            <label for="catalog-search-input" class="sr-only">Caută în magazin</label>
            <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 4 4"/></svg>
            <input id="catalog-search-input" type="search" name="q" value="<?= e($filters['query'] ?? '') ?>" placeholder="Caută produse, materiale, culori, mărimi…" autocomplete="off">
            <?php if (!empty($filters['query'])): ?><a href="<?= e(url($resetUrl)) ?>" aria-label="Șterge căutarea">×</a><?php endif; ?>
            <button class="button" type="submit">Caută</button>
        </form>

        <?php if (!empty($filters['query'])): ?><p class="catalog-search-summary">Rezultate pentru <strong>„<?= e($filters['query']) ?>”</strong></p><?php endif; ?>
        <div class="catalog-toolbar">
            <span><strong><?= (int) $catalog['total'] ?></strong> <?= (int) $catalog['total'] === 1 ? 'produs' : 'produse' ?></span>
            <form method="get" action="<?= e(url($resetUrl)) ?>">
                <label for="sort">Sortează</label>
                <?php foreach ($_GET as $key => $value): if ($key !== 'sort' && $key !== 'page' && is_scalar($value)): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endif; endforeach; ?>
                <select id="sort" name="sort" onchange="this.form.submit()">
                    <option value="">Recomandate</option>
                    <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Noutăți</option>
                    <option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Preț crescător</option>
                    <option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Preț descrescător</option>
                </select>
            </form>
        </div>

        <?php if (!$catalog['items']): ?>
            <div class="empty-state"><h2>Nicio alegere pentru aceste criterii</h2><p>Încearcă un termen mai scurt sau elimină câteva filtre.</p><a class="button" href="<?= e(url($resetUrl)) ?>">Vezi toate produsele</a></div>
        <?php else: ?>
            <div class="product-grid catalog-product-grid"><?php foreach ($catalog['items'] as $product) { require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Pagini produse">
                <?php $previousQuery = $_GET; $previousQuery['page'] = max(1, $currentPage - 1); ?>
                <a class="pagination-direction <?= $currentPage === 1 ? 'disabled' : '' ?>" href="?<?= e(http_build_query($previousQuery)) ?>" aria-label="Pagina anterioară" <?= $currentPage === 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>
                <?php if ($windowStart > 1): $firstQuery = $_GET; $firstQuery['page'] = 1; ?><a href="?<?= e(http_build_query($firstQuery)) ?>">1</a><?php if ($windowStart > 2): ?><span>…</span><?php endif; ?><?php endif; ?>
                <?php for ($index = $windowStart; $index <= $windowEnd; $index++): $query = $_GET; $query['page'] = $index; ?><a class="<?= $index === $currentPage ? 'active' : '' ?>" href="?<?= e(http_build_query($query)) ?>" <?= $index === $currentPage ? 'aria-current="page"' : '' ?>><?= $index ?></a><?php endfor; ?>
                <?php if ($windowEnd < $pages): if ($windowEnd < $pages - 1): ?><span>…</span><?php endif; $lastQuery = $_GET; $lastQuery['page'] = $pages; ?><a href="?<?= e(http_build_query($lastQuery)) ?>"><?= $pages ?></a><?php endif; ?>
                <?php $nextQuery = $_GET; $nextQuery['page'] = min($pages, $currentPage + 1); ?>
                <a class="pagination-direction <?= $currentPage === $pages ? 'disabled' : '' ?>" href="?<?= e(http_build_query($nextQuery)) ?>" aria-label="Pagina următoare" <?= $currentPage === $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
            </nav>
        <?php endif; ?>
    </div>
</section>