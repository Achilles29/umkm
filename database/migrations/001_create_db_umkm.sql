-- Rembang UMKM Hub: skema awal. Jalankan pada MariaDB 10.11 sebagai administrator.
-- Seluruh tabel memakai InnoDB dan prefix menurut rumpun fitur.
CREATE DATABASE IF NOT EXISTS db_umkm
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_umkm;

-- ref_: wilayah, klasifikasi resmi, dan kategori navigasi publik.
CREATE TABLE IF NOT EXISTS ref_provinces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_provinces_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_regencies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  province_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind VARCHAR(16) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_regencies_code (code),
  KEY idx_ref_regencies_province (province_id),
  CONSTRAINT fk_ref_regencies_province FOREIGN KEY (province_id) REFERENCES ref_provinces(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_districts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  regency_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_districts_code (code),
  KEY idx_ref_districts_regency (regency_id),
  CONSTRAINT fk_ref_districts_regency FOREIGN KEY (regency_id) REFERENCES ref_regencies(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_villages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  district_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'desa',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_villages_code (code),
  KEY idx_ref_villages_district (district_id),
  CONSTRAINT fk_ref_villages_district FOREIGN KEY (district_id) REFERENCES ref_districts(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_business_scales (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(60) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_scales_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_kbli_versions (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_label VARCHAR(30) NOT NULL,
  legal_basis VARCHAR(255) NULL,
  effective_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_kbli_versions_label (version_label)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_kbli_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_id SMALLINT UNSIGNED NOT NULL,
  code VARCHAR(8) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  parent_code VARCHAR(8) NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_kbli_version_code (version_id, code),
  KEY idx_ref_kbli_code (code),
  CONSTRAINT fk_ref_kbli_codes_version FOREIGN KEY (version_id) REFERENCES ref_kbli_versions(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ref_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  category_type VARCHAR(20) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY uq_ref_categories_type_slug (category_type, slug),
  KEY idx_ref_categories_parent (parent_id),
  CONSTRAINT fk_ref_categories_parent FOREIGN KEY (parent_id) REFERENCES ref_categories(id)
) ENGINE=InnoDB;

-- iam_: identitas, izin, cakupan kecamatan, dan verifikasi nomor.
CREATE TABLE IF NOT EXISTS iam_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  phone_e164 VARCHAR(20) NULL,
  phone_verified_at DATETIME NULL,
  email VARCHAR(190) NULL,
  email_verified_at DATETIME NULL,
  password_hash VARCHAR(255) NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_iam_users_phone (phone_e164),
  UNIQUE KEY uq_iam_users_email (email), KEY idx_iam_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iam_roles (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_iam_roles_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iam_permissions (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  description VARCHAR(255) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_iam_permissions_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iam_role_permissions (
  role_id SMALLINT UNSIGNED NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_iam_rp_role FOREIGN KEY (role_id) REFERENCES iam_roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_iam_rp_permission FOREIGN KEY (permission_id) REFERENCES iam_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iam_user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id SMALLINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_iam_ur_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_iam_ur_role FOREIGN KEY (role_id) REFERENCES iam_roles(id),
  CONSTRAINT fk_iam_ur_assigner FOREIGN KEY (assigned_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iam_district_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id SMALLINT UNSIGNED NOT NULL,
  district_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id, district_id),
  KEY idx_iam_dr_district (district_id),
  CONSTRAINT fk_iam_dr_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_iam_dr_role FOREIGN KEY (role_id) REFERENCES iam_roles(id),
  CONSTRAINT fk_iam_dr_district FOREIGN KEY (district_id) REFERENCES ref_districts(id),
  CONSTRAINT fk_iam_dr_assigner FOREIGN KEY (assigned_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iam_phone_challenges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  phone_e164 VARCHAR(20) NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  code_hash VARBINARY(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_iam_challenge_phone (phone_e164, purpose, expires_at),
  CONSTRAINT fk_iam_challenge_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- umk_: registri internal, pemilik, perizinan, klaim, dan jejak impor.
CREATE TABLE IF NOT EXISTS umk_businesses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  legal_name VARCHAR(190) NULL,
  description TEXT NULL,
  district_id BIGINT UNSIGNED NOT NULL,
  village_id BIGINT UNSIGNED NULL,
  address_private TEXT NULL,
  scale_id SMALLINT UNSIGNED NULL,
  registry_status VARCHAR(24) NOT NULL DEFAULT 'draft',
  data_source VARCHAR(80) NULL,
  source_updated_at DATE NULL,
  reviewed_at DATETIME NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_umk_business_district (district_id, registry_status),
  KEY idx_umk_business_village (village_id), KEY idx_umk_business_scale (scale_id),
  CONSTRAINT fk_umk_business_district FOREIGN KEY (district_id) REFERENCES ref_districts(id),
  CONSTRAINT fk_umk_business_village FOREIGN KEY (village_id) REFERENCES ref_villages(id),
  CONSTRAINT fk_umk_business_scale FOREIGN KEY (scale_id) REFERENCES ref_business_scales(id),
  CONSTRAINT fk_umk_business_reviewer FOREIGN KEY (reviewed_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_people (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  nik_ciphertext VARBINARY(512) NULL,
  nik_fingerprint BINARY(32) NULL,
  phone_e164 VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_umk_people_nik_fp (nik_fingerprint),
  KEY idx_umk_people_phone (phone_e164)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_business_owners (
  business_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  relationship VARCHAR(30) NOT NULL DEFAULT 'owner',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (business_id, person_id), KEY idx_umk_owners_person (person_id),
  CONSTRAINT fk_umk_owner_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_umk_owner_person FOREIGN KEY (person_id) REFERENCES umk_people(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_business_kbli (
  business_id BIGINT UNSIGNED NOT NULL,
  kbli_code_id BIGINT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (business_id, kbli_code_id), KEY idx_umk_bk_kbli (kbli_code_id),
  CONSTRAINT fk_umk_bk_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_umk_bk_kbli FOREIGN KEY (kbli_code_id) REFERENCES ref_kbli_codes(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_business_licenses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  license_type VARCHAR(30) NOT NULL DEFAULT 'NIB',
  license_number VARCHAR(80) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'submitted',
  verified_at DATETIME NULL,
  verified_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_umk_license_business (business_id, license_type, status),
  KEY idx_umk_license_number (license_number),
  CONSTRAINT fk_umk_license_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_umk_license_verifier FOREIGN KEY (verified_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_business_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'submitted',
  reason TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_umk_claim_business (business_id, status),
  KEY idx_umk_claim_user (user_id, status),
  CONSTRAINT fk_umk_claim_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_umk_claim_user FOREIGN KEY (user_id) REFERENCES iam_users(id),
  CONSTRAINT fk_umk_claim_reviewer FOREIGN KEY (reviewed_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(40) NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  sha256 BINARY(32) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_umk_documents_business (business_id, document_type),
  CONSTRAINT fk_umk_doc_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_umk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_import_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_name VARCHAR(255) NOT NULL,
  source_sha256 BINARY(32) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  accepted_rows INT UNSIGNED NOT NULL DEFAULT 0,
  rejected_rows INT UNSIGNED NOT NULL DEFAULT 0,
  started_by BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  PRIMARY KEY (id), KEY idx_umk_import_sha (source_sha256),
  CONSTRAINT fk_umk_import_actor FOREIGN KEY (started_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS umk_import_rows (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  sheet_name VARCHAR(120) NOT NULL,
  source_row INT UNSIGNED NOT NULL,
  row_sha256 BINARY(32) NULL,
  business_id BIGINT UNSIGNED NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  error_code VARCHAR(80) NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_umk_import_row (batch_id, sheet_name, source_row),
  KEY idx_umk_import_row_business (business_id),
  CONSTRAINT fk_umk_row_batch FOREIGN KEY (batch_id) REFERENCES umk_import_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_umk_row_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- tko_: toko publik dan keputusan verifikasi lapangan.
CREATE TABLE IF NOT EXISTS tko_stores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(120) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  whatsapp_e164 VARCHAR(20) NULL,
  contact_label VARCHAR(120) NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  last_activity_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tko_store_business (business_id),
  UNIQUE KEY uq_tko_store_slug (slug), KEY idx_tko_store_status (status, published_at),
  CONSTRAINT fk_tko_store_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tko_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  visit_date DATE NULL,
  decision VARCHAR(24) NOT NULL DEFAULT 'pending',
  findings TEXT NULL,
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_tko_verify_store (store_id, created_at),
  CONSTRAINT fk_tko_verify_store FOREIGN KEY (store_id) REFERENCES tko_stores(id),
  CONSTRAINT fk_tko_verify_actor FOREIGN KEY (decided_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- prd_: katalog produk dan kategorinya.
CREATE TABLE IF NOT EXISTS prd_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(150) NOT NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  price_min DECIMAL(15,2) NULL,
  price_max DECIMAL(15,2) NULL,
  unit_label VARCHAR(40) NULL,
  availability_note VARCHAR(160) NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_prd_store_slug (store_id, slug),
  KEY idx_prd_store_status (store_id, status), KEY idx_prd_name (name),
  CONSTRAINT fk_prd_product_store FOREIGN KEY (store_id) REFERENCES tko_stores(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prd_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  alt_text VARCHAR(190) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_prd_image_order (product_id, sort_order),
  CONSTRAINT fk_prd_image_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prd_product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id), KEY idx_prd_pc_category (category_id),
  CONSTRAINT fk_prd_pc_product FOREIGN KEY (product_id) REFERENCES prd_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_prd_pc_category FOREIGN KEY (category_id) REFERENCES ref_categories(id)
) ENGINE=InnoDB;

-- krj_: keranjang sementara, bukan pesanan atau pembayaran.
CREATE TABLE IF NOT EXISTS krj_carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_token CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_krj_cart_token (cart_token),
  KEY idx_krj_cart_expiry (expires_at), KEY idx_krj_cart_user (user_id),
  CONSTRAINT fk_krj_cart_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS krj_cart_items (
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_id, product_id), KEY idx_krj_item_product (product_id),
  CONSTRAINT fk_krj_item_cart FOREIGN KEY (cart_id) REFERENCES krj_carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_krj_item_product FOREIGN KEY (product_id) REFERENCES prd_products(id)
) ENGINE=InnoDB;

-- evt_: kegiatan, formulir berversi, dan pendaftaran.
CREATE TABLE IF NOT EXISTS evt_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(150) NOT NULL,
  title VARCHAR(190) NOT NULL,
  summary TEXT NULL,
  body MEDIUMTEXT NULL,
  template_key VARCHAR(60) NOT NULL DEFAULT 'standard',
  banner_key VARCHAR(255) NULL,
  venue VARCHAR(190) NULL,
  district_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  registration_opens_at DATETIME NULL,
  registration_closes_at DATETIME NULL,
  capacity INT UNSIGNED NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_evt_slug (slug),
  KEY idx_evt_status_date (status, starts_at),
  CONSTRAINT fk_evt_district FOREIGN KEY (district_id) REFERENCES ref_districts(id),
  CONSTRAINT fk_evt_creator FOREIGN KEY (created_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evt_form_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_evt_form_version (event_id, version_no),
  CONSTRAINT fk_evt_fv_event FOREIGN KEY (event_id) REFERENCES evt_events(id),
  CONSTRAINT fk_evt_fv_actor FOREIGN KEY (created_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evt_form_fields (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_version_id BIGINT UNSIGNED NOT NULL,
  field_key VARCHAR(80) NOT NULL,
  label VARCHAR(160) NOT NULL,
  field_type VARCHAR(30) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  options_json LONGTEXT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uq_evt_field_key (form_version_id, field_key),
  KEY idx_evt_field_order (form_version_id, sort_order),
  CONSTRAINT fk_evt_field_version FOREIGN KEY (form_version_id) REFERENCES evt_form_versions(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evt_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  form_version_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  reference_no VARCHAR(40) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  phone_e164 VARCHAR(20) NOT NULL,
  phone_verified_at DATETIME NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'submitted',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_evt_registration_ref (reference_no),
  UNIQUE KEY uq_evt_registration_phone (event_id, phone_e164),
  KEY idx_evt_reg_status (event_id, status),
  CONSTRAINT fk_evt_reg_event FOREIGN KEY (event_id) REFERENCES evt_events(id),
  CONSTRAINT fk_evt_reg_form FOREIGN KEY (form_version_id) REFERENCES evt_form_versions(id),
  CONSTRAINT fk_evt_reg_user FOREIGN KEY (user_id) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evt_answers (
  registration_id BIGINT UNSIGNED NOT NULL,
  field_id BIGINT UNSIGNED NOT NULL,
  answer_text MEDIUMTEXT NULL,
  PRIMARY KEY (registration_id, field_id), KEY idx_evt_answer_field (field_id),
  CONSTRAINT fk_evt_answer_reg FOREIGN KEY (registration_id) REFERENCES evt_registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_evt_answer_field FOREIGN KEY (field_id) REFERENCES evt_form_fields(id)
) ENGINE=InnoDB;

-- gis_: koordinat internal dan titik publik dipisah secara eksplisit.
CREATE TABLE IF NOT EXISTS gis_business_locations (
  business_id BIGINT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  public_latitude DECIMAL(10,7) NULL,
  public_longitude DECIMAL(10,7) NULL,
  visibility VARCHAR(20) NOT NULL DEFAULT 'hidden',
  consent_at DATETIME NULL,
  checked_at DATETIME NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (business_id), KEY idx_gis_visibility (visibility),
  CONSTRAINT fk_gis_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_gis_actor FOREIGN KEY (updated_by) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- lay_: layanan, konsultasi, dan pengaduan dengan riwayat status.
CREATE TABLE IF NOT EXISTS lay_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_no VARCHAR(40) NOT NULL,
  requester_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NULL,
  district_id BIGINT UNSIGNED NULL,
  ticket_type VARCHAR(30) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'submitted',
  assigned_to BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_lay_ticket_ref (reference_no),
  KEY idx_lay_ticket_status (status, created_at),
  KEY idx_lay_ticket_district (district_id, status),
  CONSTRAINT fk_lay_ticket_requester FOREIGN KEY (requester_id) REFERENCES iam_users(id),
  CONSTRAINT fk_lay_ticket_business FOREIGN KEY (business_id) REFERENCES umk_businesses(id),
  CONSTRAINT fk_lay_ticket_district FOREIGN KEY (district_id) REFERENCES ref_districts(id),
  CONSTRAINT fk_lay_ticket_assignee FOREIGN KEY (assigned_to) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lay_ticket_updates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  previous_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NULL,
  note TEXT NULL,
  visibility VARCHAR(20) NOT NULL DEFAULT 'internal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_lay_update_ticket (ticket_id, created_at),
  CONSTRAINT fk_lay_update_ticket FOREIGN KEY (ticket_id) REFERENCES lay_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_lay_update_actor FOREIGN KEY (actor_id) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- cms_: konten berita, panduan, dan peluang pasar.
CREATE TABLE IF NOT EXISTS cms_articles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_type VARCHAR(30) NOT NULL,
  slug VARCHAR(150) NOT NULL,
  title VARCHAR(190) NOT NULL,
  summary TEXT NULL,
  body MEDIUMTEXT NULL,
  cover_key VARCHAR(255) NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  author_id BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_cms_article_slug (slug),
  KEY idx_cms_article_type (article_type, status, published_at),
  CONSTRAINT fk_cms_article_author FOREIGN KEY (author_id) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- rank_: hasil hitung peringkat, bukan klaim kecepatan balas WhatsApp.
CREATE TABLE IF NOT EXISTS rank_store_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  district_id BIGINT UNSIGNED NULL,
  formula_version VARCHAR(30) NOT NULL,
  activity_score DECIMAL(7,4) NOT NULL DEFAULT 0,
  completeness_score DECIMAL(7,4) NOT NULL DEFAULT 0,
  engagement_score DECIMAL(7,4) NOT NULL DEFAULT 0,
  total_score DECIMAL(7,4) NOT NULL DEFAULT 0,
  calculated_at DATETIME NOT NULL,
  PRIMARY KEY (id), KEY idx_rank_context (category_id, district_id, total_score),
  KEY idx_rank_store_time (store_id, calculated_at),
  CONSTRAINT fk_rank_store FOREIGN KEY (store_id) REFERENCES tko_stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_rank_category FOREIGN KEY (category_id) REFERENCES ref_categories(id),
  CONSTRAINT fk_rank_district FOREIGN KEY (district_id) REFERENCES ref_districts(id)
) ENGINE=InnoDB;

-- ops_: audit tindakan penting tanpa menyimpan isi NIK atau dokumen dalam log.
CREATE TABLE IF NOT EXISTS ops_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id BIGINT UNSIGNED NULL,
  action_code VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  request_id VARCHAR(64) NULL,
  ip_address VARBINARY(16) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_ops_entity (entity_type, entity_id, created_at),
  KEY idx_ops_actor (actor_id, created_at),
  CONSTRAINT fk_ops_actor FOREIGN KEY (actor_id) REFERENCES iam_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO ref_business_scales (code, name, sort_order) VALUES
  ('MIKRO', 'Usaha Mikro', 10),
  ('KECIL', 'Usaha Kecil', 20),
  ('MENENGAH', 'Usaha Menengah', 30);

INSERT IGNORE INTO iam_roles (code, name) VALUES
  ('superadmin', 'Superadmin'),
  ('admin_dinas', 'Admin Dinas'),
  ('admin_kecamatan', 'Admin Kecamatan'),
  ('umkm', 'Pelaku UMKM'),
  ('masyarakat', 'Masyarakat');
