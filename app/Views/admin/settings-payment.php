<section class="admin-page-head"><div><p class="eyebrow">PLÄ‚ÈšI / <?= e(mb_strtoupper($item['code'])) ?></p><h1><?= e($item['name']) ?></h1><p>ConfigureazÄƒ procesatorul È™i verificÄƒ mediul Ã®nainte sÄƒ accepÈ›i plÄƒÈ›i.</p></div><a class="admin-button secondary" href="<?= e(url('/admin/setari/plati')) ?>">ÃŽnapoi</a></section>
<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>
<?php if($item['code']==='stripe'): ?>
<section class="admin-panel stripe-test-status">
  <div><p class="eyebrow">PLĂȚI ONLINE</p><h2>Stripe este <?= !empty($diagnostics['enabled'])&&($diagnostics['key_mode']??'')==='live'?'activ':'de verificat' ?></h2><p>Plățile online sunt procesate în modul live și confirmate automat.</p></div>
  <div class="stripe-status-grid">
    <span><small>Mediu local</small><strong><?= e($diagnostics['environment']??'necunoscut') ?></strong></span>
    <span><small>Cheie API</small><strong><?= e(($diagnostics['key_mode']??'missing')==='live'?'Live':'Lipsește / invalidă') ?></strong></span>
    <span><small>Conexiune Stripe</small><strong><?= !empty($diagnostics['account_id'])?'ConectatÄƒ':'Eroare' ?></strong></span>
    <span><small>Webhook local</small><strong><?= !empty($diagnostics['webhook_configured'])?'Configurat':'Reconciliere la revenire' ?></strong></span>
    <span><small>Apple Pay</small><strong><?= in_array($diagnostics['wallets']['apple_pay']??'', ['available','on'], true)?'Activ':'De activat' ?></strong></span>
    <span><small>Google Pay</small><strong><?= in_array($diagnostics['wallets']['google_pay']??'', ['available','on'], true)?'Activ':'De activat' ?></strong></span>
  </div>
  <?php if(!empty($diagnostics['error'])): ?><div class="admin-alert error"><?= e($diagnostics['error']) ?></div><?php endif; ?>
  
  <form method="post" action="<?= e(url('/admin/setari/plati/stripe/wallets')) ?>" class="stripe-wallet-action"><?= csrf_field() ?><div><strong>PlÄƒÈ›i rapide pe telefon</strong><small>Stripe afiÈ™eazÄƒ wallet-ul numai pe dispozitivele compatibile.</small></div><button class="admin-button secondary" type="submit">ActiveazÄƒ Apple Pay È™i Google Pay</button></form>
</section>
<?php endif; ?>
<form class="admin-panel narrow-panel" method="post" action="<?= e(url('/admin/setari/plati/'.$item['code'])) ?>"><?= csrf_field() ?>
  <div class="admin-form-grid">
    <label>Mediu<select name="environment"><?php foreach(['live'] as $environment): ?><option <?= $item['environment']===$environment?'selected':'' ?>><?= e($environment) ?></option><?php endforeach; ?></select></label>
    <label>Cheie publicÄƒ<input name="public_key" autocomplete="off" placeholder="pk_live_..."></label>
    <label>Cheie secretÄƒ<input type="password" name="secret_key" autocomplete="new-password" placeholder="<?= $item['credential_id']?'Secret pÄƒstrat criptat':'sk_live_...' ?>"></label>
    <label>Secret webhook<input type="password" name="webhook_secret" autocomplete="new-password" placeholder="whsec_... (necesar pe server)"></label>
  </div>
  <label>Webhook URL<input readonly value="<?= e(absolute_url('/webhooks/plati/'.$item['code'])) ?>"></label>
  <label class="check-label"><input type="checkbox" name="is_enabled" value="1" <?= $item['is_enabled']?'checked':'' ?>> Procesator activ</label>
  <button class="admin-button" type="submit">SalveazÄƒ procesatorul</button>
</form>
<style>.stripe-test-status{display:grid;gap:22px;margin-bottom:20px}.stripe-status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.stripe-status-grid span{display:grid;gap:5px;padding:14px;border:1px solid var(--line);background:var(--ivory)}.stripe-status-grid small{font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}.stripe-status-grid strong{font-size:.8rem}.stripe-test-card{display:grid;grid-template-columns:auto auto 1fr;align-items:center;gap:14px;padding:16px;background:#f1edea;border-left:3px solid #635bff}.stripe-test-card code{font-size:1rem;letter-spacing:.06em}.stripe-test-card small{color:var(--muted)}.stripe-wallet-action{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px;border:1px solid var(--line);background:#fff}.stripe-wallet-action div{display:grid;gap:4px}.stripe-wallet-action small{color:var(--muted)}@media(max-width:760px){.stripe-wallet-action{align-items:stretch;flex-direction:column}.stripe-wallet-action .admin-button{width:100%}.stripe-status-grid{grid-template-columns:1fr 1fr}.stripe-test-card{grid-template-columns:1fr}.stripe-test-card code{font-size:.9rem}}@media(max-width:440px){.stripe-status-grid{grid-template-columns:1fr}}</style>