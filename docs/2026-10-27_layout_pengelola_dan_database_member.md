# Penataan panel pengelola dan database member

Tanggal: 27 Oktober 2026.

## 1. Tata letak pengelola

1. Sidebar, navbar atas, navigasi bawah ponsel, dan footer memakai komponen bersama di dashboard, kegiatan, dan halaman pengelolaan lain.
2. Lebar area kerja, jarak tepi, serta tampilan panel diseragamkan pada laptop dan ponsel. Footer berada di bawah isi halaman dan tidak menutupi navigasi bawah ponsel.
3. Dashboard sekarang memakai judul halaman yang selaras dengan halaman pengelolaan lain. Superadmin dan admin Dinas melihat jumlah member bersama ringkasan registri, toko, produk, dan inbox. Statistik serta pintasan tetap mengikuti hak akses masing-masing akun.

## 2. Database member

1. Halaman `/pengelola/anggota` menampilkan data nyata dari tabel `crm_customers`. Tersedia pencarian nama atau nomor HP, pengurutan, dan paginasi ringkas per 20 member.
2. Nomor HP ditampilkan tersamarkan. Hanya superadmin dan admin Dinas yang dapat membuka daftar serta detail member.
3. Detail member menampilkan tanggal pendaftaran, waktu terakhir yang tercatat, jumlah catatan minat toko, jumlah pendaftaran kegiatan, dan masing-masing 10 aktivitas terbaru. Tautan dari notifikasi pendaftaran pengunjung tetap menuju detail member.
4. Status minat WhatsApp dibedakan antara pesan yang disiapkan dan tautan yang dibuka. Keduanya tidak dianggap sebagai transaksi selesai.
5. Nomor HP customer belum diverifikasi dengan OTP. Data ini belum membuktikan kepemilikan nomor; halaman pengelola tidak menampilkan nomor secara penuh.

## 3. Pemeriksaan

1. Tampilan dashboard, database member, detail member, dan halaman kegiatan diperiksa pada ukuran laptop dan ponsel.
2. Pencarian nama serta nomor HP, pengurutan, batas halaman, hak akses empat peran awal, dan keberadaan footer diuji pada situs aktif.
3. Tidak ada tabel baru atau data member buatan yang ditambahkan untuk perubahan ini.
