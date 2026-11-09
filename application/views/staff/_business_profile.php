<section class="staff-panel registry-intro">
    <h2>Profil kegiatan</h2>
    <p>Data pada halaman ini melekat pada kegiatan usaha. Pelaku dapat mempunyai beberapa kegiatan, sedangkan toko memakai kegiatan yang sama sebagai sumber profilnya.</p>
    <dl class="staff-facts registry-summary">
        <div><dt>Status data</dt><dd><?= $business['registry_status']==='imported_unverified'?'Belum diperiksa':html_escape($business['registry_status']) ?></dd></div>
        <div><dt>Pelaku</dt><dd><?= $owner?'<a href="'.site_url('pengelola/pelaku/'.$owner['id']).'">'.html_escape($owner['full_name']).'</a>':'Belum tercatat' ?></dd></div>
        <div><dt>Toko</dt><dd><?= $store?'<a href="'.site_url('pengelola/toko/'.$store['id']).'">'.html_escape($store['status']).' · Buka profil toko</a>':'Belum memiliki toko' ?></dd></div>
        <div><dt>Akun pengelola</dt><dd><?= $imported_account?html_escape($imported_account['email']):'Belum tercatat' ?></dd></div>
    </dl>
</section>
<section class="staff-panel"><h2>Data profil usaha</h2>
<?php if (!$can_edit): ?><p>Perubahan data profil dikelola admin Dinas. Anda dapat menambahkan catatan atau koordinat sesuai wilayah tugas pada tab lain.</p><?php endif; ?>
<form class="registry-profile-form" method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/profil') ?>">
    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
    <div class="registry-form-grid">
        <label>Nama kegiatan atau merek usaha<input class="form-control" name="name" maxlength="190" required value="<?= html_escape($business['name']) ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label>Nama legal atau badan usaha<input class="form-control" name="legal_name" maxlength="190" value="<?= html_escape($business['legal_name']?:'') ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label>Bentuk usaha<select class="form-select" name="legal_form" <?= $can_edit?'':'disabled' ?>><?php foreach(array(''=>'Belum diketahui','perorangan'=>'Perorangan','cv'=>'CV','pt'=>'PT','koperasi'=>'Koperasi','firma'=>'Firma','yayasan'=>'Yayasan','lainnya'=>'Lainnya') as $value=>$label): ?><option value="<?= $value ?>" <?= ($business['legal_form']?:'')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Bidang atau kategori kegiatan<input class="form-control" name="sector_label" maxlength="190" value="<?= html_escape($business['sector_label']?:'') ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label>Skala usaha<select class="form-select" name="scale_id" <?= $can_edit?'':'disabled' ?>><option value="">Belum diketahui</option><?php foreach($scales as $scale): ?><option value="<?= (int)$scale['id'] ?>" <?= (int)$business['scale_id']===(int)$scale['id']?'selected':'' ?>><?= html_escape($scale['name']) ?></option><?php endforeach; ?></select></label>
        <label>Tahun mulai usaha<input class="form-control" type="number" name="started_year" min="1900" max="<?= (int)date('Y') ?>" value="<?= html_escape($business['started_year']?:'') ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label>Jumlah tenaga kerja<input class="form-control" type="number" name="employee_count" min="0" max="1000000" value="<?= html_escape($business['employee_count']===NULL?'':$business['employee_count']) ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label>Omzet tahunan (Rp)<input class="form-control" type="number" name="annual_revenue_idr" min="0" step="0.01" value="<?= html_escape($business['annual_revenue_idr']===NULL?'':$business['annual_revenue_idr']) ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label>Kapasitas produksi<input class="form-control" name="production_capacity" maxlength="190" value="<?= html_escape($business['production_capacity']?:'') ?>" placeholder="Contoh: 100 unit per bulan" <?= $can_edit?'':'disabled' ?>></label>
        <label>Kecamatan<select class="form-select" id="registry-district" name="district_id" <?= $can_edit?'':'disabled' ?>><option value="">Belum diketahui</option><?php foreach($districts as $district): ?><option value="<?= (int)$district['id'] ?>" <?= (int)$business['district_id']===(int)$district['id']?'selected':'' ?>><?= html_escape($district['name']) ?></option><?php endforeach; ?></select></label>
        <label>Desa atau kelurahan<select class="form-select" id="registry-village" name="village_id" <?= $can_edit?'':'disabled' ?>><option value="">Belum diketahui</option><?php foreach($villages as $village): ?><option value="<?= (int)$village['id'] ?>" data-district="<?= (int)$village['district_id'] ?>" <?= (int)$business['village_id']===(int)$village['id']?'selected':'' ?>><?= html_escape($village['name']) ?></option><?php endforeach; ?></select></label>
        <label class="registry-field-wide">Alamat kegiatan usaha<textarea class="form-control" name="address" rows="3" maxlength="1000" <?= $can_edit?'':'disabled' ?>><?= html_escape($address) ?></textarea><small>Alamat kegiatan disimpan terenkripsi dan tidak otomatis tampil pada etalase.</small></label>
        <label class="registry-field-wide">Deskripsi kegiatan<textarea class="form-control" name="description" rows="4" maxlength="5000" <?= $can_edit?'':'disabled' ?>><?= html_escape($business['description']?:'') ?></textarea></label>
        <label>Nomor kontak kegiatan<input class="form-control" type="tel" name="contact_phone" maxlength="20" value="<?= html_escape($business['contact_phone_e164']?:'') ?>" placeholder="08… atau +62…" <?= $can_edit?'':'disabled' ?>><?php if (!$business['contact_phone_e164'] && $business['contact_source_text']): ?><small>Kontak tercatat: <?= html_escape($business['contact_source_text']) ?> · periksa sebelum disimpan sebagai nomor.</small><?php endif; ?></label>
        <label>Situs web<input class="form-control" name="website_url" maxlength="500" value="<?= html_escape($business['website_url']?:'') ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label class="registry-field-wide">Media sosial<input class="form-control" name="social_media_url" maxlength="500" value="<?= html_escape($business['social_media_url']?:'') ?>" <?= $can_edit?'':'disabled' ?>></label>
        <label class="registry-field-wide">Negara atau wilayah tujuan ekspor<textarea class="form-control" name="export_destinations" rows="2" maxlength="2000" <?= $can_edit?'':'disabled' ?>><?= html_escape($business['export_destinations']?:'') ?></textarea></label>
        <label class="registry-field-wide">Potensi dan rencana pengembangan<textarea class="form-control" name="export_potential" rows="2" maxlength="2000" <?= $can_edit?'':'disabled' ?>><?= html_escape($business['export_potential']?:'') ?></textarea></label>
    </div>
    <?php if ($can_edit): ?><button class="btn btn-primary" type="submit">Simpan profil usaha</button><?php endif; ?>
</form></section>
<script>
(function(){var d=document.getElementById('registry-district'),v=document.getElementById('registry-village');if(!d||!v)return;
function filter(){var selected=d.value;Array.prototype.forEach.call(v.options,function(o){if(!o.value)return;var visible=selected!==''&&o.dataset.district===selected;o.hidden=!visible;o.disabled=!visible;if(!visible&&o.selected)v.value='';});}
d.addEventListener('change',filter);filter();})();
</script>
