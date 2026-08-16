<?php
$state = (string) ($paymentState ?? '');
$isPaid = ($order['payment_status'] ?? '') === 'paid';
$feedback = match ($state) {
    'efectuata' => ['success', 'PLATĂ EFECTUATĂ', 'Plata cu cardul a fost confirmată. Comanda intră acum în pregătire.', '✓'],
    'anulata' => ['warning', 'PLATĂ ANULATĂ', 'Ai revenit înainte de finalizarea plății. Comanda este păstrată și poți relua plata fără să o refaci.', '!'],
    'fonduri_insuficiente' => ['danger', 'FONDURI INSUFICIENTE', 'Banca nu a aprobat plata deoarece cardul nu are fonduri suficiente. Poți încerca din nou sau folosi alt card.', '!'],
    'card_refuzat' => ['danger', 'CARD REFUZAT', 'Banca emitentă nu a aprobat această plată. Verifică datele cardului sau încearcă alt card.', '!'],
    'refuzata' => ['danger', 'PLATĂ REFUZATĂ', 'Plata nu a fost aprobată. Comanda este păstrată și poți încerca din nou în siguranță.', '!'],
    'verificare' => ['warning', 'PLATĂ ÎN VERIFICARE', 'Stripe verifică plata. Reîncarcă această pagină peste câteva secunde; comanda nu va fi duplicată.', '…'],
    'in_asteptare' => ['info', 'PLATĂ NEFINALIZATĂ', 'Comanda este salvată, dar plata cu cardul nu a fost încă finalizată.', '→'],
    default => ['success', 'COMANDĂ ÎNREGISTRATĂ', 'Vei achita curierului când primești coletul.', '✓'],
};
?>
<section class="confirmation-page shell section-space">
    <div class="payment-result payment-result-<?= e($feedback[0]) ?>" role="status">
        <span class="payment-result-icon" aria-hidden="true"><?= e($feedback[3]) ?></span>
        <div><p class="eyebrow"><?= e($feedback[1]) ?></p><h1><?= $isPaid ? 'Mulțumim, plata a reușit.' : ($state === 'ramburs' ? 'Comanda ta a fost primită.' : 'Comanda ta este păstrată.') ?></h1><p><?= e($feedback[2]) ?></p></div>
    </div>
    <div class="confirmation-order-head"><div><small>NUMĂR COMANDĂ</small><strong><?= e($order['order_number']) ?></strong></div><div><small>STATUS PLATĂ</small><strong><?= $isPaid ? 'Plătită' : (($order['payment_method'] ?? '') === 'stripe' ? 'Neplătită' : 'Ramburs') ?></strong></div></div>
    <div class="confirmation-box">
        <?php foreach ($items as $item):
            $itemOptions = json_decode((string) ($item['options_json'] ?? ''), true) ?: [];
            $itemCustomization = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
            $giftBoxRole = (string) ($itemCustomization['role'] ?? '');
            $optionalChoices = (array) ($itemCustomization['optional_variants'] ?? []);
            $personalization = (array) ($itemCustomization['personalization'] ?? []);
        ?>
            <div class="customer-order-item<?= $giftBoxRole === 'box' ? ' is-gift-box-line' : '' ?>">
                <span>
                    <strong><?= e($giftBoxRole === 'box' ? 'Împachetare în cutie cadou' : $item['name_snapshot']) ?> × <?= (int) $item['quantity'] ?></strong>
                    <span class="customer-order-specs">
                        <?php if (!empty($itemOptions['label']) && $itemOptions['label'] !== 'Standard'): ?><small>Variantă: <?= e($itemOptions['label']) ?></small><?php endif; ?>
                        <?php if ($giftBoxRole === 'box'): ?><small>Cutie: <?= e($itemCustomization['template_name'] ?? $item['name_snapshot']) ?></small><?php elseif ($giftBoxRole === 'component'): ?><small>Împachetat în: <?= e($itemCustomization['template_name'] ?? 'cutie cadou') ?></small><?php endif; ?>
                        <?php if (!empty($itemCustomization['recipient_name'])): ?><small>Destinatar: <?= e($itemCustomization['recipient_name']) ?></small><?php endif; ?>
                        <?php if (!empty($itemCustomization['gift_message'])): ?><small>Mesaj în cutie: „<?= e($itemCustomization['gift_message']) ?>”</small><?php endif; ?>
                        <?php foreach ($optionalChoices as $optionalChoice): ?><small>Opțiune: <?= e($optionalChoice['name'] ?? '') ?><?= (int) ($optionalChoice['price_delta_minor'] ?? 0) > 0 ? ' (+ ' . money((int) $optionalChoice['price_delta_minor']) . ')' : '' ?></small><?php endforeach; ?>
                        <?php if($personalization): ?><small>Personalizare: <?= e($personalization['option_name']??'') ?><?= (int)($personalization['price_delta_minor']??0)>0?' (+ '.money((int)$personalization['price_delta_minor']).')':'' ?></small><small><?= e($personalization['child_name_label']??'Nume copil') ?>: <?= e($personalization['child_name']??'') ?></small><small><?= e($personalization['event_date_label']??'Data botezului/nașterii') ?>: <?= e($personalization['event_date_formatted']??$personalization['birth_date_formatted']??'') ?></small><?php endif; ?>
                    </span>
                </span>
                <strong><?= money($item['total_minor']) ?></strong>
            </div>
        <?php endforeach; ?>
        <?php if (!empty($order['gift_message'])): ?><div class="customer-order-gift-message"><small>Mesaj cadou</small><p>„<?= nl2br(e($order['gift_message'])) ?>”</p></div><?php endif; ?>
        <div class="summary-total"><span>Total</span><strong><?= money($order['grand_total_minor']) ?></strong></div>
    </div>
    <?php if (($order['payment_method'] ?? '') === 'stripe' && !$isPaid): ?><div class="payment-retry"><p>Nu trebuie să plasezi altă comandă.</p><a class="button" href="<?= e(url('/plata/stripe/' . $order['public_token'])) ?>">Încearcă din nou plata</a></div><?php endif; ?>
    <div class="button-row"><a class="button button-outline" href="<?= e(url('/urmarire-comanda?token=' . $order['public_token'])) ?>">Urmărește comanda</a><a class="button button-outline" href="<?= e(url('/shop')) ?>">Continuă cumpărăturile</a></div>
</section>
