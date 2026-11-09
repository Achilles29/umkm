<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('staff_nav_groups'))
{
    function staff_nav_groups($role)
    {
        $admin = in_array($role, array('superadmin', 'admin_dinas'), TRUE);
        $district_admin = $role === 'admin_kecamatan';
        $merchant = $role === 'umkm';
        $groups = array(
            'Umum' => array(
                array('label' => 'Dashboard', 'url' => 'pengelola', 'icon' => '⌂'),
                array('label' => 'Inbox', 'url' => 'pengelola/inbox', 'icon' => '✉'),
            ),
        );
        if ($admin)
            $groups['Kegiatan'] = array(array('label' => 'Kelola kegiatan', 'url' => 'pengelola/kegiatan', 'icon' => '▣'));
        if ($admin || $district_admin || $merchant)
            $groups['Etalase'] = array(
                array('label' => 'Profil toko', 'url' => 'pengelola/toko', 'icon' => '◈'),
                array('label' => 'Produk', 'url' => 'pengelola/produk', 'icon' => '▤'),
                array('label' => 'Kategori toko', 'url' => 'pengelola/kategori', 'icon' => '◇'),
            );
        if ($admin)
            $groups['Etalase'][]=array('label'=>'Kode QR','url'=>'pengelola/qr','icon'=>'▣');
        if ($admin)
            $groups['Etalase'][]=array('label'=>'Peninjauan produk','url'=>'pengelola/tinjauan-produk','icon'=>'◉');
        if ($merchant)
            array_unshift($groups['Etalase'], array('label' => 'Profil usaha saya', 'url' => 'pengelola/usaha-saya', 'icon' => '▦'));
        if ($merchant)
            $groups['Etalase'][]=array('label'=>'Klaim usaha','url'=>'pengelola/klaim-usaha','icon'=>'◇');
        if ($merchant || $admin)
            $groups['Etalase'][]=array('label'=>'Pengajuan lokasi','url'=>'pengelola/pengajuan-lokasi','icon'=>'⌖');
        $groups['Layanan']=array(array('label'=>'Permohonan layanan','url'=>'pengelola/layanan','icon'=>'✉'));
        if ($admin)
            $groups['Administrasi'] = array(
                array('label' => 'Member', 'url' => 'pengelola/anggota', 'icon' => '♙'),
                array('label' => 'Pelaku UMKM', 'url' => 'pengelola/pelaku', 'icon' => '♙'),
                array('label' => 'Kegiatan usaha', 'url' => 'pengelola/registri', 'icon' => '▦'),
                array('label' => 'Peta persebaran', 'url' => 'pengelola/peta-umkm', 'icon' => '⌖'),
                array('label' => 'Master data', 'url' => 'pengelola/master', 'icon' => '▥'),
                array('label' => 'Akses toko', 'url' => 'pengelola/akses-toko', 'icon' => '◎'),
                array('label' => 'Lokasi toko', 'url' => 'pengelola/lokasi', 'icon' => '⌖'),
                array('label' => 'Banner etalase', 'url' => 'pengelola/banner', 'icon' => '▧'),
                array('label' => 'Kontak', 'url' => 'pengelola/kontak', 'icon' => '↗'),
                array('label' => 'Wilayah', 'url' => 'pengelola/wilayah', 'icon' => '◫'),
                array('label' => 'Klaim usaha', 'url' => 'pengelola/klaim-usaha', 'icon' => '◇'),
                array('label' => 'Pemulihan akun', 'url' => 'pengelola/pemulihan-akun', 'icon' => '◎'),
            );
        elseif ($role === 'admin_kecamatan')
            $groups['Wilayah tugas'] = array(
                array('label' => 'Pelaku UMKM', 'url' => 'pengelola/pelaku', 'icon' => '♙'),
                array('label' => 'Kegiatan usaha', 'url' => 'pengelola/registri', 'icon' => '▦'),
                array('label' => 'Peta persebaran', 'url' => 'pengelola/peta-umkm', 'icon' => '⌖'),
                array('label' => 'Master data', 'url' => 'pengelola/master', 'icon' => '▥'),
            );
        return $groups;
    }
}

if (!function_exists('staff_nav_active'))
{
    function staff_nav_active($path, $groups)
    {
        if ($path === 'pengelola/produk-baru') return 'pengelola/produk';
        if (strpos($path, 'pengelola/minat/') === 0)
            return 'pengelola/inbox';
        $active = 'pengelola';
        foreach ($groups as $links) foreach ($links as $link)
        {
            $url = $link['url'];
            if ($url !== 'pengelola' && ($path === $url || strpos($path, $url.'/') === 0) && strlen($url) > strlen($active))
                $active = $url;
        }
        return $active;
    }
}

if (!function_exists('staff_nav_work'))
{
    function staff_nav_work($role)
    {
        if (in_array($role, array('superadmin', 'admin_dinas'), TRUE))
            return array('url' => 'pengelola/kegiatan', 'label' => 'Kegiatan', 'icon' => '▣');
        if ($role === 'umkm')
            return array('url' => 'pengelola/produk', 'label' => 'Produk', 'icon' => '▤');
        return array('url' => 'pengelola/registri', 'label' => 'Registri', 'icon' => '▦');
    }
}

if (!function_exists('staff_nav_page_title'))
{
    function staff_nav_page_title($path, $groups)
    {
        $special = array(
            'pengelola/produk-baru' => 'Tambah produk',
        );
        if (isset($special[$path])) return $special[$path];
        if (preg_match('#^pengelola/produk/[0-9]+$#', $path)) return 'Atur produk';
        if (preg_match('#^pengelola/toko/[0-9]+$#', $path)) return 'Profil toko';
        if (preg_match('#^pengelola/registri/[0-9]+#', $path)) return 'Profil usaha';
        if (preg_match('#^pengelola/pelaku/[0-9]+$#', $path)) return 'Profil pelaku UMKM';
        if (preg_match('#^pengelola/lokasi/[0-9]+$#', $path)) return 'Atur lokasi';
        if (preg_match('#^pengelola/anggota/[0-9]+$#', $path)) return 'Detail member';
        if ($path === 'pengelola/anggota') return 'Database member';
        if (preg_match('#^pengelola/minat/[0-9]+$#', $path)) return 'Minat WhatsApp';
        if (preg_match('#^pengelola/kegiatan/[0-9]+/peserta$#', $path)) return 'Peserta kegiatan';
        if (preg_match('#^pengelola/kegiatan/[0-9]+/studio$#', $path)) return 'Studio kegiatan';
        if (preg_match('#^pengelola/kegiatan/[0-9]+/ubah$#', $path)) return 'Ubah kegiatan';
        if ($path === 'pengelola/kegiatan/baru') return 'Buat kegiatan';
        $active = staff_nav_active($path, $groups);
        foreach ($groups as $links) foreach ($links as $link)
            if ($link['url'] === $active) return $link['label'];
        return 'Pengelola';
    }
}

if (!function_exists('staff_nav_unread_count'))
{
    function staff_nav_unread_count($db, $user_id)
    {
        static $cache = array();
        $user_id = (int)$user_id;
        if (!isset($cache[$user_id]))
            $cache[$user_id] = (int)$db->query('SELECT COUNT(*) n FROM ntf_inbox_items WHERE user_id=? AND read_at IS NULL', array($user_id))->row('n');
        return $cache[$user_id];
    }
}
