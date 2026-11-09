<?php
// Jalankan hanya lewat CLI setelah migration 021. Aman diulang: batch yang
// sudah tersimpan dilewati, dan CSV kredensial dibangun ulang secara atomik.
if (PHP_SAPI !== 'cli') exit(1);
set_time_limit(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$root = dirname(__DIR__);
$key_path = $root.'/.secrets/umkm_bulk_key';
$csv_path = $root.'/docs/akun_umkm_excel_rahasia.csv';
$active = in_array('--active', $argv, TRUE);
$dry_run = in_array('--dry-run', $argv, TRUE);
$unknown = array_diff(array_slice($argv, 1), array('--active','--dry-run'));
if ($unknown) { fwrite(STDERR, "Opsi tidak dikenal. Gunakan --dry-run atau --active.\n"); exit(2); }

$db_password = trim((string) file_get_contents($root.'/.secrets/db_password'));
$db = new mysqli('localhost', 'umkm_app', $db_password, 'db_umkm');
$db->set_charset('utf8mb4');
$db_password = '';

$table = $db->query("SHOW TABLES LIKE 'iam_imported_business_accounts'")->fetch_row();
if (!$table) throw new RuntimeException('Jalankan migration 021 terlebih dahulu.');
$role = $db->query("SELECT id FROM iam_roles WHERE code='umkm' LIMIT 1")->fetch_assoc();
if (!$role) throw new RuntimeException('Role umkm tidak tersedia.');
$role_id = (int) $role['id'];

$target = (int) $db->query("SELECT COUNT(*) n FROM umk_businesses
    WHERE is_demo=0 AND import_fingerprint IS NOT NULL")->fetch_assoc()['n'];
$existing = (int) $db->query('SELECT COUNT(*) n FROM iam_imported_business_accounts')->fetch_assoc()['n'];
$collision = (int) $db->query("SELECT COUNT(*) n FROM umk_businesses b
    JOIN iam_users u ON u.email=CONCAT('umkm-',b.id,'@umkm.local')
    LEFT JOIN iam_imported_business_accounts a ON a.business_id=b.id
    WHERE b.is_demo=0 AND b.import_fingerprint IS NOT NULL AND a.business_id IS NULL")->fetch_assoc()['n'];
if ($collision) throw new RuntimeException('Ada username yang sudah dipakai tanpa kaitan profil: '.$collision);

echo 'Profil impor: '.$target.'; akun sudah disiapkan: '.$existing.'; mode: '.($active?'login aktif':'menunggu verifikasi').".\n";
if ($dry_run) exit(0);

if (!is_file($key_path))
{
    if ($existing) throw new RuntimeException('Kunci kredensial hilang; akun yang ada tidak boleh ditimpa.');
    $fd = fopen($key_path, 'x');
    if (!$fd) throw new RuntimeException('Kunci kredensial tidak dapat dibuat.');
    chmod($key_path, 0600);
    fwrite($fd, random_bytes(32));
    fclose($fd);
}
$key = file_get_contents($key_path);
if (strlen($key) !== 32) throw new RuntimeException('Kunci kredensial tidak valid.');

if ($active && $existing)
{
    // Mendukung kelanjutan aman jika sebagian batch sebelumnya dibuat pending.
    $db->begin_transaction();
    try
    {
        $db->query("UPDATE iam_users u JOIN iam_imported_business_accounts a ON a.user_id=u.id
            SET u.status='active' WHERE u.status='pending' AND a.provision_status='pending_owner'");
        $db->query("UPDATE iam_imported_business_accounts
            SET provision_status='login_active' WHERE provision_status='pending_owner'");
        $db->commit();
        echo 'Akun batch sebelumnya diaktifkan: '.$existing.".\n";
    }
    catch (Throwable $error)
    {
        $db->rollback();
        throw $error;
    }
}

function initial_password($business_id, $key)
{
    // Dua belas karakter tanpa simbol atau huruf/angka yang mudah tertukar.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $digest = hash_hmac('sha256', 'umkm-import:'.$business_id, $key, TRUE);
    $password = 'U';
    for ($i=0; $i<11; $i++) $password .= $alphabet[ord($digest[$i]) & 31];
    return $password;
}

$insert_user = $db->prepare('INSERT INTO iam_users (full_name,email,password_hash,status) VALUES (?,?,?,?)');
$insert_role = $db->prepare('INSERT INTO iam_user_roles (user_id,role_id) VALUES (?,?)');
$insert_link = $db->prepare('INSERT INTO iam_imported_business_accounts (business_id,user_id,provision_status) VALUES (?,?,?)');
$last_id = 0;
$created = 0;
while (TRUE)
{
    $batch = $db->query("SELECT b.id,b.name FROM umk_businesses b
        LEFT JOIN iam_imported_business_accounts a ON a.business_id=b.id
        WHERE b.is_demo=0 AND b.import_fingerprint IS NOT NULL
          AND b.id>".(int)$last_id." AND a.business_id IS NULL
        ORDER BY b.id LIMIT 500")->fetch_all(MYSQLI_ASSOC);
    if (!$batch) break;
    $db->begin_transaction();
    try
    {
        foreach ($batch as $business)
        {
            $business_id = (int) $business['id'];
            $name = mb_substr(trim($business['name']), 0, 160, 'UTF-8');
            $email = 'umkm-'.$business_id.'@umkm.local';
            $hash = password_hash(initial_password($business_id, $key), PASSWORD_BCRYPT, array('cost'=>8));
            $status = $active ? 'active' : 'pending';
            $insert_user->bind_param('ssss', $name, $email, $hash, $status);
            $insert_user->execute();
            $user_id = (int) $db->insert_id;
            $insert_role->bind_param('ii', $user_id, $role_id);
            $insert_role->execute();
            $provision_status = $active ? 'login_active' : 'pending_owner';
            $insert_link->bind_param('iis', $business_id, $user_id, $provision_status);
            $insert_link->execute();
            $last_id = $business_id;
            $created++;
        }
        $db->commit();
    }
    catch (Throwable $error)
    {
        $db->rollback();
        throw $error;
    }
    echo 'Akun baru: '.$created.' / '.($target-$existing)."\n";
    flush();
}

$actual = (int) $db->query('SELECT COUNT(*) n FROM iam_imported_business_accounts')->fetch_assoc()['n'];
if ($actual !== $target) throw new RuntimeException('Jumlah kaitan akun belum sama dengan jumlah profil impor.');

$tmp = $csv_path.'.tmp';
if (file_exists($tmp)) unlink($tmp);
$fd = fopen($tmp, 'x');
if (!$fd) throw new RuntimeException('CSV kredensial tidak dapat dibuat.');
chmod($tmp, 0600);
fputcsv($fd, array('business_id','nama_usaha','kecamatan','username','password_awal','status_login'), ',', '"', '');
$rows = $db->query("SELECT b.id,b.name,d.name district_name,u.email,u.status
    FROM iam_imported_business_accounts a
    JOIN umk_businesses b ON b.id=a.business_id
    JOIN iam_users u ON u.id=a.user_id
    LEFT JOIN ref_districts d ON d.id=b.district_id
    ORDER BY b.id");
while ($row = $rows->fetch_assoc())
{
    $safe_name = preg_match('/^[=+@\-\t\r]/u', $row['name']) ? "'".$row['name'] : $row['name'];
    fputcsv($fd, array($row['id'],$safe_name,$row['district_name'],$row['email'],
        initial_password((int)$row['id'],$key),$row['status']), ',', '"', '');
}
fclose($fd);
rename($tmp, $csv_path);
if (!chgrp($csv_path, 'point-break')) throw new RuntimeException('Grup editor CSV tidak dapat diatur.');
chmod($csv_path, 0640);
echo 'Selesai. Total akun: '.$actual.". CSV privat: docs/akun_umkm_excel_rahasia.csv\n";
