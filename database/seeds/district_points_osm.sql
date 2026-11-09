-- Titik representatif area kecamatan dari OpenStreetMap Nominatim, diambil 27 Oktober 2026.
-- Bukan koordinat toko atau centroid resmi batas administrasi.
USE db_umkm;
INSERT INTO gis_district_points (district_id,latitude,longitude,source_url,source_label) VALUES
((SELECT id FROM ref_districts WHERE name='Sumber' LIMIT 1),-6.7975379,111.2564957,'https://www.openstreetmap.org/node/10951702779','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Bulu' LIMIT 1),-6.8600068,111.4074397,'https://www.openstreetmap.org/node/10951704912','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Gunem' LIMIT 1),-6.8098439,111.4692582,'https://www.openstreetmap.org/node/1308640995','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Sale' LIMIT 1),-6.8634553,111.6038984,'https://www.openstreetmap.org/node/1308686929','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Sarang' LIMIT 1),-6.7241276,111.6550559,'https://www.openstreetmap.org/node/12396104465','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Sedan' LIMIT 1),-6.773599,111.5634209,'https://www.openstreetmap.org/node/10951704916','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Pamotan' LIMIT 1),-6.7571624,111.4883137,'https://www.openstreetmap.org/node/10951704918','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Sulang' LIMIT 1),-6.807291,111.3805693,'https://www.openstreetmap.org/node/10951704913','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Kaliori' LIMIT 1),-6.702239,111.2466627,'https://www.openstreetmap.org/node/10951702778','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Rembang' LIMIT 1),-6.7056856,111.3486234,'https://www.openstreetmap.org/node/5645200572','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Pancur' LIMIT 1),-6.7221217,111.4682883,'https://www.openstreetmap.org/node/10951704915','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Kragan' LIMIT 1),-6.6967976,111.6208441,'https://www.openstreetmap.org/node/1308656871','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Sluke' LIMIT 1),-6.6376916,111.5027118,'https://www.openstreetmap.org/node/11052308555','OpenStreetMap Nominatim'),
((SELECT id FROM ref_districts WHERE name='Lasem' LIMIT 1),-6.6921161,111.451562,'https://www.openstreetmap.org/node/10951704914','OpenStreetMap Nominatim')
ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),source_url=VALUES(source_url),source_label=VALUES(source_label),checked_at=NOW();
