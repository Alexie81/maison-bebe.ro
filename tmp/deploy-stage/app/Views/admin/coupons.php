<?php
$renderCouponFields=function(array $item,array $categories,array $collections,array $products,array $selectedCategories=[],array $selectedCollections=[],array $selectedProducts=[],string $mode='include'):void{
    $displayValue=($item['discount_type']??'percent')==='fixed'?number_format((int)($item['discount_value']??0)/100,2,'.',''):(int)($item['discount_value']??0);
    $pickerId='coupon-picker-'.(!empty($item['id'])?(int)$item['id']:'new');
?>
<?php if(!empty($item['id'])): ?><input type="hidden" name="coupon_id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
<div class="admin-form-grid coupon-basic-fields">
    <label>Cod<input name="code" required value="<?= e($item['code']??'') ?>" placeholder="EX: BUNVENIT10"></label>
    <label>Tip<select name="discount_type"><option value="percent" <?= ($item['discount_type']??'percent')==='percent'?'selected':'' ?>>Procent</option><option value="fixed" <?= ($item['discount_type']??'')==='fixed'?'selected':'' ?>>Sumă fixă</option></select></label>
    <label>Valoare<input type="number" step="0.01" name="discount_value" min="0.01" required value="<?= e($displayValue?:'') ?>"></label>
    <label>Minim comandă (lei)<input type="number" step="0.01" name="minimum_order" min="0" value="<?= number_format((int)($item['minimum_order_minor']??0)/100,2,'.','') ?>"></label>
    <label>Reducere maximă (lei)<input type="number" step="0.01" name="maximum_discount" min="0" value="<?= ($item['maximum_discount_minor']??null)!==null?number_format((int)$item['maximum_discount_minor']/100,2,'.',''):'' ?>"></label>
    <label>Utilizări totale maxime<input type="number" name="max_uses" min="0" value="<?= e($item['max_uses']??'') ?>"></label>
    <label>Utilizări / client<input type="number" name="max_uses_per_user" min="0" value="<?= e($item['max_uses_per_user']??'') ?>"></label>
    <label>Începe la<input type="datetime-local" name="starts_at" value="<?= !empty($item['starts_at'])?e(date('Y-m-d\TH:i',strtotime($item['starts_at']))):'' ?>"></label>
    <label>Expiră la<input type="datetime-local" name="ends_at" value="<?= !empty($item['ends_at'])?e(date('Y-m-d\TH:i',strtotime($item['ends_at']))):'' ?>"></label>
    <label>Tip eligibilitate<select name="eligibility_mode"><option value="include" <?= $mode==='include'?'selected':'' ?>>Se aplică selecției</option><option value="exclude" <?= $mode==='exclude'?'selected':'' ?>>Exclude selecția</option></select></label>
</div>
<section class="coupon-rule-builder" data-coupon-builder>
    <div class="coupon-rule-head"><div><span class="coupon-rule-icon" aria-hidden="true">◇</span><div><strong>Produse, categorii și colecții eligibile</strong><small>Alege întregul catalog, grupuri complete sau produse individuale.</small></div></div><button type="button" class="admin-button secondary" data-coupon-picker-open aria-controls="<?= e($pickerId) ?>">Configurează selecția</button></div>
    <div class="coupon-rule-summary"><div><small>Categorii</small><div data-coupon-category-summary><span>Nicio categorie selectată</span></div></div><div><small>Colecții</small><div data-coupon-collection-summary><span>Nicio colecție selectată</span></div></div><div><small>Produse</small><div data-coupon-product-summary><span>Niciun produs selectat</span></div></div></div>
    <p class="coupon-rule-note" data-coupon-scope-note><span>i</span> Fără selecție, cuponul se aplică întregului catalog.</p>
    <div id="<?= e($pickerId) ?>" class="coupon-picker-modal" data-coupon-picker hidden>
        <button class="coupon-picker-backdrop" type="button" data-coupon-picker-close aria-label="Închide selectorul"></button>
        <section class="coupon-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="<?= e($pickerId) ?>-title">
            <header><div><p class="eyebrow">ELIGIBILITATE CUPON</p><h2 id="<?= e($pickerId) ?>-title">Alege categoriile, colecțiile și produsele</h2><p>Poți include un grup complet sau numai anumite produse din el.</p></div><button class="coupon-picker-close" type="button" data-coupon-picker-close aria-label="Închide">×</button></header>
            <div class="coupon-picker-toolbar"><label><span class="sr-only">Caută produse</span><input type="search" placeholder="Caută un produs…" data-coupon-product-search></label><button type="button" data-coupon-all-catalog>Tot catalogul</button><button type="button" data-coupon-select-visible>Selectează afișate</button><button type="button" data-coupon-clear-visible>Curăță afișate</button></div>
            <div class="coupon-picker-layout">
                <aside class="coupon-category-panel"><div class="coupon-filter-groups"><section><strong>Categorii</strong><div class="coupon-category-tabs"><button type="button" class="is-active" data-coupon-filter-kind="all" data-coupon-filter-id="">Toate produsele <span><?= count($products) ?></span></button><?php foreach($categories as $category): $categoryCount=count(array_filter($products,static fn(array $product):bool=>in_array((int)$category['id'],array_map('intval',array_filter(explode(',',(string)($product['category_ids']??'')))),true))); ?><button type="button" data-coupon-filter-kind="category" data-coupon-filter-id="<?= (int)$category['id'] ?>"><?= e($category['name']) ?><span><?= $categoryCount ?></span></button><?php endforeach; ?></div></section><section><strong>Colecții</strong><div class="coupon-category-tabs"><?php foreach($collections as $collection): $collectionCount=count(array_filter($products,static fn(array $product):bool=>in_array((int)$collection['id'],array_map('intval',array_filter(explode(',',(string)($product['collection_ids']??'')))),true))); ?><button type="button" data-coupon-filter-kind="collection" data-coupon-filter-id="<?= (int)$collection['id'] ?>"><?= e($collection['name']) ?><span><?= $collectionCount ?></span></button><?php endforeach; ?></div></section></div></aside>
                <div class="coupon-picker-content">
                    <div class="coupon-category-actions" data-coupon-category-actions><?php foreach($categories as $category): ?><article data-coupon-scope-kind="category" data-coupon-scope-id="<?= (int)$category['id'] ?>" hidden><label><input type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>" data-coupon-category <?= in_array((int)$category['id'],$selectedCategories,true)?'checked':'' ?>><span><strong>Toată categoria „<?= e($category['name']) ?>”</strong><small>Include automat produsele actuale și viitoare din această categorie.</small></span></label></article><?php endforeach; ?><?php foreach($collections as $collection): ?><article data-coupon-scope-kind="collection" data-coupon-scope-id="<?= (int)$collection['id'] ?>" hidden><label><input type="checkbox" name="collection_ids[]" value="<?= (int)$collection['id'] ?>" data-coupon-collection <?= in_array((int)$collection['id'],$selectedCollections,true)?'checked':'' ?>><span><strong>Toată colecția „<?= e($collection['name']) ?>”</strong><small>Include automat produsele actuale și viitoare din această colecție.</small></span></label></article><?php endforeach; ?></div>
                    <div class="coupon-product-grid" data-coupon-products><?php foreach($products as $product): $categoryIds=array_values(array_filter(array_map('intval',explode(',',(string)($product['category_ids']??''))))); $collectionIds=array_values(array_filter(array_map('intval',explode(',',(string)($product['collection_ids']??''))))); ?><label class="coupon-product-choice catalog-product-visual" data-coupon-product-card data-product-name="<?= e(mb_strtolower($product['name'])) ?>" data-product-categories="<?= e(implode(',',$categoryIds)) ?>" data-product-collections="<?= e(implode(',',$collectionIds)) ?>"><input type="checkbox" name="product_ids[]" value="<?= (int)$product['id'] ?>" data-coupon-product <?= in_array((int)$product['id'],$selectedProducts,true)?'checked':'' ?>><span class="coupon-product-check">✓</span><img src="<?= e(url($product['image_path']??'/assets/images/packaging-reference.png')) ?>" alt="<?= e($product['name']) ?>" width="240" height="240" loading="lazy"><strong><?= e($product['name']) ?></strong><small><?= $categoryIds?'Produs categorizat':'Fără categorie' ?></small></label><?php endforeach; ?></div>
                    <div class="coupon-products-empty" data-coupon-products-empty hidden>Nu există produse pentru filtrul ales.</div>
                </div>
            </div>
            <footer><p><strong data-coupon-selection-count>0</strong> selecții configurate</p><button class="admin-button" type="button" data-coupon-picker-apply>Aplică selecția</button></footer>
        </section>
    </div>
</section>
<label class="toggle-switch coupon-active-switch"><input type="checkbox" name="is_active" value="1" <?= !isset($item['is_active'])||$item['is_active']?'checked':'' ?>><span class="switch-track"><i></i></span><b>Cupon activ și vizibil clienților</b></label>
<?php }; ?>
<section class="admin-page-head coupon-page-head"><div><p class="eyebrow">MARKETING</p><h1>Cupoane și promoții</h1><p>Creează, verifică și activează reducerile dintr-un singur loc.</p></div><button class="admin-button coupon-add-button" type="button" data-coupon-create-open><span>+</span> Adaugă cupon</button></section>
<section class="coupon-compact-list">
<?php foreach($items as $item): $isFixed=($item['discount_type']??'percent')==='fixed'; $valueLabel=$isFixed?money((int)$item['discount_value']):(int)$item['discount_value'].'%'; ?>
<details class="admin-panel coupon-compact-card">
    <summary>
        <span class="coupon-compact-status <?= $item['is_active']?'is-active':'' ?>" aria-hidden="true"></span>
        <span class="coupon-compact-main"><small><?= $item['is_active']?'ACTIV':'INACTIV' ?></small><strong><?= e($item['code']) ?></strong></span>
        <span class="coupon-compact-value"><small>REDUCERE</small><strong><?= e($valueLabel) ?></strong></span>
        <span class="coupon-compact-meta"><small>UTILIZĂRI</small><strong><?= (int)$item['used_total'] ?><?= $item['max_uses']?' / '.(int)$item['max_uses']:'' ?></strong></span>
        <span class="coupon-compact-meta coupon-date"><small>EXPIRĂ</small><strong><?= $item['ends_at']?date('d.m.Y',strtotime($item['ends_at'])):'Fără termen' ?></strong></span>
        <span class="coupon-expand-label"><b>Detalii</b><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg></span>
    </summary>
    <form class="coupon-expanded-form" method="post" action="<?= e(url('/admin/cupoane')) ?>" data-coupon-ajax-form><?= csrf_field() ?><?php $renderCouponFields($item,$categories,$collections,$products,$couponCategories[(int)$item['id']]??[],$couponCollections[(int)$item['id']]??[],$couponProducts[(int)$item['id']]??[],$couponModes[(int)$item['id']]??'include'); ?><button class="admin-button coupon-save" type="submit">Salvează modificările</button></form>
</details>
<?php endforeach; ?>
<?php if(!$items): ?><div class="admin-panel admin-empty">Nu există cupoane. Apasă „Adaugă cupon” pentru prima promoție.</div><?php endif; ?>
</section>
<div class="coupon-create-modal" data-coupon-create-modal hidden>
    <button class="coupon-create-backdrop" type="button" data-coupon-create-close aria-label="Închide"></button>
    <section class="coupon-create-dialog" role="dialog" aria-modal="true" aria-labelledby="coupon-create-title">
        <header><div><p class="eyebrow">PROMOȚIE NOUĂ</p><h2 id="coupon-create-title">Adaugă un cupon</h2><p>Completează reducerea, perioada și produsele eligibile.</p></div><button type="button" class="coupon-picker-close" data-coupon-create-close aria-label="Închide">×</button></header>
        <form method="post" action="<?= e(url('/admin/cupoane')) ?>" data-coupon-ajax-form><?= csrf_field() ?><div class="coupon-create-scroll"><?php $renderCouponFields(['discount_type'=>'percent','minimum_order_minor'=>0,'maximum_discount_minor'=>null,'is_active'=>1],$categories,$collections,$products); ?></div><footer><button class="admin-button secondary" type="button" data-coupon-create-close>Anulează</button><button class="admin-button coupon-save" type="submit">Creează cuponul</button></footer></form>
    </section>
</div>
