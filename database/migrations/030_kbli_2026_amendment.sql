USE db_umkm;

-- Peraturan BPS Nomor 6 Tahun 2026 mengubah lampiran KBLI 2025.
-- Tiga judul dan uraian berikut diselaraskan dengan lampiran terbaru.
UPDATE ref_kbli_versions
SET legal_basis='Peraturan BPS Nomor 7 Tahun 2025 sebagaimana diubah dengan Peraturan BPS Nomor 6 Tahun 2026'
WHERE version_label='KBLI 2025';

UPDATE ref_kbli_codes c JOIN ref_kbli_versions v ON v.id=c.version_id
SET c.title='INDUSTRI BATERAI',
    c.description='Pembuatan baterai primer dan isi ulang, termasuk baterai perangkat bergerak dan sistem penyimpanan energi sampai dengan 30 kWh.'
WHERE v.version_label='KBLI 2025' AND c.code='27201';

UPDATE ref_kbli_codes c JOIN ref_kbli_versions v ON v.id=c.version_id
SET c.title='INDUSTRI BATERAI KENDARAAN DAN BATTERY ENERGY STORAGE SYSTEM (BESS) KAPASITAS BESAR',
    c.description='Pembuatan baterai kendaraan listrik dan sistem penyimpanan energi berbasis baterai dengan kapasitas di atas 30 kWh.'
WHERE v.version_label='KBLI 2025' AND c.code='27203';

UPDATE ref_kbli_codes c JOIN ref_kbli_versions v ON v.id=c.version_id
SET c.title='PENGELOLAAN KAWASAN INDUSTRI',
    c.description='Pengelolaan kawasan tempat pemusatan kegiatan industri yang dilengkapi sarana dan prasarana penunjang.'
WHERE v.version_label='KBLI 2025' AND c.code='68122';

INSERT IGNORE INTO ops_schema_migrations (version_no,name)
VALUES (30,'kbli_2026_amendment');
