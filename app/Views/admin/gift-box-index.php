<section class="admin-page-head">
  <div><p class="eyebrow">GIFT BOX</p><h1>Personalizare și cutii</h1><p>Activezi configuratorul și gestionezi separat cutiile disponibile clientului.</p></div>
  <a class="admin-button" href="<?= e(url('/admin/gift-box/cutii/creare')) ?>">Adaugă cutie</a>
</section>
<?php if($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="settings-card-grid">
  <form class="admin-panel" method="post" action="<?= e(url('/admin/gift-box/setari')) ?>"><?= csrf_field() ?>
    <div class="panel-head"><div><p class="eyebrow">WEBSITE</p><h2>Configurator Gift Box</h2></div><span class="status-pill <?= !empty($setting['enabled'])?'success':'' ?>"><?= !empty($setting['enabled'])?'Activ':'Ascuns' ?></span></div>
    <p class="help">Când este dezactivat, personalizarea nu mai apare pe pagina publică și API-ul nu acceptă Gift Box-uri noi.</p>
    <label class="check-label"><input type="checkbox" name="enabled" value="1" <?= !empty($setting['enabled'])?'checked':'' ?>> Afișează configuratorul pe website</label>
    <button class="admin-button" type="submit">Salvează setarea</button>
  </form>
  <article class="admin-panel">
    <div class="panel-head"><div><p class="eyebrow">CUTII</p><h2><?= count($boxes) ?> cutii încărcate</h2></div></div>
    <p class="help">Pentru fiecare cutie setezi prețul, stocul, dimensiunile și câte produse poate alege clientul.</p>
    <a class="admin-button secondary" href="<?= e(url('/admin/gift-box/cutii/creare')) ?>">Încarcă o cutie nouă</a>
  </article>
</section>

<section class="admin-panel">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Cutie</th><th>Slug</th><th>Preț</th><th>Stoc</th><th>Dimensiuni</th><th>Produse permise</th><th>Status</th><th>Acțiuni</th></tr></thead>
      <tbody>
      <?php foreach($boxes as $box): ?>
        <tr>
          <td><a class="admin-product-cell" href="<?= e(url('/admin/gift-box/cutii/'.$box['id'].'/edit')) ?>"><img src="<?= e(url($box['image_path'])) ?>" alt="" width="54" height="54"><strong><?= e($box['name']) ?></strong></a></td>
          <td><?= e($box['slug']) ?></td>
          <td><?= money((int)$box['price_minor']) ?></td>
          <td><?php if(!(int)($box['track_inventory']??1)): ?><span class="status-pill success">Nelimitat</span><?php else: ?><?= (int)$box['current_stock'] ?><?php endif; ?></td>
          <td><?php if((float)($box['length_cm']??0)>0): ?><span class="gift-box-size-badge"><?= e(rtrim(rtrim((string)$box['length_cm'],'0'),'.')) ?> × <?= e(rtrim(rtrim((string)$box['width_cm'],'0'),'.')) ?> × <?= e(rtrim(rtrim((string)$box['height_cm'],'0'),'.')) ?> cm</span><?php else: ?><span class="status-pill">Necompletate</span><?php endif; ?></td>
          <td><?= (int)$box['min_components'] ?>–<?= (int)$box['max_components'] ?></td>
          <td><span class="status-pill <?= $box['is_active']?'success':'' ?>"><?= $box['is_active']?'Activă':'Inactivă' ?></span></td>
          <td><div class="admin-table-actions">
            <a class="admin-icon-action" href="<?= e(url('/admin/gift-box/cutii/'.$box['id'].'/edit')) ?>" title="Editează cutia" aria-label="Editează <?= e($box['name']) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg></a>
            <form method="post" action="<?= e(url('/admin/gift-box/cutii/'.$box['id'].'/sterge')) ?>" data-confirm-delete data-confirm-message="Cutia va fi ascunsă din configurator și arhivată."><?= csrf_field() ?><button class="admin-icon-action danger" type="submit" title="Șterge cutia" aria-label="Șterge <?= e($box['name']) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"/></svg></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$boxes): ?><tr><td colspan="8"><div class="admin-empty">Nu ai încă nicio cutie. Apasă „Adaugă cutie”.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
