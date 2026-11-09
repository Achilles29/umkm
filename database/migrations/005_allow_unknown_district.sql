USE db_umkm;

-- Workbook unggulan tidak selalu menyebut kecamatan; lokasi tidak boleh ditebak.
ALTER TABLE umk_businesses
  MODIFY COLUMN district_id BIGINT UNSIGNED NULL;

INSERT INTO ops_schema_migrations (version_no, name) VALUES
  (5, 'allow_unknown_district');
