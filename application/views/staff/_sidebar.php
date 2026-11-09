<?php
$this->load->helper('staff_navigation');
$groups = staff_nav_groups($user['role_code']);
$active = staff_nav_active($this->uri->uri_string(), $groups);
$work = staff_nav_work($user['role_code']);
$sidebar_unread = isset($unread_count) ? (int)$unread_count : staff_nav_unread_count($this->db, $user['id']);
?>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi pengelola">
    <a class="admin-sidebar-brand" href="<?= site_url('pengelola') ?>"><span><img src="<?= base_url('assets/logo-kabupaten-rembang.jpeg') ?>" alt=""></span><strong>Rembang UMKM<small>Panel Pengelola</small></strong></a>
    <div class="admin-sidebar-user"><span class="admin-avatar"><?= html_escape(mb_strtoupper(mb_substr($user['full_name'],0,1,'UTF-8'),'UTF-8')) ?></span><span><strong><?= html_escape($user['full_name']) ?></strong><small><?= html_escape($user['role_name']) ?></small></span></div>
    <nav class="admin-sidebar-nav" aria-label="Menu utama">
    <?php foreach ($groups as $group => $links): ?><div class="admin-nav-group"><span class="admin-nav-label"><?= html_escape($group) ?></span>
        <?php foreach ($links as $link): ?><a href="<?= site_url($link['url']) ?>" class="admin-nav-link <?= $active === $link['url'] ? 'is-active' : '' ?>" <?= $active === $link['url'] ? 'aria-current="page"' : '' ?>><span class="admin-nav-icon" aria-hidden="true"><?= $link['icon'] ?></span><span><?= html_escape($link['label']) ?></span><?php if ($link['url'] === 'pengelola/inbox' && $sidebar_unread): ?><b><?= $sidebar_unread ?></b><?php endif; ?></a><?php endforeach; ?>
    </div><?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-footer"><a href="<?= base_url() ?>" target="_blank" rel="noopener"><span aria-hidden="true">↗</span> Buka beranda</a><form method="post" action="<?= site_url('pengelola/keluar') ?>"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>"><button type="submit"><span aria-hidden="true">⇥</span> Keluar akun</button></form></div>
</aside>
<div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop" hidden></div>
<nav class="admin-mobile-bottom" aria-label="Navigasi cepat"><a href="<?= site_url('pengelola') ?>" class="<?= $active === 'pengelola' ? 'is-active' : '' ?>"><span aria-hidden="true">⌂</span><small>Beranda</small></a><a href="<?= site_url('pengelola/inbox') ?>" class="<?= $active === 'pengelola/inbox' ? 'is-active' : '' ?>"><span aria-hidden="true">✉</span><small>Inbox</small><?php if ($sidebar_unread): ?><b><?= $sidebar_unread ?></b><?php endif; ?></a><a href="<?= site_url($work['url']) ?>" class="<?= $active === $work['url'] ? 'is-active' : '' ?>"><span aria-hidden="true"><?= $work['icon'] ?></span><small><?= html_escape($work['label']) ?></small></a><button type="button" id="admin-bottom-menu" aria-controls="admin-sidebar" aria-expanded="false" class="<?= !in_array($active, array('pengelola', 'pengelola/inbox', $work['url']), TRUE) ? 'is-active' : '' ?>"><span aria-hidden="true">☰</span><small>Menu</small></button></nav>
<script defer src="<?= base_url('assets/staff-sidebar.js') ?>"></script>
