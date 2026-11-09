# Roadmap Rembang UMKM Hub

**Diperbarui 29 Oktober 2026.** Arah utama aplikasi adalah promosi usaha Rembang. Aplikasi membantu pengunjung menemukan toko dan produk, lalu membuka percakapan dengan penjual melalui WhatsApp. Pembayaran dan pengiriman disepakati di luar aplikasi.

## 1. Keputusan konsep

1. **Beranda bebas dibuka.** Pengunjung dapat melihat landing page tanpa mengisi data. Saat memilih kategori atau masuk ke etalase, aplikasi meminta nomor HP. Nomor baru juga diminta nama. Nomor yang sudah tercatat langsung masuk ke etalase.
2. **Nomor HP pada tahap ini belum dibuktikan kepemilikannya.** Sesi tersebut hanya membuka etalase dan mencatat minat pengunjung. Sesi tidak memberi akses ke pengaturan akun, riwayat pribadi, peran petugas, atau data sensitif. Verifikasi nomor baru diperlukan jika kelak fitur tersebut dibuka.
3. **Kontak langsung dan keranjang sama-sama tersedia.** Pengunjung boleh langsung menghubungi UMKM. Jika memakai keranjang, pilihan dikelompokkan per toko. Setiap toko menerima pesan WhatsApp terpisah yang memuat rincian produk melalui template. Aplikasi mencatat bahwa tautan kontak dibuka sebagai *minat*, bukan sebagai bukti penjualan.
4. **Pelaku, kegiatan usaha, dan toko adalah tiga tingkat yang terhubung.** Satu pelaku dapat mempunyai beberapa kegiatan. Kegiatan dapat masuk registri tanpa toko. Toko selalu menunjuk satu kegiatan UMKM. Aktivasi normal memerlukan NIB, kontak bisnis, dan persetujuan Dinas. Profil unggulan yang dinyatakan sudah bertoko oleh pemilik proyek memakai dasar asumsi yang ditandai terpisah sampai bukti NIB dan keputusan asli tersedia.
5. **Superadmin dapat mengelola seluruh modul.** Tindakan penting dan akses terhadap data sensitif tetap dicatat. Admin kecamatan dibatasi pada wilayah tugasnya.
6. **Kategori etalase dapat dipilih UMKM.** Pilihan toko perlu ditinjau sebelum menjadi kategori publik. Kategori etalase berbeda dari KBLI dan skala usaha resmi.
7. **Event memiliki studio visual.** Admin kelak menyusun komponen, teks, gambar, warna, dan formulir dengan drag and drop. Desain disimpan per versi dengan komponen yang tervalidasi.
8. **QR dipakai di titik promosi.** Setiap titik, misalnya kamar hotel, dapat memakai kode sendiri. QR membuka login nomor HP terlebih dahulu, lalu etalase. Aplikasi menghitung kunjungan per hari secara agregat.
9. **Top UMKM ditunda sampai data interaksi cukup.** Kecepatan respons WhatsApp tidak dipakai karena percakapannya berada di luar aplikasi.

## 2. Status saat ini

1. Domain `umkm.projectpintar.com`, CodeIgniter 3, dan tema Tabler telah terpasang. Beranda biru sudah dapat dibuka.
2. `db_umkm` memiliki 76 tabel dengan prefix menurut rumpun. Penjaga aktivasi toko dan produk berjalan di database.
3. Master wilayah Rembang berisi 14 kecamatan dan 294 desa/kelurahan, dengan kode wilayah 2025. Kategori etalase awal berisi 38 kategori. Dua template WhatsApp dan pembentuk pesan telah disiapkan.
4. Lima belas workbook Excel sudah masuk ke registri. Rekonsiliasi 64.442 baris kecamatan menghasilkan 57.325 kegiatan usaha unik; 7.117 baris identik digabung. Bersama 113 profil unggulan, terdapat 57.438 kegiatan kanonis dan 48.468 pelaku yang dapat diidentifikasi. NIK yang valid dan alamat disimpan terenkripsi; hubungan pemilik yang belum yakin menunggu peninjauan.
5. Beranda, akses etalase memakai nomor HP dan nama, sesi terbatas, tombol keluar, serta QR menuju login sudah dibuat. Etalase pelanggan mempunyai pencarian, kategori bergambar, banner yang dapat diganti lewat admin, dua kolom produk dan toko di ponsel, filter kecamatan lalu desa, pagination ringkas, navigasi bawah, dan peta. Kini 113 toko dari profil unggulan aktif; 50 toko sintetis lama dinonaktifkan dan produknya tetap sebagai arsip internal. Beranda etalase menampilkan toko selama produk unggulan belum dilengkapi.
6. Keranjang pengunjung, perubahan jumlah, kontak langsung dari produk dan toko, serta pesan per toko dari keranjang sudah berjalan. Referensi kontak, item, isi pesan, dan asal QR tersimpan. Toko bernomor valid langsung membuka WhatsApp dengan pesan terisi. Nomor tujuan sementara dari pemilik proyek dipasang pada 113 toko unggulan dan di pengaturan admin; nomor tersebut tidak ditulis dalam roadmap.
7. Login pengelola dengan email dan kata sandi sudah aktif. Dashboard, footer, database member pengunjung, database pelaku, kegiatan usaha, toko, serta modul QR tersedia dengan akses menurut peran. Unggah GeoJSON, pengaturan banner, nomor WhatsApp uji, dan pemeriksaan titik lokasi tersedia bagi superadmin dan admin Dinas. Admin kecamatan melihat pelaku dan kegiatan dalam wilayah tugas. Satu calon titik usaha dari sumber web menunggu pemeriksaan.
8. Peta menampilkan titik representatif 14 kecamatan dari OpenStreetMap dan mengelompokkan toko per area. Penanda itu bukan lokasi toko persis. Titik toko persis baru tampil jika ada koordinat publik, rujukan persetujuan pemilik, dan pemeriksaan petugas. Lokasi sintetis tidak dapat dipublikasikan sebagai titik persis.
9. Halaman master untuk kategori etalase, KBLI 2025, skala usaha, dan kode wilayah sudah tersedia dengan navigasi tab. KBLI 2025 telah diisi 1.559 kode kelompok dari publikasi resmi BPS. Peta GIS registri internal menampilkan titik koordinat per UMKM dengan pengelompokan saat zoom, filter, dan panel analisis. Baru satu calon titik yang tersedia dan belum ada titik usaha yang telah diperiksa. Rincian ada di `docs/2026-10-27_master_data_dan_peta_gis_registri.md`.

## 3. Tahap pekerjaan berikutnya

### 3.1. Peninjauan data dan aturan verifikasi

1. Rekonsiliasi baris identik dan kegiatan berbeda sudah berjalan. Berikutnya cocokkan 38 profil unggulan tanpa nama pemilik, 87 tanpa kecamatan, dan 275 calon hubungan nama pada 34 profil dengan petugas sumber. Antrean peninjauan tersedia pada detail registri.
2. Semua 113 toko unggulan memiliki akun hasil impor. Formulir klaim kepemilikan, bukti privat, antrean keputusan, dan pemulihan akun sudah dibuat. Petugas masih perlu memeriksa klaim yang masuk sebelum akses toko diberikan kepada pemilik.
3. NIB placeholder dan dasar persetujuan sumber ditandai sebagai asumsi. Ganti dengan dokumen dan keputusan asli melalui SOP Dinas; aktivasi toko baru di luar berkas unggulan harus melewati pemeriksaan normal.
4. Tinjau akun ganda dari impor lama. Akun alias masih dapat masuk dan membuka data kanonis; tentukan satu akun utama per pelaku sebelum menutup alias secara bertahap.

### 3.2. Etalase dan kontak WhatsApp

1. Daftar toko, filter kategori dan wilayah bertingkat, pencarian, detail produk, harga, keranjang, dan kontak per toko sudah berjalan. Varian, spesifikasi, galeri foto atau video per produk, pengelolaan katalog, dan akses pengelola toko sudah tersedia. Beberapa foto dapat dipilih sekaligus saat membuat atau mengedit produk; foto produk untuk katalog awal dapat diunggah bertahap.
2. Template pesan memuat produk, jumlah, dan nomor referensi. Catatan minat tersimpan saat pesan disiapkan; status `opened` hanya dicatat ketika tautan WhatsApp benar-benar dibuka untuk toko riil.
3. QR umum dan laporan jumlah kunjungan agregat sudah ada. Superadmin dan admin Dinas dapat membuat, mengunduh, serta mengatur QR tambahan di `/pengelola/qr`.
4. Profil 113 toko unggulan tampil sesuai arahan pemilik proyek. Pemilik perlu melengkapi produk, foto, kontak, kategori yang belum cocok, dan wilayah yang belum jelas. Pengelolaan profil toko, kontak, jam layanan, pengajuan kategori, dan inbox aktivitas sudah tersedia. Produk baru kini melalui peninjauan sebelum tampil; produk yang sudah tampil ditarik sementara jika pengelola ingin mengubahnya.

Rincian katalog awal yang kini diarsipkan ada di `docs/2026-10-26_katalog_demo_dan_langkah_berikutnya.md`. Perkembangan terbaru ada di `docs/2026-10-27_rekonsiliasi_pelaku_kegiatan_toko_dan_qr.md`, `docs/2026-10-27_infrastruktur_produk_dan_akses_toko.md`, dan `docs/2026-10-27_kategori_toko_dan_inbox.md`.

### 3.3. Event dan studio desain

1. Daftar kegiatan, halaman detail, kuota, pendaftaran, status peserta, dan pencatatan hadir dasar sudah berjalan. Rincian ada di `docs/2026-10-27_dashboard_dan_kegiatan.md`.
2. Studio drag and drop dari komponen aman sudah tersedia. Desain dan formulir disimpan per versi agar jawaban lama tetap terbaca.
3. Tahap dasar selesai: admin dapat membuat halaman, mengunggah gambar, dan menyusun formulir kegiatan tanpa mengubah kode aplikasi. Materi, masukan, dan laporan agregat masih menjadi pengembangan lanjutan.

### 3.4. Peta, layanan, dan laporan

1. Peta area kecamatan untuk etalase, peta GIS titik UMKM untuk registri internal, dan formulir koordinat petugas sudah berjalan. Pengajuan koordinat langsung dari pengelola toko, pilihan area atau titik persis, persetujuan titik, serta keputusan petugas sudah tersedia. Alamat rumah tetap internal.
2. Tiket konsultasi, fasilitasi, dan pengaduan untuk akun UMKM sudah tersedia dengan penugasan, status, catatan internal, riwayat, dan balasan pemohon. Akses tiket pribadi untuk pengunjung menunggu verifikasi nomor HP.
3. Bangun laporan agregat, lalu uji metode Top UMKM saat data interaksi cukup. Peringkat harus memiliki alasan yang dapat dijelaskan.

## 4. Aturan yang masih perlu dirinci saat modulnya dikerjakan

1. Dokumen minimum dan SOP keputusan NIB serta kunjungan lapangan.
2. Cara meminta izin pemilik sebelum informasi dari Excel lama ditampilkan ke publik.
3. Apakah pendaftar event perlu verifikasi nomor, dan data minimum untuk mendaftar.
4. Apakah harga produk wajib, boleh berupa kisaran, dan bagaimana stok diperbarui.
5. Metode pembuktian nomor serta pemulihan akun jika fitur akun pribadi dibuka.

Pengembangan dilakukan bertahap. Setiap modul diuji pada ponsel dan desktop, termasuk batas akses per peran, perlindungan data pribadi, pencadangan, dan pemulihan sebelum dibuka luas.

## 5. Acuan

1. Konsep utama: `docs/01_gambaran_umum_dan_proses_bisnis.md`.
2. Wilayah: [API Wilayah Indonesia 2025](https://www.emsifa.com/api-wilayah-indonesia/v2/) dan [BPS Kabupaten Rembang](https://rembangkab.bps.go.id/id/statistics-table/3/YkVWWFUyNTJTVTloVGpCeFdFVTNaMk5wUzFaUFFUMDkjMw%3D%3D/jumlah-desa-kelurahan-menurut-kecamatan-di-kabupaten-rembang--2014.html).
3. Skala usaha: [PP 7 Tahun 2021](https://peraturan.bpk.go.id/Details/161837/ppno-7-tahun-2021) dan [Permen UMKM 2 Tahun 2026](https://peraturan.bpk.go.id/Details/357830/permen-umkm-no-2-tahun-2026-pedoman-klasifikasi-dan-tingkat-perkembangan-usaha-m).
4. Lapangan usaha: [KBLI 2025 dari BPS](https://www.bps.go.id/assets/news/2025/12/19/828/bps-rilis-klasifikasi-baku-lapangan-usaha-indonesia--kbli--2025.html).
