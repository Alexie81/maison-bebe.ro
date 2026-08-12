<?php
$activeFilters=array_filter($filters,static fn($value)=>$value!==''&&$value!==0);
$exportQuery=http_build_query($activeFilters);
$canExport=MaisonBebe\Core\Auth::hasPermission('accounting_stock.export');
$canSettings=MaisonBebe\Core\Auth::hasPermission('accounting_stock.settings');
$canPeriods=MaisonBebe\Core\Auth::hasPermission('accounting_periods.manage');
$searchProducts=[];
foreach($items as $searchItem){
  $searchId=(int)$searchItem['variant_id'];
  if(isset($searchProducts[$searchId])){$searchProducts[$searchId]['search_quantity']+=(float)$searchItem['current_quantity'];continue;}
  $searchItem['search_quantity']=(float)$searchItem['current_quantity'];
  $searchProducts[$searchId]=$searchItem;
}
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact">
  <div><p class="eyebrow">REGISTRU CONTABIL</p><h1>Stocuri Conta</h1><p>Soldul fizic și valoarea contabilă, fără impact asupra disponibilității online.</p></div>
  <div class="button-row">
    <button class="admin-button secondary" type="button" data-open-accounting-modal="stock-filters">Filtre<?= $activeFilters?' · '.count($activeFilters):'' ?></button>
    <?php if($canSettings): ?><button class="admin-button secondary" type="button" data-open-accounting-modal="stock-settings">Setări</button><?php endif; ?>
    <?php if($canPeriods): ?><button class="admin-button secondary" type="button" data-open-accounting-modal="stock-periods">Perioade</button><?php endif; ?>
    <?php if($canExport): ?><a class="admin-button" href="<?= e(url('/admin/stocuri-conta/export'.($exportQuery?'?'.$exportQuery:''))) ?>">Exportă XLSX</a><?php endif; ?>
  </div>
</section>

<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="accounting-summary-strip accounting-summary-strip-stock" aria-label="Rezumat stoc contabil">
  <?php foreach([
    ['Valoare contabilă',number_format((float)$stats['value'],2,',','.').' lei','value'],
    ['Sold pozitiv',$stats['positive'],'positive'],
    ['Sold zero',$stats['zero'],'zero'],
    ['Sold negativ',$stats['negative'],'negative'],
    ['Ieșiri neacoperite',$stats['uncovered'],'uncovered'],
    ['Documente ulterioare',$stats['late'],'late'],
    ['Mișcări luna aceasta',$stats['month_movements'],'movements'],
    ['Ultimul NIR',$stats['last_nir'],'nir']
  ] as [$label,$value,$tone]): ?>
    <article class="accounting-summary-item <?= e($tone) ?>"><strong><?= e((string)$value) ?></strong><span><?= e($label) ?></span></article>
  <?php endforeach; ?>
</section>

<section class="accounting-smart-search" data-accounting-product-search>
  <div class="accounting-smart-search-box"><span aria-hidden="true">⌕</span><input type="search" autocomplete="off" placeholder="Caută rapid după produs, variantă sau SKU…" aria-label="Caută în Stocuri Conta" data-accounting-product-search-input><kbd>/</kbd></div>
  <div class="accounting-smart-search-popup" data-accounting-product-search-popup hidden>
    <header><strong>Produse și cutii în Stocuri Conta</strong><small><b data-accounting-product-search-count>0</b> rezultate</small></header>
    <div class="accounting-smart-search-results">
      <?php foreach($searchProducts as $searchProduct): $searchText=implode(' ',array_filter([$searchProduct['product_name'],$searchProduct['variant_name'],$searchProduct['sku'],!empty($searchProduct['is_gift_box'])?'cutie cadou':'produs'])); ?>
      <a href="<?= e(url('/admin/stocuri-conta/fisa/'.$searchProduct['variant_id'].'?warehouse='.$searchProduct['warehouse_id'])) ?>" data-accounting-product-search-result data-search="<?= e($searchText) ?>" data-name="<?= e($searchProduct['product_name']) ?>" data-sku="<?= e($searchProduct['sku']) ?>" hidden>
        <img src="<?= e(url($searchProduct['image_path'])) ?>" alt=""><span><?php if(!empty($searchProduct['is_gift_box'])): ?><em class="accounting-kind-badge gift-box">Cutie cadou</em><?php else: ?><em class="accounting-kind-badge">Produs</em><?php endif; ?><strong><?= e($searchProduct['product_name']) ?></strong><small><?= e($searchProduct['variant_name']) ?> · SKU <?= e($searchProduct['sku']) ?></small></span><b><?= number_format((float)$searchProduct['search_quantity'],0,',','.') ?> buc <i>→</i></b>
      </a>
      <?php endforeach; ?>
      <p data-accounting-product-search-empty hidden>Nu am găsit niciun produs. Încearcă numele, varianta sau codul SKU.</p>
    </div>
    <footer><span><kbd>↑</kbd><kbd>↓</kbd> navigare</span><span><kbd>Enter</kbd> deschide</span><span><kbd>Esc</kbd> închide</span></footer>
  </div>
</section>

<div class="accounting-context-note"><strong>Stoc Conta ≠ stoc online</strong><span>Valorile de mai jos provin exclusiv din documente confirmate.</span></div>

<section class="admin-panel accounting-table-panel accounting-register-panel">
  <div class="panel-head accounting-panel-head-compact"><div><h2>Stoc curent</h2><p class="help"><?= count($items) ?> poziții găsite. Soldurile negative rămân vizibile.</p></div><?php if($activeFilters): ?><a href="<?= e(url('/admin/stocuri-conta')) ?>">Șterge filtrele</a><?php endif; ?></div>
  <div class="admin-table-wrap">
    <table class="admin-table accounting-compact-table accounting-stock-table">
      <thead><tr><th>Produs</th><th>Localizare</th><th>Online</th><th>Stoc Conta</th><th>Cost și valoare</th><th>Ultima mișcare</th><th></th></tr></thead>
      <tbody>
      <?php foreach($items as $item): ?>
        <tr class="<?= (float)$item['current_quantity']<0?'accounting-negative-row':'' ?>">
          <td><div class="accounting-product-label"><?php if(!empty($item['is_gift_box'])): ?><span class="accounting-kind-badge gift-box">Cutie cadou</span><?php else: ?><span class="accounting-kind-badge">Produs</span><?php endif; ?><strong><?= e($item['product_name']) ?></strong></div><small><?= e($item['variant_name']) ?> · SKU <?= e($item['sku']) ?></small></td>
          <td><?= e($item['warehouse_name']) ?><small><?= e($item['category_name']) ?></small></td>
          <td><span class="status-pill <?= !$item['track_inventory']?'info':'' ?>"><?= $item['track_inventory']?'Limitat':'Nelimitat' ?></span></td>
          <td class="accounting-quantity-cell"><strong><?= number_format((float)$item['current_quantity'],0,',','.') ?> buc</strong><small>Minim istoric: <?= number_format((float)$item['minimum_historical_quantity'],0,',','.') ?> buc</small><?php if($item['has_historical_negative_balance']): ?><small class="danger-text">A avut sold negativ</small><?php endif; ?></td>
          <td><strong><?= number_format((float)$item['current_accounting_value'],2,',','.') ?> lei</strong><small>Cost unitar <?= number_format((float)$item['calculated_unit_cost'],2,',','.') ?> lei</small></td>
          <td><?= $item['last_movement_date']?date('d.m.Y',strtotime($item['last_movement_date'])):'—' ?></td>
          <td><a class="accounting-row-link" href="<?= e(url('/admin/stocuri-conta/fisa/'.$item['variant_id'].'?warehouse='.$item['warehouse_id'])) ?>">Deschide fișa <span>→</span></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$items): ?><tr><td colspan="7"><div class="admin-empty"><strong>Nu există poziții pentru filtrele selectate.</strong></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<details class="admin-panel accounting-collapsible accounting-risk-panel" <?= $uncovered?'open':'' ?>>
  <summary>
    <span><i><?= count($uncovered) ?></i><span><strong>Ieșiri neacoperite la data vânzării</strong><small>Istoricul negativ rămâne vizibil chiar dacă o recepție ulterioară acoperă soldul curent.</small></span></span>
    <b>Vezi detalii</b>
  </summary>
  <div class="accounting-collapsible-body">
    <div class="accounting-inline-actions"><?php if($canExport): ?><a class="admin-button secondary small" href="<?= e(url('/admin/stocuri-conta/export?type=uncovered')) ?>">Exportă XLSX</a><?php endif; ?></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Data</th><th>Factură</th><th>Produs</th><th>Ieșire</th><th>Sold după</th><th>Neacoperit</th><th>Status</th></tr></thead><tbody><?php foreach($uncovered as $row): ?><tr><td><?= date('d.m.Y',strtotime($row['effective_date'])) ?></td><td><?= e($row['source_document_number']) ?></td><td><a href="<?= e(url('/admin/stocuri-conta/fisa/'.$row['product_variant_id'])) ?>"><?= e($row['product_name_snapshot']) ?></a><small>SKU <?= e($row['sku_snapshot']) ?></small></td><td><?= number_format((float)$row['quantity_out'],0,',','.') ?> buc</td><td><?= number_format((float)$row['balance_quantity_after'],0,',','.') ?> buc</td><td><strong><?= number_format((float)$row['uncovered_quantity'],0,',','.') ?> buc</strong></td><td><?= e($row['status_label']) ?></td></tr><?php endforeach; ?><?php if(!$uncovered): ?><tr><td colspan="7">Nu există ieșiri neacoperite.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</details>

<div class="accounting-modal" data-accounting-modal="stock-filters" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide filtrele"></button>
  <section class="accounting-modal-card accounting-modal-card-medium" role="dialog" aria-modal="true" aria-labelledby="stock-filter-title">
    <header><div><p class="eyebrow">FILTRARE STOC</p><h2 id="stock-filter-title">Restrânge rezultatele</h2><p>Caută după produs și folosește doar filtrele relevante.</p></div><button type="button" data-close-accounting-modal aria-label="Închide">×</button></header>
    <form method="get" action="<?= e(url('/admin/stocuri-conta')) ?>">
      <div class="accounting-modal-scroll">
        <div class="accounting-filter-grid accounting-filter-grid-modal">
          <label class="span-2">Produs, SKU, NIR sau furnizor<input name="q" value="<?= e($filters['q']) ?>" autofocus placeholder="Ex. NIR-MB-2026-000001"></label>
          <label>Tip articol<select name="type"><option value="">Toate</option><option value="product" <?= $filters['type']==='product'?'selected':'' ?>>Produse</option><option value="gift_box" <?= $filters['type']==='gift_box'?'selected':'' ?>>Cutii cadou</option></select></label>
          <label>Categorie<select name="category"><option value="">Toate</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $filters['category']==$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
          <label>Gestiune<select name="warehouse"><option value="">Toate</option><?php foreach($warehouses as $warehouse): ?><option value="<?= (int)$warehouse['id'] ?>" <?= $filters['warehouse']==$warehouse['id']?'selected':'' ?>><?= e($warehouse['name']) ?></option><?php endforeach; ?></select></label>
          <label>Sold<select name="balance"><option value="">Oricare</option><option value="positive" <?= $filters['balance']==='positive'?'selected':'' ?>>Pozitiv</option><option value="zero" <?= $filters['balance']==='zero'?'selected':'' ?>>Zero</option><option value="negative" <?= $filters['balance']==='negative'?'selected':'' ?>>Negativ</option></select></label>
          <label>Disponibilitate online<select name="unlimited"><option value="">Oricare</option><option value="1" <?= $filters['unlimited']==='1'?'selected':'' ?>>Stoc nelimitat</option></select></label>
          <label>Status catalog<select name="status"><option value="">Oricare</option><option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Activ</option><option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactiv</option></select></label>
        </div>
      </div>
      <footer><a class="admin-button secondary" href="<?= e(url('/admin/stocuri-conta')) ?>">Resetează</a><button class="admin-button" type="submit">Aplică filtrele</button></footer>
    </form>
  </section>
</div>

<?php if($canSettings): ?>
<div class="accounting-modal" data-accounting-modal="stock-settings" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide setările"></button>
  <section class="accounting-modal-card accounting-modal-card-small" role="dialog" aria-modal="true" aria-labelledby="stock-settings-title">
    <header><div><p class="eyebrow">CONFIGURARE</p><h2 id="stock-settings-title">Setări evaluare</h2><p>Metoda se blochează automat după prima mișcare.</p></div><button type="button" data-close-accounting-modal aria-label="Închide">×</button></header>
    <form method="post" action="<?= e(url('/admin/stocuri-conta/setari')) ?>"><?= csrf_field() ?>
      <div class="accounting-modal-scroll"><div class="accounting-modal-form"><label>Metodă<select name="valuation_method"><option value="WeightedAverage" <?= $settings['valuation_method']==='WeightedAverage'?'selected':'' ?>>Cost mediu ponderat (CMP)</option><option value="FIFO" <?= $settings['valuation_method']==='FIFO'?'selected':'' ?>>FIFO</option></select></label><label>Serie NIR<input name="nir_series" value="<?= e($settings['nir_series']) ?>" required></label><label>Ani retenție<input type="number" min="10" name="retention_years" value="<?= (int)$settings['retention_years'] ?>"></label></div></div>
      <footer><button class="admin-button secondary" type="button" data-close-accounting-modal>Anulează</button><button class="admin-button" type="submit">Salvează setările</button></footer>
    </form>
  </section>
</div>
<?php endif; ?>

<?php if($canPeriods): ?>
<div class="accounting-modal" data-accounting-modal="stock-periods" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide perioadele"></button>
  <section class="accounting-modal-card accounting-modal-card-medium" role="dialog" aria-modal="true" aria-labelledby="stock-period-title">
    <header><div><p class="eyebrow">CONTROL CONTABIL</p><h2 id="stock-period-title">Perioade contabile</h2><p>Blochează sau redeschide explicit un interval.</p></div><button type="button" data-close-accounting-modal aria-label="Închide">×</button></header>
    <form method="post" action="<?= e(url('/admin/stocuri-conta/perioade')) ?>"><?= csrf_field() ?>
      <div class="accounting-modal-scroll">
        <div class="accounting-filter-grid accounting-filter-grid-modal"><label>De la<input type="date" name="start_date" required></label><label>Până la<input type="date" name="end_date" required></label><label class="check-label span-2"><input type="checkbox" name="is_locked" value="1" checked> Marchează perioada ca blocată</label><label class="span-2">Motiv<input name="reason" required></label></div>
        <div class="accounting-period-list accounting-period-list-modal"><?php foreach($periods as $period): ?><span><strong><?= date('d.m.Y',strtotime($period['start_date'])) ?> – <?= date('d.m.Y',strtotime($period['end_date'])) ?></strong><em><?= $period['is_locked']?'Blocată':'Deschisă' ?></em><small><?= e($period['unlock_reason']?:'Fără observații') ?></small></span><?php endforeach; ?><?php if(!$periods): ?><p class="help">Nu există perioade definite.</p><?php endif; ?></div>
      </div>
      <footer><button class="admin-button secondary" type="button" data-close-accounting-modal>Anulează</button><button class="admin-button" type="submit">Salvează perioada</button></footer>
    </form>
  </section>
</div>
<?php endif; ?>
