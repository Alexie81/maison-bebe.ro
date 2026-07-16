<section class="admin-page-head">
    <div>
        <p class="eyebrow">ECHIPĂ ȘI ACCES</p>
        <h1>Utilizatori administrativi</h1>
        <p>Adaugi persoane care lucrează în magazin și alegi exact ce pot vedea sau modifica.</p>
    </div>
    <a class="admin-button" href="<?= e(url('/admin/utilizatori/creare')) ?>">+ Utilizator nou</a>
</section>

<?php if ($notice): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>

<section class="admin-panel">
    <div class="staff-explainer">
        <span>1</span><p><strong>Creezi contul</strong><small>Nume, poreclă, email și parolă inițială.</small></p>
        <span>2</span><p><strong>Alegi accesul</strong><small>Bifezi numai activitățile necesare.</small></p>
        <span>3</span><p><strong>Protejezi echipa</strong><small>Administratorul principal nu poate fi modificat de ceilalți.</small></p>
    </div>
</section>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table admin-table-cards staff-table">
            <thead><tr><th>Utilizator</th><th>Rol / acces</th><th>Status</th><th>Ultima conectare</th><th>Acțiuni</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                $isPrimary = !empty($item['is_primary_admin']);
                $isCurrent = (int) $item['id'] === MaisonBebe\Core\Auth::id();
                $canEdit = !$isPrimary || $isCurrent;
            ?>
                <tr class="<?= $isPrimary ? 'staff-primary-row' : '' ?>">
                    <td data-label="Utilizator">
                        <div class="staff-identity">
                            <strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong>
                            <?php if (!empty($item['nickname'])): ?><span class="staff-nickname"><?= e($item['nickname']) ?></span><?php endif; ?>
                            <small><?= e($item['email']) ?></small>
                        </div>
                    </td>
                    <td data-label="Acces">
                        <?php if ($isPrimary): ?>
                            <span class="staff-primary-badge"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v10H5z"/></svg>Administrator principal</span>
                        <?php else: ?>
                            <?= e($item['roles'] ?: 'Acces personalizat') ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Status"><span class="status-pill <?= $item['status'] === 'active' ? 'success' : '' ?>"><?= $item['status'] === 'active' ? 'Activ' : 'Blocat' ?></span></td>
                    <td data-label="Ultima conectare"><?= $item['last_login_at'] ? date('d.m.Y H:i', strtotime($item['last_login_at'])) : 'Nu s-a conectat încă' ?></td>
                    <td data-label="Acțiuni">
                        <div class="admin-table-actions">
                            <?php if ($canEdit): ?>
                                <a class="admin-icon-action" href="<?= e(url('/admin/utilizatori/' . $item['id'] . '/edit')) ?>" title="<?= $isPrimary ? 'Editează profilul propriu' : 'Editează' ?>" aria-label="<?= $isPrimary ? 'Editează profilul administratorului principal' : 'Editează utilizatorul' ?>"><svg viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg></a>
                            <?php else: ?>
                                <span class="staff-protected-action" title="Cont protejat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v10H5z"/></svg>Protejat</span>
                            <?php endif; ?>

                            <?php if (!$isPrimary && !$isCurrent): ?>
                                <form method="post" action="<?= e(url('/admin/utilizatori/' . $item['id'] . '/status')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="admin-button secondary compact" type="submit"><?= $item['status'] === 'active' ? 'Blochează' : 'Reactivează' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="5">Nu există încă utilizatori administrativi.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>