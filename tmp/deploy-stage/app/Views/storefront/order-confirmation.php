<?php
$state = (string) ($paymentState ?? '');
$isPaid = ($order['payment_status'] ?? '') === 'paid';
$feedback = match ($state) {
    'efectuata' => ['success','PLATĂ EFECTUATĂ','Plata cu cardul a fost confirmată. Comanda intră acum în pregătire.','✓'],
    'anulata' => ['warning','PLATĂ ANULATĂ','Ai revenit înainte de finalizarea plății. Comanda este păstrată și poți relua plata fără să o refaci.','!'],
    'fonduri_insuficiente' => ['danger','FONDURI INSUFICIENTE','Banca nu a aprobat plata deoarece cardul nu are fonduri suficiente. Poți încerca din nou sau folosi alt card.','!'],
    'card_refuzat' => ['danger','CARD REFUZAT','Banca emitentă nu a aprobat această plată. Verifică datele cardului sau încearcă alt card.','!'],
    'refuzata' => ['danger','PLATĂ REFUZATĂ','Plata nu a fost aprobată. Comanda este păstrată și poți încerca din nou în siguranță.','!'],
    'verificare' => ['warning','PLATĂ ÎN VERIFICARE','Stripe verifică plata. Reîncarcă această pagină peste câteva secunde; comanda nu va fi duplicată.','…'],
    'in_asteptare' => ['info','PLATĂ NEFINALIZATĂ','Comanda este salvată, dar plata cu cardul nu a fost încă finalizată.','→'],
    default => ['success','COMANDĂ ÎNREGISTRATĂ','Vei achita curierului când primești coletul.','✓'],
};
?>
<section class="confirmation-page shell section-space">
    <div class="payment-result payment-result-<?= e($feedback[0]) ?>" role="status">
        <span class="payment-result-icon" aria-hidden="true"><?= e($feedback[3]) ?></span>
        <div><p class="eyebrow"><?= e($feedback[1]) ?></p><h1><?= $isPaid ? 'Mulțumim, plata a reușit.' : ($state === 'ramburs' ? 'Comanda ta a fost primită.' : 'Comanda ta este păstrată.') ?></h1><p><?= e($feedback[2]) ?></p></div>
    </div>
    <div class="confirmation-order-head"><div><small>NUMĂR COMANDĂ</small><strong><?= e($order['order_number']) ?></strong></div><div><small>STATUS PLATĂ</small><strong><?= $isPaid ? 'Plătită' : (($order['payment_method'] ?? '') === 'stripe' ? 'Neplătită' : 'Ramburs') ?></strong></div></div>
    <div class="confirmation-box"><?php foreach($items as $item): ?><div><span><?= e($item['name_snapshot']) ?> × <?= (int)$item['quantity'] ?></span><strong><?= money($item['total_minor']) ?></strong></div><?php endforeach; ?><div class="summary-total"><span>Total</span><strong><?= money($order['grand_total_minor']) ?></strong></div></div>
    <?php if(($order['payment_method']??'')==='stripe'&&!$isPaid): ?><div class="payment-retry"><p>Nu trebuie să plasezi altă comandă.</p><a class="button" href="<?= e(url('/plata/stripe/'.$order['public_token'])) ?>">Încearcă din nou plata</a></div><?php endif; ?>
    <div class="button-row"><a class="button button-outline" href="<?= e(url('/urmarire-comanda?token='.$order['public_token'])) ?>">Urmărește comanda</a><a class="button button-outline" href="<?= e(url('/shop')) ?>">Continuă cumpărăturile</a></div>
</section>