# Pelaku UMKM, kegiatan usaha, toko, dan kode QR

Diperbarui 27 Oktober 2026.

## 1. Pola data yang dipakai

1. `umk_people` menyimpan identitas pelaku. Satu pelaku dapat terhubung ke beberapa baris kegiatan pada `umk_businesses` melalui `umk_business_owners`.
2. `umk_businesses` adalah registri kegiatan usaha. Kegiatan ini dapat ada tanpa toko. Setiap baris Excel kecamatan tetap tercatat di `umk_import_rows` sebagai jejak asal.
3. Baris kecamatan digabung hanya jika pengenal pelaku, nama pelaku, jenis kegiatan, alamat, kecamatan, dan desa sama setelah normalisasi. NIK valid menjadi pengenal pelaku. Jika NIK tidak valid, nama bersama alamat dan kecamatan dipakai agar orang berbeda yang kebetulan bernama sama tidak otomatis disatukan. Perbedaan nama, alamat, atau kegiatan tetap menghasilkan baris kegiatan tersendiri.
4. `tko_stores.business_id` adalah tautan wajib dan unik ke satu kegiatan di `umk_businesses`. Toko tidak mempunyai identitas UMKM kedua. Nama tampilan toko boleh berbeda dari nama kegiatan sebagai merek dagang, tetapi riwayat pemilik, wilayah, perizinan, dan sumber kembali ke kegiatan yang sama.
5. Berkas `DAFTAR PROFILE UMKM UNGGULAN.xlsx` menjadi sumber 113 toko yang ditampilkan. Lima puluh toko sintetis lama dinonaktifkan dan tetap tersimpan sebagai arsip internal beserta produknya. Produk arsip tersebut tidak dihitung sebagai katalog publik.

## 2. Hasil rekonsiliasi Excel kecamatan

1. Sebanyak 64.442 baris menghasilkan 57.325 kegiatan usaha kanonis. Sebanyak 7.117 baris identik menunjuk kegiatan yang sama. Audit lanjutan mempertahankan 22 kegiatan yang sebelumnya tergabung karena NIK, jenis usaha, dan alamat sama, tetapi ejaan nama pelakunya berbeda. Ada 513 profil lama yang menjadi alias setelah penyatuan; tidak ditampilkan sebagai kegiatan tambahan.
2. Setelah 113 profil unggulan ditambahkan, registri berisi 57.438 kegiatan kanonis non-sintetis dan 48.468 pelaku yang dapat diidentifikasi. Dari 113 profil unggulan, 14 pemilik berhasil dihubungkan secara konservatif dengan pelaku dari Excel kecamatan melalui nama serta alamat atau wilayah yang cocok.
3. Sebanyak 38 profil unggulan tidak mencantumkan nama pemilik dan 87 tidak dapat dipetakan dengan yakin ke kecamatan. Kolom tersebut dibiarkan kosong untuk pemeriksaan petugas. Terdapat 275 calon hubungan nama pada 34 profil unggulan yang belum dapat dipastikan. Calon tersebut masuk `umk_person_match_candidates` melalui migrasi 026 dan skrip `scripts/build_person_match_candidates.py --apply`; petugas dapat meninjau, menghubungkan, atau menolak dari detail registri. Migrasi 027 mewajibkan alasan keputusan yang tersimpan bersama riwayatnya. Kesamaan nama saja tidak menggabungkan identitas secara otomatis.
4. Migrasi `database/migrations/024_activity_identity_and_store_source.sql` menambahkan sidik kegiatan dan tautan ke profil kanonis. Skrip `scripts/reconcile_umkm_activities.py --apply` dijalankan langsung terhadap `db_umkm`. Cadangan sebelum perubahan ada di `var/backups/pre_reconcile_2026-10-27.sql`; cadangan sebelum pemisahan 22 variasi nama ada di `var/backups/pre_name_variant_reconcile_2026-10-27.sql`. Keduanya berada di luar direktori publik.

## 3. Status toko dan NIB

1. Seluruh 113 toko unggulan aktif dengan nomor kontak sementara yang sebelumnya diberikan pemilik proyek. Sebanyak 95 toko mempunyai kategori yang dapat dipetakan dari kolom kategori Excel; 18 memerlukan klasifikasi lanjut.
2. Berkas unggulan tidak berisi nomor NIB. Sesuai arahan pemilik proyek, tiap kegiatan toko diberi nomor `DUMMY-NIB-<ID>` dengan status internal `assumed` dan dasar `profile_workbook`. Status tersebut tidak menyatakan bahwa dokumen NIB asli sudah diperiksa.
3. Persetujuan awal toko dicatat pada `tko_verifications` dengan dasar `profile_workbook`, tanpa menciptakan tanggal kunjungan lapangan yang tidak terjadi. Migrasi `database/migrations/025_store_source_assumptions.sql` memperbarui penjagaan aktivasi toko dan filter etalase agar dasar ini dapat dibedakan dari verifikasi dokumen dan kunjungan nyata.
4. Akun UMKM dari profil unggulan telah terhubung sebagai pengelola toko. Akun hasil impor lama masih dapat masuk. Jika satu profil lama menjadi alias, halaman akun mengarah ke kegiatan kanonis; jika satu pelaku mempunyai beberapa kegiatan, semuanya tampil pada **Profil usaha saya**. Berkas kredensial lama tetap privat dan belum diubah.
5. Nama tampilan 56.404 akun impor yang sebelumnya memakai nama kegiatan diselaraskan dengan nama pelaku melalui migrasi 028. Username dan hash kata sandi tidak berubah. Cadangan akun sebelum perubahan berada di `var/backups/pre_account_display_2026-10-27.sql`.
6. Tidak ada produk spesifik pada sebagian besar baris profil unggulan. Karena itu produk arsip lama tidak dipindahkan ke toko lain. Sambil menunggu pemilik melengkapi produk, beranda etalase menampilkan daftar toko yang dapat dihubungi.

## 4. Halaman pengelola

1. `/pengelola/pelaku` menampilkan database pelaku dan jumlah kegiatan serta toko tiap pelaku. Detail pelaku membuka seluruh kegiatan dalam lingkup akses petugas.
2. `/pengelola/registri` menampilkan kegiatan kanonis, nama pelaku, filter toko, wilayah, status, pencarian, dan pagination. Detail kegiatan menghubungkan pemilik, kegiatan lain, izin, toko terkait, dan antrean kecocokan identitas yang perlu keputusan admin Dinas.
3. `/pengelola/toko` menampilkan toko aktif secara bawaan dan menyediakan filter untuk melihat arsip atau status lain. Halaman profil toko menunjuk kegiatan UMKM sumbernya.
4. `/pengelola/qr` tersedia bagi superadmin dan admin Dinas. Pengelola dapat melihat QR umum, membuat kode per penempatan, mengunduh SVG, mengaktifkan atau menonaktifkan kode, dan melihat jumlah pemindaian. QR membuka `/q/{kode}`, lalu login nomor HP dan etalase.

## 5. Pemeriksaan

1. Situs aktif merespons HTTP 200 untuk dashboard, QR, pelaku, registri, toko, peta, etalase, daftar toko, dan detail toko.
2. Pembuatan QR melalui formulir admin menghasilkan SVG yang dapat diakses. QR dan baris sementara pengujian dihapus; satu QR umum tetap tersedia.
3. Akun contoh dari pelaku dengan beberapa kegiatan berhasil masuk dan melihat dua kegiatan. Akun profil unggulan berhasil masuk dan memperoleh akses toko. Salah satu dari 513 akun pada profil alias juga berhasil masuk dan melihat kegiatan kanonis. Keputusan kecocokan identitas diuji dengan satu calon sementara pada pelaku yang memang sudah terhubung; hubungan tetap benar dan calon uji dihapus.
4. Etalase menampilkan 113 toko unggulan dan tidak menampilkan 50 toko sintetis yang dinonaktifkan. Akun pelanggan sementara untuk pemeriksaan etalase telah dihapus.

## 6. Pemeriksaan data berikutnya

1. Cocokkan 38 profil tanpa pemilik, 87 profil tanpa kecamatan, dan 275 calon hubungan nama dengan dokumen atau petugas sumber.
2. Ganti NIB placeholder dengan nomor dan dokumen asli setelah tersedia; catat verifikasi dan persetujuan Dinas yang sungguh dilakukan.
3. Minta pemilik toko melengkapi produk, foto, kategori, kontak, dan wilayah. Jangan menyalin produk sintetis ke toko unggulan tanpa kecocokan sumber.
4. Setelah distribusi akses per pelaku disepakati, pilih satu akun utama per orang dan tutup alias login lama secara bertahap. Sampai saat itu, seluruh kredensial lama tetap berfungsi dan menuju data kanonis yang sama.

## 7. Penguatan relasi basis data

1. Migrasi 031 membersihkan 513 tautan pemilik pada kegiatan alias. Nama pelaku pada tautan lama sama dengan pemilik kegiatan kanonis. Sebanyak 513 baris pelaku duplikat yang tidak punya NIK, nomor kontak, kegiatan kanonis, atau calon kecocokan dihapus setelah dicadangkan pada `var/backups/pre_relation_integrity_2026-10-27.sql`.
2. Indeks unik `uq_umk_one_primary_owner` memastikan satu kegiatan mempunyai paling banyak satu pelaku utama. Migrasi 033 membatasi penanda utama ke angka 0 atau 1. Kegiatan yang belum mempunyai pemilik tetap boleh tersimpan agar 38 profil yang belum dapat dipastikan tidak diberi identitas buatan.
3. Pemicu database menolak tautan pemilik dan toko pada alias, serta menolak perubahan kegiatan berpemilik atau bertoko menjadi alias. Migrasi 032 memastikan alias menunjuk langsung ke kegiatan kanonis dan tidak membentuk rantai atau siklus.
4. Setelah hubungan pelaku dikonfirmasi, nama tampilan akun hasil impor yang terkait diselaraskan dalam transaksi yang sama. Username, kata sandi, dan hak akses tidak berubah. Skrip rekonsiliasi telah disesuaikan agar menghapus tautan pemilik lama sebelum menjadikan kegiatan sebagai alias.
5. Uji transaksi membuktikan database menolak pemilik utama kedua, pemilik dan toko pada alias, perubahan kegiatan berpemilik menjadi alias, serta alias yang menunjuk alias. Tidak ada relasi alias pemilik atau toko dan tidak ada dua pemilik utama pada data saat ini. Sebanyak 38 kegiatan tanpa pelaku utama tetap memerlukan pemeriksaan sumber.
