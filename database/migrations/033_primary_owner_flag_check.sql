USE db_umkm;

ALTER TABLE umk_business_owners
  ADD CONSTRAINT chk_umk_owner_primary_flag CHECK (is_primary IN (0,1));

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (33,'primary_owner_flag_check');
