USE db_umkm;
CREATE TABLE IF NOT EXISTS tko_store_hours (
  store_id BIGINT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  opens_at TIME NULL,
  closes_at TIME NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (store_id,day_of_week),
  CONSTRAINT fk_tko_hours_store FOREIGN KEY (store_id) REFERENCES tko_stores(id) ON DELETE CASCADE,
  CONSTRAINT chk_tko_hours_day CHECK (day_of_week BETWEEN 1 AND 7)
) ENGINE=InnoDB;
INSERT IGNORE INTO ops_schema_migrations (version_no,name) VALUES (17,'store_hours');
