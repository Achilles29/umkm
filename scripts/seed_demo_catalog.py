#!/usr/bin/env python3
"""Isi katalog demo di db_umkm tanpa memakai identitas usaha hasil impor."""
import argparse
import random
from pathlib import Path

import pymysql

ROOT = Path(__file__).resolve().parents[1]
SOURCE = 'synthetic_demo_v1'
COUNT = 50

# Produk contoh per subkategori. Status sintetis disimpan di kolom internal is_demo.
CATALOG = {
    'kuliner': {
        'rumah-makan': [('Nasi jagung komplit', 22000, 'porsi'), ('Lontong tuyuhan', 18000, 'porsi'), ('Sate ayam pesisir', 28000, 'porsi'), ('Paket makan keluarga', 75000, 'paket')],
        'oleh-oleh': [('Keripik ikan', 25000, 'bungkus'), ('Kue kering pesisir', 32000, 'toples'), ('Terasi kemasan', 18000, 'bungkus'), ('Paket oleh-oleh Rembang', 95000, 'paket')],
        'minuman': [('Kopi lokal seduh', 18000, 'gelas'), ('Wedang jahe', 15000, 'gelas'), ('Sirup buah lokal', 28000, 'botol'), ('Teh rempah kemasan', 35000, 'kotak')],
    },
    'batik-fesyen': {
        'batik': [('Kain batik motif pesisir', 275000, 'lembar'), ('Selendang batik', 135000, 'buah'), ('Kemeja batik', 225000, 'buah'), ('Sapu tangan batik', 45000, 'buah')],
        'tas-aksesoris': [('Tas kain motif batik', 85000, 'buah'), ('Dompet kain', 55000, 'buah'), ('Gantungan kunci kain', 18000, 'buah'), ('Pouch serbaguna', 42000, 'buah')],
    },
    'kerajinan': {
        'suvenir': [('Magnet suvenir Rembang', 18000, 'buah'), ('Gantungan kunci kayu', 22000, 'buah'), ('Miniatur perahu', 95000, 'buah'), ('Paket suvenir tamu', 75000, 'paket')],
        'dekorasi': [('Hiasan dinding anyaman', 125000, 'buah'), ('Vas dekoratif', 85000, 'buah'), ('Lampu meja kerajinan', 215000, 'buah'), ('Tatakan meja anyaman', 48000, 'set')],
    },
    'hasil-laut': {
        'ikan-segar': [('Ikan laut segar', 38000, 'kg'), ('Udang segar', 78000, 'kg'), ('Cumi segar', 69000, 'kg'), ('Paket hasil laut harian', 120000, 'paket')],
        'olahan-laut': [('Abon ikan', 42000, 'toples'), ('Kerupuk ikan', 28000, 'bungkus'), ('Ikan asap', 55000, 'paket'), ('Sambal ikan', 32000, 'botol')],
    },
    'pertanian': {
        'hasil-tani': [('Beras lokal', 75000, '5 kg'), ('Jagung pipil', 22000, 'kg'), ('Sayur kebun pilihan', 18000, 'paket'), ('Cabai segar', 30000, 'kg')],
        'pangan-olahan': [('Tepung olahan lokal', 24000, 'kg'), ('Keripik singkong', 20000, 'bungkus'), ('Sambal kemasan', 30000, 'botol'), ('Gula aren', 35000, 'bungkus')],
    },
    'kecantikan': {
        'produk-perawatan': [('Sabun herbal', 28000, 'buah'), ('Minyak rambut herbal', 45000, 'botol'), ('Lulur tradisional', 55000, 'kemasan'), ('Paket perawatan', 125000, 'paket')],
        'salon-barbershop': [('Potong rambut', 35000, 'layanan'), ('Cuci rambut', 30000, 'layanan'), ('Perawatan rambut', 85000, 'layanan'), ('Paket rias sederhana', 175000, 'layanan')],
    },
    'jasa': {
        'servis-perbaikan': [('Servis alat rumah tangga', 75000, 'layanan'), ('Pemeriksaan perangkat', 35000, 'layanan'), ('Perbaikan ringan', 60000, 'layanan'), ('Paket perawatan berkala', 180000, 'paket')],
        'fotografi-kreatif': [('Foto produk studio', 150000, 'paket'), ('Desain katalog sederhana', 120000, 'paket'), ('Foto acara', 350000, 'paket'), ('Paket konten usaha', 275000, 'paket')],
    },
    'ritel': {
        'toko-kelontong': [('Paket kebutuhan harian', 85000, 'paket'), ('Beras kemasan', 78000, '5 kg'), ('Minyak goreng kemasan', 38000, '2 liter'), ('Gula pasir', 19000, 'kg')],
        'perlengkapan-usaha': [('Kemasan makanan', 45000, 'paket'), ('Label produk cetak', 65000, 'paket'), ('Kardus pengiriman', 32000, 'paket'), ('Tas belanja kain', 55000, 'paket')],
    },
    'wisata': {
        'penginapan': [('Kamar standar', 250000, 'malam'), ('Kamar keluarga', 420000, 'malam'), ('Paket menginap akhir pekan', 650000, 'paket'), ('Sarapan tamu', 45000, 'orang')],
        'pengalaman-wisata': [('Tur sejarah Lasem', 175000, 'orang'), ('Kelas membatik', 225000, 'orang'), ('Tur kuliner lokal', 195000, 'orang'), ('Paket wisata keluarga', 550000, 'paket')],
    },
}

BRAND_PREFIX = {
    'kuliner': 'Pawon', 'batik-fesyen': 'Griya Batik', 'kerajinan': 'Rupa Karya',
    'hasil-laut': 'Bahari', 'pertanian': 'Kebun', 'kecantikan': 'Laras Cantik',
    'jasa': 'Mitra', 'ritel': 'Toko', 'wisata': 'Jelajah'
}
BRAND_WORDS = ('Sekar', 'Aruna', 'Jati', 'Kencana', 'Laras', 'Pesisir', 'Mentari',
               'Bening', 'Sagara', 'Nirmala', 'Mekar', 'Cahaya', 'Rona', 'Bumi')


def display_name(group, index, district_name):
    return f'{BRAND_PREFIX[group]} {BRAND_WORDS[(index - 1) % len(BRAND_WORDS)]} {district_name}'


def public_store_slug(name, index):
    import re
    return re.sub(r'[^a-z0-9]+', '-', name.lower()).strip('-') + f'-{index:02d}'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true', help='Tulis ke db_umkm')
    args = parser.parse_args()
    rng = random.Random(20261026)
    groups = list(CATALOG)
    category_plan = (groups * 6)[:COUNT]
    rng.shuffle(category_plan)
    print(f'Rencana: {COUNT} usaha/toko demo, {COUNT * 4} produk, {len(groups)} rumpun kategori.')
    if not args.apply:
        print('Gunakan --apply untuk menulis data.')
        return

    password = (ROOT / '.secrets/db_password').read_text().strip()
    conn = pymysql.connect(unix_socket='/tmp/mysql.sock', user='umkm_app', password=password,
                           database='db_umkm', charset='utf8mb4', autocommit=False)
    try:
        with conn.cursor() as cur:
            cur.execute('SELECT COUNT(*) FROM umk_businesses WHERE is_demo = 1 AND data_source = %s', (SOURCE,))
            existing = cur.fetchone()[0]
            if existing == COUNT:
                print('Katalog demo sudah ada; tidak ditambahkan ulang.')
                return
            if existing:
                raise RuntimeError(f'Ada {existing} usaha demo. Periksa sebelum seed ulang.')
            cur.execute('SELECT id, code, name FROM ref_districts ORDER BY code')
            districts = cur.fetchall()
            cur.execute('SELECT district_id, id FROM ref_villages ORDER BY code')
            villages = {}
            for district_id, village_id in cur.fetchall():
                villages.setdefault(district_id, []).append(village_id)
            cur.execute("SELECT id, slug, parent_id FROM ref_categories WHERE category_type='store' AND is_active=1")
            categories = {slug: (category_id, parent_id) for category_id, slug, parent_id in cur.fetchall()}
            cur.execute("SELECT id FROM iam_users WHERE email = 'superadmin@umkm.local' AND status='active' LIMIT 1")
            actor = cur.fetchone()
            cur.execute("SELECT id FROM ref_business_scales ORDER BY id")
            scales = [row[0] for row in cur.fetchall()]
            if not actor or len(districts) != 14 or len(scales) < 3:
                raise RuntimeError('Akun superadmin, wilayah, atau skala usaha belum siap.')
            actor_id = actor[0]

            for index, group in enumerate(category_plan, 1):
                district_id, district_code, district_name = districts[(index - 1) % len(districts)]
                village_id = rng.choice(villages[district_id])
                child_slug = rng.choice(list(CATALOG[group]))
                child_id, parent_id = categories[child_slug]
                if parent_id != categories[group][0]:
                    raise RuntimeError(f'Kategori {child_slug} tidak berada di {group}.')
                label = group.replace('-', ' ').title()
                store_name = display_name(group, index, district_name)
                business_name = f'DEMO Usaha {label} {index:02d}'
                cur.execute(
                    """INSERT INTO umk_businesses
                     (name, description, district_id, village_id, scale_id, registry_status,
                      data_source, is_demo, reviewed_at, reviewed_by)
                     VALUES (%s,%s,%s,%s,%s,'reviewed',%s,1,NOW(),%s)""",
                    (business_name, 'DATA DEMO SINTETIS untuk pengujian aplikasi. Bukan profil usaha nyata.',
                     district_id, village_id, rng.choice(scales), SOURCE, actor_id)
                )
                business_id = cur.lastrowid
                cur.execute('INSERT INTO umk_people (full_name) VALUES (%s)', (f'Pemilik Demo {index:02d}',))
                person_id = cur.lastrowid
                cur.execute("INSERT INTO umk_business_owners (business_id, person_id, relationship, is_primary) VALUES (%s,%s,'owner',1)",
                            (business_id, person_id))
                cur.execute(
                    """INSERT INTO umk_business_licenses
                     (business_id, license_type, license_number, status, verified_at, verified_by)
                     VALUES (%s,'NIB',%s,'verified',NOW(),%s)""",
                    (business_id, f'DEMO-NIB-{index:04d}', actor_id)
                )
                slug = public_store_slug(store_name, index)
                cur.execute(
                    """INSERT INTO tko_stores
                     (business_id, slug, display_name, description, whatsapp_e164, contact_label, status)
                     VALUES (%s,%s,%s,%s,%s,%s,'draft')""",
                    (business_id, slug, store_name,
                     f'Pilihan {label.lower()} dari Kecamatan {district_name}, Kabupaten Rembang.',
                     '+6280000000000', 'Nomor simulasi — jangan dihubungi')
                )
                store_id = cur.lastrowid
                for category_id in (parent_id, child_id):
                    cur.execute(
                        """INSERT INTO tko_store_categories (store_id, category_id, tagged_by, status)
                         VALUES (%s,%s,%s,'approved')""", (store_id, category_id, actor_id)
                    )
                for product_no, (product_name, price, unit) in enumerate(CATALOG[group][child_slug], 1):
                    cur.execute(
                        """INSERT INTO prd_products
                         (store_id, slug, name, description, price_min, price_max, unit_label,
                          availability_note, status, published_at)
                         VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'active',NOW())""",
                        (store_id, f'contoh-{index:02d}-{product_no:02d}', product_name,
                         f'Pilihan dari {store_name}. Harga dan ketersediaan dapat ditanyakan kepada penjual.',
                         price, price, unit, 'Tanyakan ketersediaan',)
                    )
                    product_id = cur.lastrowid
                    cur.execute('INSERT INTO prd_product_categories (product_id, category_id) VALUES (%s,%s)',
                                (product_id, child_id))
                cur.execute(
                    """INSERT INTO tko_verifications
                     (store_id, visit_date, decision, findings, decided_by, decided_at)
                     VALUES (%s,CURDATE(),'approved',%s,%s,NOW())""",
                    (store_id, 'SIMULASI VERIFIKASI. Tidak ada kunjungan lapangan riil.', actor_id)
                )
                cur.execute("UPDATE tko_stores SET status='active', published_at=NOW() WHERE id=%s", (store_id,))
            cur.execute(
                """INSERT INTO ops_audit_logs (actor_id, action_code, entity_type, reason)
                 VALUES (%s,'demo.seed','umk_businesses',%s)""",
                (actor_id, f'{COUNT} usaha sintetis, {COUNT * 4} produk; {SOURCE}')
            )
        conn.commit()
        print(f'Selesai: {COUNT} usaha/toko demo dan {COUNT * 4} produk dalam db_umkm.')
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == '__main__':
    main()
