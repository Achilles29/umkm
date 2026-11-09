#!/usr/bin/env python3
"""Antrean kecocokan nama pemilik unggulan dengan pelaku data kecamatan."""
import argparse
from collections import defaultdict
import pymysql
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from import_umkm_unggulan import records,path
from import_umkm_excel import ROOT,norm,secret_file


def decode(aes,value):
    if not value:return ''
    try:return aes.decrypt(value[1:13],value[13:],None).decode()
    except Exception:return ''


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--apply',action='store_true')
    args=parser.parse_args()
    db=pymysql.connect(unix_socket='/tmp/mysql.sock',user='umkm_app',
        password=(ROOT/'.secrets/db_password').read_text().strip(),
        database='db_umkm',charset='utf8mb4',autocommit=False)
    try:
        aes=AESGCM(secret_file('pii_aes256_key',32))
        with db.cursor() as q:
            q.execute("""SELECT p.id,p.full_name,d.code,b.address_ciphertext
                FROM umk_businesses b
                JOIN umk_business_owners o ON o.business_id=b.id AND o.is_primary=1
                JOIN umk_people p ON p.id=o.person_id
                LEFT JOIN ref_districts d ON d.id=b.district_id
                WHERE b.activity_fingerprint IS NOT NULL AND b.canonical_business_id IS NULL""")
            by_name=defaultdict(lambda:defaultdict(set))
            for pid,name,district,address in q.fetchall():
                by_name[norm(name)][pid].add((district,norm(decode(aes,address))))
            q.execute("""SELECT r.sheet_name,r.source_row,r.business_id,o.person_id
                FROM umk_import_rows r JOIN umk_import_batches batch ON batch.id=r.batch_id
                LEFT JOIN umk_business_owners o ON o.business_id=r.business_id AND o.is_primary=1
                WHERE batch.source_name=%s""",(path.name,))
            featured={(sheet,row):(bid,pid) for sheet,row,bid,pid in q.fetchall()}
        count=0;businesses=0
        with db.cursor() as q:
            for item in records():
                if not item['owner']:continue
                bid,current_pid=featured[(item['sheet'],item['row_no'])]
                candidates=by_name.get(norm(item['owner']),{})
                if current_pid in candidates:continue
                if candidates:businesses+=1
                for pid,places in candidates.items():
                    address=norm(item['address'])
                    if address and any(other==address for _,other in places):
                        basis='name_address'
                    elif item['district_code'] and any(d==item['district_code'] for d,_ in places):
                        basis='name_district'
                    else:
                        basis='name_only'
                    if args.apply:
                        q.execute("""INSERT INTO umk_person_match_candidates
                            (business_id,person_id,match_basis)
                            VALUES (%s,%s,%s)
                            ON DUPLICATE KEY UPDATE match_basis=VALUES(match_basis)""",
                            (bid,pid,basis))
                    count+=1
        if args.apply:db.commit()
        print('Profil yang perlu ditinjau:',businesses,'calon tautan:',count)
    except Exception:
        db.rollback();raise
    finally:db.close()


if __name__=='__main__':
    main()
