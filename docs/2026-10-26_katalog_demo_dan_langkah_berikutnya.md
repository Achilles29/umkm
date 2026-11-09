# Katalog demo dan langkah berikutnya

## 1. Data yang sudah dibuat di `db_umkm`

1. Ada 50 usaha sintetis, masing-masing mempunyai satu pemilik contoh, satu NIB dummy, satu toko aktif, dan satu catatan kunjungan yang disetujui untuk simulasi.
2. Setiap toko memiliki empat produk contoh, sehingga total ada 200 produk. Toko tersebar di 14 kecamatan dan sembilan rumpun kategori etalase.
3. NIB dummy berawalan `DEMO-NIB-`. Semua usaha sintetis memiliki `is_demo = 1` dan `data_source = 'synthetic_demo_v1'`. Penanda ini hanya untuk data internal dan laporan; nama toko, nama produk, slug, dan halaman pelanggan tidak menampilkannya.
4. Nomor WhatsApp toko demo adalah nomor simulasi dan tidak boleh digunakan untuk transaksi. Belum ada lokasi publik atau foto usaha yang dibuat seolah-olah nyata.
5. Seed berada di `scripts/seed_demo_catalog.py`. Jalankan `--apply` hanya jika katalog demo belum ada; skrip akan berhenti tanpa menggandakan 50 usaha yang sudah dibuat.

## 2. Cara mencoba

1. Buka `https://umkm.projectpintar.com/masuk` atau pindai QR umum.
2. Masukkan nomor HP. Jika nomor belum ada, masukkan nama. Akun pengunjung ini hanya membuka etalase.
3. Buka `https://umkm.projectpintar.com/jelajah`. Cari produk atau toko, atau pilih kategori. Halaman `daftar-toko` mempunyai lima halaman, masing-masing maksimal 12 toko.
4. Buka salah satu toko untuk melihat empat produk dan harga contoh. Produk tanpa varian dapat ditambahkan dari kartu. Produk dengan varian dibuka lebih dulu agar pembeli memilih ukuran, rasa, warna, atau kemasan.
5. Ubah jumlah di keranjang, lalu siapkan pesan untuk tiap toko secara terpisah. Tombol kontak langsung tersedia di halaman produk dan toko.
6. Untuk toko sintetis, nomor WhatsApp uji yang diberikan pemilik proyek sudah terpasang pada 50 toko dan di `/pengelola/kontak`. Tombol kontak langsung membuka nomor tersebut dengan pesan sesuai toko dan produk. Nomor uji sengaja tidak ditulis dalam dokumentasi. Toko riil dengan nomor valid memakai tautan WhatsApp masing-masing setelah seluruh syarat publikasi terpenuhi.
7. Buka panel pengelola untuk melihat jumlah usaha asli dan sintetis secara terpisah.

## 3. Tahap berikutnya menurut alur bisnis

1. **Kontak dan keranjang per toko — selesai.** Template pesan, referensi minat, item, dan asal QR tersimpan di database. Pengalihan WhatsApp langsung tersedia untuk toko riil dengan nomor valid atau katalog awal jika admin mengisi nomor uji.
2. **Katalog dan akses toko — selesai.** Panel menerima deskripsi, spesifikasi, varian, foto, video, pembuatan produk draf, dan pembatasan akses pelaku UMKM ke tokonya.
3. **Profil toko — selesai.** Panel pelaku UMKM dapat mengubah nama, deskripsi, kontak, dan jam layanan pada toko yang dikelolanya. Permintaan perubahan kategori dengan peninjauan admin Dinas dan inbox aktivitas juga sudah tersedia.
4. **Toko riil — ditunda.** Peninjauan profil impor, klaim pemilik, NIB, persetujuan publikasi, dan kunjungan lapangan dilanjutkan setelah infrastruktur toko stabil. Koordinat yang ditemukan dari web hanya menjadi calon titik untuk diperiksa.

Catatan: data DEMO berada di database asli untuk pengujian aplikasi, tetapi harus selalu dikecualikan dari angka UMKM nyata dan laporan resmi.
