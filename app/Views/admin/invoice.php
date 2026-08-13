<?php
$isStorno = ($invoice['document_type'] ?? 'invoice') === 'storno';
$isIssued = ($invoice['status'] ?? '') === 'issued';
$wasStornoed = !$isStorno && !empty($invoice['storno_invoice_id']);
$documentLabel = $isStorno ? 'Factură storno' : 'Factură fiscală';
$spvLabels = [
    'pending'=>'În așteptare', 'uploading'=>'Se transmite', 'processing'=>'În verificare ANAF',
    'accepted'=>'Acceptată de ANAF', 'rejected'=>'Respinsă de ANAF', 'retry'=>'Se reîncearcă',
    'requires_attention'=>'Necesită atenție',
];
$spvStatus = $spvSubmission['status'] ?? null;
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact invoice-detail-head">
  <div>
    <p class="eyebrow"><?= $isStorno?'CORECȚIE FISCALĂ':'FACTURĂ EMISĂ' ?></p>
    <div class="invoice-detail-title"><h1><?= e($invoice['number']?:'#'.$invoice['id']) ?></h1><span class="invoice-type-badge <?= $isStorno?'storno':'invoice' ?>"><?= e($documentLabel) ?></span></div>
    <p>Comanda <?= e($invoice['order_number']?:'—') ?> · <?= e($invoice['email']?:'—') ?></p>
  </div>
  <div class="button-row">
    <?php if($invoice['document_hash']): ?><a class="admin-button" target="_blank" href="<?= e(url('/factura/'.$invoice['document_hash'])) ?>">Deschide PDF ↗</a><?php endif; ?>
    <a class="admin-button secondary" href="<?= e(url('/admin/facturi/'.$invoice['id'].'/ubl')) ?>">Descarcă XML<?= $isStorno?' FCN':'' ?></a>
  </div>
</section>

<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<?php if($wasStornoed): ?>
  <section class="invoice-correction-banner">
    <span aria-hidden="true">↩</span><div><strong>Această factură a fost stornată integral.</strong><p>Documentul inițial rămâne în jurnal, iar valorile lui sunt corectate prin factura storno <a href="<?= e(url('/admin/facturi/'.$invoice['storno_invoice_id'])) ?>"><?= e($invoice['storno_number']) ?></a>.</p></div>
  </section>
<?php elseif($isStorno): ?>
  <section class="invoice-correction-banner is-storno">
    <span aria-hidden="true">−</span><div><strong>Document de corecție cu valori negative.</strong><p>Stornare integrală pentru factura inițială <a href="<?= e(url('/admin/facturi/'.$invoice['parent_invoice_id'])) ?>"><?= e($invoice['parent_number']?:'#'.$invoice['parent_invoice_id']) ?></a>.</p><p><strong>Validare ANAF:</strong> selectează standardul FCN, nu FACT1.</p></div>
  </section>
<?php endif; ?>

<div class="admin-two-columns invoice-detail-layout">
  <div>
    <section class="admin-panel invoice-document-card">
      <div class="panel-head"><div><h2>Document</h2><p class="help">Date fiscale păstrate în jurnalul imuabil.</p></div><span class="status-pill <?= $isIssued?'success':'' ?>"><?= $isIssued?'Emisă':e($invoice['status']) ?></span></div>
      <dl class="admin-details">
        <dt>Tip document</dt><dd><?= e($documentLabel) ?></dd>
        <dt>Data emiterii</dt><dd><?= $invoice['issue_date']?date('d.m.Y',strtotime($invoice['issue_date'])):'—' ?></dd>
        <dt>Scadență</dt><dd><?= $invoice['due_date']?date('d.m.Y',strtotime($invoice['due_date'])):'—' ?></dd>
        <dt>Tip client</dt><dd><?= $invoice['customer_type']==='individual'?'Persoană fizică':'Persoană juridică' ?></dd>
        <?php if($isStorno): ?><dt>Factura inițială</dt><dd><a href="<?= e(url('/admin/facturi/'.$invoice['parent_invoice_id'])) ?>"><?= e($invoice['parent_number']) ?></a></dd><?php endif; ?>
        <dt>Hash document</dt><dd class="hash-value"><?= e($invoice['document_hash']?:'—') ?></dd>
      </dl>
      <?php if(!empty($invoice['notes'])): ?><div class="invoice-document-note"><small>MENȚIUNI</small><p><?= e($invoice['notes']) ?></p></div><?php endif; ?>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><div><h2>Poziții facturate</h2><p class="help"><?= count($items) ?> poziții în document.</p></div></div>
      <div class="invoice-lines-list">
        <?php foreach($items as $item): ?><article class="admin-order-item"><div><strong><?= e($item['name']) ?></strong><small><?= e($item['sku']?:'Fără SKU') ?> · <?= number_format((float)$item['quantity'],2,',','.') ?> buc.</small></div><b><?= money($item['total_minor']) ?></b></article><?php endforeach; ?>
      </div>
      <div class="admin-order-total <?= $isStorno?'is-negative':'' ?>"><span><?= $isStorno?'Total stornat':'Total' ?></span><strong><?= money($invoice['grand_total_minor']) ?></strong></div>
    </section>

    <?php if(!$isStorno && $isIssued && !$wasStornoed): ?>
      <section class="accounting-final-action invoice-storno-action">
        <div><p class="eyebrow">CORECȚIE CONTROLATĂ</p><h2>Trebuie anulată această factură?</h2><p>Se emite o factură storno separată; documentul inițial nu este șters și nu este rescris.</p></div>
        <button class="admin-button danger" type="button" data-open-accounting-modal="invoice-storno">Emite storno integral</button>
      </section>
    <?php endif; ?>
  </div>

  <aside>
    <section class="admin-panel invoice-spv-card">
      <div class="panel-head"><div><p class="eyebrow">RO E-FACTURA</p><h2>Status SPV</h2></div></div>
      <?php if($spvSubmission): ?>
        <div class="invoice-spv-status <?= e($spvStatus) ?>"><span></span><div><strong><?= e($spvLabels[$spvStatus]??$spvStatus) ?></strong><small><?= $spvSubmission['updated_at']?date('d.m.Y H:i',strtotime($spvSubmission['updated_at'])):'—' ?></small></div></div>
        <?php if($spvSubmission['upload_id']): ?><p class="invoice-spv-meta">Index încărcare <strong><?= e($spvSubmission['upload_id']) ?></strong></p><?php endif; ?>
        <?php if($spvSubmission['last_error']): ?><p class="efactura-error"><?= e($spvSubmission['last_error']) ?></p><?php endif; ?>
      <?php else: ?><p class="help">Documentul nu a fost pus încă în coada de transmitere.</p><?php endif; ?>
      <?php if($connectedAnaf && $spvStatus!=='accepted'): ?><form method="post" action="<?= e(url('/admin/facturi/'.$invoice['id'].'/spv')) ?>"><?= csrf_field() ?><button class="admin-button" type="submit"><?= $spvSubmission?'Retrimite / verifică':'Trimite în SPV' ?></button></form>
      <?php elseif(!$connectedAnaf): ?><a class="admin-button secondary" href="<?= e(url('/admin/facturare/efactura')) ?>">Configurează conexiunea ANAF</a><?php endif; ?>
    </section>

    <section class="admin-panel"><h2>Jurnal imuabil</h2><ol class="admin-timeline"><?php foreach($events as $event): ?><li><strong><?= e($event['event_type']) ?></strong><span><?= e($event['status']?:'') ?></span><time><?= date('d.m.Y H:i',strtotime($event['created_at'])) ?></time></li><?php endforeach; ?></ol></section>
  </aside>
</div>

<?php if(!$isStorno && $isIssued && !$wasStornoed): ?>
<div class="accounting-modal" data-accounting-modal="invoice-storno" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide confirmarea"></button>
  <section class="accounting-modal-card accounting-modal-card-medium" role="dialog" aria-modal="true" aria-labelledby="invoice-storno-title">
    <header><div><p class="eyebrow">OPERAȚIUNE FISCALĂ DEFINITIVĂ</p><h2 id="invoice-storno-title">Emite factura storno</h2><p>Factura <?= e($invoice['number']) ?> rămâne intactă. Se creează un document nou cu toate valorile negative.</p></div><button type="button" data-close-accounting-modal aria-label="Închide">×</button></header>
    <form method="post" action="<?= e(url('/admin/facturi/'.$invoice['id'].'/storno')) ?>" data-confirm-delete data-confirm-message="Confirmi emiterea definitivă a facturii storno? Documentul fiscal nu va mai putea fi șters.">
      <?= csrf_field() ?>
      <div class="accounting-modal-form">
        <label>Data stornării<input type="date" name="issue_date" min="<?= e($invoice['issue_date']) ?>" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></label>
        <label>Motivul corecției<textarea name="reason" minlength="5" maxlength="1000" placeholder="Ex. Comandă anulată de client înainte de livrare" required></textarea></label>
        <label class="invoice-storno-choice"><input type="checkbox" name="physical_return" value="1" checked><span><strong>Produsele revin fizic în gestiune</strong><small>Se creează automat mișcări inverse și se reface soldul în Stocuri Conta. Debifează pentru storno strict financiar, fără retur fizic.</small></span></label>
        <label class="invoice-storno-choice"><input type="checkbox" name="send_email" value="1"><span><strong>Trimite documentul clientului</strong><small>Emailul cu factura storno va fi pus în coada de expediere.</small></span></label>
        <label class="invoice-storno-choice <?= !$connectedAnaf?'is-disabled':'' ?>"><input type="checkbox" name="send_spv" value="1" <?= !$connectedAnaf?'disabled':'' ?>><span><strong>Trimite factura storno în SPV</strong><small><?= $connectedAnaf?'Transmiterea pornește imediat după emitere.':'Conectează mai întâi certificatul în RO e-Factura.' ?></small></span></label>
        <details class="invoice-storno-advanced"><summary>Perioadă contabilă blocată</summary><label class="invoice-storno-choice"><input type="checkbox" name="period_override" value="1"><span><strong>Procedură autorizată de suprascriere</strong><small>Folosește numai cu aprobarea persoanei responsabile.</small></span></label><label>Motiv autorizare<input name="period_override_reason" maxlength="500"></label></details>
      </div>
      <footer><button class="admin-button secondary" type="button" data-close-accounting-modal>Renunță</button><button class="admin-button danger" type="submit">Confirmă și emite storno</button></footer>
    </form>
  </section>
</div>
<?php endif; ?>
