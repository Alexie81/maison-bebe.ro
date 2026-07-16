<?php
$accountCurrentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$accountNavIsCurrent = static function (string $href, bool $exact = false) use ($accountCurrentPath): bool {
    $targetPath = rtrim((string) parse_url($href, PHP_URL_PATH), '/');
    return $exact
        ? $accountCurrentPath === $targetPath
        : ($accountCurrentPath === $targetPath || str_starts_with($accountCurrentPath, $targetPath . '/'));
};
$accountLinks = [
    ['label' => 'Sumar', 'href' => url('/cont'), 'exact' => true],
    ['label' => 'Date personale', 'href' => url('/cont/date-personale')],
    ['label' => 'Comenzi', 'href' => url('/cont/comenzi')],
    ['label' => 'Adrese', 'href' => url('/cont/adrese')],
    ['label' => 'Cupoane', 'href' => url('/cont/cupoane')],
    ['label' => 'Favorite', 'href' => url('/favorite')],
];
?>
<nav class="account-nav" aria-label="Contul meu">
    <?php if (MaisonBebe\Core\Auth::isAdmin()): ?>
        <a class="account-admin-shortcut" href="<?= e(url('/admin')) ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Panou administrare</span></a>
    <?php endif; ?>
    <?php foreach ($accountLinks as $link): ?>
        <?php $isCurrent = $accountNavIsCurrent($link['href'], $link['exact'] ?? false); ?>
        <a href="<?= e($link['href']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/cont/deconectare')) ?>"><?= csrf_field() ?><button type="submit">Deconectare</button></form>
</nav>
