# Database Rembang UMKM Hub

Database bernama `db_umkm` pada MariaDB 10.11. Skema memakai InnoDB dan `utf8mb4`. Migrasi sampai nomor 040 telah dieksekusi pada 4 November 2026. Skema saat ini memiliki 76 tabel. Pemicu basis data menjaga relasi pelaku, kegiatan, toko, aktivasi toko, dan penerbitan produk.

## Prefix tabel

1. `ref_`: wilayah, skala usaha, KBLI, dan kategori etalase.
2. `iam_`: akun, peran, izin, lingkup kecamatan, dan verifikasi nomor.
3. `umk_`: registri usaha, pemilik, NIB, klaim profil, dokumen, dan jejak impor.
4. `tko_`: toko dan pemeriksaan lapangan.
5. `prd_`: produk, foto, dan kategori produk.
6. `krj_`: keranjang sementara menuju WhatsApp.
7. `evt_`: kegiatan, formulir berversi, dan pendaftaran.
8. `gis_`: lokasi internal dan titik publik.
9. `lay_`: tiket layanan dan riwayatnya.
10. `cms_`: berita, panduan, dan peluang.
11. `rank_`: hasil perhitungan Top UMKM.
12. `ops_`: audit dan catatan migrasi skema.
13. `crm_`: profil minat pengunjung dan sesi etalase dengan nomor HP yang belum diverifikasi.
14. `mkt_`: QR promosi, template WhatsApp, dan catatan minat menghubungi toko.

## Akses aplikasi

CI3 memakai akun `umkm_app` pada database ini dengan izin baca, tambah, ubah, dan hapus data. Akun tersebut tidak dapat mengubah struktur tabel. Kata sandi ada di `.secrets/db_password`, di luar `public/`, dan dibaca oleh `application/config/database.php`. Jangan menyalin kata sandi ke dokumentasi, SQL migrasi, atau repository.

## Data awal yang sudah diisi

1. Master wilayah memuat 14 kecamatan dan 294 desa/kelurahan Rembang. Seluruh kode dan nama cocok dengan lampiran Kepmendagri 300.2.2-2138 Tahun 2025; audit dapat diulang memakai `scripts/audit_wilayah_kepmendagri.py`. Sumber data awal ada dalam `seeds/wilayah_rembang_2025.json`.
2. Kategori etalase awal berjumlah 38. Dua template WhatsApp tersimpan di database; pembentuk pesan berada di `application/libraries/Whatsapp_template.php`.
3. Lima belas workbook dari `docs/umkm/` menghasilkan 57.325 kegiatan usaha unik dari Excel kecamatan dan 113 profil unggulan. Sebanyak 7.117 baris kecamatan yang benar-benar identik menunjuk satu kegiatan. Ada 48.468 pelaku teridentifikasi pada seluruh registri. Tiga puluh delapan profil unggulan belum mempunyai nama pemilik, dan 87 belum dapat dipetakan ke kecamatan dengan yakin.
4. NIK yang memenuhi bentuk 16 digit dan alamat disimpan terenkripsi dengan kunci terpisah di `.secrets/`. Impor tidak menaruh alamat sumber dalam kolom alamat teks biasa. Tanda telaah dan jejak sumber tersimpan pada baris impor.
5. Satu penempatan QR umum telah diisi melalui `seeds/qr_placements.sql`; SVG siap pakai berada di `public/assets/qr-umum.svg`. Superadmin dan admin Dinas mengelola QR tambahan melalui `/pengelola/qr`. Catatan QR menghitung kunjungan harian per penempatan dan QR menuju login pengunjung.
6. Empat akun pengelola awal telah dibuat. Email dan kata sandi ada di `docs/akun_awal_rahasia.md`, di luar web root dengan izin baca pemilik file saja. Akun masyarakat memakai nomor HP tanpa kata sandi.
7. Satu calon koordinat usaha dari OpenStreetMap tersimpan di `gis_location_candidates` untuk pemeriksaan. Tidak ada lokasi usaha yang ditampilkan ke publik. Batas GeoJSON riil belum diunggah.
8. Lima puluh toko sintetis awal dan 200 produknya kini nonaktif serta tersimpan sebagai arsip internal. Toko yang tampil berjumlah 113 dan semuanya menunjuk profil unggulan pada `umk_businesses`. Akun hasil impor unggulan menjadi pengelola toko masing-masing.
9. Nomor NIB yang tidak ada dalam Excel unggulan dicatat sebagai `DUMMY-NIB-<ID>` dengan status `assumed` dan dasar `profile_workbook`. Persetujuan toko dari sumber yang sama dicatat tanpa tanggal kunjungan lapangan buatan. Produk spesifik untuk toko unggulan belum tersedia; pelaku dapat menambahkannya dari panel.
10. KBLI 2025 telah terisi 1.559 kode kelompok. Tiga entri yang berubah dalam lampiran Peraturan BPS 6/2026 telah diselaraskan. Modul kegiatan dan pendaftaran dasar sudah tersedia.
11. Profil kegiatan unggulan menyimpan 113 label bidang, 79 kontak sumber, 5 komoditas, 5 alamat situs atau media sosial, 5 tujuan ekspor, dan 4 keterangan potensi yang tersedia di Excel. Sebanyak 78 kontak menjadi nomor terstruktur; satu kontak lain menunggu pemeriksaan. Formulir detail menyediakan kolom lanjutan untuk skala, KBLI, KBKI, NIB, tenaga kerja, omzet, dan kapasitas produksi.

Aktivasi `tko_stores` dijaga di tingkat database. Jalur normal memerlukan kontak bisnis, NIB terverifikasi, dan hasil kunjungan lapangan yang disetujui. Jalur profil unggulan menerima NIB dan persetujuan yang ditandai sebagai asumsi sumber; status tersebut dapat dibedakan dari verifikasi asli. Pembacaan katalog publik tetap memfilter status toko dan produk di kode aplikasi.

Relasi `umk_people` ke `umk_businesses` memakai `umk_business_owners`. Indeks unik memastikan paling banyak satu pemilik utama per kegiatan. Alias kegiatan tidak boleh memiliki pemilik atau toko sendiri dan harus langsung menunjuk kegiatan kanonis. Toko hanya boleh dibuat untuk kegiatan kanonis yang sudah mempunyai pelaku utama; migrasi 039 menegakkan syarat pelaku untuk toko baru dan aktivasi. Indeks unik pada `tko_stores.business_id` memastikan paling banyak satu toko per kegiatan. Sebanyak 38 profil unggulan lama masih belum mempunyai pelaku yang dapat dipastikan dari sumber dan perlu ditinjau petugas, tanpa membuat hubungan identitas asumsi.

Migrasi 040 menambah `umk_people.registration_district_id` untuk mencatat wilayah pelaku baru sebelum kegiatan pertamanya dibuat. Aplikasi membatasi admin kecamatan melalui `iam_district_roles` pada daftar, detail, dan setiap aksi simpan. Identitas pelaku yang mempunyai kegiatan lintas kecamatan hanya dapat diubah admin Dinas. Alur dan uji akses dijelaskan di `docs/2026-11-04_akses_admin_kecamatan.md`.

Pengajuan klaim memakai `umk_business_claims`; bukti privatnya disimpan di `var/documents/claims`. Peninjauan produk memakai `prd_review_requests`, pengajuan lokasi memakai `gis_location_requests`, dan layanan Dinas memakai `lay_tickets` serta `lay_ticket_updates`. Pemulihan akun memakai `iam_recovery_requests` dan `iam_recovery_attempts`; `iam_users.auth_epoch` membatalkan sesi lama setelah reset kata sandi. Alur lengkap dan pemeriksaan ada di `docs/2026-10-29_alur_klaim_katalog_lokasi_layanan.md`.

Toko sintetis lama tetap berada di basis data untuk pemeriksaan teknis, tetapi berstatus tidak aktif dan tidak boleh dihitung sebagai toko maupun produk publik.

Keranjang pengunjung tersambung ke `crm_customers` melalui `krj_carts.customer_id`; satu pengunjung mempunyai satu keranjang. Pesan kontak yang disiapkan tersimpan pada `mkt_contact_intents.message_text` beserta rincian `mkt_contact_items` dan asal QR jika ada. Toko aktif langsung membuka WhatsApp ke nomor tokonya. Pengaturan tujuan uji lama tetap tersedia di `/pengelola/kontak` tanpa mengubah nomor toko.

Banner etalase tersimpan di `cms_banners` dan dapat diatur superadmin atau admin Dinas melalui `/pengelola/banner`. Gambar yang diunggah tersimpan di `public/assets/banners/`. Titik representatif 14 kecamatan tersimpan di `gis_district_points`; sumbernya OpenStreetMap Nominatim, dengan SQL yang sudah dijalankan di `database/seeds/district_points_osm.sql`. Titik tersebut hanya penanda area kecamatan, bukan alamat toko. Koordinat toko persis hanya boleh tampil dari `gis_business_locations` jika lokasi disetujui pemilik dan telah diperiksa. Pengaturan lokasi di `/pengelola/lokasi` menyimpan rujukan persetujuan pada `gis_business_locations.consent_reference` dan mencatat perubahan di log audit.

Nomor WhatsApp yang diberikan pemilik proyek tersimpan pada 113 toko unggulan dan sebagai tujuan uji di `mkt_contact_settings`; nomor tersebut sengaja tidak disalin ke dokumentasi. Panel `/pengelola/registri` menampilkan kegiatan kanonis menurut cakupan peran, pelaku, keberadaan toko, dan catatan peninjauan pada `umk_review_notes`. Detail kegiatan memakai tab untuk profil, pelaku, klasifikasi, izin, lokasi, dan peninjauan. Menambah catatan tidak mengubah status verifikasi.

Untuk menambah QR lokasi, buka `/pengelola/qr` dengan akun superadmin atau admin Dinas. Kode dan SVG dibuat bersama dari panel. Untuk mengunggah batas wilayah, gunakan `/pengelola/wilayah`; berkas harus memakai kode wilayah yang ada pada master. Rincian rekonsiliasi terbaru ada di `docs/2026-10-27_rekonsiliasi_pelaku_kegiatan_toko_dan_qr.md`; formulir registri dijelaskan di `docs/2026-10-27_detail_registri_dan_form_profil.md`.
