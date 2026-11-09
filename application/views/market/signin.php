<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Masuk ke etalase Rembang UMKM Hub dengan nomor HP.">
    <title><?= $phone === '' ? 'Masuk ke Etalase' : 'Lengkapi Nama' ?> — Rembang UMKM Hub</title>
    <link rel="icon" href="<?= base_url('assets/favicon.ico?v=1') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= base_url('assets/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/auth.css?v=2') ?>">
</head>
<body class="auth-page">
<div class="auth-shell">
    <section class="auth-scene" aria-label="Tentang Rembang UMKM Hub">
        <div class="scene-grid" aria-hidden="true"></div>
        <div class="scene-orb scene-orb-one" aria-hidden="true"></div>
        <div class="scene-orb scene-orb-two" aria-hidden="true"></div>
        <a class="auth-brand" href="<?= base_url() ?>"><span class="auth-brand-mark"><img src="<?= base_url('assets/logo-kabupaten-rembang.jpeg?v=1') ?>" alt=""></span><span>Rembang UMKM <small>HUB</small></span></a>
        <div class="scene-content">
            <span class="scene-eyebrow"><span class="live-dot"></span> RUANG DIGITAL USAHA LOKAL</span>
            <h1>Temukan yang <em>dekat.</em><br>Dukung yang <em>tumbuh.</em></h1>
            <p>Dari kuliner dan oleh-oleh hingga karya kreatif, jelajahi potensi Rembang dalam satu ruang yang mudah diakses.</p>
            <div class="scene-preview" aria-hidden="true"><span class="preview-spark">✳</span><div><small>JELAJAHI REMBANG</small><strong>Satu langkah menuju usaha lokal.</strong></div><span class="preview-arrow">↗</span></div>
            <div class="scene-stats"><div><strong>14</strong><span>Kecamatan</span></div><span class="stat-line"></span><div><strong>294</strong><span>Desa &amp; kelurahan</span></div><span class="stat-line"></span><div><strong>38</strong><span>Kategori awal</span></div></div>
        </div>
        <div class="scene-bottom"><span>© <?= date('Y') ?> Rembang UMKM Hub</span><span>Buatan untuk Rembang</span></div>
    </section>

    <main class="auth-form-side">
        <div class="auth-topline"><a href="<?= base_url() ?>"><span aria-hidden="true">←</span> Kembali ke beranda</a><span>ETALASE UMKM</span></div>
        <div class="auth-form-wrap">
            <div class="form-step"><span><?= $phone === '' ? '01' : '02' ?></span><i></i><?= $phone === '' ? 'AKSES PENGUNJUNG' : 'LENGKAPI PROFIL' ?></div>
            <?php if ($phone === ''): ?>
                <h2>Selamat datang<span class="accent-dot">.</span></h2>
                <p class="auth-lead">Masukkan nomor HP untuk melanjutkan ke etalase UMKM Rembang.</p>
                <?php if ($error): ?><div class="alert alert-danger auth-alert" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
                <form action="<?= site_url('masuk') ?>" method="post">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="next" value="<?= html_escape($next) ?>">
                    <label for="phone" class="auth-label">Nomor HP</label>
                    <div class="auth-input-wrap"><span class="input-symbol" aria-hidden="true">✳</span><input id="phone" class="auth-input" type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" required autofocus></div>
                    <p class="field-hint">Nomor baru akan diminta mengisi nama pada langkah berikutnya.</p>
                    <button class="auth-submit" type="submit">Lanjutkan <span aria-hidden="true">→</span></button>
                </form>
            <?php else: ?>
                <h2>Kenalan dulu<span class="accent-dot">.</span></h2>
                <p class="auth-lead">Nomor <strong><?= html_escape($phone) ?></strong> belum tercatat. Isi nama agar Anda dapat mulai menjelajah.</p>
                <?php if ($error): ?><div class="alert alert-danger auth-alert" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
                <form action="<?= site_url('masuk/nama') ?>" method="post">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="phone" value="<?= html_escape($phone) ?>">
                    <input type="hidden" name="next" value="<?= html_escape($next) ?>">
                    <label for="name" class="auth-label">Nama Anda</label>
                    <div class="auth-input-wrap"><span class="input-symbol person-symbol" aria-hidden="true">◌</span><input id="name" class="auth-input" type="text" name="name" maxlength="160" autocomplete="name" placeholder="Nama lengkap" required autofocus></div>
                    <button class="auth-submit" type="submit">Buka etalase <span aria-hidden="true">→</span></button>
                </form>
                <a class="back-step" href="<?= site_url('masuk').'?next='.rawurlencode($next) ?>">← Ganti nomor HP</a>
            <?php endif; ?>
            <div class="auth-trust"><span aria-hidden="true">◇</span><p>Nomor HP belum diverifikasi. Akses ini hanya untuk menjelajah dan mencatat minat; data pribadi memerlukan verifikasi terpisah.</p></div>
            <div class="auth-alt">Pengelola Dinas atau pelaku UMKM? <a href="<?= site_url('pengelola/masuk') ?>">Masuk ke panel pengelola <span aria-hidden="true">↗</span></a></div>
        </div>
        <div class="auth-footer"><span>Rembang UMKM Hub</span><span>Promosi usaha lokal Rembang</span></div>
    </main>
</div>
</body>
</html>
