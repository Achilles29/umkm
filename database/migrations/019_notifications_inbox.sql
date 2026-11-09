USE db_umkm;

CREATE TABLE IF NOT EXISTS ntf_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(40) NOT NULL,
  dedupe_key VARCHAR(120) NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  target_path VARCHAR(255) NOT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ntf_dedupe (dedupe_key),
  KEY idx_ntf_occurred (occurred_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ntf_inbox_items (
  event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  PRIMARY KEY (event_id,user_id),
  KEY idx_ntf_user_unread (user_id,read_at,delivered_at),
  CONSTRAINT fk_ntf_item_event FOREIGN KEY (event_id) REFERENCES ntf_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_ntf_item_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (19,'notifications_inbox');
