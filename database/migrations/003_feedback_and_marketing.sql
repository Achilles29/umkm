USE db_umkm;

ALTER TABLE umk_businesses
  ADD COLUMN IF NOT EXISTS address_ciphertext BLOB NULL AFTER address_private;

CREATE TABLE IF NOT EXISTS tko_store_categories (
  store_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  tagged_by BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'selected',
  tagged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (store_id, category_id),
  KEY idx_tko_sc_category (category_id, status),
  CONSTRAINT fk_tko_sc_store FOREIGN KEY (store_id) REFERENCES tko_stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tko_sc_category FOREIGN KEY (category_id) REFERENCES ref_categories(id),
  CONSTRAINT fk_tko_sc_actor FOREIGN KEY (tagged_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Nomor HP tanpa pembuktian hanya menghasilkan profil minat, bukan akses akun terverifikasi.
CREATE TABLE IF NOT EXISTS crm_customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone_e164 VARCHAR(20) NOT NULL,
  display_name VARCHAR(160) NULL,
  verified_user_id BIGINT UNSIGNED NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'marketplace',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_crm_customer_phone (phone_e164),
  KEY idx_crm_customer_verified (verified_user_id),
  CONSTRAINT fk_crm_customer_user FOREIGN KEY (verified_user_id) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS crm_marketplace_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  token_hash BINARY(32) NOT NULL,
  trust_level VARCHAR(20) NOT NULL DEFAULT 'phone_only',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_crm_session_token (token_hash),
  KEY idx_crm_session_expiry (expires_at),
  CONSTRAINT fk_crm_session_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkt_qr_placements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  label VARCHAR(160) NOT NULL,
  placement_kind VARCHAR(40) NOT NULL DEFAULT 'other',
  location_name VARCHAR(160) NULL,
  destination_path VARCHAR(255) NOT NULL DEFAULT '/',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_mkt_qr_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkt_qr_daily_stats (
  placement_id BIGINT UNSIGNED NOT NULL,
  visit_date DATE NOT NULL,
  scan_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (placement_id, visit_date),
  CONSTRAINT fk_mkt_qr_stat_place FOREIGN KEY (placement_id) REFERENCES mkt_qr_placements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkt_whatsapp_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_key VARCHAR(40) NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  body_template TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_mkt_template_version (template_key, version_no),
  KEY idx_mkt_template_active (template_key, is_active)
) ENGINE=InnoDB;

-- Catatan minat hanya membuktikan tautan WhatsApp dibuka, bukan terjadinya transaksi.
CREATE TABLE IF NOT EXISTS mkt_contact_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_no VARCHAR(40) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  store_id BIGINT UNSIGNED NOT NULL,
  cart_id BIGINT UNSIGNED NULL,
  qr_placement_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  contact_mode VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'prepared',
  prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  opened_at DATETIME NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_mkt_intent_ref (reference_no),
  KEY idx_mkt_intent_store (store_id, prepared_at),
  KEY idx_mkt_intent_customer (customer_id, prepared_at),
  CONSTRAINT fk_mkt_intent_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_mkt_intent_store FOREIGN KEY (store_id) REFERENCES tko_stores(id),
  CONSTRAINT fk_mkt_intent_cart FOREIGN KEY (cart_id) REFERENCES krj_carts(id) ON DELETE SET NULL,
  CONSTRAINT fk_mkt_intent_qr FOREIGN KEY (qr_placement_id) REFERENCES mkt_qr_placements(id) ON DELETE SET NULL,
  CONSTRAINT fk_mkt_intent_template FOREIGN KEY (template_id) REFERENCES mkt_whatsapp_templates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mkt_contact_items (
  intent_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  line_no SMALLINT UNSIGNED NOT NULL,
  product_name VARCHAR(190) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  price_note VARCHAR(120) NULL,
  PRIMARY KEY (intent_id, line_no),
  KEY idx_mkt_item_product (product_id),
  CONSTRAINT fk_mkt_item_intent FOREIGN KEY (intent_id) REFERENCES mkt_contact_intents(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_item_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Studio event menyimpan komponen tervalidasi; tidak menyimpan kode bebas.
CREATE TABLE IF NOT EXISTS evt_design_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  component_json LONGTEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_evt_design_version (event_id, version_no),
  CONSTRAINT fk_evt_design_event FOREIGN KEY (event_id) REFERENCES evt_events(id),
  CONSTRAINT fk_evt_design_author FOREIGN KEY (created_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO ops_schema_migrations (version_no, name) VALUES
  (3, 'feedback_and_marketing');
