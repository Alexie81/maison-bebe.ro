<?php
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$previousMonthStart = date('Y-m-01', strtotime('first day of previous month'));
$previousMonthEnd = date('Y-m-t', strtotime('last day of previous month'));
$yearStart = date('Y-01-01');
$exportUrl = static fn(string $from, string $to): string => url('/admin/facturi/export-pachet?' . http_build_query(['from' => $from, 'to' => $to]));
$invoiceMailSubject='Facturi emise Maison Bébé · {PERIOADA}';
$invoiceMailMessage="Bună ziua,\n\nVă transmitem pachetul facturilor emise de Maison Bébé pentru perioada {PERIOADA}. Arhiva conține {CONTINUT}.\n\nCu mulțumiri,\nMaison Bébé";
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact">
  <div>
    <p class="eyebrow">FINANCIAR</p>
    <h1>Facturi emise</h1>
    <p>Arhiva documentelor fiscale și pachete complete pentru contabilitate.</p>
  </div>
  <div class="button-row">
    <a class="admin-button secondary" href="<?= e(url('/admin/facturare')) ?>">Overview</a>
    <button class="admin-button invoice-export-button" type="button" data-open-accounting-modal="invoice-accounting-export">
      <span aria-hidden="true">↓</span> Exportă pentru contabilitate
    </button>
    <button class="admin-button secondary" type="button" data-open-accounting-modal="invoice-accounting-email">Trimite contabilității</button>
  </div>
</section>

<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="invoice-export-explainer" aria-label="Conținut export contabil">
  <article><span>XLSX</span><div><strong>Registru contabil complet</strong><small>Date document, client, plată, TVA, totaluri și fiecare poziție facturată.</small></div></article>
  <article><span>XML</span><div><strong>RO e-Factura pentru fiecare document</strong><small>Fișiere UBL pregătite individual pentru perioada selectată.</small></div></article>
  <article><span>ZIP</span><div><strong>Un singur pachet</strong><small>Excelul și toate XML-urile sunt descărcate împreună.</small></div></article>
</section>

<section class="admin-panel">
  <div class="panel-head">
    <div><h2>Arhivă facturi</h2><p class="help"><?= count($items) ?> documente afișate.</p></div>
    <button class="admin-button secondary small" type="button" data-open-accounting-modal="invoice-accounting-export">Alege perioada</button>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Factură</th><th>Comandă</th><th>Client</th><th>Tip</th><th>Status</th><th>Data</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach($items as $item): ?>
        <tr>
          <td><a href="<?= e(url('/admin/facturi/'.$item['id'])) ?>"><strong><?= e($item['number']?:'#'.$item['id']) ?></strong></a><?php if($item['document_type']==='storno'): ?><small>Corectează <?= e($item['parent_number']?:'factura inițială') ?></small><?php elseif(!empty($item['storno_invoice_id'])): ?><small class="danger-text">Stornată prin <a href="<?= e(url('/admin/facturi/'.$item['storno_invoice_id'])) ?>"><?= e($item['storno_number']) ?></a></small><?php endif; ?></td>
          <td><?= e($item['order_number']?:'—') ?></td>
          <td><?= e($item['email']?:'—') ?></td>
          <td><?= e(['invoice'=>'Factură','credit_note'=>'Notă de credit','storno'=>'Storno'][$item['document_type']]??$item['document_type']) ?></td>
          <td><span class="status-pill <?= $item['status']==='issued'?'success':'' ?>"><?= e(['issued'=>'Emisă','draft'=>'Ciornă','cancelled'=>'Anulată','failed'=>'Eșuată'][$item['status']]??$item['status']) ?></span></td>
          <td><?= $item['issue_date']?date('d.m.Y',strtotime($item['issue_date'])):'—' ?></td>
          <td><?= money($item['grand_total_minor']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$items): ?><tr><td colspan="7"><div class="admin-empty">Nu există încă facturi emise.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="accounting-modal" data-accounting-modal="invoice-accounting-export" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide exportul"></button>
  <section class="accounting-modal-card accounting-modal-card-medium" role="dialog" aria-modal="true" aria-labelledby="invoice-export-title">
    <header>
      <div><p class="eyebrow">EXPORT CONTABIL</p><h2 id="invoice-export-title">Alege perioada facturilor</h2><p>Vei primi un ZIP cu registrul Excel și toate fișierele RO e-Factura din interval.</p></div>
      <button type="button" data-close-accounting-modal aria-label="Închide">×</button>
    </header>
    <form method="get" action="<?= e(url('/admin/facturi/export-pachet')) ?>" data-nir-date-range-form>
      <div class="accounting-modal-scroll invoice-export-modal-body">
        <div class="invoice-export-presets">
          <a href="<?= e($exportUrl($today,$today)) ?>"><span>Astăzi</span><small><?= date('d.m.Y') ?></small></a>
          <a href="<?= e($exportUrl($monthStart,$today)) ?>"><span>Luna aceasta</span><small><?= date('d.m',strtotime($monthStart)) ?> – <?= date('d.m.Y') ?></small></a>
          <a href="<?= e($exportUrl($previousMonthStart,$previousMonthEnd)) ?>"><span>Luna trecută</span><small><?= date('d.m',strtotime($previousMonthStart)) ?> – <?= date('d.m.Y',strtotime($previousMonthEnd)) ?></small></a>
          <a href="<?= e($exportUrl($yearStart,$today)) ?>"><span>Anul acesta</span><small><?= date('Y') ?></small></a>
        </div>
        <div class="invoice-export-custom-period">
          <p><strong>Perioadă personalizată</strong><small>Ambele capete ale intervalului sunt incluse în export.</small></p>
          <div class="accounting-modal-form accounting-modal-form-grid">
            <label>De la<input type="date" name="from" max="<?= e($today) ?>" value="<?= e($monthStart) ?>" required></label>
            <label>Până la<input type="date" name="to" max="<?= e($today) ?>" value="<?= e($today) ?>" required></label>
          </div>
          <small class="invoice-export-error" data-nir-date-range-error hidden>Data de început trebuie să fie înaintea datei finale.</small>
        </div>
        <div class="invoice-export-package-note"><span>ZIP</span><p><strong>Ce se descarcă</strong><small>1 registru XLSX + câte 1 XML UBL pentru fiecare factură emisă.</small></p></div>
      </div>
      <footer><button class="admin-button secondary" type="button" data-close-accounting-modal>Anulează</button><button class="admin-button" type="submit">Descarcă pachetul ZIP</button></footer>
    </form>
  </section>
</div>

<div class="accounting-modal" data-accounting-modal="invoice-accounting-email" hidden>
  <button class="accounting-modal-backdrop" type="button" data-close-accounting-modal aria-label="Închide trimiterea"></button>
  <section class="accounting-modal-card accounting-modal-card-medium" role="dialog" aria-modal="true" aria-labelledby="invoice-email-title">
    <header>
      <div><p class="eyebrow">TRIMITERE CONTABILITATE</p><h2 id="invoice-email-title">Trimite facturile emise</h2><p>Alege perioada și adresa destinatarului. Mesajul pleacă prin același profil SMTP folosit pentru facturile către clienți.</p></div>
      <button type="button" data-close-accounting-modal aria-label="Închide">×</button>
    </header>
    <form method="post" action="<?= e(url('/admin/facturi/trimite-contabilitate')) ?>" data-nir-date-range-form><?= csrf_field() ?>
      <div class="accounting-modal-scroll">
        <div class="accounting-modal-form accounting-modal-form-grid">
          <label>De la<input type="date" name="from" max="<?= e($today) ?>" value="<?= e($monthStart) ?>" required></label>
          <label>Până la<input type="date" name="to" max="<?= e($today) ?>" value="<?= e($today) ?>" required></label>
          <small class="span-2" data-nir-date-range-error hidden>Data de început trebuie să fie înaintea datei finale.</small>
          <label class="span-2">Email contabilitate<input type="email" name="recipient" list="invoice-accounting-recipients" placeholder="Alege o adresă salvată sau scrie una nouă" autocomplete="email" required></label>
          <datalist id="invoice-accounting-recipients"><?php foreach($accountingRecipients as $recipient): ?><option value="<?= e($recipient) ?>"><?php endforeach; ?></datalist>
          <label class="span-2">Subiect<input name="subject" maxlength="190" value="<?= e($invoiceMailSubject) ?>" required></label>
          <label class="span-2">Mesaj<textarea name="message" rows="8" maxlength="5000" required><?= e($invoiceMailMessage) ?></textarea><small>Păstrează {PERIOADA} și {CONTINUT} unde vrei să fie completate automat.</small></label>
        </div>
        <div class="invoice-export-package-note"><span>ZIP</span><p><strong>Ce se trimite</strong><small>Registrul XLSX și câte un XML RO e-Factura pentru fiecare factură emisă în perioada aleasă.</small></p></div>
      </div>
      <footer><button class="admin-button secondary" type="button" data-close-accounting-modal>Anulează</button><button class="admin-button" type="submit">Trimite pachetul ZIP</button></footer>
    </form>
  </section>
</div>
