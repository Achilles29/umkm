# Dashboard pengelola dan kegiatan awal

Tanggal: 27 Oktober 2026.

## 1. Dashboard pengelola

1. Pada layar laptop, dashboard, halaman kegiatan, dan 17 halaman pengelolaan lain memakai satu template sidebar dan satu template navbar atas. Struktur, urutan menu, bagian akun, tombol keluar, serta bilah atas sama di semua halaman. Hak akses menentukan menu yang terlihat; halaman aktif dan judul halaman ditampilkan sesuai konteks.
2. Pada layar ponsel, navbar atas memakai tombol menu dan inbox yang sama di seluruh panel. Sidebar menjadi menu geser; navigasi cepat tersedia di bilah bawah.
3. Tautan kontekstual seperti melihat produk, toko, peta, dan etalase dipindahkan ke area tindakan halaman agar navbar tetap konsisten.
4. Dashboard, katalog pengelola, studio, dan halaman pengelolaan kegiatan telah diperiksa secara visual pada ukuran 1440 × 900 dan 390 × 844 piksel.
5. Ringkasan registri hanya menghitung profil riil. Katalog dan toko yang sedang dipakai untuk pembangunan tetap dihitung sebagai entri etalase aktif.
6. Area kerja dan footer bersama, serta halaman database member, dicatat di `docs/2026-10-27_layout_pengelola_dan_database_member.md`.

## 2. Tahap kegiatan yang sudah berjalan

1. Dinas dan superadmin dapat membuat agenda sebagai draf atau menerbitkannya. Pengaturan mencakup judul, ringkasan, deskripsi, persyaratan, tempat, kecamatan, jadwal, masa pendaftaran, dan kuota.
2. Agenda terbit tampil di `/kegiatan`. Setiap agenda memiliki halaman detail dan tautan pendaftaran.
3. Pengunjung yang sudah masuk dapat mendaftar menggunakan nama dan nomor pada akun. Pendaftaran tercatat dengan nomor referensi dan status awal menunggu. Satu nomor hanya dapat mendaftar sekali untuk satu kegiatan.
4. Pengelola dapat melihat daftar peserta, mengonfirmasi, menolak, dan mencatat kehadiran. Kuota diperiksa di dalam transaksi ketika pendaftaran masuk atau peserta yang ditolak dikembalikan ke status menunggu.
5. Pendaftaran baru masuk ke inbox Dinas dan superadmin. Pengelola dapat membuka daftar peserta langsung dari pesan.
6. Studio kegiatan memungkinkan pengelola mengurutkan komponen dengan drag and drop atau tombol panah, memilih warna, menambah catatan, serta menampilkan gambar utama yang diunggah. Komponen dibatasi pada jenis yang aman dan isi teks di-escape ketika ditampilkan.
7. Pengelola dapat menambah pertanyaan teks singkat, teks panjang, pilihan, angka, tanggal, dan persetujuan. Nama serta nomor HP memakai data akun. Setiap penyimpanan studio membuat versi desain dan formulir baru; jawaban peserta tetap terkait ke versi formulir yang dipakai saat mendaftar.
8. Pengunjung dapat melihat daftar kegiatan yang pernah didaftarkan dan statusnya dari halaman Akun.
9. Daftar kegiatan publik dan pengelola dibagi per 20 entri; daftar peserta per 50 entri. Penomoran halaman tetap ringkas.

## 3. Batasan dan tahap berikutnya

1. Nomor HP akun pengunjung saat ini belum diverifikasi dengan OTP. Karena itu status pendaftaran tetap menunggu keputusan petugas, dan kolom verifikasi nomor pada pendaftaran tidak diisi.
2. Materi kegiatan, masukan peserta, laporan kehadiran agregat, dan verifikasi nomor pendaftar merupakan pengembangan berikutnya.
3. Agenda publik hanya muncul bila pengelola menerbitkannya. Data uji integrasi sudah dibersihkan; tidak ada agenda palsu yang ditinggalkan sebagai terbitan.
