USE db_umkm;

ALTER TABLE umk_businesses
  ADD COLUMN IF NOT EXISTS is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER data_source;

CREATE INDEX IF NOT EXISTS idx_umk_business_demo ON umk_businesses (is_demo, district_id);

INSERT IGNORE INTO ops_schema_migrations (version_no, name) VALUES
  (7, 'demo_business_flag');
