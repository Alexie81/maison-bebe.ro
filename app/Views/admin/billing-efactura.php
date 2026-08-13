<?php
$statusLabels = [
    'not_configured' => 'Neautorizată',
    'connected' => 'Conectată',
    'expired' => 'Expirată',
    'error' => 'Eroare',
];
$submissionLabels = [
    'pending' => 'În așteptare',
    'uploading' => 'Se transmite',
    'processing' => 'În verificare ANAF',
    'accepted' => 'Acceptată',
    'rejected' => 'Respinsă',
    'retry' => 'Se reîncearcă',
    'requires_attention' => 'Necesită atenție',
];
?>
<section class="admin-page-head accounting-page-head accounting-page-head-compact">
  <div>
    <p class="eyebrow">FINANCIAR · SPV</p>
    <h1>RO e-Factura</h1>
    <p>Autorizare OAuth, transmitere automată și urmărirea răspunsurilor ANAF.</p>
  </div>
  <a class="admin-button secondary" href="<?= e(url('/admin/facturi')) ?>">Vezi facturile</a>
</section>

<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="invoice-export-explainer efactura-flow" aria-label="Flux RO e-Factura">
  <article><span>1</span><div><strong>Configurezi aplicația</strong><small>Client ID și secretul sunt păstrate criptat.</small></div></article>
  <article><span>2</span><div><strong>Autorizezi certificatul</strong><small>Conectarea se face direct în portalul securizat ANAF.</small></div></article>
  <article><span>3</span><div><strong>Urmărești răspunsul</strong><small>Documentul este acceptat numai după confirmarea ANAF.</small></div></article>
</section>

<div class="admin-two-columns efactura-layout">
  <section class="admin-panel">
    <div class="panel-head"><div><h2>Conexiuni ANAF</h2><p class="help">Producția este folosită la trimiterea documentelor reale în SPV.</p></div></div>
    <div class="efactura-connection-list">
      <?php foreach($connections as $item): $config=json_decode((string)($item['config_json']??'{}'),true)?:[]; ?>
        <article class="efactura-connection-card <?= $item['status']==='connected'?'is-connected':'' ?>">
          <div class="efactura-connection-head">
            <span class="efactura-environment"><?= $item['environment']==='production'?'PRODUCȚIE':'TEST' ?></span>
            <span class="status-pill <?= $item['status']==='connected'?'success':($item['status']==='error'?'danger':'') ?>"><?= e($statusLabels[$item['status']]??$item['status']) ?></span>
          </div>
          <strong><?= e($item['legal_name']) ?></strong>
          <small>Client ID: <?= e(!empty($config['client_id'])?$config['client_id']:'necompletat') ?></small>
          <small>Ultima sincronizare: <?= $item['last_sync_at']?date('d.m.Y H:i',strtotime($item['last_sync_at'])):'—' ?></small>
          <?php if(!empty($item['last_error'])): ?><p class="efactura-error"><?= e($item['last_error']) ?></p><?php endif; ?>
          <a class="admin-button <?= $item['status']==='connected'?'secondary':'' ?>" href="<?= e(url('/admin/facturare/efactura/conectare/'.$item['id'])) ?>">
            <?= $item['status']==='connected'?'Reautorizează certificatul':'Conectează certificatul' ?>
          </a>
        </article>
      <?php endforeach; ?>
      <?php if(!$connections): ?><div class="admin-empty"><strong>Nu există nicio conexiune.</strong><p>Completează datele aplicației ANAF din panoul alăturat.</p></div><?php endif; ?>
    </div>
  </section>

  <aside>
    <form class="admin-panel efactura-config-form" method="post" action="<?= e(url('/admin/facturare/efactura')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <p class="eyebrow">CONFIGURARE OAUTH</p>
      <h2>Date aplicație ANAF</h2>
      <p class="help">La salvarea unui secret nou, autorizarea veche este invalidată pentru siguranță.</p>
      <label>Mediu
        <select name="environment"><option value="production">Producție</option><option value="test">Test</option></select>
      </label>
      <label>Client ID ANAF<input name="client_id" autocomplete="off" placeholder="Client ID din portalul ANAF"></label>
      <label>Client Secret ANAF<input type="password" name="client_secret" autocomplete="new-password" placeholder="Se salvează criptat"></label>
      <label>Redirect URI<input readonly value="<?= e(absolute_url('/admin/facturare/efactura/callback')) ?>"></label>
      <button class="admin-button" type="submit">Salvează configurația</button>
    </form>
  </aside>
</div>

<section class="admin-panel">
  <div class="panel-head"><div><h2>Transmiteri recente</h2><p class="help">Istoricul nu marchează nimic ca acceptat până când ANAF nu confirmă documentul.</p></div></div>
  <div class="admin-table-wrap"><table class="admin-table efactura-submissions-table">
    <thead><tr><th>Document</th><th>Status</th><th>Index încărcare</th><th>Încercări</th><th>Eroare / detalii</th><th>Actualizat</th></tr></thead>
    <tbody>
      <?php foreach($submissions as $item): ?>
        <tr>
          <td><a href="<?= e(url('/admin/facturi/'.$item['invoice_id'])) ?>"><strong><?= e($item['invoice_number']) ?></strong></a></td>
          <td><span class="status-pill <?= $item['status']==='accepted'?'success':(in_array($item['status'],['rejected','requires_attention'],true)?'danger':'') ?>"><?= e($submissionLabels[$item['status']]??$item['status']) ?></span></td>
          <td><?= e($item['upload_id']?:'—') ?></td><td><?= (int)$item['attempts'] ?></td>
          <td class="efactura-submission-error"><?= e($item['last_error']?:'—') ?></td>
          <td><?= date('d.m.Y H:i',strtotime($item['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$submissions): ?><tr><td colspan="6"><div class="admin-empty">Nu există încă documente trimise în SPV.</div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
