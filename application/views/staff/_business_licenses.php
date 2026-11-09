<section class="staff-panel"><h2>Nomor Induk Berusaha</h2>
    <p>Nomor sementara dipisahkan dari NIB resmi yang diajukan atau sudah diperiksa. Toko baru hanya dapat diaktifkan setelah NIB resmi diperiksa.</p>
    <?php if (!$licenses): ?><p>NIB belum tercatat.</p><?php else: ?><div class="registry-code-list"><?php foreach($licenses as $license): ?><div>
        <strong><?= html_escape($license['license_type']) ?> · <?= $license['status']==='assumed'?'Nomor sementara':html_escape($license['license_number']) ?></strong>
        <small><?= $license['status']==='assumed'?'Menunggu NIB resmi':($license['status']==='verified'?'Dokumen diperiksa':'Menunggu pemeriksaan') ?><?= $license['verified_at']?' · '.html_escape($license['verified_at']):'' ?></small>
        <?php if ($license['verification_note']): ?><p><?= nl2br(html_escape($license['verification_note'])) ?></p><?php endif; ?>
    </div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($can_edit): ?><form class="registry-nib-form" method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/nib') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <h3>Catat NIB resmi</h3>
        <label>Nomor NIB <small>13 angka</small><input class="form-control" name="nib_number" inputmode="numeric" pattern="[0-9]{13}" minlength="13" maxlength="13" required></label>
        <?php if (in_array($user['role_code'],array('superadmin','admin_dinas'),TRUE)): ?><label class="staff-checkbox"><input type="checkbox" name="document_checked" value="1"> Saya sudah memeriksa dokumen NIB</label><?php else: ?><p>NIB yang dicatat akan menunggu pemeriksaan admin Dinas.</p><?php endif; ?>
        <label>Catatan pemeriksaan<textarea class="form-control" name="verification_note" rows="3" maxlength="500" placeholder="Tulis rujukan dokumen dan tanggal pemeriksaan. Wajib jika dokumen dinyatakan sudah diperiksa."></textarea></label>
        <button class="btn btn-primary" type="submit">Simpan NIB</button>
    </form><?php endif; ?>
</section>
<section class="staff-panel"><h2>Dokumen pendukung NIB</h2>
    <p>Berkas disimpan secara privat. Unggah salinan dokumen untuk membantu pemeriksaan nomor dan identitas usaha.</p>
    <?php if (!$nib_documents): ?><p>Belum ada dokumen NIB.</p><?php else: ?><div class="registry-code-list"><?php foreach($nib_documents as $document): ?><div><strong>Dokumen NIB · <?= html_escape($document['created_at']) ?></strong><small><?= html_escape($document['mime_type']) ?></small><?php if ($can_edit): ?><a href="<?= site_url('pengelola/registri/'.$business['id'].'/dokumen-nib/'.$document['id']) ?>">Unduh dokumen →</a><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($can_edit): ?><form method="post" enctype="multipart/form-data" action="<?= site_url('pengelola/registri/'.$business['id'].'/dokumen-nib') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <label class="form-label">Berkas NIB <small>PDF, JPG, atau PNG; maksimal 5 MB</small><input class="form-control" type="file" name="nib_document" accept="application/pdf,image/jpeg,image/png" required></label>
        <button class="btn btn-outline-primary" type="submit">Unggah dokumen</button>
    </form><?php endif; ?>
</section>
<section class="staff-panel"><h2>Hubungan dengan toko</h2>
<?php if ($store): ?><p>Kegiatan ini menjadi sumber profil toko. Perubahan nama tampilan dan kontak toko dikelola pada halaman toko; identitas pelaku dan perizinan tetap mengikuti kegiatan ini.</p><a class="btn btn-outline-primary" href="<?= site_url('pengelola/toko/'.$store['id']) ?>">Buka profil toko →</a>
<?php else: ?><p>Kegiatan ini belum memiliki toko. Pencatatan kegiatan tidak otomatis membuka toko.</p><?php if($can_edit): ?><a class="btn btn-primary" href="<?= site_url('pengelola/toko/baru').'?business_id='.(int)$business['id'] ?>">+ Tambah toko untuk kegiatan ini</a><?php endif; ?><?php endif; ?>
</section>
