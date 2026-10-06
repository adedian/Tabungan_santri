<?php
declare(strict_types=1);

/**
 * Menjalankan migrasi database (database/migrations/*.sql) yang belum pernah dijalankan. Hanya CLI.
 *   php tools/migrate.php
 * Cadangkan dulu:  php tools/backup.php
 * Aman dijalankan ulang: migrasi yang sudah tercatat dilewati; objek yang sudah ada (kolom/indeks/tabel
 * duplikat, mis. pada instalasi baru dari schema.sql) dilewati tanpa galat.
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

Database::execute(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(100) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$done = array_column(Database::fetchAll('SELECT version FROM schema_migrations'), 'version');

// Kode galat MySQL "sudah ada": 1060 kolom, 1061 indeks, 1050 tabel, 1826 FK, 1068 primary key ganda
const ALREADY = [1060, 1061, 1050, 1826, 1068];

$files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($files);
$ran = 0;
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $done, true)) {
        continue;
    }
    echo "Migrasi $version ...\n";

    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', (string) $sql))) as $i => $stmt) {
        try {
            Database::pdo()->exec($stmt);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($code, ALREADY, true)) {
                echo '  - dilewati (sudah ada): ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . "...\n";
                continue;
            }
            fwrite(STDERR, "GAGAL pada pernyataan #" . ($i + 1) . ": " . $e->getMessage() . "\nMigrasi dihentikan; perbaiki lalu jalankan ulang.\n");
            exit(1);
        }
    }
    Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
    $ran++;
}
echo $ran ? "Selesai: $ran migrasi dijalankan.\n" : "Database sudah mutakhir.\n";
