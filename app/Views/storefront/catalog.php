<section class="catalog-hero shell"><nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Acasă</a><span>/</span><span><?= e($heading) ?></span></nav><h1><?= e($heading) ?></h1><p><?= e($description) ?></p></section>
<section class="shell catalog-layout section-space-small">
    <button type="button" class="filter-trigger button button-outline" data-filter-toggle aria-controls="catalog-filters" aria-expanded="false">Filtre</button>
    <aside id="catalog-filters" class="catalog-filters">
        <form method="get" action="">
            <div class="filter-head"><h2>Filtre</h2><button type="button" data-filter-toggle aria-label="Închide filtrele">×</button></div>
            <fieldset><legend>Categorii</legend><?php foreach ($categories as $item): ?><label><input type="radio" name="categorie" value="<?= e($item['slug']) ?>" <?= ($filters['category'] ?? '') === $item['slug'] ? 'checked' : '' ?>> <?= e($item['name']) ?><span><?= (int) $item['product_count'] ?></span></label><?php endforeach; ?></fieldset>
            <fieldset><legend>Colecții</legend><?php foreach ($collections as $item): ?><label><input type="radio" name="colectie" value="<?= e($item['slug']) ?>" <?= ($filters['collection'] ?? '') === $item['slug'] ? 'checked' : '' ?>> <?= e($item['name']) ?></label><?php endforeach; ?></fieldset>
            <fieldset><legend>Material</legend><?php foreach ($materials as $material): ?><label><input type="radio" name="material" value="<?= e($material) ?>" <?= ($filters['material'] ?? '') === $material ? 'checked' : '' ?>> <?= e($material) ?></label><?php endforeach; ?></fieldset>
            <fieldset><legend>Disponibilitate</legend><label><input type="checkbox" name="stoc" value="disponibil" <?= !empty($filters['stock']) ? 'checked' : '' ?>> În stoc</label></fieldset>
            <div class="price-fields"><label>De la <input type="number" name="pret_min" min="0" value="<?= e(isset($filters['min_price']) && $filters['min_price'] !== null ? (int) $filters['min_price'] / 100 : '') ?>"></label><label>Până la <input type="number" name="pret_max" min="0" value="<?= e(isset($filters['max_price']) && $filters['max_price'] !== null ? (int) $filters['max_price'] / 100 : '') ?>"></label></div>
            <button class="button" type="submit">Aplică filtrele</button><a class="text-link" href="<?= e(url('/shop')) ?>">Resetează</a>
        </form>
    </aside>
    <div class="catalog-content">
        <div class="catalog-toolbar"><span><?= (int) $catalog['total'] ?> produse</span><form method="get"><label for="sort">Sortează</label><?php foreach ($_GET as $key => $value): if ($key !== 'sort' && is_scalar($value)): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php endif; endforeach; ?><select id="sort" name="sort" onchange="this.form.submit()"><option value="">Recomandate</option><option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Noutăți</option><option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Preț crescător</option><option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Preț descrescător</option></select></form></div>
        <?php if (!$catalog['items']): ?><div class="empty-state"><h2>Nicio alegere pentru aceste filtre</h2><p>Încearcă o selecție mai largă sau revino la toate produsele.</p><a class="button" href="<?= e(url('/shop')) ?>">Vezi toate produsele</a></div><?php else: ?><div class="product-grid"><?php foreach ($catalog['items'] as $product) { require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div><?php endif; ?>
        <?php $pages = (int) ceil($catalog['total'] / 12); if ($pages > 1): ?><nav class="pagination" aria-label="Pagini produse"><?php for ($index=1;$index<=$pages;$index++): $query=$_GET;$query['page']=$index; ?><a class="<?= $index===$page?'active':'' ?>" href="?<?= e(http_build_query($query)) ?>"><?= $index ?></a><?php endfor; ?></nav><?php endif; ?>
    </div>
</section>

