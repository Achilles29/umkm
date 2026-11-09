USE db_umkm;

-- Alias kegiatan tetap disimpan untuk akun lama, tetapi tidak mempunyai
-- relasi pemilik sendiri. Pemilik selalu dibaca melalui kegiatan kanonis.
CREATE TEMPORARY TABLE tmp_alias_only_people AS
SELECT DISTINCT p.id
FROM umk_people p
JOIN umk_business_owners o ON o.person_id=p.id
JOIN umk_businesses b ON b.id=o.business_id
WHERE b.canonical_business_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM umk_business_owners current_owner
    JOIN umk_businesses current_business ON current_business.id=current_owner.business_id
    WHERE current_owner.person_id=p.id AND current_business.canonical_business_id IS NULL
  );

START TRANSACTION;
DELETE o FROM umk_business_owners o
JOIN umk_businesses b ON b.id=o.business_id
WHERE b.canonical_business_id IS NOT NULL;

DELETE p FROM umk_people p
JOIN tmp_alias_only_people tmp ON tmp.id=p.id
WHERE p.nik_fingerprint IS NULL AND p.phone_e164 IS NULL
  AND NOT EXISTS (SELECT 1 FROM umk_business_owners o WHERE o.person_id=p.id)
  AND NOT EXISTS (SELECT 1 FROM umk_person_match_candidates c WHERE c.person_id=p.id);
COMMIT;
DROP TEMPORARY TABLE tmp_alias_only_people;

-- NULL pada kolom terhitung membuat banyak kegiatan boleh tanpa pemilik utama,
-- tetapi paling banyak satu baris boleh bertanda utama pada tiap kegiatan.
ALTER TABLE umk_business_owners
  ADD COLUMN IF NOT EXISTS primary_business_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN is_primary=1 THEN business_id ELSE NULL END) PERSISTENT,
  ADD UNIQUE KEY IF NOT EXISTS uq_umk_one_primary_owner (primary_business_id);

DROP TRIGGER IF EXISTS umk_owner_before_insert;
DROP TRIGGER IF EXISTS umk_owner_before_update;
DROP TRIGGER IF EXISTS umk_business_before_alias;
DROP TRIGGER IF EXISTS tko_stores_before_insert;
DROP TRIGGER IF EXISTS tko_stores_before_update;
DELIMITER $$
CREATE TRIGGER umk_owner_before_insert
BEFORE INSERT ON umk_business_owners
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM umk_businesses b
             WHERE b.id=NEW.business_id AND b.canonical_business_id IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Alias business cannot have a separate owner';
  END IF;
END$$

CREATE TRIGGER umk_owner_before_update
BEFORE UPDATE ON umk_business_owners
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM umk_businesses b
             WHERE b.id=NEW.business_id AND b.canonical_business_id IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Alias business cannot have a separate owner';
  END IF;
END$$

CREATE TRIGGER umk_business_before_alias
BEFORE UPDATE ON umk_businesses
FOR EACH ROW
BEGIN
  IF NEW.canonical_business_id IS NOT NULL AND
     (OLD.canonical_business_id IS NULL OR NEW.canonical_business_id<>OLD.canonical_business_id) THEN
    IF EXISTS (SELECT 1 FROM tko_stores s WHERE s.business_id=NEW.id) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store business cannot become an alias';
    END IF;
    IF EXISTS (SELECT 1 FROM umk_business_owners o WHERE o.business_id=NEW.id) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remove alias owner link before merging activity';
    END IF;
  END IF;
END$$

CREATE TRIGGER tko_stores_before_insert
BEFORE INSERT ON tko_stores
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM umk_businesses b
             WHERE b.id=NEW.business_id AND b.canonical_business_id IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store must use canonical business';
  END IF;
  IF NEW.status='active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Create store as draft, then verify before activation';
  END IF;
END$$

CREATE TRIGGER tko_stores_before_update
BEFORE UPDATE ON tko_stores
FOR EACH ROW
BEGIN
  IF NEW.business_id<>OLD.business_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store business cannot be changed';
  END IF;
  IF EXISTS (SELECT 1 FROM umk_businesses b
             WHERE b.id=NEW.business_id AND b.canonical_business_id IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Store must use canonical business';
  END IF;
  IF NEW.status='active' AND OLD.status<>'active' THEN
    IF NEW.whatsapp_e164 IS NULL OR NEW.whatsapp_e164='' OR NEW.published_at IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active store requires contact and publication date';
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM umk_business_licenses l
      WHERE l.business_id=NEW.business_id AND l.license_type='NIB'
        AND ((l.status='verified' AND l.verified_at IS NOT NULL AND l.verified_by IS NOT NULL)
          OR (l.status='assumed' AND l.evidence_basis='profile_workbook'
              AND l.license_number LIKE 'DUMMY-NIB-%'))
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active store requires NIB record';
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM tko_verifications v
      WHERE v.store_id=NEW.id
        AND v.id=(SELECT MAX(v2.id) FROM tko_verifications v2 WHERE v2.store_id=NEW.id)
        AND v.decision='approved' AND v.decided_at IS NOT NULL
        AND ((v.evidence_basis='field_visit' AND v.visit_date IS NOT NULL AND v.decided_by IS NOT NULL)
          OR v.evidence_basis='profile_workbook')
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active store requires approval record';
    END IF;
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (31,'owner_and_store_relation_integrity');
