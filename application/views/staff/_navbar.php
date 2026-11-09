<?php
$this->load->helper('staff_navigation');
$navbar_groups = staff_nav_groups($user['role_code']);
$navbar_title = isset($page_title) && $page_title !== '' ? $page_title : staff_nav_page_title($this->uri->uri_string(), $navbar_groups);
$navbar_unread = isset($unread_count) ? (int)$unread_count : staff_nav_unread_count($this->db, $user['id']);
$navbar_initial = html_escape(mb_strtoupper(mb_substr($user['full_name'],0,1,'UTF-8'),'UTF-8'));
?>
<header class="admin-topbar" aria-label="Bilah atas pengelola">
    <div class="admin-desktop-heading"><span>Ruang kerja</span><strong><?= html_escape($navbar_title) ?></strong></div>
    <div class="admin-topbar-right"><span><?= html_escape(date('d M Y')) ?></span><a class="admin-topbar-public" href="<?= base_url() ?>" target="_blank" rel="noopener">Lihat situs ↗</a><a href="<?= site_url('pengelola/inbox') ?>" class="admin-topbar-inbox" aria-label="Inbox, <?= $navbar_unread ?> pesan belum dibaca">✉<?php if($navbar_unread): ?><b><?= $navbar_unread ?></b><?php endif; ?></a><span class="admin-topbar-avatar" aria-label="<?= html_escape($user['full_name']) ?>"><?= $navbar_initial ?></span></div>
</header>
<header class="admin-mobile-header" aria-label="Bilah atas pengelola pada ponsel"><button id="admin-menu-toggle" class="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Buka menu">☰</button><a href="<?= site_url('pengelola') ?>" class="admin-mobile-brand"><img src="<?= base_url('assets/logo-kabupaten-rembang.jpeg') ?>" alt=""><span>Rembang UMKM<small>Pengelola</small></span></a><a href="<?= site_url('pengelola/inbox') ?>" class="admin-mobile-inbox" aria-label="Inbox, <?= $navbar_unread ?> pesan belum dibaca">✉<?php if($navbar_unread): ?><b><?= $navbar_unread ?></b><?php endif; ?></a></header>
