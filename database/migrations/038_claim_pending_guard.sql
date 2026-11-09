USE db_umkm;
ALTER TABLE umk_business_claims
  ADD COLUMN pending_user_business VARCHAR(60)
    GENERATED ALWAYS AS (CASE WHEN status='submitted'
      THEN CONCAT(business_id,':',user_id) ELSE NULL END) PERSISTENT,
  ADD UNIQUE KEY uq_umk_pending_claim_pair (pending_user_business);
INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (38,'claim_pending_guard');
