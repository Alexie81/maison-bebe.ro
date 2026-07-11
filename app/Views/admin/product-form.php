<?php
$editing = !empty($product);
$variantRows = $variants ?: [['id'=>'','sku'=>'Generat automat','price_minor'=>0,'stock_qty'=>0,'options_map'=>[]]];
$primaryToken = '';
foreach ($images as $image) { if (!empty($image['is_primary'])) { $primaryToken = 'existing:' . $image['id']; break; } }
$contentEditors = [
    ['name'=>'description_html','label'=>'Descriere detaliată','hint'=>'Conținutul principal afișat pe toată lățimea paginii produsului.','value'=>(string)($product['description_html'] ?? '')],
    ['name'=>'care_html','label'=>'Compoziție și îngrijire','hint'=>'Opțional. Secțiunea este ascunsă dacă nu are conținut și nu există material.','value'=>(string)($product['care_html'] ?? '')],
    ['name'=>'shipping_html','label'=>'Livrare și retur','hint'=>'Opțional. Lasă gol pentru a nu afișa secțiunea.','value'=>(string)($product['shipping_html'] ?? '')],
    ['name'=>'gift_wrap_html','label'=>'Ambalaj cadou','hint'=>'Opțional. Lasă gol pentru a nu afișa secțiunea.','value'=>(string)($product['gift_wrap_html'] ?? '')],
];
?>
<section class="admin-page-head"><div><p class="eyebrow">CATALOG / PRODUS</p><h1><?= $editing ? 'Editează produs' : 'Adaugă produs' ?></h1></div><?php if ($editing): ?><a class="admin-button secondary" href="<?= e(url('/produs/'.$product['slug'])) ?>" target="_blank">Previzualizare ↗</a><?php endif; ?></section>
<form class="admin-form-layout product-editor" method="post" enctype="multipart/form-data" action="<?= e(url($editing ? '/admin/produse/'.$product['id'] : '/admin/produse')) ?>" data-product-editor>
<?= csrf_field() ?>
<div>
    <section class="admin-panel"><h2>Informații generale</h2><div class="admin-form-grid">
        <label>Nume produs<input name="name" required value="<?= e($product['name'] ?? '') ?>"></label>
        <label>Slug<input name="slug" value="<?= e($product['slug'] ?? '') ?>" placeholder="generat din nume"></label>
        <label>SKU principal<input value="<?= e($product['sku'] ?? 'Se generează la salvare') ?>" readonly tabindex="-1"><small class="field-note">Generat automat și protejat.</small></label>
        <label>Material<input name="material" value="<?= e($product['material'] ?? '') ?>"></label>
        <label class="wide">Descriere scurtă<textarea name="short_description"><?= e($product['short_description'] ?? '') ?></textarea></label>

    </div></section>

    <section class="admin-panel product-content-editors">
        <div class="panel-head"><div><p class="eyebrow">PREZENTARE</p><h2>Conținutul produsului</h2></div><span class="help">Editor vizual cu formatare și imagini</span></div>
        <?php foreach ($contentEditors as $editorIndex => $editor): ?><details class="content-editor-section" <?= $editorIndex === 0 ? 'open' : '' ?>>
            <summary><span><strong><?= e($editor['label']) ?></strong><small><?= e($editor['hint']) ?></small></span></summary>
            <div class="rich-editor" data-rich-editor>
                <div class="rich-editor-toolbar" role="toolbar" aria-label="Formatare <?= e($editor['label']) ?>">
                    <select data-rich-block aria-label="Stil paragraf"><option value="p">Paragraf</option><option value="h2">Titlu mare</option><option value="h3">Subtitlu</option><option value="blockquote">Citat</option></select>
                    <button type="button" data-rich-command="bold" aria-label="Bold"><strong>B</strong></button>
                    <button type="button" data-rich-command="italic" aria-label="Italic"><em>I</em></button>
                    <button type="button" data-rich-command="underline" aria-label="Subliniat"><u>U</u></button>
                    <button type="button" data-rich-command="insertUnorderedList" aria-label="Listă cu puncte">• Listă</button>
                    <button type="button" data-rich-command="insertOrderedList" aria-label="Listă numerotată">1. Listă</button>
                    <button type="button" data-rich-link aria-label="Adaugă link">🔗</button>
                    <label class="rich-color" title="Culoare text"><span>A</span><input type="color" value="#3d312b" data-rich-color aria-label="Culoare text"></label>
                    <button type="button" data-rich-image aria-label="Adaugă imagine">▧ Imagine</button>
                    <button type="button" data-rich-command="removeFormat" aria-label="Șterge formatarea">Tx</button>
                </div>
                <div class="rich-editor-surface" contenteditable="true" role="textbox" aria-multiline="true" data-rich-surface data-placeholder="Scrie sau lipește conținutul aici…"><?= $editor['value'] ?></div>
                <textarea name="<?= e($editor['name']) ?>" hidden data-rich-input><?= e($editor['value']) ?></textarea>
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-rich-image-input>
                <div class="rich-editor-status" data-rich-status aria-live="polite"></div>
            </div>
        </details><?php endforeach; ?>
    </section>

    <section class="admin-panel product-options-panel">
        <div class="panel-head"><div><p class="eyebrow">CONFIGURARE</p><h2>Opțiunile produsului</h2></div><button type="button" class="admin-button secondary" data-add-option>+ Grup nou</button></div>
        <p class="help">Creează grupuri separate, de exemplu „Mărime” și „Culoare”. Fiecare valoare se adaugă individual, prin buton.</p>
        <div class="option-editor-list" data-option-groups>
            <?php foreach ($options as $option): ?><article class="option-editor-row" data-option-group>
                <span class="option-drag" aria-hidden="true">⋮⋮</span>
                <label class="option-name-field">Denumire grup<input name="option_name[]" value="<?= e($option['name']) ?>" placeholder="Ex: Mărime"></label>
                <div class="option-values-block"><span>Opțiuni</span><input type="hidden" name="option_values_json[]" value="<?= e(json_encode(array_values($option['values']), JSON_UNESCAPED_UNICODE)) ?>" data-option-values-json><div class="option-value-list" data-option-value-list><?php foreach ($option['values'] as $value): ?><div class="option-value-row"><input value="<?= e($value) ?>" data-option-value-input aria-label="Valoare opțiune"><button type="button" class="option-value-remove" data-remove-option-value aria-label="Șterge opțiunea">×</button></div><?php endforeach; ?></div><button type="button" class="admin-button secondary option-value-add" data-add-option-value>+ Adaugă opțiune</button></div>
                <button type="button" class="icon-action danger" data-remove-option aria-label="Șterge grupul">×</button>
            </article><?php endforeach; ?>
        </div>        <div class="admin-empty option-empty" <?= $options ? 'hidden' : '' ?> data-option-empty>Nu există grupuri încă. Adaugă „Mărime”, „Culoare” sau orice opțiune necesară.</div>
    </section>

    <section class="admin-panel"><div class="panel-head"><div><p class="eyebrow">PREȚ ȘI STOC</p><h2>Variante</h2></div><button type="button" class="admin-button secondary" data-add-variant>+ Adaugă variantă</button></div>
        <p class="help">Fiecare rând reprezintă o combinație. SKU-ul este generat automat și nu poate fi editat.</p>
        <div class="variants-editor" data-variants>
        <?php foreach ($variantRows as $variant): $map = $variant['options_map'] ?? []; ?><article class="variant-row" data-variant-row>
            <input type="hidden" name="variant_id[]" value="<?= e($variant['id']) ?>">
            <input type="hidden" name="variant_options_json[]" value="<?= e(json_encode($map, JSON_UNESCAPED_UNICODE)) ?>" data-variant-options-json>
            <div class="variant-sku"><span>SKU</span><strong><?= e($variant['sku'] ?: 'Generat automat') ?></strong></div>
            <div class="variant-option-selects" data-variant-option-selects>
                <?php foreach ($options as $option): ?><label><?= e($option['name']) ?><select data-variant-option="<?= e($option['name']) ?>"><option value="">Alege</option><?php foreach ($option['values'] as $value): ?><option value="<?= e($value) ?>" <?= ($map[$option['name']] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
            </div>
            <label>Preț (lei)<input type="number" step="0.01" min="0" name="variant_price[]" value="<?= number_format((int) $variant['price_minor'] / 100, 2, '.', '') ?>" required></label>
            <label>Stoc<input type="number" min="0" name="variant_stock[]" value="<?= (int) $variant['stock_qty'] ?>" required></label>
            <button type="button" class="icon-action danger" data-remove-variant aria-label="Șterge varianta">×</button>
        </article><?php endforeach; ?>
        </div>
    </section>

    <section class="admin-panel product-gallery-editor">
        <div class="panel-head"><div><p class="eyebrow">MEDIA</p><h2>Fotografii</h2></div><span class="help">JPG, PNG sau WebP · max. 8 MB / imagine</span></div>
        <p class="help">Trage fotografiile pentru a schimba ordinea. Apasă steaua pentru fotografia principală.</p>
        <input type="file" id="product-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden data-product-images-input>
        <input type="hidden" name="image_order_json" value="[]" data-image-order>
        <input type="hidden" name="primary_image_token" value="<?= e($primaryToken) ?>" data-primary-image-token>
        <div class="product-image-grid" data-product-images>
            <?php foreach ($images as $image): $token = 'existing:'.$image['id']; ?><article class="product-image-card<?= !empty($image['is_primary']) ? ' is-primary' : '' ?>" draggable="true" data-image-card data-image-token="<?= e($token) ?>">
                <img src="<?= e(url($image['path'])) ?>" alt="<?= e($image['alt_text'] ?: ($product['name'] ?? 'Produs')) ?>">
                <button type="button" class="image-remove" data-remove-image aria-label="Șterge fotografia">×</button>
                <button type="button" class="image-primary" data-primary-image aria-label="Setează fotografia principală">★</button>
                <span>Principală</span>
            </article><?php endforeach; ?>
            <label class="product-image-add" for="product-images"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="15" rx="2"/><circle cx="12" cy="12" r="3"/><path d="m8 5 1-2h6l1 2"/></svg><strong>Adaugă fotografii</strong><small>Poți selecta mai multe odată</small></label>
        </div>
    </section>

    <section class="admin-panel"><h2>SEO și pagină publică</h2><div class="admin-form-grid"><label class="wide">Meta title<input name="seo_title" value="<?= e($product['seo_title'] ?? '') ?>"></label><label class="wide">Meta description<textarea name="seo_description"><?= e($product['seo_description'] ?? '') ?></textarea></label><label class="check-label"><input type="checkbox" name="robots_index" value="1" <?= !isset($product['robots_index']) || $product['robots_index'] ? 'checked' : '' ?>> Index, follow</label><label class="check-label"><input type="checkbox" name="include_sitemap" value="1" <?= !isset($product['include_sitemap']) || $product['include_sitemap'] ? 'checked' : '' ?>> Include în sitemap</label></div></section>
</div>
<aside>
    <section class="admin-panel"><h2>Publicare</h2><label>Status<select name="status"><option value="draft" <?= ($product['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="active" <?= ($product['status'] ?? '') === 'active' ? 'selected' : '' ?>>Publicat</option><option value="archived" <?= ($product['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Arhivat</option></select></label><label class="check-label"><input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Produs recomandat</label><label class="check-label"><input type="checkbox" name="is_gift_box" value="1" <?= !empty($product['is_gift_box']) ? 'checked' : '' ?>> Cutie / Gift Box configurabilă</label><p class="help">Pentru cutiile care apar în configurator. Fotografia principală devine imaginea cutiei.</p><button class="admin-button" type="submit">Salvează produsul</button></section>
    <section class="admin-panel"><h2>Categorii asociate</h2><div class="category-checks"><?php foreach ($categories as $category): ?><label><input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>" <?= in_array($category['id'], $selected) ? 'checked' : '' ?>> <?= e($category['name']) ?></label><?php endforeach; ?></div><label>Categorie principală<select name="primary_category_id" required><option value="">Alege</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($product['primary_category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label></section>
</aside>
</form>