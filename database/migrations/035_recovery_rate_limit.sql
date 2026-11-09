USE db_umkm;
ALTER TABLE iam_recovery_requests
  ADD COLUMN request_ip_hash BINARY(32) NULL,
  ADD KEY idx_iam_recovery_ip (request_ip_hash,created_at);
INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (35,'recovery_rate_limit');
