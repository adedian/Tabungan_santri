<?php
declare(strict_types=1);

/**
 * PEMASANG SEKALI PAKAI untuk hosting tanpa SSH (disalin ke public/install.php oleh tools/package-hosting.php).
 * Membuka https://DOMAIN/install.php → isi kode pemasangan + data database + akun admin → tabel dibuat, app/config/database.php ditulis,
 * storage/installed.lock dibuat. Setelah itu halaman ini menolak semua permintaan. HAPUS berkas ini setelah selesai.
 *
 * Keamanan: butuh kode pemasangan (hash-nya tertanam di berkas, kode asli hanya ada pada pemilik paket); gagal berulang dikunci;
 * menolak bila sudah terpasang (installed.lock) atau bila tabel pengguna sudah berisi data; tidak pernah menimpa data.
 */

const SETUP_KEY_SHA256 = '__SETUP_KEY_SHA256__';
const MAX_FAILS = 8;

$base = dirname(__DIR__);                   // folder yang berisi app/, storage/, public/
$lock = $base . '/storage/installed.lock';
$fails = $base . '/storage/install-fails.json';
// Skema: paket hosting membawa app/install/schema.sql; bila seluruh proyek yang terunggah, pakai database/schema.sql (dibersihkan di bawah)
$schemaFile = is_file($base . '/app/install/schema.sql') ? $base . '/app/install/schema.sql' : $base . '/database/schema.sql';

header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'");

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function page(string $title, string $body, int $code = 200)
{
    http_response_code($code);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' — Pemasangan Tabungan Santri</title><style>
body{margin:0;background:#f7f8f6;color:#1f2937;font:15px/1.55 system-ui,Segoe UI,Roboto,sans-serif}main{max-width:34rem;margin:0 auto;padding:24px 16px 64px}
h1{font-size:1.5rem;color:#14532d;margin:8px 0 4px}p.lead{color:#5b6862;margin:0 0 18px}.card{background:#fff;border:1px solid #e3e8e3;border-radius:12px;padding:20px}
label{display:block;font-weight:600;margin:14px 0 4px}input{width:100%;box-sizing:border-box;min-height:44px;padding:0 12px;border:1px solid #c6d0c8;border-radius:8px;font:inherit}
input:focus{outline:2px solid #166534;outline-offset:1px}small{color:#5b6862;display:block;margin-top:4px}button{margin-top:20px;width:100%;min-height:46px;border:0;border-radius:8px;background:#166534;color:#fff;font:600 1rem system-ui;cursor:pointer}
.err{background:#fef3f2;border:1px solid #fac9c4;color:#b42318;border-radius:8px;padding:10px 12px;margin-bottom:12px}.ok{background:#e6f2ea;border:1px solid #bfdcc9;color:#046c4e;border-radius:8px;padding:10px 12px}
code,pre{background:#f1f4f1;border-radius:6px;padding:2px 6px;overflow-wrap:anywhere;white-space:pre-wrap}h2{font-size:1.05rem;margin:22px 0 0;color:#14532d}
</style></head><body><main><h1>Pemasangan Tabungan Santri</h1>' . $body . '</main></body></html>';
    exit;
}

if (is_file($lock)) {
    page('Sudah terpasang', '<p class="lead">Aplikasi sudah terpasang.</p><div class="card"><p>Halaman ini sudah dikunci. Silakan <a href="./">masuk ke aplikasi</a>, lalu <b>hapus berkas <code>public/install.php</code></b> dari hosting.</p></div>', 410);
}
if (SETUP_KEY_SHA256 === '__SETUP_KEY' . '_SHA256__') {
    page('Tidak valid', '<p class="lead">Berkas ini belum disiapkan oleh pembuat paket.</p>', 500);
}
if (!is_file($schemaFile)) {
    page('Berkas kurang', '<div class="err">Skema database tidak ditemukan (<code>app/install/schema.sql</code> atau <code>database/schema.sql</code>). Pastikan berkas <code>install.php</code> berada di dalam folder <code>public</code> aplikasi dan folder aplikasi lainnya sudah terunggah.</div>', 500);
}
if (!extension_loaded('pdo_mysql')) {
    page('PHP', '<div class="err">Ekstensi PDO MySQL tidak aktif di hosting ini.</div>', 500);
}
if (PHP_VERSION_ID < 80000) {
    page('PHP', '<div class="err">Butuh PHP 8.0 atau lebih baru (sekarang ' . h(PHP_VERSION) . '). Ubah versi PHP di panel hosting.</div>', 500);
}
if (!is_writable($base . '/storage') || !is_writable($base . '/app/config')) {
    page('Izin folder', '<div class="err">Folder <code>storage/</code> dan <code>app/config/</code> harus dapat ditulis oleh PHP. Atur izin folder (755/775) lewat FTP, lalu muat ulang.</div>', 500);
}

$failCount = is_file($fails) ? (int) (json_decode((string) file_get_contents($fails), true)['n'] ?? 0) : 0;
$v = ['key' => '', 'host' => '', 'name' => '', 'user' => '', 'pass' => '', 'aname' => 'Administrator', 'auser' => 'admin', 'apass' => '', 'apass2' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) { $v[$k] = trim((string) ($_POST[$k] ?? '')); }
    $v['pass'] = (string) ($_POST['pass'] ?? '');    // jangan trim kata sandi
    $v['apass'] = (string) ($_POST['apass'] ?? '');
    $v['apass2'] = (string) ($_POST['apass2'] ?? '');

    if ($failCount >= MAX_FAILS) {
        page('Terkunci', '<div class="err">Terlalu banyak kode salah. Hapus berkas <code>storage/install-fails.json</code> lewat FTP untuk membuka kunci.</div>', 429);
    }
    if (!hash_equals(SETUP_KEY_SHA256, hash('sha256', $v['key']))) {
        file_put_contents($fails, json_encode(['n' => $failCount + 1]));
        sleep(2);
        $error = 'Kode pemasangan salah.';
    } elseif ($v['host'] === '' || $v['name'] === '' || $v['user'] === '') {
        $error = 'Host, nama database, dan pengguna database wajib diisi.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $v['auser'])) {
        $error = 'Username admin 3–50 karakter: huruf kecil, angka, titik, garis bawah, atau strip.';
    } elseif ($v['aname'] === '' || mb_strlen($v['aname']) > 100) {
        $error = 'Nama admin wajib diisi (maks. 100 karakter).';
    } elseif (strlen($v['apass']) < 8 || strlen($v['apass']) > 72 || $v['apass'] === $v['auser']) {
        $error = 'Kata sandi admin 8–72 karakter dan tidak boleh sama dengan username.';
    } elseif ($v['apass'] !== $v['apass2']) {
        $error = 'Konfirmasi kata sandi admin tidak sama.';
    } else {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $v['host'], $v['name']), $v['user'], $v['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 10,
            ]);
        } catch (Throwable $e) {
            $pdo = null;
            $error = 'Tidak dapat terhubung ke database. Periksa host (bentuknya sqlXXX.infinityfree.com, bukan localhost), nama database, pengguna, dan kata sandi. Pesan server: ' . $e->getMessage();
        }

        if ($pdo !== null) {
            try {
                $has = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
                if ($has && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                    throw new RuntimeException('Database ini sudah berisi pengguna. Pemasangan dibatalkan agar data tidak tertimpa.');
                }
                $sql = (string) file_get_contents($schemaFile);
                $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
                $sql = preg_replace('/^\s*CREATE DATABASE[^;]*;/mi', '', $sql) ?? $sql;   // database dibuat lewat panel hosting
                $sql = preg_replace('/^\s*USE\s+`?[A-Za-z0-9_]+`?\s*;/mi', '', $sql) ?? $sql;
                foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $i => $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        throw new RuntimeException('Gagal membuat tabel pada pernyataan #' . ($i + 1) . ': ' . $e->getMessage()
                            . ' (versi MySQL hosting mungkin terlalu lama; dibutuhkan MariaDB 10.2+ / MySQL 8).');
                    }
                }
                $st = $pdo->prepare("INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, NULL, ?, 'super_admin', 'aktif')");
                $st->execute([$v['aname'], $v['auser'], password_hash($v['apass'], PASSWORD_DEFAULT)]);

                $cfg = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export([
                    'host' => $v['host'], 'port' => 3306, 'name' => $v['name'], 'user' => $v['user'], 'password' => $v['pass'],
                    'charset' => 'utf8mb4', 'timezone' => '+07:00',
                ], true) . ";\n";
                if (@file_put_contents($base . '/app/config/database.php', $cfg) === false) {
                    throw new RuntimeException('Tabel sudah dibuat, tetapi app/config/database.php tidak dapat ditulis. Atur izin folder app/config lalu coba lagi (tabel yang sudah ada tidak akan ditimpa).');
                }
                @chmod($base . '/app/config/database.php', 0640);
                file_put_contents($lock, date('c') . "\n");
                @unlink($fails);
                page('Selesai', '<p class="lead">Pemasangan berhasil.</p><div class="ok">Tabel dibuat dan akun <b>' . h($v['auser']) . '</b> (Super Admin) siap dipakai.</div>'
                    . '<div class="card" style="margin-top:14px"><p><a href="./login"><b>Masuk ke aplikasi →</b></a></p>'
                    . '<h2>Langkah terakhir</h2><p>Hapus berkas <code>public/install.php</code> dari hosting lewat FTP. Halaman ini sudah dikunci, tetapi berkasnya tidak perlu lagi.</p></div>');
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$in = static fn (string $name, string $label, string $val, string $type = 'text', string $hint = '', string $extra = ''): string =>
    '<label for="' . $name . '">' . h($label) . '</label><input id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . ($type === 'password' ? '' : h($val)) . '" ' . $extra . ' required>'
    . ($hint !== '' ? '<small>' . h($hint) . '</small>' : '');

page('Formulir',
    '<p class="lead">Isi sekali saja. Data database Anda diketik di halaman ini (di hosting Anda sendiri) dan disimpan ke <code>app/config/database.php</code>.</p>'
    . ($error !== '' ? '<div class="err">' . h($error) . '</div>' : '')
    . '<form class="card" method="post" autocomplete="off">'
    . $in('key', 'Kode pemasangan', $v['key'], 'password', 'Kode dari pembuat paket (bukan kata sandi hosting).')
    . '<h2>Database MySQL</h2><small>Lihat di panel hosting → MySQL Databases.</small>'
    . $in('host', 'Host MySQL', $v['host'], 'text', 'Contoh: sql123.infinityfree.com', 'placeholder="sqlXXX.infinityfree.com"')
    . $in('name', 'Nama database', $v['name'], 'text', 'Contoh: if0_12345678_tabungan')
    . $in('user', 'Pengguna database', $v['user'], 'text', 'Contoh: if0_12345678')
    . $in('pass', 'Kata sandi database', '', 'password', 'Di InfinityFree sama dengan kata sandi akun hosting.')
    . '<h2>Akun admin aplikasi</h2>'
    . $in('aname', 'Nama lengkap', $v['aname'])
    . $in('auser', 'Username', $v['auser'], 'text', 'Huruf kecil, angka, titik, garis bawah, strip.')
    . $in('apass', 'Kata sandi admin', '', 'password', 'Minimal 8 karakter.')
    . $in('apass2', 'Ulangi kata sandi admin', '', 'password')
    . '<button type="submit">Pasang sekarang</button></form>');
