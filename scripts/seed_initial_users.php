<?php
// Jalankan sekali melalui CLI. Kata sandi dibuat acak dan disimpan di docs privat.
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$doc = $root.'/docs/akun_awal_rahasia.md';
if (file_exists($doc)) { fwrite(STDERR, "Dokumen akun awal sudah ada; akun tidak diubah.\n"); exit(1); }
$db = new mysqli('localhost', 'umkm_app', trim(file_get_contents($root.'/.secrets/db_password')), 'db_umkm');
$count = $db->query('SELECT COUNT(*) AS n FROM iam_users')->fetch_assoc();
if ((int) $count['n'] !== 0) { fwrite(STDERR, "Akun IAM sudah ada; seed awal dibatalkan.\n"); exit(1); }
$accounts = array(
    array('Superadmin Awal', 'superadmin@umkm.local', 'superadmin'),
    array('Admin Dinas Awal', 'dinas@umkm.local', 'admin_dinas'),
    array('Admin Kecamatan Rembang', 'kecamatan.rembang@umkm.local', 'admin_kecamatan'),
    array('Pelaku UMKM Contoh', 'umkm.contoh@umkm.local', 'umkm')
);
$text = "# Akun awal Rembang UMKM Hub\n\nDokumen ini privat, berada di luar web root, dan tidak masuk repository. Hanya untuk akses awal pengembangan.\n\nLogin pengelola: https://umkm.projectpintar.com/pengelola/masuk\n\n";
$passwords = array();
foreach ($accounts as $index => $account)
{
    $passwords[$index] = bin2hex(random_bytes(15));
    $text .= ($index + 1).". {$account[0]} ({$account[2]})\n   Email: {$account[1]}\n   Kata sandi: {$passwords[$index]}\n\n";
}
$text .= "Akun masyarakat memakai nomor HP pada /masuk dan tidak memiliki kata sandi. Akun pelaku UMKM contoh belum terhubung ke profil usaha mana pun.\n";
$temp = $doc.'.tmp';
$fd = fopen($temp, 'x');
if (!$fd) throw new RuntimeException('Tidak dapat menulis dokumen kredensial.');
chmod($temp, 0600);
fwrite($fd, $text);
fclose($fd);
try
{
    $db->begin_transaction();
    $insert = $db->prepare("INSERT INTO iam_users (full_name, email, password_hash, status) VALUES (?, ?, ?, 'active')");
    $role = $db->prepare('INSERT INTO iam_user_roles (user_id, role_id) VALUES (?, ?)');
    foreach ($accounts as $index => $account)
    {
        $hash = password_hash($passwords[$index], PASSWORD_DEFAULT);
        $insert->bind_param('sss', $account[0], $account[1], $hash);
        $insert->execute();
        $id = $db->insert_id;
        $r = $db->query("SELECT id FROM iam_roles WHERE code = '".$db->real_escape_string($account[2])."' LIMIT 1")->fetch_assoc();
        if (!$r) throw new RuntimeException('Role belum ada.');
        $role_id = (int) $r['id'];
        $role->bind_param('ii', $id, $role_id);
        $role->execute();
        if ($account[2] === 'admin_kecamatan')
        {
            $d = $db->query("SELECT id FROM ref_districts WHERE name = 'Rembang' LIMIT 1")->fetch_assoc();
            if (!$d) throw new RuntimeException('Master Kecamatan Rembang belum ada.');
            $district = (int) $d['id'];
            $scope = $db->prepare('INSERT INTO iam_district_roles (user_id, role_id, district_id) VALUES (?, ?, ?)');
            $scope->bind_param('iii', $id, $role_id, $district);
            $scope->execute();
        }
    }
    $db->commit();
    rename($temp, $doc);
    echo "Empat akun awal dibuat. Kredensial tersimpan di docs/akun_awal_rahasia.md\n";
}
catch (Throwable $error)
{
    $db->rollback();
    @unlink($temp);
    throw $error;
}
