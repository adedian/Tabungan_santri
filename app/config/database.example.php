<?php
declare(strict_types=1);

// Salin file ini menjadi database.php lalu sesuaikan. database.php TIDAK ikut git.
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'name'     => 'tabungan_santri',
    'user'     => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'timezone' => '+07:00', // harus sama dengan app.timezone (WIB)
];
