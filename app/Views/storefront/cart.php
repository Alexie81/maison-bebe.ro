<?php
$giftTotals=[];$giftComponents=[];
foreach($totals['items'] as $cartItem){
    $giftCustom=json_decode((string)($cartItem['customization_json']??''),true)?:[];
    if(($giftCustom['type']??'')!=='gift_box'||empty($giftCustom['group'])) continue;
    $giftGroup=(string)$giftCustom['group'];
    $giftTotals[$giftGroup]=($giftTotals[$giftGroup]??0)+((int)$cartItem['price_minor']*(int)$cartItem['quantity']);
    if(($giftCustom['role']??'')==='component') $giftComponents[$giftGroup][]=$cartItem;
}
?>
<section class="page-hero shell section-space-small"><p class="eyebrow">COMANDA TA</p><h1>Coșul tău</h1><p>Verifică fiecare alegere înainte de checkout.</p></section>
<section class="cart-page shell section-space-small <?= !$totals['items'] ? 'cart-page-empty' : '' ?>">
<?php if(!$totals['items']): ?>
    <div class="empty-state"><h2>Coșul este gol</h2><p>Descoperă piesele pregătite pentru începuturi prețioase.</p><a class="button" href="<?= e(url('/shop')) ?>">Începe cumpărăturile</a></div>
<?php else: ?>
    <div class="cart-items">
    <?php foreach($totals['items'] as $item): $custom=json_decode((string)($item['customization_json']??''),true)?:[]; if(($custom['type']??'')==='gift_box'&&($custom['role']??'')==='component') continue; $isGiftBox=($custom['type']??'')==='gift_box'&&($custom['role']??'')==='box'; $group=(string)($custom['group']??''); $components=$isGiftBox?($custom['components']??[]):[]; ?>
        <article class="cart-row <?= $isGiftBox?'cart-row-gift-box':'' ?>" data-cart-item="<?= (int)$item['id'] ?>">
            <img src="<?= e(url($item['image_path'])) ?>" alt="" width="100" height="120">
            <div class="cart-row-main">
                <?php if($isGiftBox): ?>
                    <h2><?= e($custom['template_name']??$item['name']) ?></h2>
                    <p class="gift-cart-note">Gift Box personalizat<?= $group?' · '.e($group):'' ?></p>
                    <?php if(!empty($custom['recipient_name'])): ?><p class="gift-cart-message"><strong>Destinatar:</strong> <?= e($custom['recipient_name']) ?></p><?php endif; ?>
                    <?php if(!empty($custom['gift_message'])): ?><p class="gift-cart-message">„<?= e($custom['gift_message']) ?>”</p><?php endif; ?>
                    <?php if($components): ?><details class="gift-cart-components"><summary><?= count($components) ?> produse selectate</summary><ul><?php foreach($components as $component): ?><li><span><?= e($component['name']??'Produs') ?></span><small><?= e($component['variant']??'Standard') ?> · <?= money((int)($component['price_minor']??0)) ?></small></li><?php endforeach; ?></ul></details><?php endif; ?>
                    <div class="cart-row-actions"><a href="<?= e(url('/gift-box?editeaza='.rawurlencode($group).'#configurator')) ?>">Editează Gift Box</a><button type="button" data-cart-remove="<?= (int)$item['id'] ?>">Elimină</button></div>
                <?php else: ?>
                    <a href="<?= e(url('/produs/'.$item['slug'])) ?>"><h2><?= e($item['name']) ?></h2></a><p><?= e($item['variant_label']?:'Standard') ?></p><button type="button" data-cart-remove="<?= (int)$item['id'] ?>">Elimină</button>
                <?php endif; ?>
            </div>
            <?php if($isGiftBox): ?><span class="cart-fixed-qty">1 set</span><?php else: ?><label>Cantitate<input type="number" min="1" max="<?= (int)$item['stock_qty'] ?>" value="<?= (int)$item['quantity'] ?>" data-cart-quantity="<?= (int)$item['id'] ?>"></label><?php endif; ?>
            <strong><?= money($isGiftBox?($giftTotals[$group]??((int)$item['price_minor']*(int)$item['quantity'])):((int)$item['price_minor']*(int)$item['quantity'])) ?></strong>
        </article>
    <?php endforeach; ?>
    </div>
    <aside class="order-summary"><h2>Sumar comandă</h2><div><span>Subtotal</span><strong><?= money($totals['subtotal_minor']) ?></strong></div><div><span>Reducere</span><strong>-<?= money($totals['discount_minor']) ?></strong></div><div><span>Livrare</span><strong><?= $totals['shipping_minor']?money($totals['shipping_minor']):'Gratuit' ?></strong></div><div class="summary-total"><span>Total</span><strong><?= money($totals['grand_total_minor']) ?></strong></div><form data-coupon-form><?= csrf_field() ?><label>Cod promoțional<input type="text" name="code" value="<?= e($totals['coupon']['code']??'') ?>"></label><button class="button button-outline" type="submit">Aplică</button></form><a class="button" href="<?= e(url('/checkout')) ?>">Continuă spre checkout</a></aside>
<?php endif; ?>
</section>