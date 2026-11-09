USE db_umkm;

-- Setiap alias langsung menunjuk kegiatan kanonis. Tidak boleh ada rantai
-- alias atau siklus karena semua pembacaan aplikasi menyelesaikan satu tingkat.
DROP TRIGGER IF EXISTS umk_business_before_insert;
DROP TRIGGER IF EXISTS umk_business_before_alias;
DELIMITER $$
CREATE TRIGGER umk_business_before_insert
BEFORE INSERT ON umk_businesses
FOR EACH ROW
BEGIN
  IF NEW.canonical_business_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM umk_businesses target
    WHERE target.id=NEW.canonical_business_id
      AND target.canonical_business_id IS NULL
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Alias target must be a canonical business';
  END IF;
END$$

CREATE TRIGGER umk_business_before_alias
BEFORE UPDATE ON umk_businesses
FOR EACH ROW
BEGIN
  IF NEW.canonical_business_id IS NOT NULL THEN
    IF NEW.canonical_business_id=NEW.id OR NOT EXISTS (
      SELECT 1 FROM umk_businesses target
      WHERE target.id=NEW.canonical_business_id
        AND target.canonical_business_id IS NULL
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Alias target must be a canonical business';
    END IF;
    IF OLD.canonical_business_id IS NULL OR
       NEW.canonical_business_id<>OLD.canonical_business_id THEN
      IF EXISTS (SELECT 1 FROM tko_stores s WHERE s.business_id=NEW.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store business cannot become an alias';
      END IF;
      IF EXISTS (SELECT 1 FROM umk_business_owners o WHERE o.business_id=NEW.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remove alias owner link before merging activity';
      END IF;
      IF EXISTS (SELECT 1 FROM umk_businesses child
                 WHERE child.canonical_business_id=NEW.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Business with aliases cannot become an alias';
      END IF;
    END IF;
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (32,'canonical_alias_target_guard');
