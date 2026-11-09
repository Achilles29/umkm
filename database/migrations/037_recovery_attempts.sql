USE db_umkm;
CREATE TABLE iam_recovery_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash BINARY(32) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_iam_recovery_attempt_ip (ip_hash,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (37,'recovery_attempts');
