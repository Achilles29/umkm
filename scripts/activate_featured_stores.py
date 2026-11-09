#!/usr/bin/env python3
"""Hubungkan profil unggulan ke toko dan catat dasar asumsi NIB/persetujuan."""
import argparse
import re
import unicodedata
from collections import defaultdict
import pymysql
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from import_umkm_unggulan import records, path
from import_umkm_excel import ROOT, norm, secret_file

CATEGORY = {
    'FASION': 'pakaian', 'MAKANAN': 'makanan-kemasan',
    'SNACK': 'jajanan', 'MINUMAN': 'minuman', 'KOPI': 'minuman',
    'CATERING': 'katering', 'BACKERY': 'jajanan',
    'AGROBISNIS': 'hasil-tani', 'CRAF': 'suvenir',
    'IKAN ASAP': 'olahan-laut', 'MADU': 'pangan-olahan',
    'EKSPOR FURNITURE': 'mebel', 'EKSPOR HASIL LAUT': 'olahan-laut',
}


def slug(name, business_id):
    text = unicodedata.normalize('NFKD', name).encode('ascii', 'ignore').decode().lower()
    text = re.sub(r'[^a-z0-9]+', '-', text).strip('-')[:80].strip('-')
    return (text or 'toko') + '-' + str(business_id)


def decode(aes, value):
    if not value:
        return ''
    try:
        return aes.decrypt(value[1:13], value[13:], None).decode()
    except Exception:
        return ''


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    data = list(records())
    db = pymysql.connect(unix_socket='/tmp/mysql.sock', user='umkm_app',
                         password=(ROOT/'.secrets/db_password').read_text().strip(),
                         database='db_umkm', charset='utf8mb4', autocommit=False)
    try:
        with db.cursor() as q:
            q.execute("""SELECT r.sheet_name,r.source_row,r.business_id
                FROM umk_import_rows r JOIN umk_import_batches batch ON batch.id=r.batch_id
                WHERE batch.source_name=%s AND batch.status='completed'""", (path.name,))
            business_by_row = {(sheet, row): bid for sheet, row, bid in q.fetchall()}
            q.execute('SELECT id,slug FROM ref_categories')
            categories = {slug: cid for cid, slug in q.fetchall()}
            q.execute("""SELECT b.id,b.name,p.id,p.full_name,d.code,b.address_ciphertext
                FROM umk_businesses b JOIN umk_business_owners o ON o.business_id=b.id
                    AND o.is_primary=1
                JOIN umk_people p ON p.id=o.person_id
                LEFT JOIN ref_districts d ON d.id=b.district_id
                WHERE b.activity_fingerprint IS NOT NULL
                  AND b.canonical_business_id IS NULL""")
            candidate_people = defaultdict(lambda: defaultdict(set))
            aes = AESGCM(secret_file('pii_aes256_key', 32))
            for bid, business, pid, person, district, address in q.fetchall():
                candidate_people[norm(person)][pid].add((district, norm(decode(aes, address))))
            q.execute('SELECT id FROM iam_users WHERE email=%s LIMIT 1',
                      ('superadmin@umkm.local',))
            actor = q.fetchone()
            if not actor:
                q.execute("""SELECT u.id FROM iam_users u
                    JOIN iam_user_roles ur ON ur.user_id=u.id
                    JOIN iam_roles r ON r.id=ur.role_id
                    WHERE r.code='superadmin' LIMIT 1""")
                actor = q.fetchone()
            if not actor:
                raise RuntimeError('Akun superadmin tidak ada')
            actor_id = actor[0]
        if len(data) != len(business_by_row):
            raise RuntimeError('Jumlah profil unggulan dan jejak impor berbeda')
        if not args.apply:
            print('Profil unggulan:', len(data), 'toko yang akan disiapkan')
            print('Toko sintetis aktif akan dinonaktifkan setelah toko unggulan siap')
            return

        created = linked = categorized = 0
        with db.cursor() as q:
            for row in data:
                bid = business_by_row[(row['sheet'], row['row_no'])]
                if row['owner']:
                    candidates = candidate_people.get(norm(row['owner']), {})
                    exact = [pid for pid, areas in candidates.items()
                             if norm(row['address']) and any(a == norm(row['address'])
                                                             for _, a in areas)]
                    district = [pid for pid, areas in candidates.items()
                                if row['district_code'] and
                                any(d == row['district_code'] for d, _ in areas)]
                    pid = exact[0] if len(exact) == 1 else (
                        district[0] if len(district) == 1 and len(candidates) == 1 else None)
                    if pid:
                        q.execute("""DELETE FROM umk_business_owners
                            WHERE business_id=%s AND is_primary=1 AND person_id<>%s""", (bid, pid))
                        q.execute("""INSERT INTO umk_business_owners
                            (business_id,person_id,relationship,is_primary)
                            VALUES (%s,%s,'owner',1)
                            ON DUPLICATE KEY UPDATE is_primary=1""", (bid, pid))
                        linked += 1
                q.execute("""SELECT id FROM umk_business_licenses
                    WHERE business_id=%s AND license_type='NIB' LIMIT 1""", (bid,))
                if not q.fetchone():
                    q.execute("""INSERT INTO umk_business_licenses
                        (business_id,license_type,license_number,status,evidence_basis)
                        VALUES (%s,'NIB',%s,'assumed','profile_workbook')""",
                        (bid, 'DUMMY-NIB-' + str(bid)))
                q.execute('SELECT id,status FROM tko_stores WHERE business_id=%s LIMIT 1', (bid,))
                store = q.fetchone()
                if store:
                    sid = store[0]
                else:
                    q.execute("""INSERT INTO tko_stores
                        (business_id,slug,display_name,description,whatsapp_e164,
                         contact_label,status)
                        VALUES (%s,%s,%s,%s,%s,'Hubungi toko','draft')""",
                        (bid, slug(row['name'], bid), row['name'],
                         row['commodity'] or None, '+6285232412324'))
                    sid = q.lastrowid
                    created += 1
                q.execute('SELECT id FROM tko_verifications WHERE store_id=%s LIMIT 1', (sid,))
                if not q.fetchone():
                    q.execute("""INSERT INTO tko_verifications
                        (store_id,decision,findings,decided_at,evidence_basis)
                        VALUES (%s,'approved',%s,NOW(),'profile_workbook')""",
                        (sid, 'Persetujuan toko diasumsikan dari berkas profil unggulan sesuai arahan pemilik proyek.'))
                q.execute("""UPDATE tko_stores SET status='active',published_at=COALESCE(published_at,NOW())
                    WHERE id=%s AND status<>'active'""", (sid,))
                key = CATEGORY.get(norm(row['category']))
                if key and key in categories:
                    q.execute("""INSERT INTO tko_store_categories
                        (store_id,category_id,status) VALUES (%s,%s,'approved')
                        ON DUPLICATE KEY UPDATE status='approved'""",
                        (sid, categories[key]))
                    categorized += 1
                q.execute("""SELECT user_id FROM iam_imported_business_accounts
                    WHERE business_id=%s LIMIT 1""", (bid,))
                account = q.fetchone()
                if account:
                    q.execute("""INSERT INTO tko_store_managers
                        (store_id,user_id,access_role,status,assigned_by)
                        VALUES (%s,%s,'owner','active',%s)
                        ON DUPLICATE KEY UPDATE status='active'""",
                        (sid, account[0], actor_id))
            q.execute("""UPDATE tko_stores s JOIN umk_businesses b ON b.id=s.business_id
                SET s.status='inactive' WHERE b.is_demo=1 AND s.status='active'""")
            inactive = q.rowcount
        db.commit()
        print('Toko unggulan baru', created, 'pelaku terhubung', linked,
              'kategori terpasang', categorized, 'toko sintetis dinonaktifkan', inactive)
    except Exception:
        db.rollback()
        raise
    finally:
        db.close()


if __name__ == '__main__':
    main()
