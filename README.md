# Rembang UMKM Hub

Fondasi aplikasi menggunakan CodeIgniter 3.1.13 dan PHP 8.1. Konsep produk masih dibahas di `docs/01_gambaran_umum_dan_proses_bisnis.md`.

## Struktur

- `public/` adalah satu-satunya web root. `public/index.php` adalah front controller.
- `application/` dan `system/` adalah kode CI3 di luar web root.
- `var/sessions/` dan `var/logs/` adalah direktori runtime, juga di luar web root.
- `database/` berisi SQL migrasi yang telah dijalankan dan catatan struktur `db_umkm`.
- `docs/` berisi rancangan internal dan tidak dilayani sebagai berkas statis.

Konfigurasi situs Nginx: `/www/server/panel/vhost/nginx/umkm.projectpintar.com.conf`. Aturan route: `/www/server/panel/vhost/rewrite/umkm.projectpintar.com.conf`.

## Tema

Tabler Core 1.7.1 dipasang lokal di `public/assets/` sebagai fondasi visual. Lisensi MIT ada di `public/assets/TABLER-LICENSE.txt`. Beranda memakai `public/assets/umkm.css`, sedangkan etalase pelanggan memakai `public/assets/marketplace.css` dan ikon kategori SVG lokal. Lambang Kabupaten Rembang berasal dari `/www/wwwroot/pustaka/img`. Roadmap konsep ada di `docs/2026-10-26_roadmap_pengembangan_umkm.md`.

Database `db_umkm` memiliki 72 tabel dengan prefix per rumpun. Registri memisahkan pelaku, kegiatan usaha, dan toko. Dari Excel tersedia 57.438 kegiatan kanonis dan 48.468 pelaku teridentifikasi. Sebanyak 113 toko profil unggulan tampil; 50 toko sintetis lama dan 200 produknya menjadi arsip internal. Struktur dan status pengisian dijelaskan di `database/README.md`.

## Status akses domain

Origin Nginx melayani `http://127.0.0.1:80` dengan Host `umkm.projectpintar.com`, dan jembatan tunnel lokal melayani `http://127.0.0.1:8080` dengan Host yang sama. Public Hostname Cloudflare sudah diarahkan ke HTTP pada port 8080; `https://umkm.projectpintar.com/` telah memberi HTTP 200 pada 26 Oktober 2026.

Beranda publik dapat dilihat tanpa mengisi data. Pilihan kategori membuka etalase setelah pengunjung memasukkan nomor HP dan, untuk nomor baru, nama. Karena nomor belum dibuktikan kepemilikannya, sesi ini hanya untuk etalase. Katalog pelanggan mempunyai pencarian, kategori bergambar, daftar toko dua kolom di ponsel, filter kecamatan dan desa, peta area toko, detail produk, keranjang per toko, serta kontak WhatsApp langsung. Profil unggulan dari Excel menjadi sumber toko yang tampil. Sebagian besar profil belum mempunyai produk spesifik; halaman etalase menampilkan toko sambil menunggu pelengkapan produk.

Login pengunjung: `https://umkm.projectpintar.com/masuk`. Login superadmin, Dinas, kecamatan, dan UMKM: `https://umkm.projectpintar.com/pengelola/masuk`. Empat akun awal tersimpan dalam `docs/akun_awal_rahasia.md` yang privat dan tidak masuk repository. Panel pengelola menyediakan database pelaku di `/pengelola/pelaku`, kegiatan di `/pengelola/registri`, toko di `/pengelola/toko`, dan QR promosi di `/pengelola/qr`. Detail kegiatan sekarang memakai enam tab dan formulir profil lengkap; rincian ada di `docs/2026-10-27_detail_registri_dan_form_profil.md`.
