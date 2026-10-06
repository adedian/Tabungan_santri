<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Tangga kelas: menentukan kelas tujuan saat "Naik" dan kapan santri lulus.
 *
 *   SD  : "1A".."5A"  -> kelas berikutnya dengan rombel yang sama ("1A" -> "2A", "3B" -> "4B")
 *         "6A" (kelas 6) + Naik -> LULUS. Tidak pernah menjadi kelas 7 (hanya ada jenjang TK & SD).
 *   TK  : "TK A" -> "TK B";  "TK B" + Naik -> SD kelas 1 (rombel A, dapat diubah di layar proses).
 * Kelas yang formatnya tidak dikenali tidak dapat dinaikkan otomatis (tetap bisa diubah manual di Data Santri).
 */
final class ClassLadder
{
    public const LAST_SD_LEVEL = 6;

    /** @return array{type:string, level?:int, suffix?:string, letter?:string}|null */
    public static function parse(string $jenjang, string $kelas): ?array
    {
        $kelas = mb_strtoupper(trim($kelas));
        if ($jenjang === 'SD' && preg_match('/^(\d{1,2})\s*([A-Z]{0,3})$/', $kelas, $m)) {
            $level = (int) $m[1];
            return ($level >= 1 && $level <= self::LAST_SD_LEVEL) ? ['type' => 'sd', 'level' => $level, 'suffix' => $m[2]] : null;
        }
        if ($jenjang === 'TK' && preg_match('/^TK\s*([AB])$/', $kelas, $m)) {
            return ['type' => 'tk', 'letter' => $m[1]];
        }
        return null;
    }

    /**
     * Tujuan bila santri NAIK.
     * @return array{type:'naik',jenjang:string,kelas:string}|array{type:'lulus'}|null  null = tidak dapat ditentukan
     */
    public static function next(string $jenjang, string $kelas): ?array
    {
        $p = self::parse($jenjang, $kelas);
        if ($p === null) {
            return null;
        }
        if ($p['type'] === 'sd') {
            if ($p['level'] >= self::LAST_SD_LEVEL) {
                return ['type' => 'lulus'];
            }
            return ['type' => 'naik', 'jenjang' => 'SD', 'kelas' => ($p['level'] + 1) . $p['suffix']];
        }
        return $p['letter'] === 'A'
            ? ['type' => 'naik', 'jenjang' => 'TK', 'kelas' => 'TK B']
            : ['type' => 'naik', 'jenjang' => 'SD', 'kelas' => '1A'];
    }

    /**
     * Validasi kelas tujuan yang diisi pengguna terhadap tangga kelas (tidak boleh lompat/mundur/ganti jenjang sembarangan).
     * @return string|null pesan galat, null bila sah
     */
    public static function checkTarget(string $jenjang, string $kelas, string $targetJenjang, string $targetKelas): ?string
    {
        $next = self::next($jenjang, $kelas);
        if ($next === null || $next['type'] !== 'naik') {
            return 'Kelas ini tidak memiliki kelas tujuan.';
        }
        if ($targetJenjang !== $next['jenjang']) {
            return 'Jenjang tujuan harus ' . $next['jenjang'] . '.';
        }
        $want = self::parse($next['jenjang'], $next['kelas']);
        $got  = self::parse($targetJenjang, mb_strtoupper(trim($targetKelas)));
        if ($got === null || $got['type'] !== $want['type']
            || ($got['type'] === 'sd' && $got['level'] !== $want['level'])
            || ($got['type'] === 'tk' && $got['letter'] !== $want['letter'])) {
            return 'Kelas tujuan harus tingkat ' . ($want['type'] === 'sd' ? $want['level'] : 'TK ' . $want['letter']) . ' (mis. ' . $next['kelas'] . ').';
        }
        return null;
    }

    /** Format "TA 2025/2026": [mulai, label] untuk tahun mulai tertentu. */
    public static function yearLabel(int $startYear): string
    {
        return $startYear . '/' . ($startYear + 1);
    }

    /** Tahun mulai tahun ajaran asal bawaan: tahun ajaran yang baru berakhir/akan berakhir (Juli–Juni). */
    public static function defaultFromStart(?\DateTimeImmutable $today = null): int
    {
        $today = $today ?? new \DateTimeImmutable('today');
        return (int) $today->format('Y') - 1;
    }

    /** @return int|null tahun mulai bila label sah ("2025/2026"), selain itu null */
    public static function parseYear(string $label): ?int
    {
        if (!preg_match('/^(\d{4})\/(\d{4})$/', $label, $m) || (int) $m[2] !== (int) $m[1] + 1) {
            return null;
        }
        $start = (int) $m[1];
        return ($start >= 2000 && $start <= (int) date('Y') + 2) ? $start : null;
    }
}
