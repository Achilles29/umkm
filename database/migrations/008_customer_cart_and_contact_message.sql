USE db_umkm;

ALTER TABLE krj_carts
  ADD COLUMN IF NOT EXISTS customer_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD KEY IF NOT EXISTS idx_krj_customer_expiry (customer_id, expires_at),
  ADD CONSTRAINT fk_krj_cart_customer FOREIGN KEY (customer_id)
    REFERENCES crm_customers(id) ON DELETE CASCADE;

ALTER TABLE mkt_contact_intents
  ADD COLUMN IF NOT EXISTS message_text TEXT NULL AFTER contact_mode;

INSERT IGNORE INTO ops_schema_migrations (version_no, name) VALUES
  (8, 'customer_cart_and_contact_message');
