USE db_umkm;

-- Akun sementara hasil impor. Kaitan ini bukan bukti bahwa pemegang akun
-- adalah pemilik usaha; hak mengelola toko tetap melalui verifikasi dan
-- tko_store_managers.
CREATE TABLE IF NOT EXISTS iam_imported_business_accounts (
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  provision_status VARCHAR(24) NOT NULL DEFAULT 'pending_owner',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (business_id),
  UNIQUE KEY uq_iam_imported_account_user (user_id),
  KEY idx_iam_imported_account_status (provision_status),
  CONSTRAINT fk_iam_imported_account_business FOREIGN KEY (business_id)
    REFERENCES umk_businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_iam_imported_account_user FOREIGN KEY (user_id)
    REFERENCES iam_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (21, 'imported_business_accounts');
