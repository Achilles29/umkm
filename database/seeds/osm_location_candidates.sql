USE db_umkm;

-- Nama usaha, kecamatan Lasem, dan alamat sumber yang menyebut Babagan cocok.
-- Titik OSM tetap calon lokasi internal sampai petugas/pemilik memeriksanya.
INSERT INTO gis_location_candidates
  (business_id, source_name, source_ref, source_url, place_name, latitude, longitude, match_status)
SELECT b.id, 'OpenStreetMap', 'way/1411144316',
       'https://www.openstreetmap.org/way/1411144316',
       'Rumah Batik Lasem Sekar Mulyo', -6.7006378, 111.4418273, 'candidate'
FROM umk_businesses AS b
JOIN ref_districts AS d ON d.id = b.district_id
WHERE b.name = 'Batik Tulis Lasem Sekar Mulyo'
  AND b.data_source = 'DAFTAR PROFILE UMKM UNGGULAN.xlsx'
  AND d.name = 'Lasem'
LIMIT 1
ON DUPLICATE KEY UPDATE detected_at = detected_at;
