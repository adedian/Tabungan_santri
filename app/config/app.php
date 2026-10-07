<?php
declare(strict_types=1);

return [
    'name'      => 'Tabungan Santri',
    // Pembuat sistem: tampil di footer, halaman login, dan PDF laporan.
    'author'    => 'Ade Dian Sukmana',
    // JANGAN true di production: menampilkan detail error ke pengguna.
    'debug'     => false,
    'timezone'  => 'Asia/Jakarta',
    // null = deteksi otomatis dari URL. Isi mis. '/Tabungan_santri' bila perlu dipaksa.
    'base_path' => null,
    'session'   => [
        'name'         => 'TABSESS',
        'idle_timeout' => 7200, // detik tanpa aktivitas sebelum sesi berakhir
    ],
    // Tampilkan menu yang belum dibangun sebagai "Segera" (penanda progres pengembangan; false = final).
    'show_planned_menu' => false,
    // Interval polling realtime di browser (ms).
    'sync_interval' => 5000,
];
