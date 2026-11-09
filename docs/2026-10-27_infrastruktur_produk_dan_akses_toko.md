# Infrastruktur produk dan akses toko

Diperbarui 27 Oktober 2026. Fitur produk mula-mula diuji pada 50 toko dan 200 produk sintetis. Setelah rekonsiliasi sumber Excel, toko tersebut dinonaktifkan dan produknya menjadi arsip internal. Etalase kini memakai 113 toko dari profil unggulan; produk spesifik untuk toko tersebut menunggu pelengkapan pemilik. Rincian peralihan ada di `2026-10-27_rekonsiliasi_pelaku_kegiatan_toko_dan_qr.md`.

## 1. Yang sudah berjalan

1. Produk mempunyai deskripsi panjang, catatan ketersediaan, harga dasar, dan spesifikasi bebas seperti bahan, berat, atau dimensi.
2. Satu produk dapat mempunyai banyak varian. Setiap varian mempunyai label, atribut seperti ukuran atau warna, SKU opsional, harga tersendiri, dan stok opsional. Stok kosong berarti jumlah tidak dibatasi oleh katalog. Stok nol menonaktifkan pilihan di halaman pelanggan.
3. Produk menerima paling banyak delapan foto JPG, PNG, atau WebP dan dua video MP4 atau WebM. Saat membuat produk maupun mengeditnya, pengelola dapat memilih beberapa foto sekaligus dan melihat pratinjaunya sebelum mengunggah. Panel membatasi ukuran foto 5 MB dan video 30 MB per berkas. Foto pertama menjadi sampul awal; pengelola dapat mengganti sampul, mengubah urutan, dan menghapus media. Kartu produk menampilkan foto sampul, sedangkan halaman detail pelanggan menampilkan galeri foto.
4. Pelanggan memilih varian sebelum menambahkan produk ke keranjang atau menghubungi toko. Varian yang berbeda tersimpan sebagai baris terpisah. Pesan WhatsApp memuat nama varian dan jumlah. Catatan minat menyimpan label varian sebagai data saat kontak dibuat.
5. Panel produk ada di `/pengelola/produk`. Superadmin dan admin Dinas dapat mengelola semua produk. Pelaku UMKM hanya dapat melihat dan mengubah produk toko yang diberikan kepadanya.
6. Produk baru dapat dibuat dari `/pengelola/produk-baru`. Produk dimulai sebagai draf, memakai kategori yang sudah disetujui untuk toko, lalu dapat dilengkapi sebelum ditampilkan.
7. Superadmin dan admin Dinas mengatur hubungan akun pelaku UMKM dengan toko di `/pengelola/akses-toko`. Satu akun contoh sudah dihubungkan ke satu toko awal untuk menguji pembatasan akses.
8. Lima belas produk arsip awal mempunyai deskripsi, spesifikasi, dan beberapa pilihan ukuran, rasa, warna, atau kemasan. Dua foto ilustrasi untuk produk kopi tetap tersimpan sebagai bahan uji internal. Produk ini tidak ditampilkan setelah toko sintetis dinonaktifkan.
9. Panel `/pengelola/toko` kini mengatur nama, deskripsi, nomor WhatsApp, nama kontak, dan jam layanan. Hari tutup dapat dicatat. Informasi jam muncul di halaman toko bila diisi. Satu toko awal diberi jadwal contoh.

## 2. Pengujian yang dilakukan

1. Migrasi 014 sampai 017 dijalankan langsung di `db_umkm`.
2. Unggah dua foto sekaligus berhasil saat membuat produk dan saat mengedit produk. Kedua berkas tercatat di basis data dan foto pertama menjadi sampul. Produk dan berkas sementara dari pengujian dihapus setelah pemeriksaan. Unggah satu video juga pernah berhasil melalui panel. Dua foto kopi yang dipilih untuk katalog tetap disimpan.
3. Login pelanggan, pemilihan varian, tambah dan hapus keranjang, serta pembukaan WhatsApp dengan varian dan jumlah yang benar berhasil di situs aktif. Data pelanggan dan minat sementara dari pengujian dibersihkan.
4. Akun pelaku UMKM dapat mengedit produk dan profil toko miliknya serta membuat draf. Permintaan ke toko lain dan halaman pengaturan akses ditolak dengan HTTP 403.
5. Jam layanan yang disimpan dari panel tampil pada halaman toko di situs aktif.

## 3. Urutan pekerjaan berikutnya

1. Permintaan perubahan kategori dan inbox aktivitas sudah dibuat. Rincian ada di `2026-10-27_kategori_toko_dan_inbox.md`. Berikutnya tetapkan apakah perubahan identitas toko dan produk perlu persetujuan Dinas sebelum tampil publik.
2. Siapkan alur klaim atau pemberian akses toko yang dapat dipakai ketika toko riil mulai dimasukkan. Pertahankan pemisahan antara data registri internal dan toko publik.
3. Setelah alur toko stabil, lanjutkan modul kegiatan dan layanan sesuai roadmap utama. Penerbitan toko riil baru dilakukan setelah syarat NIB, kontak, persetujuan publikasi, dan peninjauan terpenuhi.
