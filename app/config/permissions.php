<?php
declare(strict_types=1);

/**
 * Peta izin: permission => daftar role. 'super_admin' selalu lolos (lihat Auth::can()).
 * Nama izin sengaja memakai titik, jadi baca seluruh array lewat Config::get('permissions').
 */
$all   = ['super_admin', 'admin', 'operator'];
$admin = ['super_admin', 'admin'];
$super = ['super_admin'];

return [
    'dashboard.view'  => $all,
    'savings.view'    => $all,
    'savings.create'  => $all,
    'savings.edit'    => $admin,
    'savings.delete'  => $admin,
    'students.view'   => $all,
    'students.manage' => $admin,
    'reports.view'    => $all,
    'reports.export'  => $admin,
    'audit.view'      => $admin,
    'promotions.manage' => $admin,
    'alumni.view'     => $all,
    'users.manage'    => $super,
    'settings.manage' => $super,
];
