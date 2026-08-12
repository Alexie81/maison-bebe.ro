<?php
$statusLabels=['Draft'=>'Ciornă','RequiresProductMapping'=>'Necesită asociere produse','InReception'=>'Recepție în lucru','ReadyForConfirmation'=>'Gata de confirmare','Confirmed'=>'Confirmat','PartiallyReceived'=>'Recepționat parțial','Reversed'=>'Inversat'];
$statusClasses=['Confirmed'=>'success','PartiallyReceived'=>'warning','Reversed'=>'danger','RequiresProductMapping'=>'warning','ReadyForConfirmation'=>'info'];
$activeFilters=array_filter($filters,static fn($value)=>$value!==''&&$value!==0);
$exportQuery=http_build_query($activeFilters);
$periodFilters=$activeFilters;unset($periodFilters['from'],$periodFilters['to']);
$periodUrl=static function(string $from='',string $to='')use($periodFilters):string{$query=$periodFilters;if($from!=='')$query['from']=$from;if($to!=='')$query['to']=$to;return url('/admin/nir-uri'.($query?'?'.http_build_query($query):''));};
$today=date('Y-m-d');$yesterday=date('Y-m-d',strtotime('-1 day'));$last7=date('Y-m-d',strtotime('-6 days'));$last30=date('Y-m-d',strtotime('-29 days'));$monthStart=date('Y-m-01');
$selectedPeriod=$filters['from']===''&&$filters['to']===''?'all':($filters['from']===$filters['to']&&$filters['from']!==''?'day-'.$filters['from']:($filters['from']===$last7&&$filters['to']===$today?'last7':($filters['from']===$last30&&$filters['to']===$today?'last30':($filters['from']===$monthStart&&$filters['to']===$today?'month':'custom'))));
$periodLabel=$selectedPeriod==='all'?'Toate zilele':($filters['from']===$filters['to']?'Ziua '.date('d.m.Y',strtotime($filters['from'])):date('d.m.Y',strtotime($filters['from'])).' – '.date('d.m.Y',strtotime($filters['to']?:$today)));
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact">
  <div>
    <p class="eyebrow">DOCUMENTE DE INTRARE</p>
    <h1>NIR-uri</h1>
    <p>Recepții contabile clare, separate complet de stocul comercial.</p>
  </div>
  <div class="button-row">
    <button class="admin-button secondary" type="button" data-open-accounting-modal="nir-filters">Filtre<?= $activeFilters?' · '.count($activeFilters):'' ?></button>
    <?php if(MaisonBebe\Core\Auth::hasPermission('nir.create')): ?>
      <a class="admin-button" href="<?= e(url('/admin/nir-uri/nou')) ?>">NIR nou</a>
    <?php endif; ?>
  </div>
</section>

<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="accounting-summary-toolbar">
  <div class="accounting-summary-strip" aria-label="Rezumat NIR-uri">
    <?php foreach([
      ['Ciorne',$stats['drafts'],'draft'],
      ['De confirmat',$stats['ready'],'ready'],
      ['Confirmate luna aceasta',$stats['confirmed_month'],'confirmed'],
      ['Cu diferențe',$stats['differences'],'differences'],
      ['Introduse ulterior',$stats['late'],'late'],
      ['Produse neasociate',$stats['unmapped'],'unmapped']
    ] as [$label,$value,$tone]): ?>
      <article class="accounting-summary-item <?= e($tone) ?>"><strong><?= (int)$value ?></strong><span><?= e($label) ?></span></article>
    <?php endforeach; ?>
  </div>
  <div class="accounting-date-picker" data-nir-date-picker>
    <button type="button" class="accounting-date-picker-trigger" data-nir-date-picker-toggle aria-expanded="false"><span class="accounting-date-picker-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18M8 14h2M14 14h2M8 18h2"/></svg></span><span><small>Ziua / perioada</small><strong><?= e($periodLabel) ?></strong></span><i class="accounting-date-picker-chevron"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9.5 5 5 5-5"/></svg></i></button>
    <div class="accounting-date-picker-popover" data-nir-date-picker-popover hidden>
      <header><div><p class="eyebrow">INTERVAL NIR</p><strong>Alege rapid sau selectează datele</strong></div><button type="button" data-nir-date-picker-close aria-label="Închide">×</button></header>
      <div class="accounting-date-presets">
        <a class="<?= $selectedPeriod==='day-'.$today?'is-active':'' ?>" href="<?= e($periodUrl($today,$today)) ?>">Astăzi</a>
        <a class="<?= $selectedPeriod==='day-'.$yesterday?'is-active':'' ?>" href="<?= e($periodUrl($yesterday,$yesterday)) ?>">Ieri</a>
        <a class="<?= $selectedPeriod==='last7'?'is-active':'' ?>" href="<?= e($periodUrl($last7,$today)) ?>">7 zile</a>
        <a class="<?= $selectedPeriod==='last30'?'is-active':'' ?>" href="<?= e($periodUrl($last30,$today)) ?>">30 zile</a>
        <a class="<?= $selectedPeriod==='month'?'is-active':'' ?>" href="<?= e($periodUrl($monthStart,$today)) ?>">Luna aceasta</a>
      </div>
      <form method="get" action="<?= e(url('/admin/nir-uri')) ?>" data-nir-date-range-form>
        <?php foreach($periodFilters as $key=>$value): ?><input type="hidden" name="<?= e((string)$key) ?>" value="<?= e((string)$value) ?>"><?php endforeach; ?>
        <div><label><span>De la</span><input type="date" name="from" max="<?= e($today) ?>" value="<?= e($filters['from']?:$today) ?>" required></label><span class="accounting-date-range-arrow">→</span><label><span>Până la</span><input type="date" name="to" max="<?= e($today) ?>" value="<?= e($filters['to']?:$today) ?>" required></label></div>
        <small data-nir-date-range-error hidden>Data de început trebuie să fie înaintea datei finale.</small>
        <footer><a href="<?= e($periodUrl()) ?>">Resetează</a><button class="admin-button" type="submit">Aplică perioada</button></footer>
      </form>
    </div>
  </div>
</section>

<?php if($activeFilters): ?>
  <div class="accounting-active-filters">
    <span>Filtre active: <?= count($activeFilters) ?></span>
    <a href="<?= e(url('/admin/nir-uri')) ?>">Șterge filtrele</a>
  </div>
<?php endif; ?>

<section class="admin-panel accounting-table-panel accounting-register-panel">
  <div class="panel-head accounting-panel-head-compact">
    <div><h2>Registrul NIR-urilor</h2><p class="help"><?= count($items) ?> documente găsite<?= $filters['from']!==''?' în intervalul '.date('d.m.Y',strtotime($filters['from'])).' – '.date('d.m.Y',strtotime($filters['to']?:$filters['from'])):'' ?>.</p></div>
    <a class="admin-button secondary small" href="<?= e(url('/admin/nir-uri/export'.($exportQuery?'?'.$exportQuery:''))) ?>">Exportă <?= $filters['from']!==''?'perioada':'lista' ?></a>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table accounting-compact-table">
      <thead><tr><th>Document</th><th>Furnizor și factură</th><th>Recepție</th><th>Valori</th><th>Stare</th><th></th></tr></thead>
      <tbody>
      <?php foreach($items as $item): $final=in_array($item['status'],['Confirmed','PartiallyReceived','Reversed'],true); ?>
        <tr>
          <td>
            <strong><?= e($item['formatted_number']?:'Ciornă #'.$item['id']) ?></strong>
            <small><?= date('d.m.Y',strtotime($item['receipt_date'])) ?> · <?= (int)$item['line_count'] ?> produse</small>
          </td>
          <td>
            <strong><?= e($item['supplier_name_snapshot']) ?></strong>
            <small>Factura <?= e(trim($item['invoice_series'].' '.$item['invoice_number'])) ?> · <?= date('d.m.Y',strtotime($item['invoice_date'])) ?></small>
          </td>
          <td>
            <?= e($item['warehouse_name']) ?>
            <small><?= e(trim($item['creator_name'])?:'Sistem') ?> · <?= date('d.m.Y H:i',strtotime($item['created_at'])) ?></small>
          </td>
          <td class="accounting-value-cell">
            <strong><?= number_format((float)$item['grand_total'],2,',','.') ?></strong>
            <small>Net <?= number_format((float)$item['total_without_vat'],2,',','.') ?> · TVA <?= number_format((float)$item['vat_total'],2,',','.') ?></small>
          </td>
          <td>
            <span class="status-pill <?= e($statusClasses[$item['status']]??'') ?>"><?= e($statusLabels[$item['status']]??$item['status']) ?></span>
            <div class="accounting-badges accounting-badges-compact">
              <?php if($item['is_late_entered']): ?><span>Ulterior</span><?php endif; ?>
              <?php if((int)$item['difference_count']>0): ?><span>Diferențe</span><?php endif; ?>
              <?php if((int)$item['unmapped_count']>0): ?><span class="warning">Neasociat</span><?php endif; ?>
            </div>
          </td>
          <td class="accounting-menu-cell">
            <details class="accounting-action-menu">
              <summary aria-label="Acțiuni pentru document" aria-expanded="false">•••</summary>
              <div>
                <?php if($final): ?>
                  <a href="<?= e(url('/admin/nir-uri/'.$item['id'])) ?>">Vizualizează</a>
                  <a target="_blank" href="<?= e(url('/admin/nir-uri/'.$item['id'].'/pdf')) ?>">Deschide PDF</a>
                  <a href="<?= e(url('/admin/nir-uri/'.$item['id'].'/xlsx')) ?>">Descarcă XLSX</a>
                  <a href="<?= e(url('/admin/stocuri-conta?q='.rawurlencode((string)($item['formatted_number']??'')))) ?>">Vezi mișcările</a>
                <?php else: ?>
                  <a href="<?= e(url('/admin/nir-uri/'.$item['id'].'/edit')) ?>">Continuă ciorna</a>
                  <?php if(MaisonBebe\Core\Auth::hasPermission('nir.confirm')): ?>
                    <form method="post" action="<?= e(url('/admin/nir-uri/'.$item['id'].'/confirmare')) ?>" data-nir-confirm><?= csrf_field() ?><input type="hidden" name="row_version" value="<?= (int)$item['row_version'] ?>"><button type="submit" class="accounting-confirm-action">Confirmă NIR</button></form>
                  <?php endif; ?>
                  <?php if(MaisonBebe\Core\Auth::hasPermission('nir.create')): ?>
                    <form method="post" action="<?= e(url('/admin/nir-uri/'.$item['id'].'/stergere-ciorna')) ?>" data-confirm-delete><?= csrf_field() ?><button type="submit">Șterge ciorna</button></form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$items): ?><tr><td colspan="6"><div class="admin-empty"><strong>Nu există NIR-uri pentru filtrele selectate.</strong><p>Modifică filtrele sau creează un document nou.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="accounting-modal" data-accounting-modal="nir-filters" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide filtrele"></button>
  <section class="accounting-modal-card accounting-modal-card-medium" role="dialog" aria-modal="true" aria-labelledby="nir-filter-title">
    <header><div><p class="eyebrow">FILTRARE REGISTRU</p><h2 id="nir-filter-title">Găsește rapid un NIR</h2><p>Completează doar criteriile de care ai nevoie.</p></div><button type="button" data-close-accounting-modal aria-label="Închide">×</button></header>
    <form method="get" action="<?= e(url('/admin/nir-uri')) ?>">
      <div class="accounting-filter-grid accounting-filter-grid-modal">
        <label>De la<input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
        <label>Până la<input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
        <label class="span-2">Furnizor<select name="supplier"><option value="">Toți furnizorii</option><?php foreach($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= $filters['supplier']==$supplier['id']?'selected':'' ?>><?= e($supplier['legal_name'].' · '.$supplier['tax_id']) ?></option><?php endforeach; ?></select></label>
        <label>Număr NIR<input name="number" value="<?= e($filters['number']) ?>"></label>
        <label>Factură furnizor<input name="invoice" value="<?= e($filters['invoice']) ?>"></label>
        <label>SKU<input name="sku" value="<?= e($filters['sku']) ?>"></label>
        <label>Produs<input name="product" value="<?= e($filters['product']) ?>"></label>
        <label>Status<select name="status"><option value="">Toate statusurile</option><?php foreach($statusLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Gestiune<select name="warehouse"><option value="">Toate gestiunile</option><?php foreach($warehouses as $warehouse): ?><option value="<?= (int)$warehouse['id'] ?>" <?= $filters['warehouse']==$warehouse['id']?'selected':'' ?>><?= e($warehouse['name']) ?></option><?php endforeach; ?></select></label>
        <label>Introdus ulterior<select name="late"><option value="">Oricare</option><option value="1" <?= $filters['late']==='1'?'selected':'' ?>>Da</option><option value="0" <?= $filters['late']==='0'?'selected':'' ?>>Nu</option></select></label>
        <label>Diferențe<select name="differences"><option value="">Oricare</option><option value="1" <?= $filters['differences']==='1'?'selected':'' ?>>Cu diferențe</option></select></label>
      </div>
      <footer><a class="admin-button secondary" href="<?= e(url('/admin/nir-uri')) ?>">Resetează</a><button class="admin-button" type="submit">Aplică filtrele</button></footer>
    </form>
  </section>
</div>
