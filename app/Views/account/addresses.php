<section class="account-page shell section-space-small">
    <div class="account-head"><p class="eyebrow">LIVRARE ȘI FACTURARE</p><h1>Adresele mele</h1></div>
    <div class="account-layout">
        <?php require BASE_PATH.'/app/Views/account/nav.php'; ?>
        <div class="account-content">
            <section class="account-panel address-book-panel">
                <div class="address-book-head">
                    <div><p class="eyebrow">AGENDA TA</p><h2>Adrese salvate</h2></div>
                    <button class="address-add-button" type="button" data-address-new data-open-modal="address-modal" aria-label="Adaugă o adresă nouă" title="Adaugă adresă"><span aria-hidden="true">+</span></button>
                </div>

                <?php if (!$addresses): ?>
                    <div class="empty-state compact"><p>Nu ai încă nicio adresă salvată.</p><button class="button" type="button" data-address-new data-open-modal="address-modal">Adaugă prima adresă</button></div>
                <?php else: ?>
                    <div class="address-grid">
                        <?php foreach ($addresses as $address):
                            $addressPayload = json_encode([
                                'name'=>$address['name'],
                                'contact_first_name'=>$address['contact_first_name'] ?: (auth_user()['first_name'] ?? ''),
                                'contact_last_name'=>$address['contact_last_name'] ?: (auth_user()['last_name'] ?? ''),
                                'phone'=>$address['phone'] ?? '',
                                'line1'=>$address['line1'],
                                'line2'=>$address['line2'] ?? '',
                                'city'=>$address['city'],
                                'county'=>$address['county'] ?? '',
                                'postal_code'=>$address['postal_code'] ?? '',
                                'is_default'=>(bool)$address['is_default'],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        ?>
                            <article class="address-card">
                                <button class="address-edit-button" type="button" data-address-edit data-address="<?= e($addressPayload) ?>" data-action="<?= e(url('/cont/adrese/'.$address['id'])) ?>" data-open-modal="address-modal" aria-label="Editează adresa <?= e($address['name']) ?>" title="Editează adresa">
                                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
                                </button>
                                <strong><?= e(trim(($address['contact_first_name'] ?: (auth_user()['first_name'] ?? '')).' '.($address['contact_last_name'] ?: (auth_user()['last_name'] ?? '')))) ?></strong>
                                <small><?= e($address['name']) ?><?= !empty($address['phone']) ? ' · '.e($address['phone']) : '' ?></small>
                                <p><?= e($address['line1']) ?><?php if (!empty($address['line2'])): ?><br><?= e($address['line2']) ?><?php endif; ?><br><?= e($address['city']) ?><?= !empty($address['county']) ? ', '.e($address['county']) : '' ?><?= !empty($address['postal_code']) ? ', '.e($address['postal_code']) : '' ?></p>
                                <?php if ($address['is_default']): ?><span>Implicită</span><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<div class="modal-layer address-modal-layer" id="address-modal" role="dialog" aria-modal="true" aria-labelledby="address-modal-title" hidden>
    <button class="modal-backdrop" type="button" data-close-modal aria-label="Închide formularul"></button>
    <section class="modal-panel address-modal" tabindex="-1">
        <button class="modal-close" type="button" data-close-modal aria-label="Închide">×</button>
        <p class="eyebrow">LIVRARE ȘI FACTURARE</p>
        <h2 id="address-modal-title" data-address-modal-title>Adaugă o adresă</h2>
        <p class="address-modal-intro">Păstrează datele de livrare pentru o finalizare mai rapidă a comenzilor.</p>

        <form method="post" action="<?= e(url('/cont/adrese')) ?>" data-address-form data-create-action="<?= e(url('/cont/adrese')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>Prenume persoană de contact<input name="contact_first_name" required value="<?= e(auth_user()['first_name'] ?? '') ?>" autocomplete="given-name"></label>
                <label>Nume persoană de contact<input name="contact_last_name" required value="<?= e(auth_user()['last_name'] ?? '') ?>" autocomplete="family-name"></label>
                <label>Etichetă adresă<input name="name" required placeholder="Acasă"></label>
                <label>Telefon<input name="phone" type="tel" inputmode="tel" autocomplete="tel"></label>
                <label class="span-2">Adresă<input name="line1" required autocomplete="street-address"></label>
                <label class="span-2">Detalii adresă <small>(opțional)</small><input name="line2" placeholder="Bloc, scară, apartament"></label>
                <label>Localitate<input name="city" required autocomplete="address-level2"></label>
                <label>Județ<input name="county" autocomplete="address-level1"></label>
                <label>Cod poștal<input name="postal_code" inputmode="numeric" autocomplete="postal-code"></label>
                <label class="toggle-switch address-default-switch"><input type="checkbox" name="is_default" value="1"><span class="switch-track" aria-hidden="true"><i></i></span><b>Adresă implicită</b></label>
            </div>
            <button class="button" type="submit" data-address-submit>Salvează adresa</button>
        </form>
    </section>
</div>