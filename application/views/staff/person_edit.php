<?php $page_title='Ubah pelaku UMKM';$this->load->view('staff/_layout_start',compact('user','page_title')); ?>
<section class="staff-hero"><div><span class="staff-kicker">PELAKU UMKM #<?= (int)$person['id'] ?></span><h1>Ubah identitas pelaku</h1><p>Perubahan identitas berlaku untuk seluruh kegiatan milik pelaku ini.</p></div><a class="btn btn-outline-primary" href="<?= site_url('pengelola/pelaku/'.$person['id']) ?>">Kembali ke profil</a></section>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
<section class="staff-panel"><p>Kecamatan pencatatan: <?= html_escape($person['district_name']?:'Belum dicatat') ?>. NIK: <?= $person['nik_fingerprint']?'Sudah tercatat':'Belum tercatat' ?>.</p><form method="post" class="master-form"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<label>Nama lengkap<input class="form-control" name="full_name" required minlength="2" maxlength="160" value="<?= html_escape((string)($this->input->post('full_name',TRUE)?:$person['full_name'])) ?>"></label>
<label>Nomor telepon<input class="form-control" name="phone" type="tel" maxlength="20" value="<?= html_escape((string)($this->input->method(TRUE)==='POST'?$this->input->post('phone',TRUE):$person['phone_e164'])) ?>"></label>
<label>NIK baru <small>Kosongkan bila tidak berubah</small><input class="form-control" name="nik_new" inputmode="numeric" pattern="[0-9]{16}" maxlength="16" autocomplete="off"></label>
<label>Dasar perubahan<textarea class="form-control" name="change_note" required minlength="20" maxlength="500" rows="3" placeholder="Catat dokumen atau keterangan petugas tanpa menyalin NIK lengkap."><?= html_escape((string)$this->input->post('change_note',TRUE)) ?></textarea></label>
<button class="btn btn-primary" type="submit">Simpan identitas</button></form></section>
<?php $this->load->view('staff/_layout_end'); ?>
