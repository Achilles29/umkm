USE db_umkm;

-- Satu pelaku dapat mempunyai beberapa kegiatan usaha. Baris yang identik
-- menunjuk kegiatan kanonis; baris lama tetap disimpan untuk akun dan audit.
ALTER TABLE umk_businesses
  ADD COLUMN IF NOT EXISTS activity_fingerprint BINARY(32) NULL AFTER import_fingerprint,
  ADD COLUMN IF NOT EXISTS canonical_business_id BIGINT UNSIGNED NULL AFTER activity_fingerprint,
  ADD UNIQUE KEY IF NOT EXISTS uq_umk_activity_fingerprint (activity_fingerprint),
  ADD KEY IF NOT EXISTS idx_umk_business_canonical (canonical_business_id);

ALTER TABLE umk_businesses
  ADD CONSTRAINT fk_umk_business_canonical
  FOREIGN KEY (canonical_business_id) REFERENCES umk_businesses(id);

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (24,'activity_identity_and_store_source');
