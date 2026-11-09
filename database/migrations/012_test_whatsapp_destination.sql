USE db_umkm;

CREATE TABLE IF NOT EXISTS mkt_contact_settings (
  id TINYINT UNSIGNED NOT NULL,
  test_whatsapp_e164 VARCHAR(20) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_mkt_contact_settings_actor FOREIGN KEY (updated_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO mkt_contact_settings (id) VALUES (1);

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (12, 'test_whatsapp_destination');
