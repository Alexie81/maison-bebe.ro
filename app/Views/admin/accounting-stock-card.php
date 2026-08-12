<?php
$movementLabels=['NIR_RECEIPT'=>'Intrare NIR','NIR_REVERSAL'=>'Inversare NIR','SALE'=>'Vânzare','SALE_REVERSAL'=>'Retur client','SUPPLIER_RETURN'=>'Retur furnizor','ADJUSTMENT_IN'=>'Ajustare +','ADJUSTMENT_OUT'=>'Ajustare −'];
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact">
  <div><p class="eyebrow">FIȘĂ DE STOC CONTABILĂ</p><h1><?= e($item['sku']) ?></h1><p><?php if(!empty($item['is_gift_box'])): ?><span class="accounting-kind-badge gift-box">Cutie cadou</span><?php endif; ?> <?= e($item['product_name'].' · '.$item['variant_name']) ?></p></div>
  <div class="button-row"><a class="admin-button secondary" href="<?= e(url('/admin/stocuri-conta')) ?>">Înapoi</a><?php if(MaisonBebe\Core\Auth::hasPermission('accounting_stock.export')): ?><a class="admin-button secondary" target="_blank" href="<?= e(url('/admin/stocuri-conta/fisa/'.$item['variant_id'].'/pdf?warehouse='.$item['warehouse_id'])) ?>">PDF</a><a class="admin-button" href="<?= e(url('/admin/stocuri-conta/fisa/'.$item['variant_id'].'/xlsx?warehouse='.$item['warehouse_id'])) ?>">Exportă XLSX</a><?php endif; ?></div>
</section>
<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="accounting-summary-strip accounting-stock-card-summary">
  <article class="accounting-summary-item <?= (float)$item['current_quantity']<0?'negative':'positive' ?>"><strong><?= number_format((float)$item['current_quantity'],0,',','.') ?></strong><span>Sold curent · buc</span></article>
  <article class="accounting-summary-item"><strong><?= number_format((float)$item['calculated_unit_cost'],2,',','.') ?></strong><span>Cost unitar · lei</span></article>
  <article class="accounting-summary-item value"><strong><?= number_format((float)$item['current_accounting_value'],2,',','.') ?></strong><span>Valoare · lei</span></article>
  <article class="accounting-summary-item <?= (float)$item['minimum_historical_quantity']<0?'negative':'' ?>"><strong><?= number_format((float)$item['minimum_historical_quantity'],0,',','.') ?></strong><span>Minim istoric · buc</span></article>
  <article class="accounting-summary-item <?= !$item['track_inventory']?'ready':'' ?>"><strong><?= $item['track_inventory']?'L':'∞' ?></strong><span>Online · <?= $item['track_inventory']?'limitat':'nelimitat' ?></span></article>
</section>

<form class="admin-panel accounting-as-of accounting-as-of-compact" method="get">
  <div class="accounting-as-of-fields"><label>Gestiune<select name="warehouse"><?php foreach($warehouses as $warehouse): ?><option value="<?= (int)$warehouse['id'] ?>" <?= $item['warehouse_id']==$warehouse['id']?'selected':'' ?>><?= e($warehouse['name']) ?></option><?php endforeach; ?></select></label><label>Sold la data de<input type="date" name="as_of" value="<?= e($asOf) ?>"></label><button class="admin-button" type="submit">Calculează</button></div>
  <div class="accounting-as-of-result"><small>SOLD LA <?= date('d.m.Y',strtotime($asOf)) ?></small><strong><?= number_format((float)$asOfBalance['quantity'],0,',','.') ?> buc</strong><span><?= number_format((float)$asOfBalance['value'],2,',','.') ?> lei</span></div>
</form>

<section class="admin-panel accounting-table-panel accounting-register-panel">
  <div class="panel-head accounting-panel-head-compact"><div><h2>Cronologia mișcărilor</h2><p class="help">Toate intrările și ieșirile, în ordinea lor efectivă.</p></div><span class="status-pill"><?= count($movements) ?> mișcări</span></div>
  <div class="admin-table-wrap">
    <table class="admin-table accounting-compact-table accounting-movement-table">
      <thead><tr><th>Dată și document</th><th>Tip</th><th>Partener</th><th>Intrare / ieșire</th><th>Cost și valoare</th><th>Sold după</th><th></th></tr></thead>
      <tbody>
      <?php foreach($movements as $movement): $sourceUrl=$movement['source_document_type']==='NIR'?'/admin/nir-uri/'.$movement['source_document_id']:(str_contains((string)$movement['source_document_type'],'SALES_INVOICE')?'/admin/facturi/'.$movement['source_document_id']:null); ?>
        <tr class="<?= (float)$movement['balance_quantity_after']<0?'accounting-negative-row':'' ?>">
          <td><?= date('d.m.Y',strtotime($movement['effective_date'])) ?><?php if($sourceUrl): ?><a href="<?= e(url($sourceUrl)) ?>"><?= e(trim(($movement['source_document_series']??'').' '.($movement['source_document_number']??''))) ?></a><?php else: ?><small><?= e(trim(($movement['source_document_series']??'').' '.($movement['source_document_number']??''))) ?></small><?php endif; ?></td>
          <td><span class="status-pill"><?= e($movementLabels[$movement['movement_type']]??$movement['movement_type']) ?></span></td>
          <td><?= e($movement['counterparty_snapshot']?:'—') ?></td>
          <td class="accounting-flow-cell"><?php if((float)$movement['quantity_in']>0): ?><strong class="is-in">+<?= number_format((float)$movement['quantity_in'],0,',','.') ?> buc</strong><?php endif; ?><?php if((float)$movement['quantity_out']>0): ?><strong class="is-out">−<?= number_format((float)$movement['quantity_out'],0,',','.') ?> buc</strong><?php endif; ?></td>
          <td><?= number_format((float)$movement['calculated_unit_cost'],2,',','.') ?> lei/buc<small>Mișcare <?= number_format((float)$movement['calculated_movement_value'],2,',','.') ?> lei</small></td>
          <td class="accounting-quantity-cell"><strong><?= number_format((float)$movement['balance_quantity_after'],0,',','.') ?> buc</strong><small><?= number_format((float)$movement['balance_value_after'],2,',','.') ?> lei</small></td>
          <td class="accounting-menu-cell"><details class="accounting-action-menu"><summary aria-label="Detalii mișcare">•••</summary><div class="accounting-movement-popover"><span><small>Postat la</small><strong><?= date('d.m.Y H:i',strtotime($movement['posted_at'])) ?></strong></span><span><small>Creat de</small><strong><?= e(trim((string)$movement['creator_name'])?:'Sistem') ?></strong></span><span><small>Tip sursă</small><strong><?= e($movement['source_document_type']) ?></strong></span></div></details></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$movements): ?><tr><td colspan="7"><div class="admin-empty"><strong>Nu există mișcări pentru acest SKU și această gestiune.</strong></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
