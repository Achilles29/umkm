USE db_umkm;
CREATE TABLE IF NOT EXISTS tko_store_managers (
  store_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  access_role ENUM('owner','editor') NOT NULL DEFAULT 'owner',
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  assigned_by BIGINT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (store_id,user_id),
  KEY idx_tko_manager_user (user_id,status),
  CONSTRAINT fk_tko_manager_store FOREIGN KEY (store_id) REFERENCES tko_stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tko_manager_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tko_manager_assigner FOREIGN KEY (assigned_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO ops_schema_migrations (version_no,name) VALUES (16,'store_managers');
