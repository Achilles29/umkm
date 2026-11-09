USE db_umkm;

CREATE TABLE IF NOT EXISTS ops_schema_migrations (
  version_no SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version_no)
) ENGINE=InnoDB;

DROP TRIGGER IF EXISTS tko_stores_before_insert;
DROP TRIGGER IF EXISTS tko_stores_before_update;

DELIMITER $$
CREATE TRIGGER tko_stores_before_insert
BEFORE INSERT ON tko_stores
FOR EACH ROW
BEGIN
  IF NEW.status = 'active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Create store as draft, then verify before activation';
  END IF;
END$$

CREATE TRIGGER tko_stores_before_update
BEFORE UPDATE ON tko_stores
FOR EACH ROW
BEGIN
  IF NEW.business_id <> OLD.business_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store business cannot be changed';
  END IF;
  IF NEW.status = 'active' AND OLD.status <> 'active' THEN
    IF NEW.whatsapp_e164 IS NULL OR NEW.whatsapp_e164 = '' OR NEW.published_at IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active store requires contact and publication date';
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM umk_business_licenses AS l
      WHERE l.business_id = NEW.business_id
        AND l.license_type = 'NIB'
        AND l.status = 'verified'
        AND l.verified_at IS NOT NULL
        AND l.verified_by IS NOT NULL
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active store requires verified NIB';
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM tko_verifications AS v
      WHERE v.store_id = NEW.id
        AND v.id = (SELECT MAX(v2.id) FROM tko_verifications AS v2 WHERE v2.store_id = NEW.id)
        AND v.decision = 'approved'
        AND v.visit_date IS NOT NULL
        AND v.decided_at IS NOT NULL
        AND v.decided_by IS NOT NULL
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active store requires approved field visit';
    END IF;
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO ops_schema_migrations (version_no, name) VALUES
  (1, 'create_db_umkm'),
  (2, 'store_activation_guard');
