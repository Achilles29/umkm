USE db_umkm;

CREATE TABLE IF NOT EXISTS cms_banners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slot VARCHAR(40) NOT NULL,
  eyebrow VARCHAR(80) NOT NULL,
  title VARCHAR(140) NOT NULL,
  subtitle VARCHAR(255) NOT NULL,
  cta_label VARCHAR(60) NOT NULL,
  cta_target VARCHAR(40) NOT NULL,
  image_path VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_cms_banner_slot (slot),
  CONSTRAINT fk_cms_banner_actor FOREIGN KEY (updated_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO cms_banners (slot, eyebrow, title, subtitle, cta_label, cta_target)
VALUES ('marketplace', 'REMBANG • UMKM HUB', 'Belanja lokal, temukan cerita baru.',
        'Ragam rasa, karya, dan layanan dari penjuru Rembang.', 'Jelajahi produk', 'products')
ON DUPLICATE KEY UPDATE slot = VALUES(slot);

CREATE TABLE IF NOT EXISTS gis_district_points (
  district_id BIGINT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  source_url VARCHAR(255) NOT NULL,
  source_label VARCHAR(80) NOT NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (district_id),
  CONSTRAINT fk_gis_district_point FOREIGN KEY (district_id) REFERENCES ref_districts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (10, 'banners_and_district_points');
