<?php
$editing = (bool)$category;
$collectionMode = !empty($collectionMode);
$isCollection = $collectionMode || !empty($category['is_featured']);
?>
<section class="admin-page-head">
    <div><p class="eyebrow">CATALOG / <?= $isCollection ? 'COLECȚIE' : 'CATEGORIE' ?></p><h1><?= $editing ? 'Editează '.($isCollection ? 'colecția' : 'categoria') : ($isCollection ? 'Colecție nouă' : 'Categorie nouă') ?></h1></div>
    <?php if ($editing && !empty($category['is_active'])): ?><a class="admin-button secondary" href="<?= e(url('/categorie/'.$category['slug'])) ?>" target="_blank">Vezi pe website ↗</a><?php endif; ?>
</section>

<form class="admin-form-layout" enctype="multipart/form-data" method="post" action="<?= e(url($editing ? '/admin/categorii/'.$category['id'] : '/admin/categorii')) ?>">
    <?= csrf_field() ?>
    <div>
        <section class="admin-panel">
            <h2>Imagine și texte</h2>
            <div class="admin-form-grid">
                <label>Nume / titlu<input name="name" required value="<?= e($category['name'] ?? '') ?>"></label>
                <label>Slug<input name="slug" value="<?= e($category['slug'] ?? '') ?>" placeholder="generat automat din titlu"></label>
                <label>Categorie părinte<select name="parent_id"><option value="">Fără părinte</option><?php foreach ($parents as $parent): if ((int)($category['id'] ?? 0) === (int)$parent['id']) continue; ?><option value="<?= (int)$parent['id'] ?>" <?= (int)($category['parent_id'] ?? 0) === (int)$parent['id'] ? 'selected' : '' ?>><?= e($parent['name']) ?></option><?php endforeach; ?></select></label>
                <label>Ordine afișare<input type="number" name="sort_order" value="<?= (int)($category['sort_order'] ?? 0) ?>"></label>
                <label class="wide">Text / descriere<textarea name="description" placeholder="Descrierea afișată pe pagina categoriei"><?= e($category['description'] ?? '') ?></textarea></label>
            </div>
        </section>
        <section class="admin-panel">
            <h2>SEO</h2>
            <div class="admin-form-grid">
                <label class="wide">Meta title<input name="seo_title" value="<?= e($category['seo_title'] ?? '') ?>"></label>
                <label class="wide">Meta description<textarea name="seo_description"><?= e($category['seo_description'] ?? '') ?></textarea></label>
            </div>
        </section>
    </div>

    <aside>
        <section class="admin-panel">
            <h2>Publicare</h2>
            <label class="check-label"><input type="checkbox" name="is_active" value="1" <?= !isset($category['is_active']) || $category['is_active'] ? 'checked' : '' ?>> Vizibilă pe website</label>
            <label class="check-label"><input type="checkbox" name="is_featured" value="1" <?= $isCollection ? 'checked' : '' ?>> Colecție pe homepage</label>
            <p class="help">Colecțiile apar în secțiunea „Colecțiile noastre”. Pentru un aspect uniform se recomandă 4–5 colecții active, însă poți configura și mai multe.</p>
            <label class="check-label"><input type="checkbox" name="show_in_menu" value="1" <?= !isset($category['show_in_menu']) || $category['show_in_menu'] ? 'checked' : '' ?>> Disponibilă pentru navigare</label>
            <button class="admin-button" type="submit">Salvează <?= $isCollection ? 'colecția' : 'categoria' ?></button>
        </section>

        <section class="admin-panel">
            <h2>Imagine <?= $isCollection ? 'colecție' : 'categorie' ?></h2>
            <?php if (!empty($category['image_path'])): ?><img class="admin-category-preview" src="<?= e(url($category['image_path'])) ?>" alt="<?= e($category['name']) ?>" width="640" height="420"><?php endif; ?>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <p class="help">Imaginea este folosită pe homepage și în antetul paginii. JPG, PNG sau WebP, maximum 8 MB.</p>
        </section>
    </aside>
</form>