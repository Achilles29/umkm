#!/usr/bin/env python3
"""Muat kategori toko dan template pesan yang disetujui sebagai rancangan awal."""
import json
from pathlib import Path
import pymysql

ROOT=Path(__file__).resolve().parents[1]
categories=json.loads((ROOT/'database/seeds/store_categories.json').read_text())
templates=json.loads((ROOT/'database/seeds/whatsapp_templates.json').read_text())
password=(ROOT/'.secrets/db_password').read_text().strip()
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='umkm_app',password=password,
                     database='db_umkm',charset='utf8mb4',autocommit=False)
try:
 with conn.cursor() as cur:
  for order,group in enumerate(categories,1):
   cur.execute("INSERT INTO ref_categories (parent_id,category_type,slug,name,sort_order) VALUES (NULL,'store',%s,%s,%s) ON DUPLICATE KEY UPDATE name=VALUES(name),sort_order=VALUES(sort_order)",
               (group['slug'],group['name'],order*10))
   cur.execute("SELECT id FROM ref_categories WHERE category_type='store' AND slug=%s",(group['slug'],))
   parent_id=cur.fetchone()[0]
   for child_order,(slug,name) in enumerate(group['children'],1):
    cur.execute("INSERT INTO ref_categories (parent_id,category_type,slug,name,sort_order) VALUES (%s,'store',%s,%s,%s) ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id),name=VALUES(name),sort_order=VALUES(sort_order)",
                (parent_id,slug,name,child_order*10))
  for template in templates:
   cur.execute("INSERT INTO mkt_whatsapp_templates (template_key,version_no,name,body_template,is_active) VALUES (%s,1,%s,%s,1) ON DUPLICATE KEY UPDATE name=VALUES(name),body_template=VALUES(body_template),is_active=1",
               (template['key'],template['name'],template['body']))
  cur.execute("SELECT COUNT(*) FROM ref_categories WHERE category_type='store'")
  category_count=cur.fetchone()[0]
  cur.execute("SELECT COUNT(*) FROM mkt_whatsapp_templates WHERE is_active=1")
  template_count=cur.fetchone()[0]
 conn.commit()
 print(f'Seeded {category_count} store categories and {template_count} WhatsApp templates')
except:
 conn.rollback()
 raise
finally:
 conn.close()
