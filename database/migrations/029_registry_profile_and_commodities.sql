USE db_umkm;

ALTER TABLE umk_businesses
  ADD COLUMN IF NOT EXISTS legal_form VARCHAR(32) NULL AFTER legal_name,
  ADD COLUMN IF NOT EXISTS sector_label VARCHAR(190) NULL AFTER legal_form,
  ADD COLUMN IF NOT EXISTS started_year SMALLINT UNSIGNED NULL AFTER scale_id,
  ADD COLUMN IF NOT EXISTS employee_count INT UNSIGNED NULL AFTER started_year,
  ADD COLUMN IF NOT EXISTS annual_revenue_idr DECIMAL(18,2) NULL AFTER employee_count,
  ADD COLUMN IF NOT EXISTS contact_phone_e164 VARCHAR(20) NULL AFTER annual_revenue_idr,
  ADD COLUMN IF NOT EXISTS contact_source_text VARCHAR(190) NULL AFTER contact_phone_e164,
  ADD COLUMN IF NOT EXISTS website_url VARCHAR(500) NULL AFTER contact_source_text,
  ADD COLUMN IF NOT EXISTS social_media_url VARCHAR(500) NULL AFTER website_url,
  ADD COLUMN IF NOT EXISTS export_destinations TEXT NULL AFTER social_media_url,
  ADD COLUMN IF NOT EXISTS export_potential TEXT NULL AFTER export_destinations,
  ADD COLUMN IF NOT EXISTS production_capacity VARCHAR(190) NULL AFTER export_potential;

CREATE TABLE IF NOT EXISTS umk_business_commodities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  commodity_name TEXT NOT NULL,
  kbki_code VARCHAR(10) NULL,
  kbki_version VARCHAR(20) NULL,
  kbki_review_status VARCHAR(20) NOT NULL DEFAULT 'not_mapped',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_umk_commodity_business (business_id),
  KEY idx_umk_commodity_kbki (kbki_version,kbki_code),
  CONSTRAINT fk_umk_commodity_business FOREIGN KEY (business_id)
    REFERENCES umk_businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE umk_business_licenses
  ADD COLUMN IF NOT EXISTS verification_note VARCHAR(500) NULL AFTER evidence_basis;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (29,'registry_profile_and_commodities');
