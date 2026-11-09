USE db_umkm;

CREATE TABLE IF NOT EXISTS umk_person_match_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  match_basis VARCHAR(32) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  decided_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_umk_person_match (business_id,person_id),
  KEY idx_umk_person_match_status (business_id,status),
  CONSTRAINT fk_umk_match_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_umk_match_person FOREIGN KEY (person_id) REFERENCES umk_people(id),
  CONSTRAINT fk_umk_match_actor FOREIGN KEY (decided_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (26,'person_match_review');
