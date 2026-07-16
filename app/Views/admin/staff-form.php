<?php
$editing = !empty($user['id']);
$isPrimaryAdmin = !empty($isPrimaryAdmin);
$groups = [
    'dashboard' => 'Prezentare generală', 'orders' => 'Comenzi', 'products' => 'Produse',
    'categories' => 'Categorii și colecții', 'customers' => 'Clienți', 'shipping' => 'Livrare și AWB',
    'billing' => 'Facturare', 'cms' => 'Pagini și texte', 'atelier' => 'Atelier', 'seo' => 'SEO',
    'reports' => 'Rapoarte', 'audit' => 'Audit', 'settings' => 'Setările magazinului',
];
$byGroup = [];
foreach ($permissions as $permission) {
    $prefix = explode('.', $permission['name'], 2)[0];
    $byGroup[$prefix][] = $permission;
}
?>

<section class="admin-page-head">
    <div>
        <p class="eyebrow">ECHIPĂ / <?= $isPrimaryAdmin ? 'ADMINISTRATOR PRINCIPAL' : ($editing ? 'EDITARE' : 'CONT NOU') ?></p>
        <h1><?= $isPrimaryAdmin ? 'Profilul principal' : ($editing ? 'Editează accesul' : 'Utilizator nou') ?></h1>
        <p><?= $isPrimaryAdmin ? 'Poți actualiza propriile date, însă rolul principal și accesul complet rămân protejate.' : 'Poți schimba ulterior orice permisiune sau poți bloca temporar contul.' ?></p>
    </div>
    <a class="admin-button secondary" href="<?= e(url('/admin/utilizatori')) ?>">Înapoi la echipă</a>
</section>

<?php if ($isPrimaryAdmin): ?>
    <div class="staff-primary-notice">
        <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v10H5z"/></svg></span>
        <div>
            <strong>Cont protejat permanent</strong>
            <p>Ceilalți administratori nu pot deschide acest formular, modifica datele, bloca sau șterge administratorul principal, indiferent de permisiunile lor.</p>
        </div>
    </div>
<?php endif; ?>

<form class="admin-form-layout staff-form" method="post" action="<?= e(url($editing ? '/admin/utilizatori/' . $user['id'] : '/admin/utilizatori')) ?>">
    <?= csrf_field() ?>
    <div>
        <section class="admin-panel">
            <p class="eyebrow">PASUL 1</p>
            <h2>Datele persoanei</h2>
            <div class="admin-form-grid">
                <label>Prenume<input name="first_name" required autocomplete="given-name" value="<?= e($user['first_name'] ?? '') ?>"></label>
                <label>Nume<input name="last_name" required autocomplete="family-name" value="<?= e($user['last_name'] ?? '') ?>"></label>
                <label class="wide staff-nickname-field">Poreclă <small>Opțional · va fi afișată în echipă și în bara de administrare</small><input name="nickname" maxlength="100" autocomplete="nickname" placeholder="Ex: Alex, Mia, Atelier" value="<?= e($user['nickname'] ?? '') ?>"></label>
                <label class="wide">Email pentru conectare<input type="email" name="email" required autocomplete="email" value="<?= e($user['email'] ?? '') ?>"></label>
                <label class="wide"><?= $editing ? 'Parolă nouă (lasă gol pentru a o păstra)' : 'Parolă inițială' ?><input type="password" name="password" <?= $editing ? '' : 'required minlength="10"' ?> autocomplete="new-password"></label>
            </div>
        </section>

        <?php if ($isPrimaryAdmin): ?>
            <section class="admin-panel staff-protected-permissions">
                <p class="eyebrow">ACCES PROTEJAT</p>
                <h2>Acces complet la magazin</h2>
                <p>Rolul de administrator principal nu poate fi redus, înlocuit sau eliminat din acest formular.</p>
                <div class="staff-access-lock"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v10H5z"/></svg>Toate permisiunile sunt active permanent</div>
            </section>
        <?php else: ?>
            <section class="admin-panel">
                <p class="eyebrow">PASUL 2</p>
                <div class="panel-head">
                    <div><h2>Ce poate face în magazin</h2><p>Bifează numai zonele de care persoana are nevoie.</p></div>
                    <button class="admin-button secondary compact" type="button" data-permissions-all>Selectează tot</button>
                </div>
                <div class="permission-grid">
                    <?php foreach ($byGroup as $prefix => $entries): ?>
                        <fieldset>
                            <legend><?= e($groups[$prefix] ?? ucfirst($prefix)) ?></legend>
                            <?php foreach ($entries as $permission): ?>
                                <label class="permission-check"><input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>" <?= in_array((int) $permission['id'], $selected, true) ? 'checked' : '' ?>><span><strong><?= e($permission['label']) ?></strong><small><?= e($permission['name']) ?></small></span></label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside>
        <section class="admin-panel staff-publish">
            <p class="eyebrow">PASUL <?= $isPrimaryAdmin ? '2' : '3' ?></p>
            <h2>Starea contului</h2>
            <?php if ($isPrimaryAdmin): ?>
                <input type="hidden" name="status" value="active">
                <div class="staff-primary-status"><span></span><div><strong>Activ permanent</strong><small>Contul principal nu poate fi blocat.</small></div></div>
            <?php else: ?>
                <label>Status<select name="status"><option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activ — se poate conecta</option><option value="blocked" <?= ($user['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocat — acces oprit</option></select></label>
            <?php endif; ?>
            <div class="staff-safety <?= $isPrimaryAdmin ? 'is-protected' : '' ?>"><strong><?= $isPrimaryAdmin ? 'Protecție activă' : 'Recomandare' ?></strong><p><?= $isPrimaryAdmin ? 'Doar administratorul principal își poate actualiza propriul profil.' : 'Nu oferi acces la setările magazinului decât unei persoane de încredere.' ?></p></div>
            <button class="admin-button" type="submit"><?= $isPrimaryAdmin ? 'Salvează profilul' : ($editing ? 'Salvează modificările' : 'Creează utilizatorul') ?></button>
        </section>
    </aside>
</form>

<?php if (!$isPrimaryAdmin): ?>
<script>
document.querySelector('[data-permissions-all]')?.addEventListener('click', event => {
    const boxes = [...document.querySelectorAll('input[name="permissions[]"]')];
    const all = boxes.every(box => box.checked);
    boxes.forEach(box => box.checked = !all);
    event.currentTarget.textContent = all ? 'Selectează tot' : 'Deselectează tot';
});
</script>
<?php endif; ?>