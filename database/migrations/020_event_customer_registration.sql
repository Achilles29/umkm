-- Hubungkan pendaftar kegiatan dengan akun pengunjung etalase.
ALTER TABLE evt_registrations
  ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD KEY idx_evt_reg_customer (customer_id),
  ADD CONSTRAINT fk_evt_reg_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE SET NULL;
INSERT IGNORE INTO ops_schema_migrations (version_no,name) VALUES (20,'event_customer_registration');
