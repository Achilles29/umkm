"""Isi inbox dari pendaftaran pengunjung dan kontak WhatsApp yang sudah tercatat.

Aman dijalankan ulang; event_key mencegah duplikasi.
"""
import sqlite3
import pymysql

root=sqlite3.connect('/www/server/panel/data/default.db').execute('SELECT mysql_root FROM config LIMIT 1').fetchone()[0]
db=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',password=root,database='db_umkm',charset='utf8mb4',autocommit=False)
with db.cursor() as q:
 q.execute("SELECT DISTINCT u.id FROM iam_users u JOIN iam_user_roles ur ON ur.user_id=u.id JOIN iam_roles r ON r.id=ur.role_id WHERE u.status='active' AND r.code IN ('superadmin','admin_dinas')")
 admins=[row[0] for row in q.fetchall()]
 q.execute('SELECT id,display_name,phone_e164,created_at FROM crm_customers ORDER BY id')
 customers=q.fetchall()
 q.execute("SELECT i.id,i.store_id,i.reference_no,i.opened_at,s.display_name,b.district_id FROM mkt_contact_intents i JOIN tko_stores s ON s.id=i.store_id JOIN umk_businesses b ON b.id=s.business_id WHERE i.status='opened' AND i.opened_at IS NOT NULL ORDER BY i.id")
 contacts=q.fetchall()
 def event(event_type,key,title,body,path,occurred,recipients):
  recipients=sorted(set(recipients))
  if not recipients:return
  q.execute('INSERT INTO ntf_events (event_type,dedupe_key,title,body,target_path,occurred_at) VALUES (%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',(event_type,key,title,body,path,occurred))
  event_id=q.lastrowid
  for recipient in recipients:q.execute('INSERT IGNORE INTO ntf_inbox_items (event_id,user_id) VALUES (%s,%s)',(event_id,recipient))
 for cid,name,phone,created in customers:
  masked=phone[:5]+'***'+phone[-3:]
  event('customer_registered',f'customer:{cid}','Pendaftaran pengunjung baru',f'{name or "Pengunjung"} mendaftar ke etalase ({masked}).',f'/pengelola/anggota/{cid}',created,admins)
 for iid,store_id,reference,opened,store_name,district_id in contacts:
  q.execute("SELECT DISTINCT u.id FROM tko_store_managers m JOIN iam_users u ON u.id=m.user_id AND u.status='active' WHERE m.store_id=%s AND m.status='active'",(store_id,))
  managers=[row[0] for row in q.fetchall()]
  q.execute("SELECT DISTINCT u.id FROM iam_district_roles dr JOIN iam_roles r ON r.id=dr.role_id AND r.code='admin_kecamatan' JOIN iam_users u ON u.id=dr.user_id AND u.status='active' WHERE dr.district_id=%s",(district_id,))
  district_admins=[row[0] for row in q.fetchall()]
  event('contact_opened',f'contact:{iid}','Minat WhatsApp baru',f'Pengunjung membuka kontak untuk {store_name} ({reference}).',f'/pengelola/minat/{iid}',opened,admins+managers+district_admins)
db.commit()
print(f'Backfilled {len(customers)} registrations and {len(contacts)} opened contacts')
