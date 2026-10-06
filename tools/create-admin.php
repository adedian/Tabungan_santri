<?php
declare(strict_types=1);

/**
 * Membuat akun Super Admin pertama (tanpa seed.sql). Hanya CLI.
 *   php tools/create-admin.php [username] [nama lengkap]
 * Kata sandi ditanyakan di terminal (tidak pernah lewat argumen/riwayat perintah).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

Config::load(BASE_PATH . '/app/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Jakarta'));

function ask(string $label, ?string $default = null): string
{
    echo $label . ($default !== null ? " [$default]" : '') . ': ';
    $v = trim((string) fgets(STDIN));
    return $v !== '' ? $v : (string) $default;
}

function askPassword(string $label): string
{
    echo $label . ': ';
    // Sembunyikan ketikan bila memungkinkan (Linux/macOS); di Windows ketikan terlihat.
    $hide = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($hide) { shell_exec('stty -echo'); }
    $v = rtrim((string) fgets(STDIN), "\r\n");
    if ($hide) { shell_exec('stty echo'); echo "\n"; }
    return $v;
}

$username = strtolower($argv[1] ?? ask('Username', 'superadmin'));
$name     = $argv[2] ?? ask('Nama lengkap', 'Super Admin');

if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Username 3–50 karakter: huruf kecil, angka, titik, garis bawah, strip.\n");
    exit(1);
}
if ($name === '' || mb_strlen($name) > 100) {
    fwrite(STDERR, "Nama wajib diisi (maks. 100 karakter).\n");
    exit(1);
}

$password = askPassword('Kata sandi (min. 8 karakter)');
if (strlen($password) < 8 || strlen($password) > 72 || $password === $username) {
    fwrite(STDERR, "Kata sandi 8–72 byte dan tidak boleh sama dengan username.\n");
    exit(1);
}

if (Database::fetchValue('SELECT COUNT(*) FROM users WHERE username = ?', [$username]) > 0) {
    fwrite(STDERR, "Username \"$username\" sudah dipakai.\n");
    exit(1);
}

Database::execute(
    "INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, NULL, ?, 'super_admin', 'aktif')",
    [$name, $username, password_hash($password, PASSWORD_DEFAULT)]
);
echo "Super Admin \"$username\" dibuat. Masuk lewat halaman /login.\n";
