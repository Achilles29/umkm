USE db_umkm;

-- Akun impor sebelumnya memakai nama kegiatan sebagai nama pengguna.
-- Tampilkan nama pelaku yang benar tanpa mengubah username atau kata sandi.
UPDATE iam_users u
JOIN iam_imported_business_accounts a ON a.user_id=u.id
JOIN umk_businesses source ON source.id=a.business_id
JOIN umk_business_owners o
  ON o.business_id=COALESCE(source.canonical_business_id,source.id) AND o.is_primary=1
JOIN umk_people p ON p.id=o.person_id
SET u.full_name=p.full_name
WHERE u.full_name<>p.full_name;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (28,'account_display_person');
