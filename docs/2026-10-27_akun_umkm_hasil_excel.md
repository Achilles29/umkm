# Akun UMKM hasil impor Excel

Tanggal: 27 Oktober 2026

1. Sebanyak **56.445 profil usaha hasil impor Excel** masing-masing diberi satu akun pengelola berperan UMKM. Username dibentuk dari ID profil, misalnya `umkm-123@umkm.local`. Kata sandi awal berbeda untuk tiap profil dan terdiri dari 12 karakter yang mudah diketik. Seluruh 56.445 akun aktif sesuai arahan pemilik proyek.

2. Daftar username dan kata sandi awal disimpan di `docs/akun_umkm_excel_rahasia.csv`. File berada di luar direktori publik, hanya dapat dibaca root dan pengguna IDE proyek, serta diabaikan oleh Git. Cocokkan ID, nama usaha, dan kecamatan sebelum memberikan **satu baris** kredensial kepada pemilik yang bersangkutan. Lembar ini berisi kata sandi **awal**; setelah pemilik menggantinya, nilai di CSV tidak berlaku lagi.

3. Pemilik masuk melalui `https://umkm.projectpintar.com/pengelola/masuk`. Menu **Profil usaha saya** memperlihatkan data registri yang terkait dengan akun, menerima usulan nama dan deskripsi, menampilkan riwayat keputusan, serta menyediakan ganti kata sandi. Usulan masuk ke inbox admin Dinas dan superadmin. Admin meninjau di detail registri lalu menyetujui atau menolak dengan catatan. Keputusan kembali ke inbox akun UMKM.

4. Kaitan akun ini mengikuti **profil usaha**, bukan verifikasi identitas orang yang memegang kredensial. Data pemilik pada Excel tidak memiliki nomor kontak yang dapat dipakai untuk mengirim akses secara aman. Karena itu penyerahan kredensial harus dicocokkan terlebih dahulu. Akun tidak otomatis mengubah status verifikasi registri, memverifikasi NIB, mengaktifkan toko, atau memperoleh hak mengelola toko. Hak toko tetap melalui penetapan akses tersendiri.

5. Migrasi database yang dijalankan pada `db_umkm`: `021_imported_business_accounts.sql` dan `022_umkm_profile_updates.sql`. Skrip `scripts/provision_imported_umkm_accounts.php --active` membuat akun secara bertahap dan aman diulang. Skrip menghasilkan ulang CSV secara atomik setelah jumlah kaitan akun sesuai jumlah profil impor.

6. Pemeriksaan akhir: jumlah profil, kaitan akun, akun aktif, dan baris CSV sama-sama 56.445; tidak ada profil impor tanpa akun. Semua username unik dan panjang kata sandi sesuai format. Tiga kata sandi sampel cocok dengan hash di database. Login akun awal dan akhir berhasil di situs aktif. Akun UMKM tidak dapat membuka detail registri admin. Pengiriman usulan, persetujuan admin, pergantian kata sandi, dan login ulang berhasil. URL langsung ke CSV menghasilkan HTTP 404; file mempunyai izin `0640` dengan grup khusus pengguna IDE proyek.

## Perubahan setelah rekonsiliasi pelaku dan kegiatan

1. Pada 27 Oktober 2026, baris Excel kecamatan direkonsiliasi menjadi kegiatan usaha kanonis. Ada 513 profil lama yang menjadi alias karena barisnya identik. Akun dan kata sandi dalam CSV tetap berlaku; login alias diarahkan ke kegiatan kanonis.
2. Satu pelaku yang memiliki beberapa kegiatan kini melihat seluruhnya pada `/pengelola/usaha-saya` dan dapat memilih kegiatan yang ingin diajukan pembaruannya. Akun profil unggulan yang telah memiliki toko juga memperoleh akses pengelolaan toko terkait.
3. CSV ini adalah daftar kredensial awal hasil impor sebelum rekonsiliasi, sehingga jumlah barisnya tidak boleh dibaca sebagai jumlah pelaku maupun jumlah kegiatan saat ini. Jumlah terkini adalah 48.468 pelaku teridentifikasi dan 57.438 kegiatan kanonis. Distribusi satu akun utama per pelaku dan penghentian alias lama memerlukan pencocokan identitas lebih lanjut. Rincian ada di `2026-10-27_rekonsiliasi_pelaku_kegiatan_toko_dan_qr.md`.
4. Nama tampilan akun kini mengikuti nama pelaku dari registri bila tersedia. Username dan kata sandi pada CSV tidak berubah.
