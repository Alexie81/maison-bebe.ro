<section class="account-page shell section-space-small">
    <div class="account-head">
        <p class="eyebrow">COMANDĂ</p>
        <h1><?= e($order['order_number']) ?></h1>
        <p>Plasată la <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
    </div>
    <div class="account-layout">
        <?php require BASE_PATH . '/app/Views/account/nav.php'; ?>
        <div class="account-content">
            <section class="account-panel">
                <h2>Produsele comandate</h2>
                <?php foreach ($items as $item):
                    $itemOptions = json_decode((string) ($item['options_json'] ?? ''), true) ?: [];
                    $itemCustomization = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
                    $giftBoxRole = (string) ($itemCustomization['role'] ?? '');
                    $optionalChoices = (array) ($itemCustomization['optional_variants'] ?? []);
                    $personalization = (array) ($itemCustomization['personalization'] ?? []);
                ?>
                    <div class="order-item-line<?= $giftBoxRole === 'box' ? ' is-gift-box-line' : '' ?>">
                        <span>
                            <strong><?= e($giftBoxRole === 'box' ? 'Împachetare în cutie cadou' : $item['name_snapshot']) ?></strong>
                            <small><?= e($item['sku_snapshot']) ?> · <?= (int) $item['quantity'] ?> buc.</small>
                            <span class="customer-order-specs">
                                <?php if (!empty($itemOptions['label']) && $itemOptions['label'] !== 'Standard'): ?><small>Variantă: <?= e($itemOptions['label']) ?></small><?php endif; ?>
                                <?php if ($giftBoxRole === 'box'): ?><small>Cutie: <?= e($itemCustomization['template_name'] ?? $item['name_snapshot']) ?></small><?php elseif ($giftBoxRole === 'component'): ?><small>Împachetat în: <?= e($itemCustomization['template_name'] ?? 'cutie cadou') ?></small><?php endif; ?>
                                <?php if (!empty($itemCustomization['recipient_name'])): ?><small>Destinatar: <?= e($itemCustomization['recipient_name']) ?></small><?php endif; ?>
                                <?php if (!empty($itemCustomization['gift_message'])): ?><small>Mesaj în cutie: „<?= e($itemCustomization['gift_message']) ?>”</small><?php endif; ?>
                                <?php foreach ($optionalChoices as $optionalChoice): ?><small>Opțiune: <?= e($optionalChoice['name'] ?? '') ?><?= (int) ($optionalChoice['price_delta_minor'] ?? 0) > 0 ? ' (+ ' . money((int) $optionalChoice['price_delta_minor']) . ')' : '' ?></small><?php endforeach; ?>
                                <?php if($personalization): ?><small>Personalizare: <?= e($personalization['option_name']??'') ?><?= (int)($personalization['price_delta_minor']??0)>0?' (+ '.money((int)$personalization['price_delta_minor']).')':'' ?></small><small>Nume copil: <?= e($personalization['child_name']??'') ?></small><small>Data botezului/nașterii: <?= e($personalization['birth_date_formatted']??'') ?></small><?php endif; ?>
                            </span>
                        </span>
                        <b><?= money($item['total_minor']) ?></b>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($order['gift_message'])): ?><div class="customer-order-gift-message"><small>Mesaj cadou</small><p>„<?= nl2br(e($order['gift_message'])) ?>”</p></div><?php endif; ?>
                <div class="summary-total"><span>Total</span><strong><?= money($order['grand_total_minor']) ?></strong></div>
            </section>
            <section class="account-panel">
                <h2>Parcursul comenzii</h2>
                <ol class="timeline">
                    <?php foreach ($history as $event): ?><li><strong><?= e($event['public_label']) ?></strong><p><?= e($event['public_message']) ?></p><time><?= date('d.m.Y H:i', strtotime($event['created_at'])) ?></time></li><?php endforeach; ?>
                </ol>
                <?php if ($shipment && $shipment['awb']): ?><a class="button button-outline" href="<?= e($shipment['tracking_url']) ?>">Urmărește AWB <?= e($shipment['awb']) ?></a><?php endif; ?>
            </section>
        </div>
    </div>
</section>
