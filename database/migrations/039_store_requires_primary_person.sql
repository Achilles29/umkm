USE db_umkm;

-- Berlaku untuk toko baru. Profil lama yang masih menunggu kepastian pelaku
-- tidak ditautkan secara otomatis ke orang yang belum diverifikasi.
DROP TRIGGER IF EXISTS tko_stores_owner_before_insert;
DROP TRIGGER IF EXISTS tko_stores_owner_before_activate;
DELIMITER $$
CREATE TRIGGER tko_stores_owner_before_insert
BEFORE INSERT ON tko_stores
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM umk_business_owners o
    WHERE o.business_id=NEW.business_id AND o.is_primary=1
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store requires a primary person on its business';
  END IF;
END$$

CREATE TRIGGER tko_stores_owner_before_activate
BEFORE UPDATE ON tko_stores
FOR EACH ROW
BEGIN
  IF NEW.status='active' AND OLD.status<>'active' AND NOT EXISTS (
    SELECT 1 FROM umk_business_owners o
    WHERE o.business_id=NEW.business_id AND o.is_primary=1
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store activation requires a primary person';
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (39,'store_requires_primary_person');
