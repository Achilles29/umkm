USE db_umkm;

-- Berkas profil unggulan dinyatakan sudah boleh mempunyai toko oleh pemilik
-- proyek. NIB yang tidak tersedia pada berkas tetap ditandai sebagai asumsi,
-- bukan sebagai nomor atau kunjungan lapangan yang sungguh telah diverifikasi.
ALTER TABLE umk_business_licenses
  ADD COLUMN IF NOT EXISTS evidence_basis VARCHAR(32) NOT NULL DEFAULT 'document_checked';
ALTER TABLE tko_verifications
  ADD COLUMN IF NOT EXISTS evidence_basis VARCHAR(32) NOT NULL DEFAULT 'field_visit';

DROP TRIGGER IF EXISTS tko_stores_before_update;
DELIMITER $$
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
      WHERE l.business_id = NEW.business_id AND l.license_type = 'NIB'
        AND ((l.status = 'verified' AND l.verified_at IS NOT NULL AND l.verified_by IS NOT NULL)
          OR (l.status = 'assumed' AND l.evidence_basis = 'profile_workbook'
              AND l.license_number LIKE 'DUMMY-NIB-%'))
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active store requires NIB record';
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM tko_verifications AS v
      WHERE v.store_id = NEW.id
        AND v.id = (SELECT MAX(v2.id) FROM tko_verifications AS v2 WHERE v2.store_id = NEW.id)
        AND v.decision = 'approved' AND v.decided_at IS NOT NULL
        AND ((v.evidence_basis = 'field_visit' AND v.visit_date IS NOT NULL AND v.decided_by IS NOT NULL)
          OR v.evidence_basis = 'profile_workbook')
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active store requires approval record';
    END IF;
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (25,'store_source_assumptions');
