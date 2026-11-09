<?php $layout_title = isset($page_title) && $page_title !== '' ? $page_title : 'Pengelola'; ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= html_escape($layout_title) ?> — Rembang UMKM</title>
    <link rel="icon" href="<?= base_url('assets/favicon.ico?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff.css?v=8') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-page-layout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/events.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/events-studio.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/events-pagination.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-navbar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-admin-layout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-members.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-list-system.css?v=2') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-unified.css?v=3') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/leaflet/leaflet.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/leaflet/markercluster/MarkerCluster.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-master-gis.css?v=2') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-master-gis-extra.css?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/product-photo-picker.css?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-qr.css?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-activities.css?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/staff-registry-detail.css?v=1') ?>">
</head>
<body class="staff-body admin-dashboard-body staff-unified-body">
<div class="admin-shell">
    <?php $this->load->view('staff/_sidebar',array('user'=>$user,'unread_count'=>isset($unread_count)?$unread_count:NULL)); ?>
    <div class="admin-workspace staff-admin-workspace">
        <?php $this->load->view('staff/_navbar',array('user'=>$user,'page_title'=>$layout_title,'unread_count'=>isset($unread_count)?$unread_count:NULL)); ?>
        <main class="staff-content-main staff-unified-main" id="main-content">
