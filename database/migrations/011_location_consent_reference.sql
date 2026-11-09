USE db_umkm;

ALTER TABLE gis_business_locations
  ADD COLUMN IF NOT EXISTS consent_reference VARCHAR(190) NULL AFTER consent_at;

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (11, 'location_consent_reference');
