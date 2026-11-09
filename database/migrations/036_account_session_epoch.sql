USE db_umkm;
ALTER TABLE iam_users ADD COLUMN auth_epoch INT UNSIGNED NOT NULL DEFAULT 0;
INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (36,'account_session_epoch');
