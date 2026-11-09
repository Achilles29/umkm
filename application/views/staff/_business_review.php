<section class="staff-panel"><h2>Catatan peninjauan</h2>
    <p>Catat hal yang perlu diperiksa tanpa mengubah status verifikasi usaha. Hindari menulis NIK atau alamat rumah di catatan.</p>
    <form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/catatan') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <label class="form-label">Catatan baru<textarea class="form-control" name="note" rows="4" minlength="10" maxlength="1000" required placeholder="Contoh: periksa NIB dan kecocokan nama usaha saat kunjungan."></textarea></label>
        <button class="btn btn-primary" type="submit">Simpan catatan</button>
    </form>
    <?php if ($notes): ?><div class="staff-note-list"><?php foreach($notes as $note): ?><article><strong><?= html_escape($note['full_name']) ?></strong><small><?= html_escape($note['created_at']) ?></small><p><?= nl2br(html_escape($note['note_text'])) ?></p></article><?php endforeach; ?></div><?php endif; ?>
</section>
<section class="staff-panel"><h2>Usulan pembaruan dari pemilik</h2>
<?php if (!$updates): ?><p>Belum ada usulan dari akun usaha ini.</p><?php else: ?><div class="staff-origin-list"><?php foreach($updates as $item): ?><div><strong><?= html_escape($item['proposed_name']) ?></strong><small><?= html_escape($item['account_email']) ?> · <?= html_escape($item['created_at']) ?> · <?= html_escape($item['status']) ?></small><p><?= nl2br(html_escape($item['proposed_description']?:'Tanpa deskripsi')) ?></p><?php if ($item['decision_note']): ?><p>Catatan: <?= nl2br(html_escape($item['decision_note'])) ?></p><?php endif; ?>
    <?php if ($item['status']==='submitted' && $can_edit): ?><form method="post" action="<?= site_url('pengelola/pembaruan-profil/'.$item['id'].'/putusan') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <label class="form-label">Catatan keputusan<textarea class="form-control" name="decision_note" maxlength="500" rows="2" placeholder="Wajib jika usulan ditolak."></textarea></label>
        <button class="btn btn-primary mt-2" type="submit" name="decision" value="approved">Setujui</button>
        <button class="btn btn-outline-danger mt-2" type="submit" name="decision" value="rejected">Tolak</button>
    </form><?php endif; ?>
</div><?php endforeach; ?></div><?php endif; ?>
</section>
