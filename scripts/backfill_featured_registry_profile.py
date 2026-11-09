#!/usr/bin/env python3
"""Copy structured profile fields from the already linked featured workbook rows."""
import argparse
import re
import pymysql
from import_umkm_unggulan import path, records, contact_e164, online_fields
from import_umkm_excel import ROOT


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--apply',action='store_true')
    args=parser.parse_args()
    rows=list(records())
    print('profiles',len(rows),'contacts',sum(bool(r['contact']) for r in rows),
          'commodities',sum(bool(r['commodity']) for r in rows))
    if not args.apply:return
    conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='umkm_app',
                         password=(ROOT/'.secrets/db_password').read_text().strip(),
                         database='db_umkm',charset='utf8mb4',autocommit=False)
    try:
        with conn.cursor() as q:
            q.execute("""SELECT r.sheet_name,r.source_row,r.business_id
                FROM umk_import_rows r JOIN umk_import_batches batch ON batch.id=r.batch_id
                WHERE batch.source_name=%s AND batch.status='completed'""",(path.name,))
            mapping={(sheet,row):bid for sheet,row,bid in q.fetchall()}
            if len(mapping)!=len(rows):
                raise RuntimeError('Profile row count does not match linked workbook')
            changed=0
            for item in rows:
                bid=mapping.get((item['sheet'],item['row_no']))
                if not bid:raise RuntimeError('Unlinked profile row')
                website,social=online_fields(item['online'])
                form=('pt' if re.match(r'^PT\b',item['name'],re.I) else
                      'cv' if re.match(r'^CV\b',item['name'],re.I) else None)
                q.execute("""UPDATE umk_businesses SET
                    legal_form=COALESCE(legal_form,%s),
                    sector_label=COALESCE(sector_label,%s),
                    contact_phone_e164=COALESCE(contact_phone_e164,%s),
                    contact_source_text=COALESCE(contact_source_text,%s),
                    website_url=COALESCE(website_url,%s),
                    social_media_url=COALESCE(social_media_url,%s),
                    export_destinations=COALESCE(export_destinations,%s),
                    export_potential=COALESCE(export_potential,%s)
                    WHERE id=%s""",(form,item['category'][:190] or None,contact_e164(item['contact']),
                    item['contact'][:190] or None,website,social,
                    item['destinations'] or None,item['potential'] or None,bid))
                if item['commodity']:
                    q.execute('SELECT id FROM umk_business_commodities WHERE business_id=%s LIMIT 1',(bid,))
                    if not q.fetchone():
                        q.execute('INSERT INTO umk_business_commodities (business_id,commodity_name,is_primary) VALUES (%s,%s,1)',
                                  (bid,item['commodity']))
                changed+=1
        conn.commit()
        print('updated',changed)
    except Exception:
        conn.rollback()
        raise
    finally:conn.close()


if __name__=='__main__':main()
