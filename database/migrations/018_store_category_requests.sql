USE db_umkm;

CREATE TABLE IF NOT EXISTS tko_category_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  request_type ENUM('add','remove') NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_by BIGINT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decision_note VARCHAR(500) NULL,
  pending_category_id BIGINT UNSIGNED AS (CASE WHEN status='pending' THEN category_id ELSE NULL END) PERSISTENT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tko_category_pending (store_id,pending_category_id),
  KEY idx_tko_category_review (status,requested_at),
  KEY idx_tko_category_store (store_id,requested_at),
  CONSTRAINT fk_tko_category_request_store FOREIGN KEY (store_id) REFERENCES tko_stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tko_category_request_category FOREIGN KEY (category_id) REFERENCES ref_categories(id),
  CONSTRAINT fk_tko_category_request_actor FOREIGN KEY (requested_by) REFERENCES iam_users(id),
  CONSTRAINT fk_tko_category_request_reviewer FOREIGN KEY (decided_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (18,'store_category_requests');
