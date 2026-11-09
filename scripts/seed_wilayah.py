#!/usr/bin/env python3
"""Muat master wilayah Rembang yang telah diperiksa berjumlah 294 desa/kelurahan."""
import json
from pathlib import Path
import pymysql

ROOT = Path(__file__).resolve().parents[1]
data = json.loads((ROOT / 'database/seeds/wilayah_rembang_2025.json').read_text())
assert data['province']['code'] == '33'
assert data['regency']['code'] == '33.17'
assert len(data['districts']) == 14
assert sum(len(x['villages']) for x in data['districts']) == 294
password = (ROOT / '.secrets/db_password').read_text().strip()
conn = pymysql.connect(unix_socket='/tmp/mysql.sock', user='umkm_app', password=password,
                       database='db_umkm', charset='utf8mb4', autocommit=False)
try:
    with conn.cursor() as cur:
        cur.execute("INSERT INTO ref_provinces (code,name) VALUES (%s,%s) ON DUPLICATE KEY UPDATE name=VALUES(name)",
                    (data['province']['code'],data['province']['name']))
        cur.execute("SELECT id FROM ref_provinces WHERE code=%s", (data['province']['code'],))
        province_id = cur.fetchone()[0]
        cur.execute("INSERT INTO ref_regencies (province_id,code,name,kind) VALUES (%s,%s,%s,'kabupaten') ON DUPLICATE KEY UPDATE name=VALUES(name)",
                    (province_id,data['regency']['code'],data['regency']['name']))
        cur.execute("SELECT id FROM ref_regencies WHERE code=%s", (data['regency']['code'],))
        regency_id = cur.fetchone()[0]
        for district in data['districts']:
            cur.execute("INSERT INTO ref_districts (regency_id,code,name) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE name=VALUES(name)",
                        (regency_id,district['code'],district['name']))
            cur.execute("SELECT id FROM ref_districts WHERE code=%s", (district['code'],))
            district_id = cur.fetchone()[0]
            rows = [(district_id,v['code'],v['name'], 'kelurahan' if v['code'].split('.')[-1].startswith('1') else 'desa')
                    for v in district['villages']]
            cur.executemany("INSERT INTO ref_villages (district_id,code,name,kind) VALUES (%s,%s,%s,%s) ON DUPLICATE KEY UPDATE name=VALUES(name),kind=VALUES(kind)",rows)
        cur.execute("SELECT COUNT(*) FROM ref_districts WHERE regency_id=%s", (regency_id,))
        district_count=cur.fetchone()[0]
        cur.execute("SELECT COUNT(*) FROM ref_villages v JOIN ref_districts d ON d.id=v.district_id WHERE d.regency_id=%s",(regency_id,))
        village_count=cur.fetchone()[0]
        assert (district_count,village_count)==(14,294),(district_count,village_count)
    conn.commit()
    print('Seeded Rembang:',district_count,'kecamatan,',village_count,'desa/kelurahan')
except:
    conn.rollback()
    raise
finally:
    conn.close()
