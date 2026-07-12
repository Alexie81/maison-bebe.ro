<?php
$editing = !empty($product);
$variantRows = $variants ?: [['id'=>'','sku'=>'Generat automat','price_minor'=>0,'stock_qty'=>0,'options_map'=>[]]];
$primaryToken = '';
foreach ($images as $image) { if (!empty($image['is_primary'])) { $primaryToken = 'existing:' . $image['id']; break; } }
$renderEditorValue = static function (string $html): string {
    return (string) preg_replace_callback('/(<img\b[^>]*\bsrc=["\'])(\/uploads\/[^"\']+)/i', static fn(array $match): string => $match[1] . url($match[2]), $html);
};
$specificationRows = [];
if (trim((string)($product['material'] ?? '')) !== '') $specificationRows[] = ['Material', (string)$product['material']];
foreach ($options as $specOption) {
    $specValues = array_values(array_filter(array_map('trim', (array)($specOption['values'] ?? []))));
    if ($specValues) $specificationRows[] = [(string)$specOption['name'], implode(', ', $specValues)];
}
$generatedSpecifications = '';
if ($specificationRows) {
    $generatedSpecifications = '<table><tbody>';
    foreach ($specificationRows as [$specLabel, $specValue]) $generatedSpecifications .= '<tr><th>'.htmlspecialchars($specLabel, ENT_QUOTES, 'UTF-8').'</th><td>'.htmlspecialchars($specValue, ENT_QUOTES, 'UTF-8').'</td></tr>';
    $generatedSpecifications .= '</tbody></table>';
}
$savedSpecifications = (string)($product['care_html'] ?? '');
$specificationsValue = trim(strip_tags($savedSpecifications)) !== '' || preg_match('/<(img|table)\b/i', $savedSpecifications) ? $savedSpecifications : $generatedSpecifications;
$contentEditors = [
    ['name'=>'description_html','label'=>'Descriere detaliată','hint'=>'Conținutul principal afișat pe toată lățimea paginii produsului.','value'=>(string)($product['description_html'] ?? '')],
    ['name'=>'care_html','label'=>'Specificații','hint'=>'Generate din material și opțiuni, apoi complet editabile.','value'=>$specificationsValue,'kind'=>'specifications','auto'=>$savedSpecifications===''],
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
        <?php foreach ($contentEditors as $editorIndex => $editor): $editorDisplay = $renderEditorValue((string) $editor['value']); ?><details class="content-editor-section" <?= $editorIndex === 0 ? 'open' : '' ?>>
            <summary><span><strong><?= e($editor['label']) ?></strong><small><?= e($editor['hint']) ?></small></span></summary>
            <div class="rich-editor" data-rich-editor<?= ($editor['kind'] ?? '') === 'specifications' ? ' data-specifications-editor data-auto-specifications="'.(!empty($editor['auto'])?'1':'0').'"' : '' ?>>
                <div class="rich-editor-toolbar" role="toolbar" aria-label="Formatare <?= e($editor['label']) ?>">
                    <div class="rich-editor-tools" data-rich-tools>
                        <button type="button" class="is-emphasis" data-rich-command="bold" title="Text îngroșat" aria-label="Text îngroșat"><strong>B</strong></button>
                        <button type="button" data-rich-command="italic" title="Text înclinat" aria-label="Text înclinat"><em>I</em></button>
                        <button type="button" data-rich-format="h2" title="Titlu / revino la paragraf" aria-label="Titlu">H</button>
                        <select class="rich-tool-select rich-font-select" data-rich-font title="Font" aria-label="Font"><option value="">Font</option><option value="Montserrat">Montserrat</option><option value="Playfair Display">Playfair</option><option value="Georgia">Georgia</option><option value="Arial">Arial</option></select>
                        <select class="rich-tool-select rich-size-select" data-rich-size title="Dimensiune text" aria-label="Dimensiune text"><option value="">Mărime</option><option value="12px">12</option><option value="14px">14</option><option value="16px">16</option><option value="18px">18</option><option value="24px">24</option><option value="32px">32</option><option value="48px">48</option></select>
                        <span class="rich-tool-separator" aria-hidden="true"></span>
                        <button type="button" data-rich-checklist title="Listă de verificare" aria-label="Listă de verificare">☑</button>
                        <button type="button" data-rich-command="insertUnorderedList" title="Listă cu puncte" aria-label="Listă cu puncte">•</button>
                        <button type="button" data-rich-command="insertOrderedList" title="Listă numerotată" aria-label="Listă numerotată">1.</button>
                        <button type="button" data-rich-format="pre" title="Bloc de cod" aria-label="Bloc de cod">&lt;/&gt;</button>
                        <span class="rich-tool-separator" aria-hidden="true"></span>
                        <button type="button" data-rich-search title="Caută în text" aria-label="Caută în text">⌕</button>
                        <button type="button" data-rich-link title="Adaugă link" aria-label="Adaugă link">↗</button>
                        <button type="button" data-rich-highlight title="Evidențiază textul" aria-label="Evidențiază textul">M</button>
                        <button type="button" data-rich-theme title="Mod întunecat al editorului" aria-label="Mod întunecat">◕</button>
                        <button type="button" data-rich-table title="Inserează tabel" aria-label="Inserează tabel">⊞</button>
                        <button type="button" data-rich-image title="Adaugă imagine" aria-label="Adaugă imagine">▧</button>
                        <label class="rich-color" title="Culoare text"><span>A</span><input type="color" value="#3d312b" data-rich-color aria-label="Culoare text"></label>
                        <button type="button" data-rich-color-reset title="Revino la culoarea implicită" aria-label="Culoare implicită">A↺</button>
                        <button type="button" data-rich-command="removeFormat" title="Șterge formatarea" aria-label="Șterge formatarea">Tx</button><?php if (($editor['kind'] ?? '') === 'specifications'): ?><button type="button" class="rich-spec-generate" data-generate-specifications title="Regenerează specificațiile din material și opțiuni">↻ Specificații</button><?php endif; ?>
                    </div>
                    <div class="rich-editor-modes" role="group" aria-label="Mod editor">
                        <button type="button" class="is-active" data-rich-mode="edit" aria-pressed="true">Edit</button>
                        <button type="button" data-rich-mode="preview" aria-pressed="false">Preview</button>
                    </div>
                </div>
                <div class="rich-editor-canvas">
                    <div class="rich-editor-surface" contenteditable="true" role="textbox" aria-multiline="true" data-rich-surface data-placeholder="Începe să scrii conținutul produsului…"><?= $editorDisplay ?></div>
                    <div class="rich-editor-preview" data-rich-preview hidden></div>
                </div>
                <aside class="rich-image-inspector" data-rich-image-inspector hidden>
                    <div class="rich-image-inspector-head"><div><strong>Setări imagine</strong><small>Dimensiune, colțuri și poziție</small></div><button type="button" data-rich-image-close aria-label="Închide setările">×</button></div>
                    <label>Lățime <input type="range" min="20" max="100" step="5" value="100" data-rich-image-width><output data-rich-image-width-output>100%</output></label>
                    <label>Rotunjire <input type="range" min="0" max="48" step="2" value="0" data-rich-image-radius><output data-rich-image-radius-output>0px</output></label>
                    <div class="rich-image-position"><span>Aliniere</span><div><button type="button" data-rich-image-align="left">Stânga</button><button type="button" data-rich-image-align="center" class="is-active">Centru</button><button type="button" data-rich-image-align="right">Dreapta</button></div></div>
                    <div class="rich-image-position"><span>Poziție în conținut</span><div><button type="button" data-rich-image-move="up">↑ Mai sus</button><button type="button" data-rich-image-move="down">↓ Mai jos</button></div></div>
                </aside>
                <textarea name="<?= e($editor['name']) ?>" hidden data-rich-input><?= e($editor['value']) ?></textarea>
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-rich-image-input>
                <div class="rich-editor-footer">
                    <span><b data-rich-words>0</b> cuvinte</span>
                    <span><b data-rich-characters>0</b> caractere</span>
                    <span><b data-rich-lines>1</b> linii</span>
                    <span class="rich-editor-status" data-rich-status aria-live="polite"></span>
                </div>
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
        <div class="panel-head"><div><p class="eyebrow">MEDIA PRODUS</p><h2>Fotografiile produsului</h2><p class="help">Prima fotografie este cea afișată în catalog. Trage cardurile pentru a schimba ordinea.</p></div><div class="gallery-upload-meta"><strong><span data-product-image-count><?= count($images) ?></span>/12</strong><small>JPG, PNG sau WebP · max. 8 MB</small></div></div>
        <input type="file" id="product-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden data-product-images-input>
        <input type="hidden" name="image_order_json" value="[]" data-image-order>
        <input type="hidden" name="primary_image_token" value="<?= e($primaryToken) ?>" data-primary-image-token>
        <div class="product-image-grid<?= $images ? '' : ' is-empty' ?>" data-product-images data-product-dropzone>
            <?php foreach ($images as $image): $token = 'existing:'.$image['id']; ?><article class="product-image-card<?= !empty($image['is_primary']) ? ' is-primary' : '' ?>" draggable="true" data-image-card data-image-token="<?= e($token) ?>">
                <img src="<?= e(url($image['path'])) ?>" alt="<?= e($image['alt_text'] ?: ($product['name'] ?? 'Produs')) ?>" draggable="false">
                <span class="image-drag-handle" aria-hidden="true">⠿</span>
                <button type="button" class="image-remove" data-remove-image aria-label="Șterge fotografia">×</button>
                <button type="button" class="image-primary" data-primary-image aria-label="Setează fotografia principală">★</button>
                <span class="image-primary-label">Principală</span>
            </article><?php endforeach; ?>
            <label class="product-image-add" for="product-images"><span class="product-image-add-icon"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="15" rx="2"/><circle cx="12" cy="12" r="3"/><path d="m8 5 1-2h6l1 2"/></svg></span><strong>Adaugă fotografii</strong><small><span>Selectează mai multe imagini</span><span>sau trage-le aici</span></small></label>
        </div>
        <p class="gallery-upload-note"><span>↕</span> Poți reordona fotografiile prin glisare. Steaua marchează fotografia principală.</p>
    </section>

    <section class="admin-panel seo-assistant" data-seo-assistant data-seo-kind="product">
        <div class="panel-head"><div><p class="eyebrow">OPTIMIZARE LOCALĂ</p><h2>SEO și pagină publică</h2><p class="help">Titlul și descrierea sunt compuse în timp real din nume, categorie, material și conținut. Le poți ajusta oricând.</p></div><button class="admin-button secondary" type="button" data-seo-regenerate>↻ Regenerează</button></div>
        <div class="seo-live-status"><span class="seo-pulse"></span><strong>Generator inteligent activ</strong><small>fără servicii externe</small></div>
        <div class="admin-form-grid">
            <label class="wide">Meta title <span class="seo-counter" data-seo-title-count>0/60</span><input name="seo_title" maxlength="70" data-seo-title value="<?= e($product['seo_title'] ?? '') ?>"></label>
            <label class="wide">Meta description <span class="seo-counter" data-seo-description-count>0/160</span><textarea name="seo_description" maxlength="180" data-seo-description><?= e($product['seo_description'] ?? '') ?></textarea></label>
            <div class="seo-preview wide"><small>PREVIZUALIZARE GOOGLE</small><strong data-seo-preview-title>Titlul paginii</strong><span data-seo-preview-url><?= e(url('/produs/'.($product['slug'] ?? 'produs'))) ?></span><p data-seo-preview-description>Descrierea paginii va apărea aici.</p></div>
            <label class="check-label"><input type="checkbox" name="robots_index" value="1" <?= !isset($product['robots_index']) || $product['robots_index'] ? 'checked' : '' ?>> Index, follow</label><label class="check-label"><input type="checkbox" name="include_sitemap" value="1" <?= !isset($product['include_sitemap']) || $product['include_sitemap'] ? 'checked' : '' ?>> Include în sitemap</label>
        </div>
    </section>
</div>
<aside>
    <section class="admin-panel"><h2>Publicare</h2><label>Status<select name="status"><option value="draft" <?= ($product['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="active" <?= ($product['status'] ?? '') === 'active' ? 'selected' : '' ?>>Publicat</option><option value="archived" <?= ($product['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Arhivat</option></select></label><label class="check-label"><input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Produs recomandat</label><label class="check-label"><input type="checkbox" name="is_gift_box" value="1" <?= !empty($product['is_gift_box']) ? 'checked' : '' ?>> Cutie / Gift Box configurabilă</label><p class="help">Pentru cutiile care apar în configurator. Fotografia principală devine imaginea cutiei.</p><button class="admin-button" type="submit">Salvează produsul</button></section>
    <section class="admin-panel"><h2>Categorii asociate</h2><div class="category-checks"><?php foreach ($categories as $category): ?><label><input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>" <?= in_array($category['id'], $selected) ? 'checked' : '' ?>> <?= e($category['name']) ?></label><?php endforeach; ?></div><label>Categorie principală<select name="primary_category_id" required><option value="">Alege</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($product['primary_category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label></section>
</aside>
</form>