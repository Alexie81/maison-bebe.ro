<?php $templates=$templates??[]; $components=$components??[]; $configuratorEnabled=$configuratorEnabled??true; $editConfiguration=$editConfiguration??null; $editComponentIds=[]; if(is_array($editConfiguration)){ foreach((array)($editConfiguration['components']??[]) as $editComponent){ $editComponentIds[(int)($editComponent['variant_id']??0)]=true; } } ?>
<section class="gift-hero-premium">
    <div class="gift-hero-copy">
        <p class="eyebrow">GIFT ATELIER · MAISON BÉBÉ</p>
        <h1><span>Un dar creat</span><em>pentru începuturi prețioase.</em></h1>
        <p class="gift-hero-lead">Alege cutia, adaugă produsele preferate și lasă-ne mesajul tău. Noi pregătim fiecare detaliu cu delicatețe.</p>
        <?php if($configuratorEnabled): ?><a class="button gift-hero-cta" href="#configurator">Creează un Gift Box</a><?php endif; ?>
        <div class="gift-hero-notes"><span><b>01</b> Ambalat manual</span><span><b>02</b> Mesaj personal</span></div>
    </div>
    <div class="gift-hero-visual">
        <img src="<?= e(asset('images/packaging-reference.png')) ?>" alt="Gift Box premium Maison Bébé" width="1100" height="900">
        <div class="gift-hero-seal"><span>Pregătit</span><strong>cu grijă</strong><small>în atelierul nostru</small></div>
        <p class="gift-hero-caption">O experiență de dăruit, de la primul detaliu până la panglica finală.</p>
    </div>
</section>
<?php if (!empty($products)): ?>
<section class="shell section-space"><div class="section-heading centered"><p class="eyebrow">Alese de noi</p><h2>Gift Box-uri pregătite în Atelier</h2></div><div class="product-grid"><?php foreach ($products as $product) { require BASE_PATH . '/app/Views/partials/product-card.php'; } ?></div></section>
<?php endif; ?>
<?php if($configuratorEnabled): ?>
<section id="configurator" class="configurator gift-builder-section section-space"><div class="shell">
    <div class="gift-builder-intro"><p class="eyebrow">PERSONALIZEAZĂ</p><h2>Compune un dar unic, pas cu pas.</h2><p>Alege cutia încărcată din admin, apoi selectează produsele care intră în ea. Numărul de produse permis este controlat separat pentru fiecare cutie.</p></div>
    <?php if(!$templates): ?>
        <div class="empty-state"><h2>Nu există cutii active momentan.</h2><p>Adaugă în admin o cutie din secțiunea <strong>Gift Box / Cutii</strong>, cu poză, preț, stoc și numărul de produse acceptate.</p></div>
    <?php else: ?>
    <form class="gift-builder gift-builder-modern" data-gift-configurator><?php if(!empty($editConfiguration['group'])): ?><input type="hidden" name="edit_group" value="<?= e((string)$editConfiguration['group']) ?>"><?php endif; ?>
        <section class="gift-builder-step gift-box-choice">
            <div class="gift-step-head"><span>01</span><div><p class="eyebrow">CUTIA</p><h3>Alege cutia</h3><p>Fiecare cutie are propriul preț, stoc și limită de produse.</p></div></div>
            <?php $selectedTemplateId=(int)($editConfiguration['template_id']??($templates[0]['id']??0)); $firstTemplate=$templates[0]??null; foreach($templates as $candidate){ if((int)($candidate['id']??0)===$selectedTemplateId){ $firstTemplate=$candidate; break; } } ?>
            <div class="gift-box-compact">
                <img data-gift-box-preview-image src="<?= e(url($firstTemplate['image_path']??'')) ?>" alt="<?= e($firstTemplate['name']??'Cutie Gift Box') ?>" width="170" height="130">
                <div><small>CUTIA SELECTATĂ</small><strong data-gift-box-preview-name><?= e($firstTemplate['name']??'Alege cutia') ?></strong><p data-gift-box-preview-meta><?php if($firstTemplate): ?><?= money((int)($firstTemplate['price_minor']??$firstTemplate['base_price_minor']??0)) ?> · <?= (int)($firstTemplate['min_components']??1) ?>–<?= (int)($firstTemplate['max_components']??6) ?> produse<?php endif; ?></p></div>
                <button class="button button-outline" type="button" data-gift-boxes-open>Schimbă cutia</button>
            </div>
            <div class="gift-box-picker" data-gift-boxes-dialog hidden>
                <button class="gift-box-picker-backdrop" type="button" data-gift-boxes-close aria-label="Închide catalogul de cutii"></button>
                <div class="gift-box-picker-panel" role="dialog" aria-modal="true" aria-labelledby="gift-box-picker-title">
                    <header><div><p class="eyebrow">COLECȚIA DE CUTII</p><h3 id="gift-box-picker-title">Alege cutia</h3></div><button class="gift-picker-close" type="button" data-gift-boxes-close aria-label="Închide">×</button></header>
                    <div class="gift-box-tools"><label><span>Caută</span><input type="search" data-gift-box-search placeholder="Caută o cutie…" autocomplete="off"></label></div>
                    <div class="gift-box-picker-body">
                        <div class="gift-template-grid gift-box-picker-grid"><?php foreach($templates as $index=>$template): $boxMeta=money((int)($template['price_minor']??$template['base_price_minor']??0)).' · '.(int)($template['stock_qty']??0).' în stoc · '.(int)($template['min_components']??1).'–'.(int)($template['max_components']??6).' produse'; ?><label class="gift-template-card" data-gift-box-card data-box-name="<?= e($template['name']) ?>"><input type="radio" name="template_id" value="<?= (int)$template['id'] ?>" data-min="<?= (int)($template['min_components']??1) ?>" data-max="<?= (int)($template['max_components']??6) ?>" data-price="<?= (int)($template['price_minor']??$template['base_price_minor']??0) ?>" data-name="<?= e($template['name']) ?>" data-image="<?= e(url($template['image_path'])) ?>" data-meta="<?= e($boxMeta) ?>" <?= $index===0?'checked':'' ?>><span class="choice-check" aria-hidden="true">✓</span><img src="<?= e(url($template['image_path'])) ?>" alt="<?= e($template['name']) ?>" width="320" height="260" loading="lazy"><strong><?= e($template['name']) ?></strong><small><?= e($boxMeta) ?></small></label><?php endforeach; ?></div>
                        <div class="gift-products-empty" data-gift-boxes-empty hidden><strong>Nicio cutie găsită.</strong><p>Încearcă alt termen de căutare.</p></div>
                    </div>
                    <footer><div><strong data-gift-box-selected><?= e($firstTemplate['name']??'Nicio cutie selectată') ?></strong><span data-gift-box-results></span></div><button class="button button-outline" type="button" data-gift-box-more>Mai multe</button><button class="button" type="button" data-gift-boxes-close>Gata</button></footer>
                </div>
            </div>
        </section>
        <section class="gift-builder-step gift-products-choice">
            <div class="gift-step-head"><span>02</span><div><p class="eyebrow">CONȚINUTUL</p><h3>Alege produsele</h3><p data-gift-limit>Alege produsele pentru cutie.</p></div></div>
            <?php
            $componentCategories=[];
            $componentCollections=[];
            foreach($components as $component){
                $categoryIds=array_values(array_filter(explode(',',(string)($component['category_ids']??''))));
                $categoryNames=array_values(array_filter(explode('||',(string)($component['category_names']??($component['category_name']??'')))));
                foreach($categoryIds as $index=>$categoryId){ if(isset($categoryNames[$index])) $componentCategories[(string)$categoryId]=$categoryNames[$index]; }
                $collectionIds=array_values(array_filter(explode(',',(string)($component['collection_ids']??''))));
                $collectionNames=array_values(array_filter(explode('||',(string)($component['collection_names']??''))));
                foreach($collectionIds as $index=>$collectionId){ if(isset($collectionNames[$index])) $componentCollections[(string)$collectionId]=$collectionNames[$index]; }
            }
            natcasesort($componentCategories);
            natcasesort($componentCollections);
            ?>
            <div class="gift-products-compact">
                <div><strong data-gift-selected-count>0 produse alese</strong><p>Deschide catalogul și adaugă produsele dorite.</p></div>
                <button class="button" type="button" data-gift-products-open>Alege produsele</button>
            </div>
            <div class="gift-selected-products" data-gift-selected-list><p>Produsele selectate vor apărea aici.</p></div>
            <div class="gift-product-picker" data-gift-products-dialog hidden>
                <button class="gift-product-picker-backdrop" type="button" data-gift-products-close aria-label="Închide catalogul"></button>
                <div class="gift-product-picker-panel" role="dialog" aria-modal="true" aria-labelledby="gift-picker-title">
                    <header><div><p class="eyebrow">CATALOG GIFT BOX</p><h3 id="gift-picker-title">Alege produsele</h3></div><button class="gift-picker-close" type="button" data-gift-products-close aria-label="Închide">×</button></header>
                    <div class="gift-product-tools">
                        <label class="gift-product-search"><span>Caută</span><input type="search" data-gift-search placeholder="Caută un produs…" autocomplete="off"></label>
                        <label><span>Categorie</span><select data-gift-category><option value="">Toate categoriile</option><?php foreach($componentCategories as $categoryId=>$category): ?><option value="<?= e($categoryId) ?>"><?= e($category) ?></option><?php endforeach; ?></select></label>
                        <label><span>Colecție</span><select data-gift-collection><option value="">Toate colecțiile</option><?php foreach($componentCollections as $collectionId=>$collection): ?><option value="<?= e($collectionId) ?>"><?= e($collection) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="gift-product-picker-body">
                        <div class="gift-component-grid gift-product-picker-grid"><?php foreach($components as $component): $category=(string)($component['category_name']??'Selecție'); ?><label class="gift-component-card" data-gift-product-card data-product-name="<?= e($component['name']) ?>" data-product-categories="<?= e((string)($component['category_ids']??'')) ?>" data-product-collections="<?= e((string)($component['collection_ids']??'')) ?>"><input type="checkbox" name="components[]" value="<?= (int)$component['variant_id'] ?>" data-name="<?= e($component['name']) ?>" data-price="<?= (int)$component['price_minor'] ?>" <?= isset($editComponentIds[(int)$component['variant_id']])?'checked':'' ?>><span class="choice-check" aria-hidden="true">✓</span><img src="<?= e(url($component['image_path'])) ?>" alt="<?= e($component['name']) ?>" width="180" height="180" loading="lazy"><em><?= e($category) ?></em><strong><?= e($component['name']) ?></strong><small><?= e($component['variant_label']?:'Standard') ?> · <?= money((int)$component['price_minor']) ?></small></label><?php endforeach; ?></div>
                        <div class="gift-products-empty" data-gift-products-empty hidden><strong>Niciun produs găsit.</strong><p>Încearcă alt termen sau altă categorie.</p></div>
                    </div>
                    <footer><div><strong data-gift-picker-selected>0 produse selectate</strong><span data-gift-results></span></div><button class="button button-outline" type="button" data-gift-more>Mai multe</button><button class="button" type="button" data-gift-products-close>Gata</button></footer>
                </div>
            </div>
        </section>
        <section class="gift-builder-step gift-message-step"><div class="gift-step-head"><span>03</span><div><p class="eyebrow">FELICITAREA</p><h3>Mesajul cadoului</h3><p>Adaugă un nume și un mesaj scurt pentru felicitare.</p></div></div><div class="gift-message-grid"><label>Numele destinatarului<input name="recipient_name" maxlength="190" placeholder="ex. Pentru Sofia" value="<?= e((string)($editConfiguration['recipient_name']??'')) ?>"></label><label>Mesaj pe felicitare<textarea name="gift_message" maxlength="500" placeholder="Un gând pentru începutul unei povești..."><?= e((string)($editConfiguration['gift_message']??'')) ?></textarea></label></div><aside class="gift-builder-summary" aria-live="polite"><small>Selecția ta</small><strong data-gift-total>0,00 lei</strong><p class="gift-selection-progress" data-gift-progress>0 produse selectate</p><p data-gift-summary>Alege produsele pentru cutie.</p><button class="button gift-submit" type="submit"><?= $editConfiguration?'Salvează modificările':'Adaugă Gift Box în coș' ?></button></aside></section>
    </form>
    <?php endif; ?>
</div></section>
<?php endif; ?>
