<?php if ($total_pages > 1): ?>
<?php
$url = function ($number) use ($pagination_base, $pagination_params) {
    $params = $pagination_params;
    if ($number > 1) $params['page'] = $number;
    return $pagination_base.($params ? '?'.http_build_query($params) : '');
};
$numbers = array(1, $total_pages);
for ($number = max(1, $page - 1); $number <= min($total_pages, $page + 1); $number++) $numbers[] = $number;
if ($page <= 3) $numbers[] = min(2, $total_pages);
if ($page >= $total_pages - 2) $numbers[] = max(1, $total_pages - 1);
$numbers = array_values(array_unique($numbers));
sort($numbers);
?>
<nav class="shop-pagination" aria-label="Halaman hasil">
    <?php if ($page > 1): ?><a class="shop-page-arrow" href="<?= html_escape($url($page - 1)) ?>" aria-label="Halaman sebelumnya">‹</a><?php endif; ?>
    <?php $last = 0; foreach ($numbers as $number): ?>
        <?php if ($last && $number - $last > 1): ?><span class="shop-page-gap" aria-hidden="true">…</span><?php endif; ?>
        <a class="<?= $number === $page ? 'active' : '' ?>" href="<?= html_escape($url($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
    <?php $last = $number; endforeach; ?>
    <?php if ($page < $total_pages): ?><a class="shop-page-arrow" href="<?= html_escape($url($page + 1)) ?>" aria-label="Halaman berikutnya">›</a><?php endif; ?>
</nav>
<p class="shop-page-summary">Halaman <?= (int) $page ?> dari <?= (int) $total_pages ?></p>
<?php endif; ?>
