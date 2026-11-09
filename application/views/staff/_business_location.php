<section class="staff-panel"><h2>Koordinat kegiatan usaha</h2>
    <p>Titik kegiatan yang diketahui tampil pada peta internal pengelola. Titik tidak otomatis ditampilkan kepada pengunjung. Periksa bahwa lokasi adalah tempat usaha, bukan rumah pribadi.</p>
    <?php if ($location): ?><p>Status titik: <?= $location['checked_at']?'Diperiksa '.html_escape($location['checked_at']):'Belum diperiksa' ?><?= $location['visibility']==='public'?' · Dipublikasikan':'' ?>.</p><?php endif; ?>
    <?php if ($location_candidates): ?><div class="staff-origin-list"><strong>Titik yang perlu diperiksa</strong><?php foreach($location_candidates as $candidate): ?><div><strong><?= html_escape($candidate['place_name']) ?></strong><small><?= html_escape($candidate['source_name']) ?> · <?= html_escape($candidate['latitude']) ?>, <?= html_escape($candidate['longitude']) ?></small><button type="button" class="btn btn-sm btn-outline-primary gis-use-candidate" data-lat="<?= html_escape($candidate['latitude']) ?>" data-lng="<?= html_escape($candidate['longitude']) ?>">Isi koordinat</button></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($location && $location['visibility']==='public'): ?><p>Ubah lokasi publik melalui pengaturan lokasi toko agar persetujuan publikasi tetap diperiksa.</p>
    <?php else: ?><form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/koordinat') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <div class="registry-form-grid"><label>Lintang<input class="form-control" id="registry-lat" name="latitude" type="number" step="any" min="-7.3" max="-6.3" required value="<?= $location?html_escape($location['latitude']):'' ?>"></label>
        <label>Bujur<input class="form-control" id="registry-lng" name="longitude" type="number" step="any" min="110.8" max="111.8" required value="<?= $location?html_escape($location['longitude']):'' ?>"></label></div>
        <label class="form-label">Sumber dan alasan kecocokan<textarea class="form-control" id="registry-location-source" name="source_note" minlength="10" maxlength="500" rows="3" required placeholder="Contoh: lokasi usaha diperiksa saat kunjungan; nama dan alamat sesuai."></textarea></label>
        <?php if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)): ?><label class="staff-checkbox"><input type="checkbox" name="checked" value="1"> Koordinat usaha sudah saya periksa</label><?php endif; ?>
        <button class="btn btn-primary" type="submit">Simpan koordinat</button>
    </form><?php endif; ?>
    <p><a href="<?= site_url('pengelola/peta-umkm').'?district='.(int)$business['district_id'] ?>">Buka peta persebaran →</a></p>
</section>
<script>document.querySelectorAll('.gis-use-candidate').forEach(function(button){button.addEventListener('click',function(){var lat=document.getElementById('registry-lat'),lng=document.getElementById('registry-lng'),source=document.getElementById('registry-location-source');if(lat&&lng){lat.value=button.dataset.lat;lng.value=button.dataset.lng;if(source)source.focus();}});});</script>
