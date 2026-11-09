USE db_umkm;
ALTER TABLE prd_products ADD COLUMN base_price DECIMAL(15,2) NULL AFTER price_max;
UPDATE prd_products SET base_price=price_min;
INSERT IGNORE INTO ops_schema_migrations (version_no,name) VALUES (15,'product_base_price');
