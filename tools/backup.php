<?php
declare(strict_types=1);

/**
 * Cadangan database + logo ke storage/backups/. Hanya CLI.
 *   php tools/backup.php [--keep=14] [--mysqldump=C:\xampp\mysql\bin\mysqldump.exe]
 * Hasil: storage/backups/tabungan-YYYYmmdd-HHMMSS.sql (+ logo-YYYYmmdd-HHMMSS.png bila ada).
 * Cadangan lama di luar --keep terbaru dihapus. Jadwalkan lewat Task Scheduler / cron.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Config;

Config::load(BASE_PATH . '/app/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Jakarta'));

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) { $opts[$m[1]] = $m[2]; }
}
$keep = max(1, (int) ($opts['keep'] ?? 14));
$dump = $opts['mysqldump'] ?? (is_file('C:\xampp\mysql\bin\mysqldump.exe') ? 'C:\xampp\mysql\bin\mysqldump.exe' : 'mysqldump');

$db  = (array) Config::get('database');
$dir = BASE_PATH . '/storage/backups';
if (!is_dir($dir)) { mkdir($dir, 0775, true); }
$stamp = date('Ymd-His');
$sql   = "$dir/tabungan-$stamp.sql";

// Kata sandi lewat berkas opsi sementara agar tidak tampil di daftar proses.
$cnf = tempnam(sys_get_temp_dir(), 'tbn');
$quote = static fn(string $v): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
file_put_contents($cnf, "[client]\nuser=" . $quote((string) $db['user']) . "\npassword=" . $quote((string) $db['password']) . "\n");
$cmd = escapeshellarg($dump) . ' --defaults-extra-file=' . escapeshellarg($cnf)
     . ' --host=' . escapeshellarg((string) $db['host']) . ' --port=' . (int) $db['port']
     . ' --single-transaction --routines --default-character-set=utf8mb4 --result-file=' . escapeshellarg($sql)
     . ' ' . escapeshellarg((string) $db['name']);
exec($cmd . ' 2>&1', $out, $code);
@unlink($cnf);

if ($code !== 0 || !is_file($sql) || filesize($sql) < 100) {
    @unlink($sql);
    fwrite(STDERR, "Backup GAGAL (kode $code). " . implode(' ', $out) . "\n");
    exit(1);
}
echo 'Database  : ' . basename($sql) . ' (' . number_format(filesize($sql) / 1024, 1) . " KB)\n";

$logo = BASE_PATH . '/storage/uploads/logo.png';
if (is_file($logo)) {
    copy($logo, "$dir/logo-$stamp.png");
    echo "Logo      : logo-$stamp.png\n";
}

foreach (['tabungan-*.sql', 'logo-*.png'] as $pattern) {
    $files = glob("$dir/$pattern") ?: [];
    rsort($files);
    foreach (array_slice($files, $keep) as $old) { @unlink($old); echo 'Dihapus   : ' . basename($old) . "\n"; }
}
echo "Selesai.\n";
