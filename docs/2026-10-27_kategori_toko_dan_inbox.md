# Pengajuan kategori toko dan inbox pengelola

Diperbarui 27 Oktober 2026. Katalog awal tetap memakai toko yang sudah tersedia. Penambahan atau penghapusan kategori toko sekarang melalui pengajuan pemilik dan keputusan admin Dinas. Inbox dalam panel mencatat aktivitas sesuai hak akses akun.

## 1. Alur kategori

1. Pelaku UMKM membuka `/pengelola/kategori`, memilih toko yang dikelola, lalu mengajukan penambahan atau penghapusan subkategori dengan alasan.
2. Pengajuan berstatus menunggu dan langsung masuk ke inbox superadmin serta admin Dinas. Kategori publik belum berubah pada tahap ini.
3. Admin meninjau alasan, jumlah produk yang memakai kategori, lalu menyetujui atau menolak. Penolakan wajib disertai alasan. Keputusan tercatat dalam audit dan dikirim ke inbox pemilik toko.
4. Kategori yang disetujui tersedia sebagai pilihan saat membuat atau menyunting produk. Penghapusan kategori ditolak bila masih ada produk yang menggunakannya. Produk dapat dipindahkan dahulu dari editor produk.
5. Pengajuan berulang untuk kategori yang sama selama masih menunggu dibatasi di database. Riwayat keputusan tetap tersimpan.

## 2. Isi inbox

1. Pendaftaran pengunjung baru dari formulir nomor HP dan nama dikirim ke superadmin dan admin Dinas. Nomor ditampilkan tersamar; kepemilikan nomor belum diverifikasi.
2. Pembukaan tautan kontak WhatsApp dikirim ke superadmin, admin Dinas, pengelola toko tujuan, dan admin kecamatan yang bertugas di wilayah toko. Catatan ini adalah minat pelanggan, bukan bukti pembayaran atau pesanan selesai.
3. Produk draf yang dibuat pelaku UMKM dikirim ke superadmin dan admin Dinas.
4. Pengajuan kategori dan keputusan admin dikirim ke pihak yang perlu menindaklanjuti. Pemberian atau pencabutan akses toko juga dikirim ke akun pelaku UMKM terkait.
5. Setiap akun mempunyai status sudah dibaca tersendiri. Panel menampilkan jumlah pesan belum dibaca dan halaman `/pengelola/inbox` menyediakan filter serta tombol tandai semua dibaca.
6. Inbox berjalan di dalam aplikasi. Belum ada pesan keluar melalui email atau WhatsApp.

## 3. Pengecekan

1. Migrasi 018 dan 019 dijalankan langsung di `db_umkm`. Jumlah tabel saat ini 68.
2. Penambahan kategori diuji sampai disetujui, lalu kategori tersedia pada formulir produk. Penghapusan kategori yang tidak dipakai diuji sampai disetujui.
3. Penghapusan kategori yang masih dipakai produk ditolak; kategori tetap aktif. Pengajuan tersebut kemudian ditolak dengan alasan.
4. Pendaftaran pengunjung, pembukaan WhatsApp, dan pembuatan produk draf diuji menghasilkan notifikasi sesuai penerima. Data pengujian sementara dibersihkan sesudah diperiksa.
5. Satu pendaftaran dan satu kontak yang sudah ada sebelum inbox dibuat dimasukkan dengan tanggal kejadian aslinya. Skrip `scripts/backfill_inbox.py` aman dijalankan ulang.
6. Pelaku UMKM tidak dapat mengajukan kategori toko lain atau melihat detail pendaftaran pengunjung. Status baca miliknya tidak mengubah inbox admin.

## 4. Pekerjaan berikutnya

1. Perjelas aturan peninjauan perubahan identitas toko dan produk bila Dinas memerlukan persetujuan sebelum perubahan tampil publik.
2. Bangun alur klaim profil, pemeriksaan NIB, dan verifikasi toko riil saat toko riil akan mulai dimasukkan.
3. Lanjutkan modul kegiatan dan layanan sesuai roadmap utama setelah alur katalog dan toko stabil.
