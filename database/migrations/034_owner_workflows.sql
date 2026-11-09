USE db_umkm;

ALTER TABLE umk_business_claims
  ADD COLUMN evidence_key VARCHAR(100) NULL,
  ADD COLUMN evidence_mime VARCHAR(40) NULL,
  ADD COLUMN evidence_sha256 BINARY(32) NULL,
  ADD COLUMN decision_note VARCHAR(500) NULL,
  ADD COLUMN approved_business_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN status='approved' THEN business_id ELSE NULL END) PERSISTENT,
  ADD UNIQUE KEY uq_umk_approved_claim_business (approved_business_id),
  ADD KEY idx_umk_claim_queue (status,created_at);

CREATE TABLE prd_review_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decision_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  pending_product_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN status='pending' THEN product_id ELSE NULL END) PERSISTENT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prd_one_pending_review (pending_product_id),
  KEY idx_prd_review_queue (status,created_at),
  KEY idx_prd_review_product (product_id,created_at),
  CONSTRAINT fk_prd_review_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_prd_review_user FOREIGN KEY (user_id) REFERENCES iam_users(id),
  CONSTRAINT fk_prd_review_actor FOREIGN KEY (reviewed_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gis_location_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  display_mode VARCHAR(12) NOT NULL,
  source_note VARCHAR(500) NOT NULL,
  consent_at DATETIME NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decision_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  pending_business_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN status='pending' THEN business_id ELSE NULL END) PERSISTENT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gis_one_pending_location (pending_business_id),
  KEY idx_gis_location_request_queue (status,created_at),
  KEY idx_gis_location_request_user (user_id,created_at),
  CONSTRAINT fk_gis_request_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_gis_request_user FOREIGN KEY (user_id) REFERENCES iam_users(id),
  CONSTRAINT fk_gis_request_actor FOREIGN KEY (reviewed_by) REFERENCES iam_users(id) ON DELETE SET NULL,
  CONSTRAINT chk_gis_request_mode CHECK (display_mode IN ('point','area','hidden')),
  CONSTRAINT chk_gis_request_consent CHECK (display_mode<>'point' OR consent_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE iam_recovery_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NULL,
  requester_note VARCHAR(500) NOT NULL,
  evidence_key VARCHAR(100) NOT NULL,
  evidence_mime VARCHAR(40) NOT NULL,
  evidence_sha256 BINARY(32) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  decision_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  pending_user_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN status='pending' THEN user_id ELSE NULL END) PERSISTENT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_iam_one_pending_recovery (pending_user_id),
  KEY idx_iam_recovery_queue (status,created_at),
  CONSTRAINT fk_iam_recovery_user FOREIGN KEY (user_id) REFERENCES iam_users(id),
  CONSTRAINT fk_iam_recovery_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_iam_recovery_actor FOREIGN KEY (reviewed_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS prd_products_before_insert;
DROP TRIGGER IF EXISTS prd_products_before_update;
DELIMITER $$
CREATE TRIGGER prd_products_before_insert BEFORE INSERT ON prd_products FOR EACH ROW
BEGIN
  IF NEW.status='active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Create product as draft before review';
  END IF;
END$$
CREATE TRIGGER prd_products_before_update BEFORE UPDATE ON prd_products FOR EACH ROW
BEGIN
  IF NEW.status='active' AND OLD.status<>'active' AND NOT EXISTS (
    SELECT 1 FROM prd_review_requests r
    WHERE r.product_id=NEW.id AND r.status='approved'
      AND r.id=(SELECT MAX(r2.id) FROM prd_review_requests r2 WHERE r2.product_id=NEW.id)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Product activation requires approved review';
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (34,'owner_workflows');
