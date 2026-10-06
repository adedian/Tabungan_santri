<?php
declare(strict_types=1);

/**
 * Menu sidebar. 'perm' = izin yang dibutuhkan, 'ready' = halaman sudah dibangun.
 * Item yang belum ready tampil redup "Segera" selama app.show_planned_menu = true
 * (hanya penanda progres pengembangan; matikan di Phase 17).
 */
return [
    ['label' => null, 'items' => [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'path' => '/dashboard', 'perm' => 'dashboard.view', 'ready' => true],
    ]],
    ['label' => 'Tabungan', 'items' => [
        ['label' => 'Tambah Tabungan',  'icon' => 'circle-plus', 'path' => '/tabungan/tambah', 'perm' => 'savings.create', 'ready' => true],
        ['label' => 'Riwayat Tabungan', 'icon' => 'history',     'path' => '/tabungan',        'perm' => 'savings.view',   'ready' => true],
    ]],
    ['label' => 'Santri', 'items' => [
        ['label' => 'Data Santri', 'icon' => 'users', 'path' => '/santri', 'perm' => 'students.view', 'ready' => true],
    ]],
    ['label' => 'Laporan', 'items' => [
        ['label' => 'Laporan Tabungan', 'icon' => 'file-chart', 'path' => '/laporan', 'perm' => 'reports.view', 'ready' => true],
    ]],
    ['label' => 'Sistem', 'items' => [
        ['label' => 'Pengguna',         'icon' => 'user-round',  'path' => '/pengguna',   'perm' => 'users.manage',    'ready' => true],
        ['label' => 'Pengaturan',       'icon' => 'settings',    'path' => '/pengaturan', 'perm' => 'settings.manage', 'ready' => true],
        ['label' => 'Audit Log',        'icon' => 'scroll-text', 'path' => '/audit',      'perm' => 'audit.view',      'ready' => true],
        ['label' => 'Panduan Komponen', 'icon' => 'palette',     'path' => '/styleguide', 'perm' => 'settings.manage', 'ready' => true],
    ]],
];
