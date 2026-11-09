USE db_umkm;

ALTER TABLE krj_carts
  ADD UNIQUE KEY IF NOT EXISTS uq_krj_cart_customer (customer_id);

INSERT IGNORE INTO ops_schema_migrations (version_no, name) VALUES
  (9, 'one_cart_per_customer');
