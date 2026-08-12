<?php
$variantId=(int)($line['product_variant_id']??0);
$selectedCatalogProduct=$variantId>0?($productLookup[$variantId]??null):null;
$selectedName=trim((string)($line['product_name_snapshot']??'').' '.(string)($line['variant_name_snapshot']??''));
$selectedSku=(string)($line['sku_snapshot']??'');
?>
<article class="nir-line-card" data-nir-line
  data-catalog-product-name="<?= e($selectedCatalogProduct['product_name']??'') ?>"
  data-supplier-mappings="<?= e(json_encode($selectedCatalogProduct['supplier_mappings']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>">
  <header>
    <div class="nir-line-heading">
      <span class="nir-line-number"><?= $isTemplate?'':$index+1 ?></span>
      <div><strong data-nir-line-title><?= e($line['supplier_product_name']?:'Produs nou') ?></strong><small data-nir-line-sku><?= e($selectedSku?:'Necesită asociere cu un produs') ?></small></div>
    </div>
    <div class="nir-line-header-actions">
      <span class="nir-line-state <?= $variantId?'is-mapped':'' ?>" data-line-map-state><?= $variantId?'Asociat':'Neasociat' ?></span>
      <button class="accounting-text-button" type="button" data-open-line-details>Detalii și diferențe</button>
      <button class="icon-action danger" type="button" data-remove-nir-line aria-label="Șterge produsul">×</button>
    </div>
  </header>

  <input type="hidden" name="supplier_line_id[]" value="<?= e($line['supplier_invoice_line_id']??'') ?>">
  <input type="hidden" name="line_variant_id[]" value="<?= e($line['product_variant_id']??'') ?>" data-line-variant-id>
  <input type="hidden" name="line_original_json[]" value="<?= e($line['original_imported_data_json']??'') ?>">

  <div class="nir-line-primary">
    <label class="nir-line-supplier-name">Denumire pe factura furnizorului<input name="line_name[]" required value="<?= e($line['supplier_product_name']??'') ?>" data-line-name placeholder="Ex: Pătuț bebe natur 120 × 60"></label>

    <div class="nir-product-select" data-product-select>
      <small>ASOCIAZĂ CU PRODUSUL DIN MAGAZIN</small>
      <button class="nir-product-select-trigger" type="button" data-toggle-product-dropdown aria-expanded="false">
        <span class="nir-selected-product-image"><img data-selected-product-image src="<?= $selectedCatalogProduct?e(url($selectedCatalogProduct['image_path'])):'' ?>" alt="" <?= $selectedCatalogProduct?'':'hidden' ?>><i data-selected-product-placeholder <?= $selectedCatalogProduct?'hidden':'' ?>>+</i></span>
        <span><em class="accounting-kind-badge gift-box" data-selected-product-kind <?= $selectedCatalogProduct&&!empty($selectedCatalogProduct['is_gift_box'])?'':'hidden' ?>>Cutie cadou</em><strong data-selected-product><?= e($selectedName?:'Selectează produsul existent') ?></strong><small data-selected-sku><?= e($selectedSku?'SKU '.$selectedSku:'Imagine, nume, variantă și SKU') ?></small></span>
        <b>Schimbă⌄</b>
      </button>
      <div class="nir-product-dropdown" data-product-dropdown hidden>
        <div class="nir-product-dropdown-head"><strong>Alege produsul existent</strong><button type="button" data-close-product-dropdown aria-label="Închide">×</button></div>
        <label>Caută după nume, SKU, EAN sau variantă<input type="search" data-inline-product-search autocomplete="off" placeholder="Începe să scrii..."></label>
        <div class="nir-product-dropdown-results" data-inline-product-results></div>
      </div>
    </div>

    <label>Facturat<input type="number" step="0.0001" min="0" name="line_invoiced_quantity[]" value="<?= e($line['invoiced_quantity']??'') ?>" data-qty-invoiced></label>
    <label>Recepționat<input type="number" step="0.0001" min="0" name="line_received_quantity[]" value="<?= e($line['received_quantity']??'') ?>" data-qty-received></label>
    <label>Acceptat<input type="number" step="0.0001" min="0" name="line_accepted_quantity[]" value="<?= e($line['accepted_quantity']??'') ?>" data-qty-accepted></label>
    <label>Preț unitar fără TVA · <span data-nir-currency-label><?= e($documentCurrency) ?></span><input type="number" step="0.000001" min="0" name="line_unit_price[]" value="<?= e($line['unit_purchase_price_without_vat']??'') ?>" data-unit-price></label>
    <label>TVA %<input type="number" step="0.01" min="0" name="line_vat_rate[]" value="<?= e($line['vat_rate']??'') ?>" data-vat-rate></label>
    <label>Total cu TVA · <span data-nir-currency-label><?= e($documentCurrency) ?></span><input type="number" step="0.01" name="line_total[]" value="<?= e($line['total_with_vat']??'') ?>" data-total></label>
  </div>

  <div class="nir-line-footer">
    <label class="check-label"><input type="checkbox" <?= $isTemplate?'data-remember-mapping':'name="line_remember_mapping['.$index.']"' ?> value="1"> Memorează asocierea la următoarea factură de la acest furnizor</label>
    <span><b data-line-qty-summary><?= number_format((float)($line['accepted_quantity']??0),0,',','.') ?></b> buc acceptate · <b data-line-total-summary><?= number_format((float)($line['total_with_vat']??0),2,',','.') ?></b> <i data-nir-currency-label><?= e($documentCurrency) ?></i> total</span>
  </div>

  <section class="nir-line-advanced" data-line-advanced hidden>
    <div class="accounting-subpanel-grid">
      <div class="accounting-field-group">
        <div><p class="eyebrow">IDENTIFICARE</p><h3>Datele furnizorului</h3></div>
        <div class="accounting-modal-form accounting-modal-form-grid">
          <label>Cod furnizor<input name="line_supplier_code[]" value="<?= e($line['supplier_product_code']??'') ?>"></label>
          <label>SKU importat<input name="line_imported_sku[]" value="<?= e($line['imported_sku']??'') ?>"></label>
          <label>EAN<input name="line_imported_ean[]" value="<?= e($line['imported_ean']??'') ?>"></label>
          <label>Tip linie<select name="line_type[]" data-line-type><?php foreach($lineTypes as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($line['line_type']??'stockable')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>Unitate de măsură<input name="line_unit[]" value="<?= e($line['unit_of_measure_snapshot']??'buc') ?>"></label>
        </div>
      </div>
      <div class="accounting-field-group">
        <div><p class="eyebrow">VALORI SUPLIMENTARE</p><h3>Costuri și recepție</h3></div>
        <div class="accounting-modal-form accounting-modal-form-grid">
          <label>Cantitate deteriorată<input type="number" step="0.0001" min="0" name="line_damaged_quantity[]" value="<?= e($line['damaged_quantity']??'0') ?>"></label>
          <label>Discount<input type="number" step="0.01" min="0" name="line_discount[]" value="<?= e($line['discount_value']??'0') ?>" data-discount></label>
          <label>Cost auxiliar repartizat<input type="number" step="0.01" min="0" name="line_allocated_cost[]" value="<?= e($line['allocated_acquisition_cost']??'0') ?>"></label>
          <label>Valoare fără TVA<input type="number" step="0.01" name="line_value_without_vat[]" value="<?= e($line['value_without_vat']??'') ?>" data-net></label>
          <label>Valoare TVA<input type="number" step="0.01" name="line_vat_value[]" value="<?= e($line['vat_value']??'') ?>" data-vat></label>
        </div>
      </div>
      <div class="accounting-field-group accounting-field-group-wide">
        <div><p class="eyebrow">RECEPȚIE</p><h3>Diferențe și observații</h3></div>
        <div class="accounting-modal-form accounting-modal-form-grid">
          <label>Tip diferență<select name="line_difference_type[]"><?php foreach($differenceTypes as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($line['difference_type']??'none')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>Motiv diferență<input name="line_difference_reason[]" value="<?= e($line['difference_reason']??'') ?>"></label>
          <label class="span-2">Observații linie<input name="line_observations[]" value="<?= e($line['observations']??'') ?>"></label>
          <label class="span-2">Justificare linie ignorată<input name="line_ignore_reason[]" value="<?= e($line['ignore_reason']??'') ?>"></label>
        </div>
      </div>
    </div>
  </section>

  <div class="nir-online-note" data-online-note <?= $selectedCatalogProduct&&empty($selectedCatalogProduct['track_inventory'])?'':'hidden' ?>>Produsul este nelimitat online; acest NIR modifică numai Stocuri Conta.</div>
</article>
