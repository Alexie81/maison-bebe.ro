<?php
$statusLabels = [
    'new' => 'Comandă nouă',
    'confirmed' => 'Confirmată',
    'processing' => 'În pregătire',
    'ready_for_shipping' => 'Pregătită pentru curier',
    'shipped' => 'Expediată',
    'delivered' => 'Livrată',
    'cancelled' => 'Anulată',
    'return_requested' => 'Retur solicitat',
    'returned' => 'Returnată',
    'partially_refunded' => 'Rambursată parțial',
    'refunded' => 'Rambursată',
];
$statusMessages = [
    'confirmed' => 'Comanda a fost confirmată și intră în pregătire.',
    'processing' => 'Pregătim cu grijă produsele din comandă.',
    'ready_for_shipping' => 'Comanda este ambalată și pregătită pentru curier.',
    'shipped' => 'Comanda a plecat către tine.',
    'delivered' => 'Comanda a fost livrată. Îți mulțumim!',
    'cancelled' => 'Comanda a fost anulată.',
    'return_requested' => 'Solicitarea de retur a fost înregistrată.',
    'returned' => 'Produsele returnate au ajuns la noi.',
    'partially_refunded' => 'O parte din valoarea comenzii a fost rambursată.',
    'refunded' => 'Valoarea comenzii a fost rambursată.',
];
$allowed = [
    'new' => ['confirmed', 'cancelled'],
    'confirmed' => ['processing', 'cancelled'],
    'processing' => ['ready_for_shipping', 'cancelled'],
    'ready_for_shipping' => ['shipped', 'cancelled'],
    'shipped' => ['delivered', 'returned'],
    'delivered' => ['return_requested', 'partially_refunded', 'refunded'],
    'return_requested' => ['returned'],
    'returned' => ['refunded'],
];
$mainSteps = ['new', 'confirmed', 'processing', 'ready_for_shipping', 'shipped', 'delivered'];
$currentStatus = (string) $order['order_status'];
$currentIndex = array_search($currentStatus, $mainSteps, true);
$nextStatuses = $allowed[$currentStatus] ?? [];
$isCod = (string) $order['payment_method'] === 'cod';
$isPaid = (string) $order['payment_status'] === 'paid';
$paymentLabels = [
    'paid' => 'Plătită',
    'unpaid' => 'Neplătită',
    'pending' => 'În așteptare',
    'failed' => 'Eșuată',
    'refunded' => 'Rambursată',
    'partially_refunded' => 'Rambursată parțial',
];
?>

<section class="admin-page-head order-page-head">
    <div>
        <p class="eyebrow">COMANDĂ</p>
        <h1><?= e($order['order_number']) ?></h1>
        <p><?= e($order['email']) ?> · <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
    </div>
    <div class="button-row">
        <button class="admin-button" type="button" data-invoice-modal-open><?= $invoiceState ? 'Gestionează factura' : 'Emite factura' ?></button>
        <a class="admin-button secondary" href="<?= e(url('/admin/comenzi/' . $order['id'] . '/awb')) ?>">Livrare și AWB</a>
    </div>
</section>

<?php if (!empty($notice)): ?><div class="admin-alert success"><?= e($notice) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>

<section class="order-status-hero status-<?= e($currentStatus) ?>">
    <div>
        <p class="eyebrow">STATUS CURENT</p>
        <h2><?= e($statusLabels[$currentStatus] ?? $currentStatus) ?></h2>
        <p><?= e($statusMessages[$currentStatus] ?? 'Comanda este în curs de procesare.') ?></p>
    </div>
    <span><?= e(mb_strtoupper($statusLabels[$currentStatus] ?? $currentStatus)) ?></span>
</section>

<nav class="order-progress" aria-label="Progres comandă">
    <?php foreach ($mainSteps as $index => $step):
        $state = $currentIndex === false ? 'upcoming' : ($index <= $currentIndex ? 'done' : ($index === $currentIndex + 1 ? 'current' : 'upcoming'));
    ?>
        <div class="<?= e($state) ?>">
            <i><?= $state === 'done' ? '✓' : $index + 1 ?></i>
            <span><?= e($statusLabels[$step]) ?></span>
        </div>
    <?php endforeach; ?>
</nav>

<div class="admin-two-columns order-admin-layout">
    <div>
        <section class="admin-panel">
            <div class="panel-head"><h2>Produse comandate</h2><span class="order-item-count"><?= count($items) ?> poziții</span></div>
            <?php foreach ($items as $item): $itemOptions=json_decode((string)($item['options_json']??''),true)?:[];$itemCustomization=json_decode((string)($item['customization_json']??''),true)?:[];$giftBoxRole=(string)($itemCustomization['role']??'');$optionalChoices=(array)($itemCustomization['optional_variants']??[]);$personalization=(array)($itemCustomization['personalization']??[]);$personalizationChoices=(array)($personalization['options']??[]); ?>
                <div class="admin-order-item<?= $giftBoxRole==='box'?' is-gift-box-line':'' ?>">
                    <div><strong><?= e($giftBoxRole==='box'?($itemCustomization['template_name']??$item['name_snapshot']):$item['name_snapshot']) ?></strong><small><?= e($item['sku_snapshot']) ?> · <?= (int) $item['quantity'] ?> buc.</small><div class="admin-order-item-specs"><?php if(!empty($itemOptions['label'])&&$itemOptions['label']!=='Standard'): ?><span>Variantă: <b><?= e($itemOptions['label']) ?></b></span><?php endif; ?><?php if($giftBoxRole==='box'): ?><span class="gift">Împachetare: <b><?= e($itemCustomization['template_name']??$item['name_snapshot']) ?></b></span><?php elseif($giftBoxRole==='component'): ?><span class="gift">Cutie cadou: <b><?= e($itemCustomization['template_name']??'Selectată') ?></b></span><?php endif; ?><?php if(!empty($itemCustomization['recipient_name'])): ?><span>Destinatar: <b><?= e($itemCustomization['recipient_name']) ?></b></span><?php endif; ?><?php if(!empty($itemCustomization['gift_message'])): ?><span>Mesaj în cutie: <b>„<?= e($itemCustomization['gift_message']) ?>”</b></span><?php endif; ?><?php foreach($optionalChoices as $optionalChoice): ?><span>Opțiune: <b><?= e($optionalChoice['name']??'') ?><?= (int)($optionalChoice['price_delta_minor']??0)>0?' (+ '.money((int)$optionalChoice['price_delta_minor']).')':'' ?></b></span><?php endforeach; ?><?php if($personalization): ?><?php if($personalizationChoices): ?><?php foreach($personalizationChoices as $personalizationChoice): ?><span class="personalization">Personalizare: <b><?= e($personalizationChoice['option_name']??'') ?><?= (int)($personalizationChoice['price_delta_minor']??0)>0?' (+ '.money((int)$personalizationChoice['price_delta_minor']).')':'' ?></b></span><?php endforeach; ?><?php else: ?><span class="personalization">Personalizare: <b><?= e($personalization['option_name']??'') ?><?= (int)($personalization['price_delta_minor']??0)>0?' (+ '.money((int)$personalization['price_delta_minor']).')':'' ?></b></span><?php endif; ?><span class="personalization">Nume copil: <b><?= e($personalization['child_name']??'') ?></b></span><span class="personalization">Data botezului/nașterii: <b><?= e($personalization['birth_date_formatted']??'') ?></b></span><?php endif; ?></div><?php if($personalization): ?><div class="admin-personalization-instructions"><small>MESAJ PENTRU ATELIER</small><strong><?= e($personalization['instructions']??'') ?></strong></div><?php endif; ?></div>
                    <b><?= money($item['total_minor']) ?></b>
                </div>
            <?php endforeach; ?>
            <?php if(!empty($order['gift_message'])): ?><div class="admin-order-gift-message"><small>MESAJ CADOU AL COMENZII</small><p>„<?= nl2br(e($order['gift_message'])) ?>”</p></div><?php endif; ?>
            <div class="admin-order-total"><span>Total</span><strong><?= money($order['grand_total_minor']) ?></strong></div>
        </section>

        <section class="admin-panel">
            <div class="panel-head"><h2>Istoric și note</h2><small>Cele mai noi apar primele</small></div>
            <ol class="admin-timeline order-history">
                <?php foreach ($history as $event): ?>
                    <li>
                        <strong><?= e($statusLabels[$event['new_status']] ?? $event['new_status']) ?></strong>
                        <span><?= e($event['public_message']) ?></span>
                        <time><?= date('d.m.Y H:i', strtotime($event['created_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php foreach ($notes as $note): ?>
                <blockquote class="internal-note">
                    <p><?= e($note['note']) ?></p>
                    <small><?= e($note['author'] ?? 'Sistem') ?> · <?= date('d.m.Y H:i', strtotime($note['created_at'])) ?></small>
                </blockquote>
            <?php endforeach; ?>
        </section>
    </div>

    <aside class="order-admin-sidebar">
        <section class="admin-panel order-status-panel">
            <div class="panel-head"><div><p class="eyebrow">URMĂTORUL PAS</p><h2>Actualizează statusul</h2></div></div>
            <?php if ($nextStatuses): ?>
                <form method="post" action="<?= e(url('/admin/comenzi/' . $order['id'] . '/status')) ?>" data-order-status-form>
                    <?= csrf_field() ?>
                    <fieldset class="order-status-choices">
                        <legend>Alege următoarea etapă</legend>
                        <?php foreach ($nextStatuses as $index => $status): ?>
                            <label class="<?= in_array($status, ['cancelled', 'returned', 'refunded'], true) ? 'is-exception' : '' ?>">
                                <input type="radio" name="status" value="<?= e($status) ?>" data-status-message="<?= e($statusMessages[$status] ?? '') ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                <span><i></i><strong><?= e($statusLabels[$status] ?? $status) ?></strong><small><?= e($statusMessages[$status] ?? '') ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label>Mesaj vizibil clientului<textarea name="public_message" rows="3" placeholder="<?= e($statusMessages[$nextStatuses[0]] ?? '') ?>" data-order-public-message></textarea></label>
                    <label>Notă internă <small>(nu este trimisă clientului)</small><textarea name="internal_note" rows="3"></textarea></label>
                    <label class="toggle-switch order-notify-switch"><input type="checkbox" name="notify" value="1" checked><span class="switch-track"><i></i></span><b>Trimite notificare clientului prin email</b></label>
                    <button class="admin-button order-status-submit" type="submit">Confirmă schimbarea statusului</button>
                </form>
            <?php else: ?>
                <div class="order-status-complete"><span>✓</span><strong>Nu există o etapă următoare disponibilă</strong><p>Statusul curent este final sau necesită o operațiune financiară separată.</p></div>
            <?php endif; ?>
        </section>

        <?php if ($isCod): ?>
            <section class="admin-panel cod-payment-panel <?= $isPaid ? 'is-paid' : 'is-unpaid' ?>">
                <div class="cod-payment-heading">
                    <div><p class="eyebrow">RAMBURS</p><h2>Încasarea comenzii</h2></div>
                    <span class="cod-payment-badge"><?= $isPaid ? 'Încasată' : 'Neîncasată' ?></span>
                </div>
                <form method="post" action="<?= e(url('/admin/comenzi/' . $order['id'] . '/plata-ramburs')) ?>">
                    <?= csrf_field() ?>
                    <label class="cod-payment-toggle">
                        <input type="checkbox" name="payment_received" value="1" <?= $isPaid ? 'checked' : '' ?>>
                        <span class="cod-payment-switch" aria-hidden="true"><i></i></span>
                        <span><strong>Plata a fost încasată</strong><small>Poți bifa sau debifa manual. La marcarea comenzii ca „Livrată”, plata se confirmă automat.</small></span>
                    </label>
                    <?php if ($invoiceState): ?>
                        <p class="cod-payment-invoice-note">După schimbarea plății, folosește „Gestionează factura” pentru a regenera PDF-ul cu mențiunea Plătită sau Neplătită.</p>
                    <?php else: ?>
                        <p class="cod-payment-invoice-note">Factura va prelua automat starea plății din momentul emiterii.</p>
                    <?php endif; ?>
                    <button class="admin-button" type="submit">Salvează starea plății</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="admin-panel">
            <h2>Client și plată</h2>
            <dl class="admin-details">
                <dt>Tip client</dt><dd><?= e($order['customer_type']) ?></dd>
                <dt>Tip plată</dt><dd><span class="status-pill <?= $isPaid ? 'success' : '' ?>"><?= $isCod ? 'Ramburs la curier' : ($isPaid ? 'Card — plătită' : 'Card — în așteptare') ?></span></dd>
                <dt>Stare plată</dt><dd><?= e($paymentLabels[$order['payment_status']] ?? $order['payment_status']) ?></dd>
                <dt>Livrare</dt><dd><?= e(['courier' => 'Curier', 'pickup' => 'Ridicare personală', 'manual' => 'Curier'][$order['shipping_method']] ?? ucfirst((string) $order['shipping_method'])) ?></dd>
                <?php if ($shipment): ?><dt>AWB</dt><dd><?= e($shipment['awb'] ?: 'În pregătire') ?></dd><?php endif; ?>
            </dl>
        </section>
    </aside>
</div>

<div class="invoice-action-modal" data-invoice-action-modal hidden>
    <button class="invoice-action-backdrop" type="button" data-invoice-modal-close aria-label="Închide"></button>
    <section class="invoice-action-dialog" role="dialog" aria-modal="true" aria-labelledby="invoice-action-title">
        <header>
            <div>
                <p class="eyebrow">FACTURARE</p>
                <h2 id="invoice-action-title"><?= $invoiceState ? 'Factura ' . e($invoiceState['number']) : 'Emite factura comenzii' ?></h2>
                <p><?= $invoiceState ? 'Factura este deja emisă. O poți regenera cu starea actuală a plății sau descărca.' : 'Verifică opțiunile înainte de emitere.' ?></p>
            </div>
            <button class="coupon-picker-close" type="button" data-invoice-modal-close aria-label="Închide">×</button>
        </header>
        <?php if ($invoiceState): ?>
            <div class="invoice-action-status">
                <span class="status-pill success">Emisă</span>
                <div><small>PLATĂ CURENTĂ</small><strong><?= $isPaid ? 'Plătită' : 'Neplătită' ?> · <?= $isCod ? 'Ramburs' : 'Card' ?></strong></div>
                <div><small>EMAIL CLIENT</small><strong><?= $invoiceState['email_status'] === 'sent' ? 'Trimis' . ($invoiceState['email_sent_at'] ? ' la ' . date('d.m.Y H:i', strtotime($invoiceState['email_sent_at'])) : '') : (($invoiceState['email_status'] ?? '') === 'pending' ? 'În așteptare' : 'Netrimis') ?></strong></div>
            </div>
            <p class="invoice-action-help">Regenerarea păstrează seria și numărul facturii, dar actualizează PDF-ul cu starea curentă a plății.</p>
            <div class="invoice-action-buttons invoice-existing-actions">
                <a class="admin-button secondary" target="_blank" href="<?= e(url('/factura/' . $invoiceState['document_hash'])) ?>">Deschide PDF</a>
                <a class="admin-button secondary" href="<?= e(url('/admin/facturi/' . $invoiceState['id'] . '/ubl')) ?>">Descarcă XML UBL</a>
                <form method="post" action="<?= e(url('/admin/comenzi/' . $order['id'] . '/factura')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="send_email" value="0">
                    <button class="admin-button secondary" type="submit">Regenerează doar PDF-ul</button>
                </form>
                <form method="post" action="<?= e(url('/admin/comenzi/' . $order['id'] . '/factura')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="send_email" value="1">
                    <button class="admin-button" type="submit">Regenerează și trimite clientului</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/comenzi/' . $order['id'] . '/factura')) ?>">
                <?= csrf_field() ?>
                <label class="invoice-send-choice"><input type="checkbox" name="send_email" value="1" checked><span><strong>Trimite factura clientului prin email</strong><small>Clientul primește un email optimizat pentru telefon cu buton către PDF.</small></span></label>
                <p class="invoice-action-help">Factura va afișa <?= $isPaid ? 'Plătită' : 'Neplătită' ?> și metoda <?= $isCod ? 'Ramburs la curier' : 'Card' ?>. Nu se emite dacă lipsesc datele fiscale obligatorii.</p>
                <footer><button class="admin-button secondary" type="button" data-invoice-modal-close>Anulează</button><button class="admin-button" type="submit">Emite factura</button></footer>
            </form>
        <?php endif; ?>
    </section>
</div>
