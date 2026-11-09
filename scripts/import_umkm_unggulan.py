#!/usr/bin/env python3
"""Impor profil unggulan sebagai registri internal; lokasi kosong jika belum pasti."""
import hashlib
import hmac
import json
import re
from collections import Counter,defaultdict
from pathlib import Path

import openpyxl
import pymysql
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from import_umkm_excel import ROOT, clean, norm, encrypt, fingerprint, secret_file

path=ROOT/'docs/umkm/DAFTAR PROFILE UMKM UNGGULAN.xlsx'
master=json.loads((ROOT/'database/seeds/wilayah_rembang_2025.json').read_text())
district_by_name={norm(d['name']):d for d in master['districts']}
villages_by_name=defaultdict(list)
for d in master['districts']:
 for v in d['villages']:
  villages_by_name[norm(v['name'])].append((d,v))


def infer_location(address):
    text=norm(address)
    district_hits=[d for name,d in district_by_name.items()
                   if re.search(r'\b(?:KEC|KECAMATAN)\s+'+re.escape(name)+r'\b',text)]
    district=district_hits[0] if len(district_hits)==1 else None
    village=None
    for name,matches in villages_by_name.items():
        if len(matches)!=1:continue
        if re.search(r'\b(?:DESA|DS|KEL|KELURAHAN)\s+'+re.escape(name)+r'\b',text):
            v_d,v=matches[0]
            if district is None or district['code']==v_d['code']:
                district=v_d;village=v
                break
    return (district['code'] if district else None,village['code'] if village else None)


def contact_e164(raw):
    digits=re.sub(r'\D','',raw or '')
    if digits.startswith('0'): digits='62'+digits[1:]
    if digits.startswith('62') and 10<=len(digits)<=15:
        return '+'+digits
    return None


def online_fields(raw):
    if not raw: return (None,None)
    if re.search(r'(^https?://|\.[a-z]{2,}(?:/|$))',raw,re.I):
        return (raw[:500],None)
    return (None,raw[:500])


def records():
    book=openpyxl.load_workbook(path,read_only=True,data_only=True)
    for sheet in book.worksheets:
        is_main=sheet.title.strip().lower()=='format'
        if sheet.max_row < (6 if is_main else 4):continue
        for row_no,row in enumerate(sheet.iter_rows(values_only=True),1):
            if row_no < (6 if is_main else 4):continue
            if is_main:
                owner=clean(row[1] if len(row)>1 else None)
                name=clean(row[2] if len(row)>2 else None) or 'Usaha belum bernama'
                category=clean(row[3] if len(row)>3 else None)
                contact=clean(row[4] if len(row)>4 else None)
                address=clean(row[5] if len(row)>5 else None)
                commodity=clean(row[6] if len(row)>6 else None)
                destinations=clean(row[7] if len(row)>7 else None)
                online=clean(row[8] if len(row)>8 else None)
                potential=clean(row[9] if len(row)>9 else None)
            else:
                owner=''
                name=clean(row[1] if len(row)>1 else None)
                category=sheet.title.strip()
                contact=clean(row[2] if len(row)>2 else None)
                address=clean(row[3] if len(row)>3 else None)
                commodity=clean(row[4] if len(row)>4 else None)
                destinations=clean(row[5] if len(row)>5 else None)
                online=clean(row[6] if len(row)>6 else None)
                potential=''
            if not name or norm(name) in {'NAMA PERUSAHAAN','BRAND','JUMLAH','TOTAL'}:
                continue
            if len(name)>190:name=name[:190]
            district_code,village_code=infer_location(address)
            yield {'sheet':sheet.title[:120],'row_no':row_no,'owner':owner[:160],
                   'name':name,'address':address,'commodity':commodity,'category':category,
                   'contact':contact,'destinations':destinations,'online':online,'potential':potential,
                   'district_code':district_code,'village_code':village_code}
    book.close()


def main():
    key=secret_file('pii_aes256_key',32)
    hmac_key=secret_file('nik_hmac_key',32)
    aes=AESGCM(key)
    password=(ROOT/'.secrets/db_password').read_text().strip()
    conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='umkm_app',password=password,
                         database='db_umkm',charset='utf8mb4',autocommit=False)
    source_sha=hashlib.sha256(path.read_bytes()).digest()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM umk_import_batches WHERE source_name=%s AND source_sha256=%s AND status='completed' LIMIT 1",(path.name,source_sha))
            if cur.fetchone():
                print('Featured workbook already imported; skipped')
                return
            cur.execute('SELECT code,id FROM ref_districts')
            district_ids=dict(cur.fetchall())
            cur.execute('SELECT code,id FROM ref_villages')
            village_ids=dict(cur.fetchall())
            cur.execute('SELECT import_fingerprint,id FROM umk_businesses WHERE import_fingerprint IS NOT NULL')
            businesses={bytes(fp):bid for fp,bid in cur.fetchall()}
            cur.execute("INSERT INTO umk_import_batches (source_name,source_sha256,status) VALUES (%s,%s,'processing')",(path.name,source_sha))
            batch_id=cur.lastrowid
            counts=Counter()
            for item in records():
                counts['rows']+=1
                business_fp=fingerprint(hmac_key,'featured',norm(item['name']),norm(item['address']))
                row_fp=fingerprint(hmac_key,'featured_row',item['sheet'],str(item['row_no']),item['name'],item['address'])
                business_id=businesses.get(business_fp)
                status='needs_review' if not item['district_code'] else 'imported'
                error='district_unknown' if not item['district_code'] else None
                if business_id:
                    status='duplicate';error='same_business_fingerprint';counts['duplicate']+=1
                else:
                    website,social=online_fields(item['online'])
                    legal_form=('pt' if re.match(r'^PT\b',item['name'],re.I) else
                                'cv' if re.match(r'^CV\b',item['name'],re.I) else None)
                    cur.execute("INSERT INTO umk_businesses (name,description,district_id,village_id,address_ciphertext,registry_status,data_source,import_fingerprint,legal_form,sector_label,contact_phone_e164,contact_source_text,website_url,social_media_url,export_destinations,export_potential) VALUES (%s,%s,%s,%s,%s,'imported_unverified',%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
                                (item['name'],item['commodity'] or None,
                                 district_ids.get(item['district_code']),village_ids.get(item['village_code']),
                                 encrypt(aes,item['address']),path.name,business_fp,legal_form,
                                 item['category'][:190] or None,
                                 contact_e164(item['contact']),item['contact'][:190] or None,
                                 website,social,item['destinations'] or None,item['potential'] or None))
                    business_id=cur.lastrowid
                    businesses[business_fp]=business_id
                    counts['new_business']+=1
                    if item['commodity']:
                        cur.execute("INSERT INTO umk_business_commodities (business_id,commodity_name,is_primary) VALUES (%s,%s,1)",
                                    (business_id,item['commodity']))
                    if item['owner']:
                        cur.execute("INSERT INTO umk_people (full_name) VALUES (%s)",(item['owner'],))
                        person_id=cur.lastrowid
                        cur.execute("INSERT INTO umk_business_owners (business_id,person_id,relationship,is_primary) VALUES (%s,%s,'owner',1)",
                                    (business_id,person_id))
                if status=='needs_review':counts['needs_review']+=1
                cur.execute("INSERT INTO umk_import_rows (batch_id,sheet_name,source_row,row_sha256,business_id,status,error_code) VALUES (%s,%s,%s,%s,%s,%s,%s)",
                            (batch_id,item['sheet'],item['row_no'],row_fp,business_id,status,error))
            cur.execute("UPDATE umk_import_batches SET status='completed',total_rows=%s,accepted_rows=%s,rejected_rows=0,finished_at=NOW() WHERE id=%s",
                        (counts['rows'],counts['rows'],batch_id))
        conn.commit()
        print('Featured workbook:',dict(counts))
    except:
        conn.rollback()
        raise
    finally:
        conn.close()

if __name__=='__main__':
    main()
