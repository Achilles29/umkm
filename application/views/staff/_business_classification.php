<section class="staff-panel"><h2>KBLI kegiatan usaha</h2>
    <p>KBLI mengelompokkan kegiatan ekonomi. Kode pertama pada isian menjadi kegiatan utama. Gunakan kode lima digit yang ada pada master KBLI aktif.</p>
    <?php if (!$kbli): ?><p>KBLI belum ditentukan untuk kegiatan ini.</p><?php else: ?><div class="registry-code-list"><?php foreach($kbli as $item): ?><div><strong><?= html_escape($item['code']) ?> · <?= html_escape($item['title']) ?></strong><small><?= html_escape($item['version_label']) ?><?= $item['is_primary']?' · Utama':'' ?></small></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($can_edit): ?><form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/kbli') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <label class="form-label">Kode KBLI <small>satu kode per baris</small><textarea class="form-control" name="kbli_codes" rows="4" placeholder="Contoh: 10750"><?= html_escape(implode("\n",array_column($kbli,'code'))) ?></textarea></label>
        <p><a href="<?= site_url('pengelola/master/kbli') ?>" target="_blank" rel="noopener">Cari kode pada master KBLI ↗</a></p>
        <button class="btn btn-primary" type="submit">Simpan KBLI</button>
    </form><?php endif; ?>
</section>
<section class="staff-panel"><h2>Komoditas atau jasa yang dihasilkan</h2>
    <p>KBKI mengelompokkan hasil produksi barang dan jasa. Kode KBKI bersifat opsional sampai komoditasnya dipastikan. Kode yang diisi dicatat untuk peninjauan, bukan otomatis dianggap sudah terverifikasi.</p>
    <?php if (!$commodities): ?><p>Komoditas belum dicatat.</p><?php else: ?><div class="registry-commodity-list"><?php foreach($commodities as $item): ?><div class="registry-commodity-item">
        <?php if ($can_edit): ?><small><?= $item['is_primary']?'Komoditas utama · ':'' ?><?= $item['kbki_code']?'KBKI menunggu peninjauan':'KBKI belum ditentukan' ?></small><form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/komoditas/'.$item['id'].'/ubah') ?>">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="registry-form-grid"><label>Nama komoditas atau jasa<textarea class="form-control" name="commodity_name" rows="2" maxlength="1000" required><?= html_escape($item['commodity_name']) ?></textarea></label>
            <label>Kode KBKI 2015<input class="form-control" name="kbki_code" inputmode="numeric" pattern="[0-9]{5}|[0-9]{7}|[0-9]{10}" maxlength="10" value="<?= html_escape($item['kbki_code']?:'') ?>" placeholder="5, 7, atau 10 angka"></label></div>
            <label class="staff-checkbox"><input type="checkbox" name="is_primary" value="1" <?= $item['is_primary']?'checked':'' ?>> Komoditas utama</label>
            <button class="btn btn-sm btn-outline-primary" type="submit">Simpan komoditas</button>
        </form><form method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/komoditas/'.$item['id'].'/hapus') ?>" onsubmit="return confirm('Hapus komoditas ini?')">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
        </form><?php else: ?><strong><?= html_escape($item['commodity_name']) ?></strong><small><?= $item['kbki_code']?'KBKI '.html_escape($item['kbki_code']).' · Menunggu peninjauan':'KBKI belum ditentukan' ?><?= $item['is_primary']?' · Utama':'' ?></small><?php endif; ?>
    </div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($can_edit): ?><form class="registry-add-form" method="post" action="<?= site_url('pengelola/registri/'.$business['id'].'/komoditas') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <h3>Tambah komoditas</h3><div class="registry-form-grid"><label>Nama komoditas atau jasa<textarea class="form-control" name="commodity_name" rows="2" maxlength="1000" required></textarea></label>
        <label>Kode KBKI 2015 <small>opsional</small><input class="form-control" name="kbki_code" inputmode="numeric" pattern="[0-9]{5}|[0-9]{7}|[0-9]{10}" maxlength="10" placeholder="5, 7, atau 10 angka"></label></div>
        <button class="btn btn-primary" type="submit">Tambah komoditas</button>
    </form><?php endif; ?>
    <p><a href="https://klasifikasi.web.bps.go.id/app/kbki" target="_blank" rel="noopener noreferrer">Lihat rujukan KBKI 2015 di BPS ↗</a></p>
</section>
