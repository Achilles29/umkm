USE db_umkm;

INSERT INTO mkt_qr_placements (code, label, placement_kind, location_name, destination_path, is_active)
VALUES ('umum', 'QR promosi umum Rembang UMKM Hub', 'general', 'Materi promosi umum', '/masuk', 1)
ON DUPLICATE KEY UPDATE label = VALUES(label), destination_path = VALUES(destination_path);
