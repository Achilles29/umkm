<?php $page_title='Profil kegiatan usaha'; $this->load->view('staff/_layout_start',compact('user','page_title')); ?>
<section class="staff-hero registry-hero">
    <div><span class="staff-kicker">KEGIATAN USAHA #<?= (int)$business['id'] ?></span>
        <h1><?= html_escape($business['name']) ?></h1>
        <p><?= html_escape($business['village_name'] ? $business['village_name'].', '.$business['district_name'] : ($business['district_name'] ?: 'Wilayah belum dilengkapi')) ?> · <?= $store?'Memiliki toko':'Belum memiliki toko' ?></p>
    </div>
    <a class="btn btn-outline-primary" href="<?= site_url('pengelola/registri') ?>">Kembali ke kegiatan</a>
</section>
<?php if ($this->input->get('tersimpan')): ?><div class="alert alert-success" role="status">Data berhasil disimpan.</div><?php endif; ?>
<?php if ($saved): ?><div class="alert alert-success" role="status">Catatan peninjauan tersimpan.</div><?php endif; ?>
<?php if ($this->input->get('kecocokan')): ?><div class="alert alert-success" role="status">Keputusan kecocokan tersimpan.</div><?php endif; ?>
<?php if ($this->input->get('koordinat')): ?><div class="alert alert-success" role="status">Koordinat berhasil disimpan.</div><?php endif; ?>
<?php
$tabs=array(
    'profil'=>'Profil usaha',
    'pelaku'=>'Pelaku',
    'klasifikasi'=>'KBLI dan komoditas',
    'perizinan'=>'NIB dan toko',
    'lokasi'=>'Lokasi',
    'peninjauan'=>'Peninjauan',
);
?>
<nav class="registry-tabs" aria-label="Bagian profil kegiatan usaha">
<?php foreach($tabs as $key=>$label): ?><a href="<?= site_url('pengelola/registri/'.$business['id']).'?tab='.$key ?>" class="<?= $tab===$key?'is-active':'' ?>" <?= $tab===$key?'aria-current="page"':'' ?>><?= html_escape($label) ?></a><?php endforeach; ?>
</nav>
<?php
$partials=array(
    'profil'=>'staff/_business_profile',
    'pelaku'=>'staff/_business_people',
    'klasifikasi'=>'staff/_business_classification',
    'perizinan'=>'staff/_business_licenses',
    'lokasi'=>'staff/_business_location',
    'peninjauan'=>'staff/_business_review',
);
$this->load->view($partials[$tab]);
?>
<?php $this->load->view('staff/_layout_end'); ?>
