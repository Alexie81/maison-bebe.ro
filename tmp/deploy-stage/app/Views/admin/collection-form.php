<?php $editing=(bool)$collection; ?>
<section class="admin-page-head"><div><p class="eyebrow">CATALOG / COLECȚIE</p><h1><?= $editing?'Editează colecția':'Colecție nouă' ?></h1></div><?php if($editing&&!empty($collection['is_active'])): ?><a class="admin-button secondary" href="<?= e(url('/colectie/'.$collection['slug'])) ?>" target="_blank">Vezi pe website ↗</a><?php endif; ?></section>
<form class="admin-form-layout" enctype="multipart/form-data" method="post" action="<?= e(url($editing?'/admin/colectii/'.$collection['id']:'/admin/colectii')) ?>" data-seo-assistant data-seo-kind="collection">
<?= csrf_field() ?><div>
<section class="admin-panel"><h2>Imagine și texte</h2><div class="admin-form-grid">
<label>Nume / titlu<input name="name" required value="<?= e($collection['name']??'') ?>" data-seo-name></label>
<label>Slug<input name="slug" value="<?= e($collection['slug']??'') ?>" placeholder="generat automat din titlu"></label>
<label>Ordine afișare<input type="number" name="sort_order" value="<?= (int)($collection['sort_order']??0) ?>"></label>
<label class="wide">Text / descriere<textarea name="description" data-seo-source placeholder="Descrierea afișată pe pagina colecției"><?= e($collection['description']??'') ?></textarea></label>
</div></section>
<section class="admin-panel seo-assistant"><div class="panel-head"><div><h2>SEO generat automat</h2><p class="help">Se actualizează din titlu și descriere. Poți modifica rezultatul.</p></div><button type="button" class="admin-button secondary" data-seo-regenerate>↻ Regenerează</button></div><div class="admin-form-grid">
<label class="wide">Meta title <span class="seo-counter" data-seo-title-count></span><input name="seo_title" data-seo-title value="<?= e($collection['seo_title']??'') ?>"></label>
<label class="wide">Meta description <span class="seo-counter" data-seo-description-count></span><textarea name="seo_description" data-seo-description><?= e($collection['seo_description']??'') ?></textarea></label>
<div class="seo-preview wide"><small>PREVIZUALIZARE GOOGLE</small><strong data-seo-preview-title></strong><span><?= e(url('/colectie/'.($collection['slug']??'colectie'))) ?></span><p data-seo-preview-description></p></div>
</div></section></div>
<aside><section class="admin-panel"><h2>Publicare</h2><label class="check-label"><input type="checkbox" name="is_active" value="1" <?= !isset($collection['is_active'])||$collection['is_active']?'checked':'' ?>> Vizibilă pe website</label><p class="help">Pentru un aspect uniform se recomandă 4–5 colecții active.</p><button class="admin-button" type="submit">Salvează colecția</button></section>
<section class="admin-panel"><h2>Imagine colecție</h2><?php if(!empty($collection['image_path'])): ?><img class="admin-category-preview" src="<?= e(url($collection['image_path'])) ?>" alt="" width="640" height="420"><?php endif; ?><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><p class="help">JPG, PNG sau WebP, maximum 8 MB.</p></section></aside>
</form>