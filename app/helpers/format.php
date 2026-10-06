<?php
declare(strict_types=1);

/** Format Rupiah hanya untuk tampilan: 10000 -> "Rp 10.000". Database selalu menyimpan integer. */
function rupiah(int|float|string|null $amount, bool $prefix = true): string
{
    $n   = (int) round((float) $amount);
    $str = number_format(abs($n), 0, ',', '.');
    return ($n < 0 ? '-' : '') . ($prefix ? 'Rp ' : '') . $str;
}

/** Untuk angka besar (kartu ringkasan): "Rp" dikecilkan agar hemat lebar. Sudah di-escape; keluarkan apa adanya. */
function rupiah_html(int|float|string|null $amount): string
{
    $n = (int) round((float) $amount);
    return ($n < 0 ? '−' : '') . '<span class="cur">Rp</span> ' . e(number_format(abs($n), 0, ',', '.'));
}

/** "Rp 10.000" / "10.000" / "10000" -> 10000 (hanya digit yang diambil). */
function parse_rupiah(string $input): int
{
    $digits = preg_replace('/\D+/', '', $input) ?? '';
    return $digits === '' ? 0 : (int) $digits;
}

function bulan_list(): array
{
    return [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
}

function bulan_pendek(int $month): string
{
    $short = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
    return $short[$month] ?? '';
}

/** Salam menurut jam: pagi / siang / sore / malam. */
function salam(?int $hour = null): string
{
    $h = $hour ?? (int) date('G');
    return match (true) {
        $h < 4  => 'Selamat malam',
        $h < 11 => 'Selamat pagi',
        $h < 15 => 'Selamat siang',
        $h < 18 => 'Selamat sore',
        default => 'Selamat malam',
    };
}

function bulan_nama(int $month): string
{
    return bulan_list()[$month] ?? '';
}

/** "2026-10-06" -> "Selasa". */
function hari_id(?string $date = null): string
{
    $names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $t = $date === null ? time() : strtotime($date);
    return $t === false ? '' : $names[(int) date('w', $t)];
}

/** "2026-10-06" -> "6 Oktober 2026" (atau "06/10/2026" jika $short). */
function tanggal_id(?string $date, bool $short = false): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $t = strtotime($date);
    if ($t === false) {
        return '';
    }
    return $short
        ? date('d/m/Y', $t)
        : (int) date('j', $t) . ' ' . bulan_nama((int) date('n', $t)) . ' ' . date('Y', $t);
}
