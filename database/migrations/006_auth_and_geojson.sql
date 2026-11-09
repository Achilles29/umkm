USE db_umkm;

CREATE TABLE IF NOT EXISTS iam_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash BINARY(32) NOT NULL,
  ip_hash BINARY(32) NOT NULL,
  failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_iam_attempt_email (email_hash, failed_at),
  KEY idx_iam_attempt_ip (ip_hash, failed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gis_area_boundaries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_type VARCHAR(20) NOT NULL,
  area_code VARCHAR(16) NOT NULL,
  geometry_json MEDIUMTEXT NOT NULL,
  source_name VARCHAR(190) NOT NULL,
  imported_by BIGINT UNSIGNED NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gis_area_code (area_type, area_code),
  CONSTRAINT fk_gis_area_actor FOREIGN KEY (imported_by) REFERENCES iam_users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gis_location_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  source_name VARCHAR(40) NOT NULL,
  source_ref VARCHAR(80) NOT NULL,
  source_url VARCHAR(255) NOT NULL,
  place_name VARCHAR(190) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  match_status VARCHAR(20) NOT NULL DEFAULT 'candidate',
  detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gis_candidate (business_id, source_name, source_ref),
  KEY idx_gis_candidate_status (match_status),
  CONSTRAINT fk_gis_candidate_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no, name) VALUES
  (6, 'auth_and_geojson');
