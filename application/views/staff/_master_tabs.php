<?php
$master_tabs=array(
    'home'=>array('label'=>'Ringkasan','url'=>'pengelola/master'),
    'categories'=>array('label'=>'Kategori','url'=>'pengelola/master/kategori'),
    'kbli'=>array('label'=>'KBLI','url'=>'pengelola/master/kbli'),
    'scales'=>array('label'=>'Skala usaha','url'=>'pengelola/master/skala'),
    'territory'=>array('label'=>'Wilayah','url'=>'pengelola/master/wilayah'),
);
?>
<nav class="master-tabs" aria-label="Halaman master data">
<?php foreach($master_tabs as $key=>$tab): ?><a href="<?= site_url($tab['url']) ?>" class="<?= $master_tab===$key?'is-active':'' ?>" <?= $master_tab===$key?'aria-current="page"':'' ?>><?= html_escape($tab['label']) ?></a><?php endforeach; ?>
</nav>
