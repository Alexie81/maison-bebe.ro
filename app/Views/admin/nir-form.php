<?php
$editing=!empty($document);
$mutableLines=$lines;
if(!$editing && $importRows){
  $count=count($importRows['line_name']??[]);$mutableLines=[];
  for($i=0;$i<$count;$i++)$mutableLines[]=['supplier_invoice_line_id'=>'','supplier_product_name'=>$importRows['line_name'][$i]??'','supplier_product_code'=>$importRows['line_supplier_code'][$i]??'','imported_sku'=>$importRows['line_imported_sku'][$i]??'','imported_ean'=>$importRows['line_imported_ean'][$i]??'','product_variant_id'=>'','sku_snapshot'=>'','product_name_snapshot'=>$importRows['line_name'][$i]??'','variant_name_snapshot'=>'','unit_of_measure_snapshot'=>$importRows['line_unit'][$i]??'buc','invoiced_quantity'=>$importRows['line_invoiced_quantity'][$i]??0,'received_quantity'=>$importRows['line_received_quantity'][$i]??0,'accepted_quantity'=>$importRows['line_accepted_quantity'][$i]??0,'damaged_quantity'=>0,'unit_purchase_price_without_vat'=>$importRows['line_unit_price'][$i]??0,'discount_value'=>$importRows['line_discount'][$i]??0,'allocated_acquisition_cost'=>0,'vat_rate'=>$importRows['line_vat_rate'][$i]??0,'value_without_vat'=>$importRows['line_value_without_vat'][$i]??'','vat_value'=>$importRows['line_vat_value'][$i]??'','total_with_vat'=>$importRows['line_total'][$i]??'','line_type'=>'stockable','difference_type'=>'none','difference_reason'=>'','observations'=>'','ignore_reason'=>'','original_imported_data_json'=>$importRows['line_original_json'][$i]??''];
}
$emptyLine=['supplier_invoice_line_id'=>'','supplier_product_name'=>'','supplier_product_code'=>'','imported_sku'=>'','imported_ean'=>'','product_variant_id'=>'','sku_snapshot'=>'','product_name_snapshot'=>'','variant_name_snapshot'=>'','unit_of_measure_snapshot'=>'buc','invoiced_quantity'=>'','received_quantity'=>'','accepted_quantity'=>'','damaged_quantity'=>'0','unit_purchase_price_without_vat'=>'','discount_value'=>'0','allocated_acquisition_cost'=>'0','vat_rate'=>'','value_without_vat'=>'','vat_value'=>'','total_with_vat'=>'','line_type'=>'stockable','difference_type'=>'none','difference_reason'=>'','observations'=>'','ignore_reason'=>'','original_imported_data_json'=>''];
$mutableLines=$mutableLines?:[$emptyLine];
$supplierAddress=json_decode((string)($document['supplier_address_snapshot_json']??''),true)?:[];
$lineTypes=['stockable'=>'Produs stocabil','made_to_order'=>'Produs la comandă urmărit contabil','assembled_bundle'=>'Bundle cumpărat gata asamblat','service'=>'Serviciu','transport'=>'Transport','tax'=>'Taxă','acquisition_cost'=>'Alte costuri de achiziție','ignored'=>'Linie ignorată justificat'];
$differenceTypes=['none'=>'Fără diferențe','shortage'=>'Lipsă cantitativă','surplus'=>'Plus cantitativ','damaged'=>'Produse deteriorate','wrong_product'=>'Produs greșit','other'=>'Altă diferență'];
$productLookup=[];foreach($products as $product)$productLookup[(int)$product['variant_id']]=$product;
$documentCurrency=strtoupper((string)($document['currency']??'RON'));
$savedExchangeRateSource=trim((string)($document['exchange_rate_source']??''));
$savedExchangeRateManual=mb_strtolower($savedExchangeRateSource,'UTF-8')==='manual';
$initialCurrencyNames=['RON'=>'Leu românesc','EUR'=>'Euro','USD'=>'Dolar american','GBP'=>'Liră sterlină','TRY'=>'Liră turcească'];
$initialCurrencyName=$initialCurrencyNames[$documentCurrency]??'Monedă internațională';
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact">
  <div><p class="eyebrow">NIR / <?= $editing?'CIORNĂ #'.(int)$document['id']:'DOCUMENT NOU' ?></p><h1><?= $editing?'Continuă recepția':'Creează NIR' ?></h1><p>Asociază fiecare poziție din factura furnizorului cu produsul existent din magazin.</p></div>
  <a class="admin-button secondary" href="<?= e(url('/admin/nir-uri')) ?>">Înapoi la listă</a>
</section>
<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<form class="nir-editor nir-editor-compact" method="post" enctype="multipart/form-data" action="<?= e(url($editing?'/admin/nir-uri/'.$document['id']:'/admin/nir-uri')) ?>" data-nir-editor data-exchange-rate-url="<?= e(url('/admin/nir-uri/curs-valutar')) ?>" data-has-saved-exchange-rate="<?= $editing?'1':'0' ?>">
  <?= csrf_field() ?>
  <?php if($editing): ?><input type="hidden" name="row_version" value="<?= (int)$document['row_version'] ?>"><?php endif; ?>
  <?php if($importToken): ?><input type="hidden" name="import_token" value="<?= e($importToken) ?>"><?php endif; ?>

  <nav class="nir-step-nav nir-step-nav-compact is-static" aria-label="Pașii NIR"><a aria-disabled="true"><span>1</span><b>Document</b></a><a aria-disabled="true"><span>2</span><b>Produse și asociere</b></a><a aria-disabled="true"><span>3</span><b>Salvare</b></a></nav>

  <section id="nir-document" class="admin-panel nir-section nir-document-panel">
    <div class="panel-head accounting-panel-head-compact"><div><p class="eyebrow">PASUL 1</p><h2>Furnizor și factură</h2><p class="help">Acestea sunt datele obligatorii pentru document.</p></div><span class="status-pill">Date principale</span></div>
    <div class="accounting-form-grid nir-document-main-grid">
      <label class="wide">Furnizor<input name="supplier_name" list="nir-suppliers" required value="<?= e($document['supplier_name_snapshot']??'') ?>" placeholder="Denumirea legală" data-nir-supplier-name><datalist id="nir-suppliers"><?php foreach($suppliers as $supplier): ?><option value="<?= e($supplier['legal_name']) ?>" data-tax-id="<?= e($supplier['tax_id']) ?>"><?php endforeach; ?></datalist></label>
      <label>CUI furnizor<input name="supplier_tax_id" required inputmode="text" autocomplete="off" spellcheck="false" data-company-code value="<?= e($document['supplier_tax_id_snapshot']??'') ?>"><small class="accounting-field-help">Poți lipi direct CUI-ul; spațiile și etichetele sunt eliminate automat.</small></label>
      <label>Gestiune<select name="warehouse_id" required><?php foreach($warehouses as $warehouse): ?><option value="<?= (int)$warehouse['id'] ?>" <?= (int)($document['warehouse_id']??0)===(int)$warehouse['id']||(!$editing&&!empty($warehouse['is_default']))?'selected':'' ?>><?= e($warehouse['name']) ?></option><?php endforeach; ?></select></label>
      <label>Serie factură <small class="accounting-optional-tag">Opțional</small><input name="invoice_series" value="<?= e($document['invoice_series']??'') ?>" placeholder="Lasă gol dacă factura nu are serie"><small class="accounting-field-help">Nu inventa o serie. Pentru factura Westpack 796534, acest câmp rămâne gol.</small></label>
      <label>Număr factură<input name="invoice_number" required value="<?= e($document['invoice_number']??'') ?>"></label>
      <label>Data facturii<input type="date" name="invoice_date" max="<?= date('Y-m-d') ?>" required value="<?= e($document['invoice_date']??'') ?>"></label>
      <label>Data recepției<input type="date" name="receipt_date" max="<?= date('Y-m-d') ?>" required value="<?= e($document['receipt_date']??'') ?>"></label>
      <label class="accounting-currency-field">
        <span>Moneda facturii</span>
        <div class="accounting-currency-picker" data-currency-picker>
          <input type="hidden" name="currency" value="<?= e($documentCurrency) ?>" data-nir-currency>
          <button type="button" class="accounting-currency-trigger" data-currency-toggle aria-haspopup="listbox" aria-expanded="false"><span data-currency-trigger-code><?= e($documentCurrency) ?></span><span><strong data-currency-trigger-name><?= e($initialCurrencyName) ?></strong><small>Caută după cod sau denumire</small></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></button>
          <section class="accounting-currency-popover" data-currency-popover hidden>
            <header><div><strong>Alege moneda facturii</strong><small>Toate monedele internaționale disponibile</small></div><button type="button" data-currency-close aria-label="Închide">×</button></header>
            <label class="accounting-currency-search" data-admin-ignore-dirty><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg><input type="search" autocomplete="off" spellcheck="false" placeholder="Ex. TRY, TL, lire turcești, euro…" data-currency-search></label>
            <div class="accounting-currency-results" role="listbox" data-currency-results></div>
            <p data-currency-empty hidden>Nicio monedă nu corespunde căutării.</p>
          </section>
        </div>
        <small class="accounting-field-help">Se aplică tuturor produselor din acest NIR.</small>
      </label>
      <label>Curs valutar<div class="accounting-exchange-rate-field"><input type="number" name="exchange_rate" min="0.000001" step="0.000001" inputmode="decimal" required readonly value="<?= e($document['exchange_rate']??'1.000000') ?>" data-nir-exchange-rate><button type="button" data-edit-exchange-rate title="Editează manual cursul valutar" aria-label="Editează manual cursul valutar" aria-pressed="false">✎</button><button type="button" data-refresh-exchange-rate title="Preia din nou cursul curent" aria-label="Preia din nou cursul curent">↻</button></div><input type="hidden" name="exchange_rate_date" value="<?= e($document['exchange_rate_date']??'') ?>" data-nir-exchange-date><input type="hidden" name="exchange_rate_source" value="<?= e($savedExchangeRateSource) ?>" data-nir-exchange-source><input type="hidden" name="exchange_rate_manual" value="<?= $savedExchangeRateManual?'1':'0' ?>" data-nir-exchange-manual><small class="accounting-field-help" data-nir-exchange-help>Se completează automat, dar poate fi înlocuit cu un curs istoric.</small><small class="accounting-rate-attribution">Apasă ✎ pentru introducere manuală sau ↻ pentru cursul curent. Pentru monedele nepublicate de BNR: <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener">Rates by ExchangeRate-API</a>.</small></label>
    </div>

    <div class="accounting-subpanel-row">
      <details class="accounting-subpanel"><summary><span><b>Date suplimentare</b><small>Adresă, scadență și aviz</small></span><i>+</i></summary><div class="accounting-form-grid accounting-subpanel-content"><label class="wide">Adresă furnizor<input name="supplier_address" value="<?= e($supplierAddress['line1']??'') ?>"></label><label>Data primirii facturii<input type="date" name="invoice_received_date" value="<?= e($document['invoice_received_date']??'') ?>"></label><label>Scadență<input type="date" name="due_date" value="<?= e($document['due_date']??'') ?>"></label><label>Număr aviz<input name="delivery_note_number" value="<?= e($document['delivery_note_number']??'') ?>"></label><label>Data avizului<input type="date" name="delivery_note_date" value="<?= e($document['delivery_note_date']??'') ?>"></label></div></details>
      <details class="accounting-subpanel"><summary><span><b>Situații speciale</b><small>Tranșe, introducere ulterioară și observații</small></span><i>+</i></summary><div class="accounting-subpanel-content"><div class="nir-special-flags"><label class="admin-switch-row"><input type="checkbox" name="partial_receipt" value="1"><span class="admin-switch"><i></i></span><b>Recepție în tranșe</b></label><label class="admin-switch-row"><input type="checkbox" name="is_late_entered" value="1" <?= !empty($document['is_late_entered'])?'checked':'' ?> data-late-entry-toggle><span class="admin-switch"><i></i></span><b>Document introdus ulterior</b></label></div><label data-late-entry-reason <?= empty($document['is_late_entered'])?'hidden':'' ?>>Motiv introducere ulterioară<select name="late_entry_reason"><option value="">Selectează motivul</option><?php foreach(['Factura furnizorului a fost primită ulterior','Documentul nu fusese introdus în aplicație','Recepția a fost efectuată anterior pe baza avizului','Corectare omisiune documentară','Alt motiv'] as $reason): ?><option <?= ($document['late_entry_reason']??'')===$reason?'selected':'' ?>><?= e($reason) ?></option><?php endforeach; ?></select></label><label>Observații<textarea name="notes" rows="3"><?= e($document['notes']??'') ?></textarea></label></div></details>
      <details class="accounting-subpanel"><summary><span><b>Atașamente</b><small>Factura și avizul în format digital</small></span><i>+</i></summary><div class="nir-attachments accounting-subpanel-content"><label>Factura PDF<input type="file" name="source_pdf" accept="application/pdf,.pdf"></label><label>Factura XLSX<input type="file" name="source_xlsx" accept=".xlsx"></label><label>Factura XML<input type="file" name="source_xml" accept="application/xml,text/xml,.xml"></label><label>Aviz PDF<input type="file" name="delivery_note_attachment" accept="application/pdf,.pdf"></label></div></details>
    </div>
  </section>

  <section id="nir-products" class="admin-panel nir-section nir-products-panel">
    <div class="panel-head accounting-panel-head-compact">
      <div><p class="eyebrow">PASUL 2</p><h2>Produsele din factură</h2><p class="help">Pentru fiecare denumire de la furnizor, selectează produsul existent din dropdown. Poți adăuga oricâte poziții are factura.</p></div>
      <button class="admin-button" type="button" data-add-nir-line>+ Adaugă produs</button>
    </div>
    <div class="nir-lines" data-nir-lines>
      <?php foreach($mutableLines as $index=>$line): $isTemplate=false; require __DIR__.'/partials/nir-line.php'; endforeach; ?>
    </div>
    <button class="nir-add-another" type="button" data-add-nir-line><span>+</span><strong>Adaugă încă un produs din factură</strong><small>Se creează o poziție nouă în același NIR.</small></button>
  </section>

  <section id="nir-review" class="admin-panel nir-review nir-review-compact">
    <div><p class="eyebrow">PASUL 3</p><h2>Salvează ciorna</h2><p>Ciorna nu modifică niciun stoc. O poți verifica înainte de confirmarea definitivă.</p></div>
    <div class="nir-review-totals"><span><small>Produse</small><strong data-review-lines><?= count($mutableLines) ?></strong></span><span><small>Acceptat</small><strong data-review-accepted>0</strong></span><span><small>Fără TVA · <i data-nir-currency-label><?= e($documentCurrency) ?></i></small><strong data-review-net>0,00</strong></span><span><small>TVA · <i data-nir-currency-label><?= e($documentCurrency) ?></i></small><strong data-review-vat>0,00</strong></span><span class="primary"><small>Total · <i data-nir-currency-label><?= e($documentCurrency) ?></i></small><strong data-review-total>0,00</strong></span></div>
    <button class="admin-button" type="submit"><?= $editing?'Salvează modificările':'Creează ciorna NIR' ?></button>
  </section>

  <div class="accounting-modal" data-line-details-modal hidden>
    <button class="accounting-modal-backdrop" type="button" data-close-line-details aria-label="Închide detaliile"></button>
    <section class="accounting-modal-card accounting-modal-card-large" role="dialog" aria-modal="true" aria-labelledby="line-details-title">
      <header><div><p class="eyebrow">DETALII PRODUS</p><h2 id="line-details-title">Date suplimentare și diferențe</h2><p>Câmpurile rare sunt grupate aici ca formularul principal să rămână ușor de urmărit.</p></div><button type="button" data-close-line-details aria-label="Închide">×</button></header>
      <div data-line-details-slot></div>
      <footer><button class="admin-button" type="button" data-close-line-details>Gata</button></footer>
    </section>
  </div>
</form>

<?php if($editing && MaisonBebe\Core\Auth::hasPermission('nir.confirm')): ?>
<section class="accounting-final-action">
  <div><p class="eyebrow">DOCUMENT PREGĂTIT?</p><h2>Confirmarea este definitivă</h2><p>Se generează numărul NIR și mișcările din Stocuri Conta. Stocul online rămâne neatins.</p></div>
  <button class="admin-button" type="button" data-open-accounting-modal="nir-confirm">Verifică și confirmă</button>
</section>
<div class="accounting-modal" data-accounting-modal="nir-confirm" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide confirmarea"></button>
  <section class="accounting-modal-card accounting-modal-card-small" role="dialog" aria-modal="true" aria-labelledby="nir-confirm-title">
    <header><div><p class="eyebrow">OPERAȚIUNE DEFINITIVĂ</p><h2 id="nir-confirm-title">Confirmă NIR-ul</h2><p>După confirmare, documentul nu mai poate fi editat.</p></div><button type="button" data-close-accounting-modal aria-label="Închide">×</button></header>
    <form method="post" action="<?= e(url('/admin/nir-uri/'.$document['id'].'/confirmare')) ?>" data-nir-confirm><?= csrf_field() ?><input type="hidden" name="row_version" value="<?= (int)$document['row_version'] ?>"><div class="accounting-modal-form"><label class="check-label"><input type="checkbox" name="period_override" value="1"> Procedură autorizată pentru perioadă blocată</label><label>Motiv autorizare<input name="period_override_reason"></label></div><footer><button class="admin-button secondary" type="button" data-close-accounting-modal>Anulează</button><button class="admin-button" type="submit">Confirmă definitiv</button></footer></form>
  </section>
</div>
<?php endif; ?>

<template id="nir-product-results-template">
  <?php foreach($products as $product): ?>
    <button type="button" class="nir-product-result nir-product-result-compact" data-pick-product data-search="<?= e(mb_strtolower(implode(' ',array_filter([$product['sku'],$product['ean'],$product['product_name'],$product['variant_name'],$product['category_name'],!empty($product['is_gift_box'])?'cutie cadou':null]))) ) ?>" data-variant-id="<?= (int)$product['variant_id'] ?>" data-product="<?= e($product['product_name']) ?>" data-variant="<?= e($product['variant_name']) ?>" data-sku="<?= e($product['sku']) ?>" data-image="<?= e(url($product['image_path'])) ?>" data-unlimited="<?= empty($product['track_inventory'])?'1':'0' ?>" data-kind="<?= !empty($product['is_gift_box'])?'gift_box':'product' ?>" data-supplier-mappings="<?= e(json_encode($product['supplier_mappings']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>">
      <img src="<?= e(url($product['image_path'])) ?>" alt=""><span><?php if(!empty($product['is_gift_box'])): ?><em class="accounting-kind-badge gift-box">Cutie cadou</em><?php endif; ?><strong><?= e($product['product_name']) ?></strong><small><?= e($product['variant_name']?:'Varianta standard') ?></small><i>SKU <?= e($product['sku']) ?><?= $product['ean']?' · EAN '.$product['ean']:'' ?></i></span><span class="nir-product-meta"><b>Stoc Conta <?= number_format((float)$product['accounting_quantity'],0,',','.') ?></b><small><?= e($product['category_name']) ?></small></span>
    </button>
  <?php endforeach; ?>
</template>

<template id="nir-line-template">
  <?php $line=$emptyLine;$index=0;$isTemplate=true;require __DIR__.'/partials/nir-line.php'; ?>
</template>
