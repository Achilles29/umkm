USE db_umkm;

ALTER TABLE umk_person_match_candidates
  ADD COLUMN IF NOT EXISTS decision_note VARCHAR(500) NULL AFTER status;

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (27,'person_match_decision_reason');
