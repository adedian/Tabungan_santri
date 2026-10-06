<?php
declare(strict_types=1);

/**
 * Menyiapkan paket unggah untuk hosting gratis/bersama TANPA SSH (mis. InfinityFree). Hanya CLI.
 *   php tools/package-hosting.php
 *
 * Hasil di deploy/ (di-ignore Git):
 *   htdocs/               isi folder yang diunggah ke htdocs lewat FTP (app, public, routes, storage kosong, .htaccess khusus hosting)
 *   tabungan-santri-htdocs.zip   isi yang sama (untuk arsip)
 *   hosting-schema.sql    skema database tanpa CREATE DATABASE/USE (impor lewat phpMyAdmin pada database yang sudah dibuat di panel)
 *   hosting-admin.sql     satu akun Super Admin dengan kata sandi sementara ACAK (ganti segera setelah login)
 *   PANDUAN-HOSTING.md    langkah demi langkah
 * Tidak menyertakan: seed.sql (data dummy), akun demo, tools/, .git, README, berkas konfigurasi lokal.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$out  = $root . '/deploy';
$web  = $out . '/htdocs';

function rrmdir(string $d): void
{
    if (!is_dir($d)) { return; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($d);
}
function copyTree(string $src, string $dst, array $skip = []): int
{
    $n = 0;
    @mkdir($dst, 0775, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($src) + 1));
        foreach ($skip as $s) {
            if ($rel === $s || str_starts_with($rel, $s . '/')) { continue 2; }
        }
        if ($f->isDir()) { @mkdir($dst . '/' . $rel, 0775, true); continue; }
        copy($f->getPathname(), $dst . '/' . $rel);
        $n++;
    }
    return $n;
}

rrmdir($out);
mkdir($web, 0775, true);

$n  = copyTree($root . '/app', $web . '/app', ['config/database.php']); // kredensial lokal TIDAK ikut
$n += copyTree($root . '/public', $web . '/public');
$n += copyTree($root . '/routes', $web . '/routes');
@mkdir($web . '/database', 0775, true);
copy($root . '/database/.htaccess', $web . '/database/.htaccess');
foreach (['logs', 'sessions', 'cache', 'uploads', 'backups'] as $d) {
    @mkdir($web . '/storage/' . $d, 0775, true);
    file_put_contents($web . '/storage/' . $d . '/.gitkeep', '');
}
file_put_contents($web . '/storage/.htaccess', "Require all denied\n");
foreach (glob($web . '/public/assets/js/_*.js') ?: [] as $tmp) { @unlink($tmp); } // berkas uji sementara

// .htaccess khusus hosting: tanpa direktif "Options" (sebagian hosting bersama menolaknya dengan galat 500)
$strip = static fn (string $s): string => (string) preg_replace('/^\s*Options\b[^\n]*\r?\n/m', '', $s);
file_put_contents($web . '/.htaccess', $strip((string) file_get_contents($root . '/.htaccess')));
file_put_contents($web . '/public/.htaccess', $strip((string) file_get_contents($root . '/public/.htaccess')));

// Konfigurasi database (diisi pemilik hosting)
file_put_contents($web . '/app/config/database.php', <<<'PHP'
<?php
declare(strict_types=1);

// ISI sesuai panel hosting (menu MySQL Databases): host, nama database, pengguna, kata sandi.
return [
    'host'     => 'ISI_HOST_MYSQL',       // mis. sql123.infinityfree.com (BUKAN localhost)
    'port'     => 3306,
    'name'     => 'ISI_NAMA_DATABASE',    // mis. if0_12345678_tabungan
    'user'     => 'ISI_PENGGUNA_MYSQL',   // mis. if0_12345678
    'password' => 'ISI_KATA_SANDI',
    'charset'  => 'utf8mb4',
    'timezone' => '+07:00', // WIB
];
PHP);

// Skema untuk hosting: tanpa CREATE DATABASE / USE (database dibuat lewat panel)
$schema = (string) file_get_contents($root . '/database/schema.sql');
$schema = preg_replace('/^CREATE DATABASE[^;]*;\s*$/ms', '', $schema) ?? $schema;
$schema = preg_replace('/^USE\s+`?[A-Za-z0-9_]+`?;\s*$/m', '', (string) $schema) ?? $schema;
if (stripos((string) $schema, 'CREATE DATABASE') !== false || preg_match('/^USE\s/mi', (string) $schema)) {
    fwrite(STDERR, "Gagal membersihkan CREATE DATABASE/USE dari schema.sql\n");
    exit(1);
}
file_put_contents($out . '/hosting-schema.sql', $schema);

// Pemasang web sekali pakai: public/install.php + skema di app/install/ (dilindungi app/.htaccess)
$alphabetKey = 'abcdefghjkmnpqrstuvwxyz23456789';
$setupKey = '';
for ($i = 0; $i < 10; $i++) { $setupKey .= $alphabetKey[random_int(0, strlen($alphabetKey) - 1)]; }
@mkdir($web . '/app/install', 0775, true);
copy($out . '/hosting-schema.sql', $web . '/app/install/schema.sql');
file_put_contents($web . '/public/install.php', str_replace('__SETUP_KEY_SHA256__', hash('sha256', $setupKey), (string) file_get_contents(__DIR__ . '/hosting-install.php')));

// Jalur SATU BERKAS: untuk kasus seluruh proyek sudah terunggah ke htdocs/<folder>/ — cukup unggah install.php ke folder public-nya
@mkdir($out . '/satu-berkas', 0775, true);
copy($web . '/public/install.php', $out . '/satu-berkas/install.php');
file_put_contents($out . '/satu-berkas/ALIHKAN-AKAR.htaccess', "# OPSIONAL: simpan sebagai .htaccess di htdocs (di luar folder proyek) agar alamat utama menuju aplikasi.\n# Ganti Tabungan_santri bila nama folder proyek di hosting berbeda.\nRewriteEngine On\nRewriteCond %{REQUEST_URI} !^/Tabungan_santri/\nRewriteRule ^(.*)$ /Tabungan_santri/$1 [R=302,L]\n");

// Super Admin awal dengan kata sandi sementara acak (jalur manual lewat phpMyAdmin; pemasang web tidak memakainya)
$alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
$pw = '';
for ($i = 0; $i < 14; $i++) { $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
$hash = password_hash($pw, PASSWORD_DEFAULT);
file_put_contents($out . '/hosting-admin.sql',
    "-- Akun Super Admin awal. Masuk dengan username \"admin\" lalu SEGERA ganti kata sandi (menu Pengguna).\n"
    . "INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `status`)\n"
    . "VALUES ('Administrator', 'admin', NULL, '" . $hash . "', 'super_admin', 'aktif');\n");
file_put_contents($out . '/AKUN-AWAL.local.txt', "KODE PEMASANGAN (untuk https://DOMAIN/install.php): {$setupKey}\n\n[Jalur manual lewat phpMyAdmin saja]\nusername: admin\nkata sandi sementara: {$pw}\n(Ganti segera setelah login. Berkas ini jangan dibagikan.)\n");

// Zip
$zipPath = $out . '/tabungan-santri-htdocs.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS)) as $f) {
    $zip->addFile($f->getPathname(), str_replace('\\', '/', substr($f->getPathname(), strlen($web) + 1)));
}
$zip->close();

copy(__DIR__ . '/hosting-guide.md', $out . '/PANDUAN-HOSTING.md');

echo "Paket siap di: $out\n";
echo "  htdocs/ ($n berkas) + zip " . round(filesize($zipPath) / 1024) . " KB\n";
echo "  hosting-schema.sql, hosting-admin.sql, PANDUAN-HOSTING.md\n";
echo "  Kode pemasangan (install.php): {$setupKey}\n";
echo "  [jalur manual] kata sandi sementara akun admin: {$pw}\n";
