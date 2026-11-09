USE db_umkm;

-- Menjaga cakupan pelaku baru sebelum kegiatan pertamanya dicatat.
-- Data lama tetap NULL bila kecamatan asal identitas belum dapat dipastikan.
ALTER TABLE umk_people
  ADD COLUMN IF NOT EXISTS registration_district_id BIGINT UNSIGNED NULL AFTER phone_e164,
  ADD KEY IF NOT EXISTS idx_umk_people_registration_district (registration_district_id),
  ADD CONSTRAINT fk_umk_people_registration_district
    FOREIGN KEY (registration_district_id) REFERENCES ref_districts(id);

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (40,'person_registration_district');
