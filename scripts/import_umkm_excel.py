#!/usr/bin/env python3
"""Impor Excel kecamatan ke registri internal; tidak mengaktifkan toko publik."""
import argparse
import hashlib
import hmac
import json
import os
import re
import unicodedata
from collections import Counter
from pathlib import Path

import openpyxl
import pymysql
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

ROOT = Path(__file__).resolve().parents[1]
SOURCES = ROOT / 'docs/umkm'
MASTER = json.loads((ROOT / 'database/seeds/wilayah_rembang_2025.json').read_text())


def clean(value):
    if value is None:
        return ''
    if isinstance(value, float) and value.is_integer():
        value = int(value)
    return ' '.join(str(value).strip().split())


def norm(value):
    text = unicodedata.normalize('NFKD', clean(value)).encode('ascii', 'ignore').decode().upper()
    text = re.sub(r'[^A-Z0-9]+', ' ', text)
    text = re.sub(r'^(DESA|DS|KELURAHAN|KEL|KECAMATAN|KEC)\s+', '', text).strip()
    return ' '.join(text.split())


def nik_digits(value):
    # Excel numeric cells do not preserve every digit of a 16-digit NIK.
    if not isinstance(value, str):
        return None
    raw = clean(value).replace(' ', '').replace('-', '').replace('.', '')
    if raw.endswith('0') and len(raw) == 17 and isinstance(value, str) and '.0' in value:
        raw = raw[:-1]
    return raw if re.fullmatch(r'\d{16}', raw) else None


def secret_file(name, length):
    path = ROOT / '.secrets' / name
    if not path.exists():
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o640)
        with os.fdopen(fd, 'wb') as out:
            out.write(os.urandom(length))
        import grp
        os.chown(path, 0, grp.getgrnam('www').gr_gid)
    secret = path.read_bytes()
    if len(secret) != length:
        raise ValueError(f'Invalid key length for {name}')
    return secret


def encrypt(aes, value):
    if not value:
        return None
    nonce = os.urandom(12)
    # v1: 1-byte version, 12-byte nonce, ciphertext, 16-byte GCM tag.
    return b'\x01' + nonce + aes.encrypt(nonce, value.encode('utf-8'), None)


def fingerprint(key, *parts):
    return hmac.new(key, '\x1f'.join(parts).encode('utf-8'), hashlib.sha256).digest()


def district_from_file(path):
    name = path.stem.replace('KEC_', '').replace('Kec.', '').strip()
    return norm(name)


def parse_workbook(path, districts_by_name, villages_by_district):
    district_key = district_from_file(path)
    if district_key not in districts_by_name:
        raise ValueError(f'No official district match for {path.name}')
    district = districts_by_name[district_key]
    offset = 1 if district_key == 'SLUKE' else 0
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    for sheet in wb.worksheets:
        if 'REKAP' in sheet.title.upper() or sheet.max_row < 2:
            continue
        for row_no, row in enumerate(sheet.iter_rows(values_only=True), 1):
            if len(row) < 7 + offset:
                continue
            nik = nik_digits(row[1 + offset])
            owner = clean(row[2 + offset])
            if not owner or len(owner) < 2 or len(owner) > 160:
                continue
            if norm(owner) in {'NAMA', 'NAMA PEMILIK', 'NAMA OWNER', 'JUMLAH', 'TOTAL'}:
                continue
            if owner.isdigit() or norm(owner).startswith('DATA USAHA MIKRO'):
                continue
            village_raw = clean(row[3 + offset])
            district_raw = clean(row[4 + offset])
            address = clean(row[5 + offset])
            kind = clean(row[6 + offset]) or 'Usaha belum diklasifikasi'
            if len(kind) > 190:
                kind = kind[:190]
            village = villages_by_district[district_key].get(norm(village_raw))
            mismatch = bool(district_raw and norm(district_raw) not in (district_key, 'KAB REMBANG', 'REMBANG')
                            and norm(district_raw) in districts_by_name)
            status = 'needs_review' if village is None or mismatch or nik is None else 'imported'
            yield {
                'source_file': path.name, 'sheet': sheet.title[:120], 'row_no': row_no,
                'district_code': district['code'], 'village_code': village['code'] if village else None,
                'owner': owner, 'nik': nik, 'address': address, 'business_name': kind,
                'status': status, 'error_code': 'district_mismatch' if mismatch else ('village_unmatched' if village is None else ('nik_invalid' if nik is None else None)),
            }
    wb.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--dry-run', action='store_true')
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--only', help='Nama workbook tunggal untuk uji impor bertahap')
    args = parser.parse_args()
    districts_by_name = {norm(d['name']): d for d in MASTER['districts']}
    villages_by_district = {norm(d['name']): {norm(v['name']):v for v in d['villages']} for d in MASTER['districts']}
    paths = sorted(p for p in SOURCES.glob('*.xlsx') if not p.name.startswith('DAFTAR'))
    if args.only:
        paths = [p for p in paths if p.name == args.only]
        if not paths:
            raise SystemExit('Workbook tidak ditemukan')
    counts = Counter()
    per_file = []
    for path in paths:
        c=Counter()
        for record in parse_workbook(path,districts_by_name,villages_by_district):
            c['rows'] += 1
            c[record['status']] += 1
            if record['nik']: c['valid_nik'] += 1
            if record['village_code']: c['matched_village'] += 1
        counts.update(c)
        per_file.append((path.name,c['rows'],c['imported'],c['needs_review']))
    for item in per_file:
        print('%s rows=%s ready=%s review=%s' % item)
    print('TOTAL',dict(counts))
    if args.apply:
        apply_import(paths, districts_by_name, villages_by_district)
    elif not args.dry_run:
        raise SystemExit('Choose --dry-run or --apply')


def apply_import(paths, districts_by_name, villages_by_district):
    key = secret_file('pii_aes256_key', 32)
    hmac_key = secret_file('nik_hmac_key', 32)
    aes = AESGCM(key)
    password = (ROOT / '.secrets/db_password').read_text().strip()
    conn = pymysql.connect(unix_socket='/tmp/mysql.sock', user='umkm_app', password=password,
                           database='db_umkm', charset='utf8mb4', autocommit=False)
    try:
        with conn.cursor() as cur:
            cur.execute('SELECT code,id FROM ref_districts')
            district_ids = dict(cur.fetchall())
            cur.execute('SELECT code,id FROM ref_villages')
            village_ids = dict(cur.fetchall())
            cur.execute('SELECT nik_fingerprint,id,full_name FROM umk_people WHERE nik_fingerprint IS NOT NULL')
            people = {bytes(fp):(pid,norm(name)) for fp,pid,name in cur.fetchall()}
            cur.execute('SELECT import_fingerprint,id FROM umk_businesses WHERE import_fingerprint IS NOT NULL')
            businesses = {bytes(fp):bid for fp,bid in cur.fetchall()}
        for path in paths:
            source_sha = hashlib.sha256(path.read_bytes()).digest()
            with conn.cursor() as cur:
                cur.execute("SELECT id FROM umk_import_batches WHERE source_name=%s AND source_sha256=%s AND status='completed' LIMIT 1",
                            (path.name,source_sha))
                if cur.fetchone():
                    print(path.name, 'already imported; skipped')
                    continue
            file_stats = Counter()
            try:
                with conn.cursor() as cur:
                    cur.execute("INSERT INTO umk_import_batches (source_name,source_sha256,status) VALUES (%s,%s,'processing')",
                                (path.name,source_sha))
                    batch_id = cur.lastrowid
                    for record in parse_workbook(path,districts_by_name,villages_by_district):
                        file_stats['rows'] += 1
                        nik = record['nik']
                        owner_key = nik if nik else norm(record['owner']) + '\x1e' + record['source_file'] + '\x1e' + record['sheet'] + '\x1e' + str(record['row_no'])
                        business_fp = fingerprint(hmac_key,'business',owner_key,norm(record['business_name']),
                                                  record['district_code'],record['village_code'] or '')
                        row_fp = fingerprint(hmac_key,'row',record['source_file'],record['sheet'],str(record['row_no']),
                                             record['owner'],nik or '',record['address'],record['business_name'])
                        business_id = businesses.get(business_fp)
                        status = record['status']
                        error_code = record['error_code']
                        if business_id:
                            status = 'duplicate'
                            error_code = 'same_business_fingerprint'
                            file_stats['duplicate'] += 1
                        else:
                            person_id = None
                            nik_fp = fingerprint(hmac_key,'nik',nik) if nik else None
                            if nik_fp and nik_fp in people:
                                person_id, known_name = people[nik_fp]
                                if known_name != norm(record['owner']):
                                    status = 'needs_review'
                                    error_code = 'owner_name_conflict'
                            if person_id is None:
                                cur.execute("INSERT INTO umk_people (full_name,nik_ciphertext,nik_fingerprint) VALUES (%s,%s,%s)",
                                            (record['owner'],encrypt(aes,nik),nik_fp))
                                person_id = cur.lastrowid
                                if nik_fp:
                                    people[nik_fp] = (person_id,norm(record['owner']))
                            cur.execute("INSERT INTO umk_businesses (name,district_id,village_id,address_ciphertext,registry_status,data_source,import_fingerprint) VALUES (%s,%s,%s,%s,'imported_unverified',%s,%s)",
                                        (record['business_name'],district_ids[record['district_code']],
                                         village_ids.get(record['village_code']),encrypt(aes,record['address']),
                                         record['source_file'],business_fp))
                            business_id = cur.lastrowid
                            businesses[business_fp] = business_id
                            cur.execute("INSERT INTO umk_business_owners (business_id,person_id,relationship,is_primary) VALUES (%s,%s,'owner',1)",
                                        (business_id,person_id))
                            file_stats['new_business'] += 1
                        if status == 'needs_review': file_stats['needs_review'] += 1
                        cur.execute("INSERT INTO umk_import_rows (batch_id,sheet_name,source_row,row_sha256,business_id,status,error_code) VALUES (%s,%s,%s,%s,%s,%s,%s)",
                                    (batch_id,record['sheet'],record['row_no'],row_fp,business_id,status,error_code))
                    cur.execute("UPDATE umk_import_batches SET status='completed',total_rows=%s,accepted_rows=%s,rejected_rows=0,finished_at=NOW() WHERE id=%s",
                                (file_stats['rows'],file_stats['rows'],batch_id))
                conn.commit()
                print(path.name, 'rows',file_stats['rows'],'new businesses',file_stats['new_business'],
                      'duplicates',file_stats['duplicate'],'review',file_stats['needs_review'],flush=True)
            except:
                conn.rollback()
                raise
    finally:
        conn.close()

if __name__ == '__main__':
    main()
