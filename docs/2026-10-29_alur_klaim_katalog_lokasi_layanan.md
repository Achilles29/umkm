# Alur klaim usaha, katalog, lokasi, dan layanan

Dikerjakan 29 Oktober 2026. Modul berjalan pada situs aktif dan memakai `db_umkm`.

## 1. Klaim kepemilikan dan pemulihan akun

1. Akun UMKM membuka `/pengelola/klaim-usaha`, mencari kegiatan usaha, menjelaskan hubungannya, dan mengunggah bukti PDF, JPG, atau PNG maksimal 5 MB. Bukti disimpan privat di `var/documents/claims`, bukan di direktori publik.
2. Superadmin atau admin Dinas memeriksa registri dan berkas pada halaman yang sama, lalu mencatat dasar persetujuan atau penolakan. Persetujuan memberi akses mengelola toko terkait dan membuat kegiatan dapat dibuka pada **Profil usaha saya**. Klaim akses akun tidak otomatis mengubah identitas pelaku pada `umk_people`; pemilik yang belum tercatat dilengkapi melalui formulir pelaku di detail registri setelah bukti diperiksa.
3. Akun impor yang belum mempunyai klaim disetujui tetap dapat masuk dan mengajukan pembaruan profil untuk ditinjau. Pengubahan langsung pada toko dan produk sumber tersebut memerlukan klaim yang disetujui. Akun yang diberi akses editor secara langsung oleh admin tetap memakai hak editor yang tercatat.
4. Pemilik yang kehilangan kata sandi membuka `/pengelola/pulihkan-akun`, mengisi email dan bukti, lalu menghubungi petugas. Halaman memberi jawaban umum agar keberadaan akun tidak diumumkan. Permintaan dibatasi tiga kali per pasangan email dan alamat asal dalam 24 jam. Admin memeriksa bukti pada `/pengelola/pemulihan-akun`, lalu dapat menerbitkan kata sandi sementara yang hanya tampil sekali dan diserahkan melalui prosedur Dinas. Reset menaikkan nomor sesi akun agar sesi lama tidak berlaku. Kata sandi sementara tidak masuk audit, inbox, atau dokumentasi.

## 2. Peninjauan produk

1. Pengelola toko mengisi produk sebagai draf. Deskripsi minimal 20 karakter, kategori yang disetujui, dan sedikitnya satu foto diperlukan sebelum menekan **Kirim untuk peninjauan**.
2. Produk berstatus menunggu tidak tampil di etalase dan tidak dapat diedit sampai pengajuan dibatalkan atau diputuskan. Admin meninjau dari `/pengelola/tinjauan-produk`; persetujuan menerbitkan produk, penolakan mengembalikannya ke draf beserta alasan.
3. Produk yang sudah tampil harus ditarik ke draf sebelum pengelola toko mengubahnya, lalu diajukan ulang. Admin dapat memperbaiki produk aktif sesuai kewenangan. Pemicu database menolak aktivasi produk baru tanpa keputusan peninjauan yang disetujui.
4. Produk lama pada toko arsip tetap tidak tampil. Fitur ini tidak mengarang produk bagi 113 toko profil unggulan.

## 3. Pengajuan lokasi toko

1. Pengelola toko yang berhak membuka `/pengelola/pengajuan-lokasi`, mengisi koordinat, sumber titik, dan pilihan tampilan: titik persis, area kecamatan saja, atau internal saja.
2. Titik persis memerlukan persetujuan eksplisit pada formulir. Admin memeriksa koordinat dan pilihan itu sebelum menyetujui. Hanya keputusan atas titik persis yang menulis `gis_business_locations` sebagai lokasi publik dengan waktu persetujuan dan pemeriksaan. Pilihan area atau internal menyimpan koordinat internal tanpa menerbitkan titik persis.
3. Satu kegiatan hanya boleh mempunyai satu pengajuan lokasi yang masih menunggu. Penolakan tidak mengubah lokasi publik yang sudah ada. Jalur pengaturan lokasi langsung oleh admin tetap tersedia untuk pemeriksaan dengan rujukan persetujuan yang diperoleh di luar formulir.

## 4. Tiket layanan Dinas

1. Akun UMKM membuat konsultasi, fasilitasi, atau pengaduan di `/pengelola/layanan/baru`. Kegiatan usaha terkait boleh dipilih; nomor referensi unik dibuat otomatis.
2. Superadmin dan admin Dinas melihat seluruh tiket; admin kecamatan hanya melihat tiket di wilayah tugas. Petugas dapat menetapkan status, menugaskan admin Dinas, memberi catatan internal atau catatan yang terlihat pemohon, serta menutup tiket. Pemohon dapat membalas dan mengirim kembali tiket yang perlu perbaikan.
3. Setiap perubahan dicatat di `lay_ticket_updates`, sedangkan inbox memberi tahu petugas atau pemohon. Catatan internal tidak ditampilkan pada akun pemohon. Status yang meminta perbaikan atau menutup tiket harus memakai catatan yang terlihat pemohon.
4. Saat ini tiket dibuat dari akun UMKM pengelola. Akun pengunjung etalase belum diberi akses tiket pribadi karena nomor HP pengunjung belum diverifikasi kepemilikannya.

## 5. Skema, keamanan, dan pemeriksaan

1. Migrasi 034–038 telah dieksekusi. Tabel baru adalah `prd_review_requests`, `gis_location_requests`, `iam_recovery_requests`, dan `iam_recovery_attempts`. Tabel klaim lama diperluas. Saat ini ada 76 tabel dan 38 migrasi.
2. Bukti klaim dan pemulihan disimpan di luar direktori publik dengan nama acak, batas ukuran, pemeriksaan tanda berkas dan gambar, serta unduhan hanya bagi admin yang masuk. Pemeriksaan akses dilakukan di setiap aksi, mengikuti prinsip [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html) dan [File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).
3. Uji HTTP pada situs aktif memakai akun pengujian sementara untuk peran admin Dinas dan UMKM: login; membuka halaman sesuai peran; klaim diajukan dan disetujui; akses toko aktif setelah klaim; lokasi diajukan dan ditolak; tiket dibuat, diproses, dan dibalas; produk dibuat, diajukan, dan ditolak; pemulihan menghasilkan kata sandi baru serta membatalkan sesi lama. Akun, berkas, pengajuan, produk, tiket, dan notifikasi pengujian dihapus setelah pemeriksaan.
4. Uji transaksi database memastikan produk baru tidak dapat diaktifkan tanpa peninjauan yang disetujui, lalu dapat diaktifkan setelah keputusan disimpan. Tidak ada produk pengujian atau lokasi pengujian yang diterbitkan ke publik.

## 6. Data operasional yang masih perlu dilengkapi

1. Tiga puluh delapan profil toko masih belum memiliki pelaku utama yang dapat dipastikan dari Excel. Klaim akses dapat diajukan, tetapi petugas tetap perlu memeriksa dan mengisi identitas pelaku pada registri.
2. Dua ratus tujuh puluh lima calon kecocokan nama dan 87 profil unggulan tanpa kecamatan pasti menunggu pemeriksaan sumber. Tidak ada keputusan yang dibuat otomatis.
3. Belum ada produk profil unggulan atau titik toko persis yang telah diperiksa. Pengelola dan petugas kini memiliki alur untuk mengajukan serta meninjaunya.
