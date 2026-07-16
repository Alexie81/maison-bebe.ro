<?php
$now = time();
$groups = ['active' => [], 'used' => [], 'expired' => []];
foreach ($coupons as $coupon) {
    $expired = !$coupon['is_active']
        || ($coupon['ends_at'] && strtotime($coupon['ends_at']) < $now)
        || ($coupon['max_uses'] !== null && (int) $coupon['used_total'] >= (int) $coupon['max_uses']);
    $used = (int) $coupon['used_by_user'] > 0;
    $groups[$used ? 'used' : ($expired ? 'expired' : 'active')][] = $coupon;
}
$labels = [
    'active' => ['title' => 'Active', 'copy' => 'Gata de folosit la următoarea comandă.'],
    'used' => ['title' => 'Utilizate', 'copy' => 'Cupoanele pe care le-ai folosit deja.'],
    'expired' => ['title' => 'Expirate', 'copy' => 'Oferte care nu mai sunt disponibile.'],
];
?>
<section class="account-page shell section-space-small">
    <div class="account-head">
        <p class="eyebrow">AVANTAJELE TALE</p>
        <h1>Cupoane</h1>
        <p>Vezi codurile disponibile și condițiile lor înainte de checkout.</p>
    </div>
    <div class="account-layout">
        <?php require BASE_PATH . '/app/Views/account/nav.php'; ?>
        <div class="account-content coupon-wallet">
            <?php foreach ($groups as $key => $items): ?>
                <section class="account-panel coupon-group coupon-group-<?= e($key) ?>">
                    <div class="coupon-group-head">
                        <div>
                            <p class="eyebrow"><?= e($key === 'active' ? 'DISPONIBILE ACUM' : 'ISTORIC') ?></p>
                            <h2><?= e($labels[$key]['title']) ?></h2>
                            <p><?= e($labels[$key]['copy']) ?></p>
                        </div>
                        <span class="coupon-group-count" aria-label="<?= count($items) ?> cupoane"><?= count($items) ?></span>
                    </div>
                    <?php if (!$items): ?>
                        <div class="coupon-empty"><span aria-hidden="true">◇</span><p>Nu există cupoane în această secțiune.</p></div>
                    <?php else: ?>
                        <div class="customer-coupon-grid">
                            <?php foreach ($items as $coupon): ?>
                                <article class="customer-coupon-card coupon-<?= e($key) ?>">
                                    <div class="coupon-value-line">
                                        <span><?= $coupon['discount_type'] === 'percent' ? (int) $coupon['discount_value'] . '%' : money($coupon['discount_value']) ?></span>
                                        <strong><?= e($coupon['code']) ?></strong>
                                    </div>
                                    <ul>
                                        <li>Comandă minimă: <?= money($coupon['minimum_order_minor']) ?></li>
                                        <?php if ($coupon['category_names']): ?><li><?= e($coupon['category_names']) ?></li><?php endif; ?>
                                        <?php if ($coupon['collection_names']): ?><li><?= e($coupon['collection_names']) ?></li><?php endif; ?>
                                        <?php if ($coupon['product_names']): ?><li><?= e($coupon['product_names']) ?></li><?php endif; ?>
                                        <?php if ($coupon['max_uses_per_user']): ?><li>Maximum <?= (int) $coupon['max_uses_per_user'] ?> utilizări / client</li><?php endif; ?>
                                    </ul>
                                    <?php if ($key === 'active' && $coupon['ends_at']): ?>
                                        <div class="coupon-countdown" data-coupon-countdown="<?= e(date(DATE_ATOM, strtotime($coupon['ends_at']))) ?>"><small>Expiră în</small><strong data-countdown-value>—</strong></div>
                                    <?php elseif ($coupon['ends_at']): ?>
                                        <small class="coupon-validity">Valabil până la <?= e(date('d.m.Y H:i', strtotime($coupon['ends_at']))) ?></small>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
