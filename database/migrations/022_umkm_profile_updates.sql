USE db_umkm;

-- Pemilik akun hasil impor dapat mengusulkan koreksi data tanpa langsung
-- menimpa registri atau menerbitkan toko.
CREATE TABLE IF NOT EXISTS umk_profile_update_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  proposed_name VARCHAR(190) NOT NULL,
  proposed_description TEXT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'submitted',
  decision_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_umk_update_business (business_id,status,created_at),
  KEY idx_umk_update_user (user_id,created_at),
  CONSTRAINT fk_umk_update_business FOREIGN KEY (business_id)
    REFERENCES umk_businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_umk_update_user FOREIGN KEY (user_id)
    REFERENCES iam_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_umk_update_reviewer FOREIGN KEY (reviewed_by)
    REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (22, 'umkm_profile_updates');
