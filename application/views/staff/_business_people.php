<section class="staff-panel"><h2>Pelaku kegiatan</h2>
<?php if (!$owner): ?><p>Nama pelaku belum tercatat. Hubungan dengan profil lain perlu diperiksa menggunakan dokumen atau keterangan petugas.</p>
<?php else: ?><dl class="staff-facts registry-summary">
    <div><dt>Nama pelaku</dt><dd><a href="<?= site_url('pengelola/pelaku/'.$owner['id']) ?>"><?= html_escape($owner['full_name']) ?></a></dd></div>
    <div><dt>NIK</dt><dd><?= $owner['has_nik']?'Tercatat dan terlindungi'.($owner['nik_hint']?' · '.html_escape($owner['nik_hint']):''):'Belum tercatat' ?></dd></div>
    <div><dt>Kontak pelaku</dt><dd><?= html_escape($owner['phone_e164']?:'Belum tercatat') ?></dd></div>
</dl><?php endif; ?>
<p>Data pelaku berlaku untuk semua kegiatan miliknya. Gunakan halaman pelaku untuk melihat hubungan antarkegiatan.</p></section>
<?php if ($can_edit_person): ?><section class="staff-panel"><h2><?= $owner?'Perbarui identitas pelaku':'Lengkapi identitas pelaku' ?></h2>
    <p><?= $owner?'Perubahan nama dan kontak berlaku pada seluruh kegiatan serta akun yang terhubung ke pelaku ini.':'Cari pelaku pada database terlebih dahulu. Tambahkan identitas baru hanya setelah sumbernya diperiksa.' ?></p>
    <form class="registry-person-form" method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/'.($owner?'pelaku':'pelaku-baru')) ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <div class="registry-form-grid"><label>Nama lengkap pelaku<input class="form-control" name="full_name" maxlength="160" required value="<?= $owner?html_escape($owner['full_name']):'' ?>"></label>
        <label>Nomor kontak pelaku<input class="form-control" name="phone" type="tel" maxlength="20" value="<?= $owner?html_escape($owner['phone_e164']?:''):'' ?>" placeholder="08… atau +62…"></label>
        <label>NIK <small><?= $owner?'Kosongkan bila tidak berubah':'Isi jika tersedia dan telah diperiksa' ?></small><input class="form-control" name="nik_new" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" autocomplete="off" placeholder="16 angka"></label>
        <label class="registry-field-wide">Dasar perubahan atau sumber identitas<textarea class="form-control" name="change_note" rows="3" minlength="20" maxlength="500" required placeholder="Jelaskan dokumen atau keterangan petugas yang diperiksa tanpa menyalin NIK lengkap."></textarea></label></div>
        <button class="btn btn-primary" type="submit"><?= $owner?'Simpan identitas pelaku':'Tambahkan pelaku' ?></button>
    </form>
</section><?php endif; ?>
<?php if($owner && $can_edit && !$can_edit_person): ?><p>Identitas pelaku yang mempunyai kegiatan di luar wilayah tugas hanya dapat diubah admin Dinas.</p><?php endif; ?>
<?php if (!$owner && $can_edit): ?><section class="staff-panel"><h2>Hubungkan pelaku yang sudah terdaftar</h2>
    <p>Cari pelaku pada <a href="<?= site_url('pengelola/pelaku') ?>" target="_blank" rel="noopener">database pelaku ↗</a>, lalu masukkan ID yang sesuai setelah identitasnya diperiksa. Cara ini menjaga satu pelaku tetap mempunyai satu profil.</p>
    <form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/hubungkan-pelaku') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <div class="registry-form-grid"><label>ID pelaku<input class="form-control" name="person_id" type="number" min="1" required></label>
        <label class="registry-field-wide">Dasar kecocokan identitas<textarea class="form-control" name="change_note" rows="3" minlength="20" maxlength="500" required placeholder="Jelaskan dokumen atau keterangan petugas yang membuktikan hubungan."></textarea></label></div>
        <button class="btn btn-outline-primary mt-3" type="submit">Hubungkan pelaku</button>
    </form>
</section><?php endif; ?>
<?php if ($related): ?><section class="staff-panel"><h2>Kegiatan lain milik pelaku ini</h2><div class="staff-activity-list"><?php foreach($related as $item): ?><a href="<?= site_url('pengelola/registri/'.$item['id']) ?>"><strong><?= html_escape($item['name']) ?></strong><small><?= $item['store_id']?'Memiliki toko':'Belum memiliki toko' ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
<?php if ($match_total): ?><section class="staff-panel"><h2>Calon kecocokan pelaku</h2>
    <p>Ada <?= (int)$match_total ?> kemungkinan hubungan berdasarkan nama. Periksa dokumen, NIK, dan alamat sebelum memutuskan. Kesamaan nama saja belum cukup.</p>
    <div class="staff-origin-list"><?php foreach($match_candidates as $candidate): ?><div>
        <strong><a href="<?= site_url('pengelola/pelaku/'.$candidate['person_id']) ?>"><?= html_escape($candidate['full_name']) ?></a></strong>
        <small><?= $candidate['match_basis']==='name_address'?'Nama dan alamat sama':($candidate['match_basis']==='name_district'?'Nama dan kecamatan sama':'Nama sama') ?> · <?= (int)$candidate['activity_count'] ?> kegiatan · <?= html_escape($candidate['districts']?:'Wilayah belum dipetakan') ?></small>
        <form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/cocokkan/'.$candidate['id']) ?>">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <label class="form-label">Alasan dan bukti pemeriksaan<textarea class="form-control" name="decision_note" minlength="15" maxlength="500" rows="2" required placeholder="Catat dokumen atau keterangan petugas tanpa menyalin nomor identitas lengkap."></textarea></label>
            <button class="btn btn-sm btn-primary" type="submit" name="decision" value="confirmed" onclick="return confirm('Sudah memeriksa bukti bahwa ini pelaku yang sama?')">Hubungkan pelaku</button>
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="decision" value="rejected">Bukan orang yang sama</button>
        </form>
    </div><?php endforeach; ?></div>
    <?php if($match_total>count($match_candidates)): ?><p>Menampilkan 10 calon pertama. Selesaikan keputusan secara bertahap untuk melihat calon lain.</p><?php endif; ?>
</section><?php endif; ?>
