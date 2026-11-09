USE db_umkm;

CREATE TABLE IF NOT EXISTS umk_review_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  note_text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_umk_review_notes_business (business_id, created_at),
  CONSTRAINT fk_umk_review_notes_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_umk_review_notes_author FOREIGN KEY (author_id) REFERENCES iam_users(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (13, 'registry_review_notes');
