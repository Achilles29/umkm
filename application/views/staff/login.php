<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Pengelola — Rembang UMKM Hub</title>
    <link rel="icon" href="<?= base_url('assets/favicon.ico?v=1') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= base_url('assets/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/auth.css?v=2') ?>">
</head>
<body class="auth-page auth-staff">
<div class="auth-shell">
    <section class="auth-scene" aria-label="Panel pengelola Rembang UMKM Hub">
        <div class="scene-grid" aria-hidden="true"></div><div class="scene-orb scene-orb-one" aria-hidden="true"></div><div class="scene-orb scene-orb-two" aria-hidden="true"></div>
        <a class="auth-brand" href="<?= base_url() ?>"><span class="auth-brand-mark"><img src="<?= base_url('assets/logo-kabupaten-rembang.jpeg?v=1') ?>" alt=""></span><span>Rembang UMKM <small>HUB</small></span></a>
        <div class="scene-content"><span class="scene-eyebrow"><span class="live-dot"></span> RUANG KERJA PENGELOLA</span><h1>Kelola potensi.<br><em>Bangun dampak.</em></h1><p>Data usaha, wilayah, verifikasi, dan promosi dikelola dari satu ruang kerja yang tertata.</p><div class="scene-preview" aria-hidden="true"><span class="preview-spark">✳</span><div><small>PANEL TERPADU</small><strong>Data yang rapi, keputusan yang jelas.</strong></div><span class="preview-arrow">↗</span></div><div class="scene-stats"><div><strong>01</strong><span>Data usaha</span></div><span class="stat-line"></span><div><strong>02</strong><span>Verifikasi</span></div><span class="stat-line"></span><div><strong>03</strong><span>Promosi</span></div></div></div>
        <div class="scene-bottom"><span>© <?= date('Y') ?> Rembang UMKM Hub</span><span>Ruang kerja internal</span></div>
    </section>
    <main class="auth-form-side">
        <div class="auth-topline"><a href="<?= base_url() ?>"><span aria-hidden="true">←</span> Kembali ke beranda</a><span>AKSES PENGELOLA</span></div>
        <div class="auth-form-wrap"><div class="form-step"><span>✦</span><i></i> PANEL PENGELOLA</div><h2>Masuk ke ruang kerja<span class="accent-dot">.</span></h2><p class="auth-lead">Gunakan email dan kata sandi akun superadmin, Dinas, kecamatan, atau pelaku UMKM.</p>
            <?php if ($error): ?><div class="alert alert-danger auth-alert" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
            <form action="<?= site_url('pengelola/masuk') ?>" method="post">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <label for="email" class="auth-label">Email</label><div class="auth-input-wrap"><span class="input-symbol" aria-hidden="true">@</span><input id="email" class="auth-input" type="email" name="email" autocomplete="username" placeholder="nama@contoh.id" maxlength="190" required autofocus></div>
                <label for="password" class="auth-label auth-label-spaced">Kata sandi</label><div class="auth-input-wrap"><span class="input-symbol" aria-hidden="true">✳</span><input id="password" class="auth-input" type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required></div>
                <button class="auth-submit" type="submit">Masuk ke panel <span aria-hidden="true">→</span></button>
            </form>
            <div class="auth-trust"><span aria-hidden="true">◇</span><p>Akses panel dibatasi sesuai peran dan wilayah tugas. Tindakan penting dicatat dalam audit.</p></div>
            <div class="auth-alt"><a href="<?= site_url('pengelola/pulihkan-akun') ?>">Lupa kata sandi akun UMKM?</a></div>
            <div class="auth-alt">Ingin menjelajah sebagai pengunjung? <a href="<?= site_url('masuk') ?>">Masuk ke etalase <span aria-hidden="true">↗</span></a></div>
        </div>
        <div class="auth-footer"><span>Rembang UMKM Hub</span><span>Akses pengelola</span></div>
    </main>
</div>
</body></html>
