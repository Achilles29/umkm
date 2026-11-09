<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Jelajahi Toko — Rembang UMKM</title>
    <link rel="icon" href="<?= base_url('assets/favicon.ico?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/marketplace.css?v=4') ?>">
<link rel="stylesheet" href="<?= base_url('assets/market-nearby.css?v=3') ?>"></head>
<body class="shop-body">
<?php $this->load->view('market/_header'); ?>
<main class="shop-main container-xl">
    <div class="shop-page-title shop-title-row">
        <div><span class="shop-eyebrow">DIREKTORI USAHA</span><h1>Jelajahi toko</h1><p>Temukan toko berdasarkan kecamatan dan desa atau kelurahan.</p></div>
        <a class="shop-map-link" href="<?= site_url('peta').($selected_district ? '?district='.(int)$selected_district['id'] : '') ?>">⌖ Lihat peta</a>
    </div>
    <?php $near_params=array();if($selected_district)$near_params['district']=$selected_district['id'];if($selected_village)$near_params['village']=$selected_village['id'];$near_target=site_url('daftar-toko').'?'.http_build_query($near_params); ?>
    <div class="shop-category-grid shop-category-shortcuts"><a class="shop-category-item <?= !$nearby?'selected':'' ?>" href="<?= site_url('daftar-toko') ?>"><span class="shop-category-art all" aria-hidden="true"><svg viewBox="0 0 96 96"><circle cx="29" cy="29" r="11"/><circle cx="67" cy="29" r="11"/><circle cx="29" cy="67" r="11"/><circle cx="67" cy="67" r="11"/></svg></span><span>Semua toko</span></a><?php $this->load->view('market/_nearby_action',array('nearby'=>$nearby,'target'=>$near_target)); ?></div>
    <?php if($nearby): ?><p class="shop-nearby-note">Urutan perkiraan dari lokasi toko atau area kecamatan. <a href="<?= html_escape($near_target) ?>">Hapus urutan</a></p><?php endif; ?>
    <form class="shop-area-filter" method="get" action="<?= site_url('daftar-toko') ?>">
        <label for="district">Kecamatan
            <select id="district" name="district">
                <option value="">Semua kecamatan</option>
                <?php foreach ($districts as $district): ?><option value="<?= (int)$district['id'] ?>" <?= $selected_district && $selected_district['id'] == $district['id'] ? 'selected' : '' ?>><?= html_escape($district['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label for="village">Desa atau kelurahan
            <select id="village" name="village" <?= $selected_district ? '' : 'disabled' ?>>
                <option value="">Semua desa dan kelurahan</option>
                <?php foreach ($villages as $village): ?><option value="<?= (int)$village['id'] ?>" <?= $selected_village && $selected_village['id'] == $village['id'] ? 'selected' : '' ?>><?= html_escape($village['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php if($nearby): ?><input type="hidden" name="lat" value="<?= html_escape($nearby[0]) ?>"><input type="hidden" name="lng" value="<?= html_escape($nearby[1]) ?>"><?php endif; ?>
        <button type="submit">Terapkan</button>
        <?php if ($selected_district): ?><a href="<?= site_url('daftar-toko') ?>">Reset</a><?php endif; ?>
    </form>
    <div class="shop-list-head"><strong><?= number_format($total_stores,0,',','.') ?> toko</strong><?php if ($selected_district): ?><span>di <?= html_escape($selected_village ? $selected_village['name'].', '.$selected_district['name'] : $selected_district['name']) ?></span><?php endif; ?></div>
    <?php if (!$stores): ?><div class="shop-empty"><strong>Belum ada toko di wilayah ini</strong><p>Pilih wilayah lain untuk melihat toko.</p><a href="<?= site_url('daftar-toko') ?>">Lihat semua toko</a></div><?php else: ?>
    <div class="shop-store-grid">
        <?php foreach ($stores as $store): ?>
        <a class="shop-store-card" href="<?= site_url('toko/'.$store['slug']) ?>">
            <span class="shop-store-icon"><img src="<?= category_art_url($store['category_slug'] ?: 'ritel') ?>" alt="" loading="lazy"></span>
            <span class="shop-store-info"><strong><?= html_escape($store['display_name']) ?></strong><small>⌖ <?= html_escape($store['village_name'] ? $store['village_name'].', '.($store['district_name'] ?: 'Rembang') : ($store['district_name'] ?: 'Kabupaten Rembang')) ?></small><em><?= (int)$store['product_count'] ?> produk</em></span>
            <span class="shop-store-arrow" aria-hidden="true">→</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php $pagination_base = site_url('daftar-toko'); $pagination_params = array(); if ($selected_district) $pagination_params['district'] = $selected_district['id']; if ($selected_village) $pagination_params['village'] = $selected_village['id']; if ($nearby) { $pagination_params['lat']=$nearby[0];$pagination_params['lng']=$nearby[1]; } $this->load->view('market/_pagination', array('pagination_base' => $pagination_base, 'pagination_params' => $pagination_params, 'page' => $page, 'total_pages' => $total_pages)); ?>
    <?php endif; ?>
</main>
<?php $active_tab='stores'; $this->load->view('market/_bottom_nav', array('active_tab' => $active_tab, 'cart_count' => $cart_count)); ?>
<script>
const districtSelect=document.getElementById('district');
const villageSelect=document.getElementById('village');
districtSelect.addEventListener('change',()=>{villageSelect.value='';districtSelect.form.submit()});
villageSelect.addEventListener('change',()=>villageSelect.form.submit());
</script>
</body></html>
