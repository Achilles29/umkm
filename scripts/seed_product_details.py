"""Isi contoh spesifikasi dan pilihan produk pada katalog awal. Aman dijalankan ulang."""
import sqlite3
import pymysql

root = sqlite3.connect('/www/server/panel/data/default.db').execute('SELECT mysql_root FROM config LIMIT 1').fetchone()[0]
db = pymysql.connect(unix_socket='/tmp/mysql.sock', user='root', password=root, database='db_umkm', charset='utf8mb4', autocommit=False)
products = {
 2: ('Sabun herbal untuk kebutuhan mandi sehari-hari. Pilih aroma yang Anda sukai. Kemasan disiapkan rapi untuk pembelian satuan atau hadiah.', 'Bentuk: Batang\nPenggunaan: Mandi', [('Serai',28000,{'Aroma':'Serai'}),('Lavender',30000,{'Aroma':'Lavender'})]),
 12: ('Gantungan kunci kayu buatan tangan dengan pilihan bentuk yang terinspirasi dari pesisir Rembang. Cocok untuk buah tangan.', 'Bahan: Kayu\nPengerjaan: Buatan tangan', [('Bentuk perahu',22000,{'Bentuk':'Perahu'}),('Bentuk ikan',24000,{'Bentuk':'Ikan'})]),
 18: ('Kopi lokal untuk seduh harian. Pilih tingkat giling dan ukuran kemasan sesuai alat seduh di rumah.', 'Bentuk: Bubuk kopi\nPenyimpanan: Tempat kering', [('100 g, bubuk halus',18000,{'Berat':'100 g','Gilingan':'Halus'}),('250 g, biji utuh',42000,{'Berat':'250 g','Gilingan':'Biji utuh'})]),
 20: ('Wedang jahe beraroma hangat, praktis untuk dinikmati di rumah. Tersedia dalam dua ukuran kemasan.', 'Penyajian: Seduh air panas', [('Kemasan 5 sachet',15000,{'Isi':'5 sachet'}),('Kemasan 10 sachet',28000,{'Isi':'10 sachet'})]),
 34: ('Abon ikan untuk lauk pendamping nasi, bubur, atau bekal perjalanan. Pilih ukuran kemasan sesuai kebutuhan.', 'Jenis: Olahan ikan\nPenyimpanan: Tempat kering', [('Kemasan 100 g',42000,{'Berat':'100 g'}),('Kemasan 200 g',78000,{'Berat':'200 g'})]),
 36: ('Kerupuk ikan renyah untuk camilan atau pelengkap makan. Dapat dipilih dalam bentuk siap santap atau mentah.', 'Jenis: Olahan ikan', [('Siap santap 100 g',28000,{'Bentuk':'Siap santap','Berat':'100 g'}),('Mentah 250 g',35000,{'Bentuk':'Mentah','Berat':'250 g'})]),
 42: ('Tas kain bermotif batik pesisir yang praktis dipakai harian. Tersedia dua pilihan ukuran.', 'Bahan: Kain\nMotif: Pesisir', [('Ukuran sedang',85000,{'Ukuran':'Sedang'}),('Ukuran besar',105000,{'Ukuran':'Besar'})]),
 50: ('Beras lokal pilihan untuk kebutuhan dapur keluarga. Pilih kemasan yang paling sesuai.', 'Asal: Rembang\nJenis: Beras', [('Kemasan 5 kg',75000,{'Berat':'5 kg'}),('Kemasan 10 kg',145000,{'Berat':'10 kg'})]),
 66: ('Kain batik motif pesisir untuk busana atau hadiah. Setiap lembar memiliki nuansa warna yang khas.', 'Motif: Pesisir\nBentuk: Kain lembaran', [('Warna biru',275000,{'Warna':'Biru'}),('Warna cokelat',275000,{'Warna':'Cokelat'})]),
 70: ('Kemeja batik motif pesisir untuk acara santai maupun formal. Pilih ukuran yang nyaman.', 'Bahan: Kain batik\nModel: Kemeja', [('Ukuran M',225000,{'Ukuran':'M'}),('Ukuran L',225000,{'Ukuran':'L'}),('Ukuran XL',240000,{'Ukuran':'XL'})]),
 156: ('Keripik singkong gurih untuk camilan bersama keluarga. Pilih rasa dan ukuran kemasan.', 'Bahan utama: Singkong', [('Original 100 g',20000,{'Rasa':'Original','Berat':'100 g'}),('Pedas 100 g',22000,{'Rasa':'Pedas','Berat':'100 g'})]),
 162: ('Nasi jagung komplit dengan lauk pendamping. Cocok untuk santap siang. Pilih tingkat kepedasan saat memesan.', 'Penyajian: Siap santap', [('Tidak pedas',22000,{'Pedas':'Tidak'}),('Pedas',22000,{'Pedas':'Ya'})]),
 194: ('Keripik ikan renyah khas pesisir. Tersedia pilihan kemasan untuk camilan pribadi atau berbagi.', 'Jenis: Camilan ikan', [('Kemasan 100 g',25000,{'Berat':'100 g'}),('Kemasan 250 g',55000,{'Berat':'250 g'})]),
 202: ('Hiasan dinding anyaman dengan tampilan alami untuk ruang keluarga, ruang kerja, atau hadiah.', 'Bahan: Anyaman\nPengerjaan: Buatan tangan', [('Diameter 30 cm',125000,{'Ukuran':'30 cm'}),('Diameter 40 cm',165000,{'Ukuran':'40 cm'})]),
 210: ('Kain batik motif pesisir dengan pilihan warna untuk kebutuhan busana, dekorasi, atau hadiah.', 'Motif: Pesisir\nBentuk: Kain lembaran', [('Warna biru',275000,{'Warna':'Biru'}),('Warna merah',275000,{'Warna':'Merah'})]),
}
updated=0
with db.cursor() as q:
 for product_id,(description,specs,variants) in products.items():
  q.execute('SELECT id FROM prd_products WHERE id=%s',(product_id,))
  if not q.fetchone(): continue
  q.execute('SELECT COUNT(*) FROM prd_variants WHERE product_id=%s',(product_id,))
  if q.fetchone()[0]: continue
  updated+=1
  q.execute('UPDATE prd_products SET description=%s WHERE id=%s',(description,product_id))
  for n,line in enumerate(specs.split('\n')):
   key,value=[s.strip() for s in line.split(':',1)]
   q.execute('INSERT INTO prd_product_specs (product_id,spec_name,spec_value,sort_order) VALUES (%s,%s,%s,%s) ON DUPLICATE KEY UPDATE spec_value=VALUES(spec_value),sort_order=VALUES(sort_order)',(product_id,key,value,n))
  for n,(label,price,attributes) in enumerate(variants):
   sku=f'INIT-{product_id}-{n+1}'
   q.execute('INSERT INTO prd_variants (product_id,label,sku,price,sort_order) VALUES (%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE label=VALUES(label),price=VALUES(price),sort_order=VALUES(sort_order)',(product_id,label,sku,price,n))
   q.execute('SELECT id FROM prd_variants WHERE product_id=%s AND sku=%s',(product_id,sku))
   variant_id=q.fetchone()[0]
   for key,value in attributes.items():
    q.execute('INSERT INTO prd_variant_attributes (variant_id,attribute_name,attribute_value) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE attribute_value=VALUES(attribute_value)',(variant_id,key,value))
  q.execute('UPDATE prd_products SET price_min=%s,price_max=%s WHERE id=%s',(min(v[1] for v in variants),max(v[1] for v in variants),product_id))
db.commit()
print(f'Updated {updated} products with variants and specifications')
