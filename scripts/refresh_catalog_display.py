#!/usr/bin/env python3
"""Rapikan teks etalase; penanda data sintetis tetap di database internal."""
import argparse
import re
from pathlib import Path

import pymysql
from seed_demo_catalog import CATALOG, SOURCE, display_name, public_store_slug

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    password = (ROOT / '.secrets/db_password').read_text().strip()
    conn = pymysql.connect(unix_socket='/tmp/mysql.sock', user='umkm_app', password=password,
                           database='db_umkm', charset='utf8mb4', autocommit=False)
    try:
        with conn.cursor() as cur:
            cur.execute("""SELECT s.id, s.slug, d.name FROM tko_stores AS s
                         JOIN umk_businesses AS b ON b.id = s.business_id
                         JOIN ref_districts AS d ON d.id = b.district_id
                         WHERE b.is_demo = 1 AND b.data_source = %s ORDER BY s.id""", (SOURCE,))
            stores = cur.fetchall()
            if len(stores) != 50:
                raise RuntimeError(f'Diharapkan 50 toko sintetis, ditemukan {len(stores)}.')
            for store_id, slug, district_name in stores:
                matched = re.fullmatch(r'demo-(.+)-(\d{2})', slug)
                if matched and matched.group(1) in CATALOG:
                    group, index = matched.group(1), int(matched.group(2))
                else:
                    continue
                name = display_name(group, index, district_name)
                description = f'Pilihan {group.replace("-", " ")} dari Kecamatan {district_name}, Kabupaten Rembang.'
                if args.apply:
                    cur.execute('UPDATE tko_stores SET slug=%s, display_name=%s, description=%s WHERE id=%s',
                                (public_store_slug(name, index), name, description, store_id))
                    cur.execute('SELECT id, name FROM prd_products WHERE store_id=%s', (store_id,))
                    for product_id, product_name in cur.fetchall():
                        clean_name = product_name.removeprefix('DEMO — ')
                        cur.execute('UPDATE prd_products SET name=%s, description=%s, availability_note=%s WHERE id=%s',
                                    (clean_name, f'Pilihan dari {name}. Harga dan ketersediaan dapat ditanyakan kepada penjual.',
                                     'Tanyakan ketersediaan', product_id))
        if args.apply:
            conn.commit()
            print('Teks 50 toko dan 200 produk diperbarui. Penanda internal tetap ada.')
        else:
            conn.rollback()
            print('Pratinjau: 50 toko siap diperbarui. Gunakan --apply.')
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == '__main__':
    main()
