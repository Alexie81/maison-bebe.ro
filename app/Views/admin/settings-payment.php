<section class="admin-page-head"><div><p class="eyebrow">PLĂȚI / <?= e(mb_strtoupper($item['code'])) ?></p><h1><?= e($item['name']) ?></h1><p>Configurează procesatorul și verifică mediul înainte să accepți plăți.</p></div><a class="admin-button secondary" href="<?= e(url('/admin/setari/plati')) ?>">Înapoi</a></section>
<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>
<?php if($item['code']==='stripe'): ?>
<section class="admin-panel stripe-test-status">
  <div><p class="eyebrow">MEDIU DE TEST</p><h2>Stripe Test este <?= !empty($diagnostics['enabled'])&&($diagnostics['key_mode']??'')==='test'&&!($diagnostics['api_livemode']??true)?'pregătit':'de verificat' ?></h2><p>Plățile de aici sunt simulate. Nu se retrag bani reali.</p></div>
  <div class="stripe-status-grid">
    <span><small>Mediu local</small><strong><?= e($diagnostics['environment']??'necunoscut') ?></strong></span>
    <span><small>Cheie API</small><strong><?= e(($diagnostics['key_mode']??'missing')==='test'?'Test':'Lipsește / invalidă') ?></strong></span>
    <span><small>Conexiune Stripe</small><strong><?= !empty($diagnostics['account_id'])?'Conectată':'Eroare' ?></strong></span>
    <span><small>Webhook local</small><strong><?= !empty($diagnostics['webhook_configured'])?'Configurat':'Reconciliere la revenire' ?></strong></span>
  </div>
  <?php if(!empty($diagnostics['error'])): ?><div class="admin-alert error"><?= e($diagnostics['error']) ?></div><?php endif; ?>
  <div class="stripe-test-card"><strong>Card pentru test reușit</strong><code>4242 4242 4242 4242</code><small>Data: orice dată viitoare · CVC: orice 3 cifre · nume și adresă: date de test.</small></div>
</section>
<?php endif; ?>
<form class="admin-panel narrow-panel" method="post" action="<?= e(url('/admin/setari/plati/'.$item['code'])) ?>"><?= csrf_field() ?>
  <div class="admin-form-grid">
    <label>Mediu<select name="environment"><?php foreach(['test','sandbox','live'] as $environment): ?><option <?= $item['environment']===$environment?'selected':'' ?>><?= e($environment) ?></option><?php endforeach; ?></select></label>
    <label>Cheie publică<input name="public_key" autocomplete="off" placeholder="pk_test_..."></label>
    <label>Cheie secretă<input type="password" name="secret_key" autocomplete="new-password" placeholder="<?= $item['credential_id']?'Secret păstrat criptat':'sk_test_...' ?>"></label>
    <label>Secret webhook<input type="password" name="webhook_secret" autocomplete="new-password" placeholder="whsec_... (necesar pe server)"></label>
  </div>
  <label>Webhook URL<input readonly value="<?= e(absolute_url('/webhooks/plati/'.$item['code'])) ?>"></label>
  <label class="check-label"><input type="checkbox" name="is_enabled" value="1" <?= $item['is_enabled']?'checked':'' ?>> Procesator activ</label>
  <button class="admin-button" type="submit">Salvează procesatorul</button>
</form>
<style>.stripe-test-status{display:grid;gap:22px;margin-bottom:20px}.stripe-status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.stripe-status-grid span{display:grid;gap:5px;padding:14px;border:1px solid var(--line);background:var(--ivory)}.stripe-status-grid small{font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}.stripe-status-grid strong{font-size:.8rem}.stripe-test-card{display:grid;grid-template-columns:auto auto 1fr;align-items:center;gap:14px;padding:16px;background:#f1edea;border-left:3px solid #635bff}.stripe-test-card code{font-size:1rem;letter-spacing:.06em}.stripe-test-card small{color:var(--muted)}@media(max-width:760px){.stripe-status-grid{grid-template-columns:1fr 1fr}.stripe-test-card{grid-template-columns:1fr}.stripe-test-card code{font-size:.9rem}}@media(max-width:440px){.stripe-status-grid{grid-template-columns:1fr}}</style>