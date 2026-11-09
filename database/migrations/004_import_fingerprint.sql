USE db_umkm;

-- Jejak stabil untuk menghindari pembuatan usaha ganda ketika Excel diimpor ulang.
ALTER TABLE umk_businesses
  ADD COLUMN import_fingerprint BINARY(32) NULL AFTER data_source,
  ADD UNIQUE KEY uq_umk_import_fingerprint (import_fingerprint);

INSERT INTO ops_schema_migrations (version_no, name) VALUES
  (4, 'import_fingerprint');
