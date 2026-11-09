USE db_umkm;

CREATE TABLE IF NOT EXISTS prd_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(190) NOT NULL,
  sku VARCHAR(80) NULL,
  price DECIMAL(15,2) NULL,
  stock_qty INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prd_variant_sku (product_id, sku),
  KEY idx_prd_variant_product (product_id, is_active, sort_order),
  CONSTRAINT fk_prd_variant_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prd_variant_attributes (
  variant_id BIGINT UNSIGNED NOT NULL,
  attribute_name VARCHAR(60) NOT NULL,
  attribute_value VARCHAR(120) NOT NULL,
  PRIMARY KEY (variant_id, attribute_name),
  CONSTRAINT fk_prd_variant_attribute FOREIGN KEY (variant_id) REFERENCES prd_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prd_product_specs (
  product_id BIGINT UNSIGNED NOT NULL,
  spec_name VARCHAR(80) NOT NULL,
  spec_value VARCHAR(255) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, spec_name),
  CONSTRAINT fk_prd_spec_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prd_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  alt_text VARCHAR(190) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prd_media_product (product_id, media_type, sort_order),
  CONSTRAINT fk_prd_media_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_prd_media_actor FOREIGN KEY (created_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE krj_cart_items
  DROP PRIMARY KEY,
  ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD COLUMN variant_id BIGINT UNSIGNED NULL AFTER product_id,
  ADD COLUMN variant_key BIGINT UNSIGNED AS (IFNULL(variant_id,0)) PERSISTENT AFTER variant_id,
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY uq_krj_cart_product_variant (cart_id, product_id, variant_key),
  ADD KEY idx_krj_cart_variant (variant_id),
  ADD CONSTRAINT fk_krj_cart_variant FOREIGN KEY (variant_id) REFERENCES prd_variants(id);

ALTER TABLE mkt_contact_items
  ADD COLUMN IF NOT EXISTS variant_label VARCHAR(190) NULL AFTER product_name;

INSERT IGNORE INTO ops_schema_migrations (version_no, name)
VALUES (14, 'product_variants_media_and_cart');
