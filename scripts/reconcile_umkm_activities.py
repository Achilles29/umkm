#!/usr/bin/env python3
"""Rekonsiliasi baris kecamatan: identitas pelaku dan kegiatan usaha kanonis."""
import argparse
import json
from collections import OrderedDict, defaultdict
import pymysql
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from import_umkm_excel import ROOT, SOURCES, MASTER, encrypt, fingerprint, norm, parse_workbook, secret_file


def actor_key(row):
    if row['nik']:
        return ('nik', row['nik'])
    return ('nama-alamat', norm(row['owner']), norm(row['address']), row['district_code'])


def activity_key(row):
    return (actor_key(row), norm(row['business_name']), norm(row['address']),
            row['district_code'], row['village_code'] or '')


def activity_groups(rows):
    """Keep alternate owner names as separate source activities, even with one NIK.

    The first spelling retains its existing key so a reconciliation rerun does not
    replace previously published business IDs or break their store/account links.
    """
    first_owner = {}
    groups = OrderedDict()
    for row in rows:
        base = activity_key(row)
        owner = norm(row['owner'])
        first_owner.setdefault(base, owner)
        key = base if first_owner[base] == owner else base + ('owner-name', owner)
        groups.setdefault(key, []).append(row)
    return groups


def activity_fingerprint(secret, key):
    return fingerprint(secret, 'activity-v2',
                       json.dumps(key, ensure_ascii=False, separators=(',', ':')))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    districts = {norm(d['name']): d for d in MASTER['districts']}
    villages = {norm(d['name']): {norm(v['name']): v for v in d['villages']}
                for d in MASTER['districts']}
    paths = sorted(p for p in SOURCES.glob('*.xlsx') if not p.name.startswith('DAFTAR'))
    if not args.apply:
        for path in paths:
            rows = list(parse_workbook(path, districts, villages))
            print(path.name, 'baris', len(rows), 'kegiatan unik',
                  len(activity_groups(rows)))
        return

    secret = secret_file('nik_hmac_key', 32)
    aes = AESGCM(secret_file('pii_aes256_key', 32))
    password = (ROOT / '.secrets/db_password').read_text().strip()
    conn = pymysql.connect(unix_socket='/tmp/mysql.sock', user='umkm_app',
                           password=password, database='db_umkm', charset='utf8mb4',
                           autocommit=False)
    try:
        with conn.cursor() as cur:
            cur.execute('SELECT code,id FROM ref_districts')
            district_ids = dict(cur.fetchall())
            cur.execute('SELECT code,id FROM ref_villages')
            village_ids = dict(cur.fetchall())
            cur.execute('SELECT nik_fingerprint,id FROM umk_people WHERE nik_fingerprint IS NOT NULL')
            nik_people = {bytes(fp): pid for fp, pid in cur.fetchall()}
            cur.execute('SELECT activity_fingerprint,id FROM umk_businesses WHERE activity_fingerprint IS NOT NULL')
            by_fp = {bytes(fp): bid for fp, bid in cur.fetchall()}

        for path in paths:
            rows = list(parse_workbook(path, districts, villages))
            groups = activity_groups(rows)
            with conn.cursor() as cur:
                cur.execute("""SELECT r.id,r.sheet_name,r.source_row,r.business_id
                    FROM umk_import_rows r JOIN umk_import_batches batch ON batch.id=r.batch_id
                    WHERE batch.source_name=%s AND batch.status='completed'""", (path.name,))
                source_rows = {(sheet, row): (rid, bid)
                               for rid, sheet, row, bid in cur.fetchall()}
                if len(source_rows) != len(rows):
                    raise RuntimeError(path.name + ': jumlah jejak impor berbeda dari Excel')
                old_ids = {bid for _, bid in source_rows.values() if bid}
                cur.execute("""SELECT o.business_id,o.person_id FROM umk_business_owners o
                    JOIN umk_businesses b ON b.id=o.business_id
                    WHERE b.data_source=%s AND o.is_primary=1""", (path.name,))
                existing_owners = dict(cur.fetchall())
                cur.execute('SELECT id,activity_fingerprint FROM umk_businesses WHERE data_source=%s',
                            (path.name,))
                existing_fp = {bid: bytes(fp) if fp else None for bid, fp in cur.fetchall()}
            used = set()
            people_by_actor = {}
            first_target = {}
            counts = defaultdict(int)
            try:
                with conn.cursor() as cur:
                    for key, members in groups.items():
                        fp = activity_fingerprint(secret, key)
                        linked = sorted({source_rows[(r['sheet'], r['row_no'])][1] for r in members})
                        bid = by_fp.get(fp)
                        if not bid:
                            choices = [old for old in linked if old not in used
                                       and existing_fp.get(old) is None]
                            bid = choices[0] if choices else None
                        actor = key[0]
                        if actor in people_by_actor:
                            pid = people_by_actor[actor]
                        elif actor[0] == 'nik':
                            nik_fp = fingerprint(secret, 'nik', actor[1])
                            pid = nik_people.get(nik_fp)
                            if not pid:
                                cur.execute("""INSERT INTO umk_people
                                    (full_name,nik_ciphertext,nik_fingerprint) VALUES (%s,%s,%s)""",
                                    (members[0]['owner'], encrypt(aes, members[0]['nik']), nik_fp))
                                pid = cur.lastrowid
                                nik_people[nik_fp] = pid
                        else:
                            pid = next((existing_owners[old] for old in linked
                                        if old in existing_owners), None)
                            if not pid:
                                cur.execute('INSERT INTO umk_people (full_name) VALUES (%s)',
                                            (members[0]['owner'],))
                                pid = cur.lastrowid
                        people_by_actor[actor] = pid
                        first = members[0]
                        if bid:
                            cur.execute("""UPDATE umk_businesses SET activity_fingerprint=%s,
                                canonical_business_id=NULL,district_id=%s,village_id=%s,
                                address_ciphertext=%s WHERE id=%s""",
                                (fp, district_ids[first['district_code']],
                                 village_ids.get(first['village_code']),
                                 encrypt(aes, first['address']), bid))
                            counts['reused'] += 1
                        else:
                            cur.execute("""INSERT INTO umk_businesses
                                (name,district_id,village_id,address_ciphertext,
                                 registry_status,data_source,import_fingerprint,activity_fingerprint)
                                VALUES (%s,%s,%s,%s,'imported_unverified',%s,%s,%s)""",
                                (first['business_name'], district_ids[first['district_code']],
                                 village_ids.get(first['village_code']),
                                 encrypt(aes, first['address']), path.name,
                                 fingerprint(secret, 'business-v2', fp.hex()), fp))
                            bid = cur.lastrowid
                            counts['new'] += 1
                        by_fp[fp] = bid
                        used.add(bid)
                        cur.execute("""DELETE FROM umk_business_owners
                            WHERE business_id=%s AND is_primary=1 AND person_id<>%s""", (bid, pid))
                        cur.execute("""INSERT INTO umk_business_owners
                            (business_id,person_id,relationship,is_primary)
                            VALUES (%s,%s,'owner',1)
                            ON DUPLICATE KEY UPDATE is_primary=1""", (bid, pid))
                        for i, row in enumerate(members):
                            rid, old_bid = source_rows[(row['sheet'], row['row_no'])]
                            first_target.setdefault(old_bid, bid)
                            status = row['status'] if i == 0 else 'duplicate'
                            error = row['error_code'] if i == 0 else 'exact_activity_duplicate'
                            cur.execute("""UPDATE umk_import_rows
                                SET business_id=%s,status=%s,error_code=%s WHERE id=%s""",
                                (bid, status, error, rid))
                            counts['rows'] += 1
                    for old_bid in old_ids - used:
                        target = first_target.get(old_bid)
                        if not target or target == old_bid:
                            raise RuntimeError(path.name + ': profil lama tanpa kegiatan kanonis')
                        cur.execute('DELETE FROM umk_business_owners WHERE business_id=%s',
                                    (old_bid,))
                        cur.execute("""UPDATE umk_businesses SET canonical_business_id=%s
                            WHERE id=%s AND activity_fingerprint IS NULL""", (target, old_bid))
                        counts['merged'] += 1
                conn.commit()
                print(path.name, 'baris', counts['rows'], 'kegiatan', len(groups),
                      'baru', counts['new'], 'digabung', counts['merged'], flush=True)
            except Exception:
                conn.rollback()
                raise
    finally:
        conn.close()


if __name__ == '__main__':
    main()
