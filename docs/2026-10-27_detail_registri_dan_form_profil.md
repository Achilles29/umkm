# Detail kegiatan usaha dan formulir profil

Diperbarui 27 Oktober 2026.

## 1. Arti dua halaman

1. `/pengelola/pelaku` berisi orang atau pemilik usaha. Satu pelaku dapat mempunyai beberapa kegiatan usaha. NIK, jika tersedia, disimpan terenkripsi dan digunakan untuk menghindari profil orang yang ganda.
2. `/pengelola/registri` berisi setiap kegiatan usaha. Kegiatan dapat berdiri tanpa toko. Satu toko menunjuk satu kegiatan melalui `tko_stores.business_id`; nama tampilan toko dapat menjadi merek dagang yang berbeda dari nama legal kegiatan.

## 2. Detail registri

1. Detail `/pengelola/registri/{id}` sekarang mempunyai tab **Profil usaha**, **Pelaku**, **KBLI dan komoditas**, **NIB dan toko**, **Lokasi**, dan **Peninjauan**. Informasi teknis sumber berkas tidak ditampilkan pada halaman pengelola.
2. Profil usaha memuat nama kegiatan, nama legal, bentuk usaha, bidang atau kategori, skala, tahun berdiri, jumlah tenaga kerja, omzet tahunan, kapasitas produksi, kecamatan, desa, alamat terenkripsi, deskripsi, kontak, situs, media sosial, tujuan ekspor, dan potensi pengembangan.
3. Tab pelaku menampilkan kegiatan lain milik orang yang sama. Admin Dinas dapat melengkapi identitas yang kosong, memperbarui identitas yang ada, atau menghubungkan pelaku yang sudah terdaftar. Setiap keputusan memerlukan alasan. Mengubah nama pelaku juga memperbarui nama tampilan akun yang terhubung; NIK lama tidak pernah ditampilkan lengkap pada formulir.
4. Tab klasifikasi menggunakan `umk_business_kbli` untuk beberapa kode KBLI, dengan kode pertama sebagai utama. Kode wajib ada pada master aktif. `umk_business_commodities` menyimpan lebih dari satu komoditas atau jasa, kode KBKI 2015 opsional, dan status peninjauan kode. Kode KBKI yang diketik belum otomatis dianggap cocok dengan uraian resmi.
5. Tab perizinan menerima NIB resmi 13 angka dan salinan dokumen PDF, JPG, atau PNG maksimal 5 MB. Berkas disimpan di luar direktori publik dan hanya dapat diunduh superadmin atau admin Dinas. Nomor yang baru diajukan menunggu pemeriksaan. Admin Dinas hanya dapat mencatat status diperiksa jika menandai pemeriksaan dokumen dan mengisi catatan. NIB sementara dari profil unggulan tetap mempunyai status tersendiri dan tidak diubah menjadi dokumen terverifikasi secara otomatis.
6. Admin kecamatan dapat melihat data dalam wilayah tugas serta mencatat lokasi dan peninjauan sesuai haknya. Perubahan profil, pelaku, klasifikasi, dan NIB dibatasi pada superadmin dan admin Dinas.

## 3. Data Excel dan basis data

1. Migrasi `029_registry_profile_and_commodities.sql` menambah kolom profil, tabel komoditas, dan catatan pemeriksaan NIB. Migrasi dieksekusi langsung pada `db_umkm`.
2. Dari 113 profil unggulan, 113 label bidang atau kategori, 79 kontak sumber, 5 komoditas, 5 situs atau media sosial, 5 tujuan ekspor, dan 4 keterangan potensi yang tersedia sudah disalin ke kolom terstruktur. Sebanyak 78 kontak dapat dinormalisasi menjadi nomor telepon; satu kontak tetap terlihat sebagai teks untuk diperiksa. Kolom yang kosong pada Excel tetap kosong agar petugas atau pemilik dapat melengkapinya.
3. Data alamat dan NIK menggunakan enkripsi AES-256-GCM yang sama dengan impor awal. Nomor NIK tidak disimpan dalam teks biasa. Cadangan sebelum perubahan profil ada di `var/backups/pre_registry_profile_2026-10-27.sql` di luar direktori publik.
4. Migrasi `030_kbli_2026_amendment.sql` memperbarui judul dan ringkasan kode 27201, 27203, dan 68122, serta dasar hukum master KBLI 2025 menurut perubahan lampiran September 2026. Cadangan master sebelumnya ada di `var/backups/pre_kbli_2026_amendment_2026-10-27.sql`.

## 4. Pemeriksaan

1. Enam tab merespons HTTP 200 pada situs aktif. Istilah sumber teknis yang diminta untuk dihapus tidak muncul pada tampilan.
2. Formulir profil, KBLI, tambah/ubah/hapus komoditas, NIB diajukan dan diperiksa, tambah/ubah/hubungkan pelaku, serta unggah dan unduh dokumen NIB diuji dengan entri sementara. Nilai tersimpan dan alamat/NIK dapat didekripsi kembali dengan benar. Unduhan dokumen ditolak untuk admin kecamatan. Seluruh entri sementara, berkas, serta audit pengujiannya dihapus.
3. Admin kecamatan mendapat HTTP 404 pada profil di luar wilayahnya, dapat membaca profil di dalam wilayahnya, dan mendapat HTTP 403 saat mencoba mengubah profil.

## 5. Rujukan klasifikasi

1. Master kegiatan menggunakan [KBLI 2025 dari BPS](https://www.bps.go.id/id/publication/2025/12/24/a9b2f130776c7bea36008556/klasifikasi-baku-lapangan-usaha-indonesia-kbli-2025-.html) beserta [perubahan lampiran pada Peraturan BPS 6/2026](https://peraturan.bpk.go.id/Details/358147/peraturan-bps-no-6-tahun-2026-perubahan-atas-peraturan-badan-pusat-statistik-nom).
2. Struktur kode komoditas merujuk [KBKI 2015 pada Kamus Pembakuan Statistik BPS](https://klasifikasi.web.bps.go.id/app/kbki). Kode lima digit dipakai untuk subkelas, tujuh digit untuk kelompok komoditas, dan sepuluh digit untuk komoditas barang.
3. Panjang NIB 13 angka mengikuti [format dokumen NIB pada lampiran peraturan BKPM](https://peraturan.bpk.go.id/Home/Download/162112/2020._1_PerBKPM_OSS_Lampiran_salinan_.pdf). Pemeriksaan dokumen asli tetap dilakukan oleh petugas, bukan disimpulkan dari panjang nomornya.
