<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Jelajahi produk dan toko UMKM Rembang, simpan pilihan di keranjang, lalu hubungi penjual.">
    <title>Rembang UMKM Hub — Belanja Produk Lokal</title>
    <link rel="icon" href="<?= base_url('assets/favicon.ico?v=1') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= base_url('assets/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/umkm.css?v=8') ?>">
</head>
<body>
<div class="site-shell">
    <header class="site-header">
        <div class="container-xl header-inner">
            <a class="brand" href="<?= base_url() ?>" aria-label="Rembang UMKM Hub, beranda">
                <span class="brand-mark"><img src="<?= base_url('assets/logo-kabupaten-rembang.jpeg?v=1') ?>" alt="Lambang Kabupaten Rembang"></span>
                <span><strong>Rembang UMKM</strong><small>Hub</small></span>
            </a>
            <nav class="header-links" aria-label="Navigasi utama">
                <a href="#kategori">Kategori</a>
                <a href="#arah-aplikasi">Layanan</a>
                <a href="<?= site_url('kegiatan') ?>">Kegiatan</a>
                <a href="#alur">Cara kerja</a>
                <a href="#status">Mulai belanja</a>
            </nav>
            <div class="header-auth"><a href="<?= site_url('masuk') ?>">Masuk etalase</a><a class="header-admin" href="<?= site_url('pengelola/masuk') ?>">Pengelola <span aria-hidden="true">↗</span></a></div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container-xl hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow"><span class="eyebrow-dot"></span> Dari Rembang, untuk semua</span>
                    <h1>Temukan rasa dan karya <em>Rembang.</em></h1>
                    <p>Jelajahi produk lokal, kenali toko dari berbagai kecamatan, lalu hubungi penjual dengan mudah.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary btn-lg" href="<?= site_url('masuk') ?>">Jelajahi produk <span aria-hidden="true">→</span></a>
                        <a class="text-link" href="<?= site_url('masuk').'?next='.rawurlencode('/peta') ?>">Lihat peta toko <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="hero-spotlight" aria-label="Kategori populer"><a href="<?= site_url('masuk').'?next='.rawurlencode('/jelajah?category=kuliner') ?>"><img src="<?= base_url('assets/categories/kuliner.svg') ?>" alt=""><span>Kuliner</span></a><a href="<?= site_url('masuk').'?next='.rawurlencode('/jelajah?category=batik-fesyen') ?>"><img src="<?= base_url('assets/categories/batik-fesyen.svg') ?>" alt=""><span>Batik &amp; Fesyen</span></a><a href="<?= site_url('masuk').'?next='.rawurlencode('/jelajah?category=kerajinan') ?>"><img src="<?= base_url('assets/categories/kerajinan.svg') ?>" alt=""><span>Kerajinan</span></a></div>
                </div>
                <div class="preview" aria-label="Etalase UMKM Rembang">
                    <div class="preview-top"><span class="preview-title">Jelajahi Rembang</span><span class="preview-menu" aria-hidden="true">•••</span></div>
                    <div class="preview-search"><span aria-hidden="true">⌕</span> Cari produk atau usaha lokal</div>
                    <div class="preview-banner"><span class="preview-banner-kicker">ETALASE LOKAL</span><strong>Cerita, karya, dan rasa dari Rembang.</strong><span>Temukan yang dekat. Dukung yang tumbuh.</span><img src="<?= base_url('assets/categories/hasil-laut.svg') ?>" alt=""></div>
                    <div class="preview-category-title">Jelajahi kategori</div>
                    <div class="preview-categories"><span>Kuliner</span><span>Kerajinan</span><span>Fesyen</span><span>Lainnya</span></div>
                    <div class="preview-foot">Temukan pilihan dari penjuru Rembang</div>
                </div>
            </div>
        </section>

        <section id="kategori" class="section category-section"><div class="container-xl">
            <span class="section-kicker">PILIH SESUAI KEBUTUHAN</span>
            <h2>Kategori usaha lokal</h2>
            <p class="category-intro">Pilih kategori untuk menemukan produk dan toko yang sesuai kebutuhanmu.</p>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <a class="category-link" href="<?= site_url('masuk').'?next='.rawurlencode('/jelajah?category='.$category['slug']) ?>">
                        <img src="<?= category_art_url($category['slug']) ?>" alt=""><span><?= html_escape($category['name']) ?></span><span aria-hidden="true">↗</span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="top-note"><strong>Belanja lokal</strong><span>Simpan beberapa pilihan di keranjang, lalu hubungi tiap toko dengan rincian produk yang kamu pilih.</span></div>
        </div></section>

        <section id="arah-aplikasi" class="section features">
            <div class="container-xl">
                <div class="section-heading"><div><span class="section-kicker">SATU RUANG, BANYAK KESEMPATAN</span><h2>Kenali UMKM Rembang</h2></div><p>Temukan produk lokal, kenali toko, dan mulai percakapan dengan penjual melalui ponsel.</p></div>
                <div class="feature-grid">
                    <article class="feature-card"><span class="feature-icon" aria-hidden="true">◈</span><h3>Etalase produk</h3><p>Jelajahi produk dan toko berdasarkan kategori serta wilayah, lalu hubungi penjual melalui WhatsApp.</p><span class="feature-tag">Promosi UMKM</span></article>
                    <article class="feature-card"><span class="feature-icon" aria-hidden="true">⌖</span><h3>Direktori toko</h3><p>Lihat pilihan toko dari berbagai kecamatan dan jelajahi produk yang mereka tawarkan.</p><span class="feature-tag">Temukan usaha</span></article>
                    <article class="feature-card"><span class="feature-icon" aria-hidden="true">▣</span><h3>Keranjang belanja</h3><p>Rangkum pilihan produk dari beberapa toko dalam satu tempat.</p><span class="feature-tag">Simpan pilihan</span></article>
                    <article class="feature-card"><span class="feature-icon" aria-hidden="true">✧</span><h3>Hubungi penjual</h3><p>Siapkan pesan berisi rincian produk dan lanjutkan percakapan dengan tiap toko.</p><span class="feature-tag">Mulai percakapan</span></article>
                </div>
            </div>
        </section>

        <section id="alur" class="section flow"><div class="container-xl flow-inner"><div><span class="section-kicker">MUDAH DARI AWAL</span><h2>Lihat, pilih, lalu terhubung.</h2><p>Beranda dapat dilihat tanpa masuk. Saat membuka etalase, isi nomor HP dan nama jika baru pertama kali. Pilih produk, simpan di keranjang, lalu siapkan pesan untuk penjual.</p></div><ol><li><span>01</span><div><strong>Jelajahi</strong><small>Temukan usaha dan produk lokal.</small></div></li><li><span>02</span><div><strong>Simpan pilihan</strong><small>Rangkum produk dalam keranjang.</small></div></li><li><span>03</span><div><strong>Hubungi penjual</strong><small>Sepakati transaksi langsung dengan penjual.</small></div></li></ol></div></section>

        <section id="status" class="section status"><div class="container-xl"><div class="status-panel"><div><span class="section-kicker">MULAI DARI SINI</span><h2>Jelajahi produk Rembang.</h2><p>Cari berdasarkan nama produk, pilih kategori favorit, atau kunjungi halaman toko untuk melihat koleksinya.</p></div><a class="status-pill" href="<?= site_url('masuk') ?>"><span class="eyebrow-dot"></span> Buka etalase →</a></div></div></section>
    </main>

    <footer class="site-footer"><div class="container-xl"><span>© <?= date('Y') ?> Rembang UMKM Hub</span><span>Ruang digital untuk usaha lokal Rembang</span></div></footer>
</div>
<script src="<?= base_url('assets/tabler.min.js') ?>" defer></script>
</body>
</html>
