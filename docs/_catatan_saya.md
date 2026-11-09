- dimana letak qr code nya? saya belum nemu. harusnya ada di modul admin / superadmin

- lakukan modifikasi data. seperti yang sudah saya jelaskan sebelumnya, kita punya 2 data yaitu UMKM dan Toko. Toko sudah pasti UMKM. tapi UMKM belum tentu punya toko.
nah dari dari excel yang saya kirim, kan ada "DATA PROFIL UMKM ..." dan data per kecamatan. data yang per kecamatan itu adalah database global pelaku UMKM. 1 Pelaku UMKM bisa memiliki lebih dari 1 kegiatan usaha. di data excel per kecamatan banyak yang duplikat, jika identik ambil 1, jika 1 nama / nik punya data lain baik alamat, usaha , maka ambil semua. nah sementara Toko ini adalah UMKM yang sudah memiliki NIB, dan sudah disetujui untuk membuka toko oleh Dinas. file "DATA PROFIL UMKM ..."  ini adalah UMKM yang sudah punya toko. anggap saja sudah punya NIB, jika di excel belum ada kamu buatkan dummy nya.
- nah sekarang reka ulang data nya sesuai pola saya.
- lakukan refraktur yang diperlukan baik database maupun UI / backend yang diperlukan.
- jangan ada dualisme yang tidak sinkron antara umkm dan toko, karena sumber nya sama.


kerjakan dengan teliti.



- /pengelola/registri/ jangan tampilkan "Asal impor" donk, walaupun ini pengembangan tapi hindari menampilkan hal semacam itu. buat halamannya ber tab sesuai rumpun agar lebih rapi
