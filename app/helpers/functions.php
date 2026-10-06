<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;

/** Escape output HTML (XSS). Gunakan untuk SEMUA data dinamis di view. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return Url::to($path);
}

function asset(string $path): string
{
    return Url::asset($path);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Ikon dari sprite (public/assets/icons/sprite.svg). Hanya nama statis dari kode, bukan input pengguna. */
function icon(string $name, string $class = ''): string
{
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false">'
        . '<use href="' . e(Url::asset('icons/sprite.svg')) . '#' . e($name) . '"></use></svg>';
}

/** JSON aman untuk <script type="application/json"> (mencegah penutupan tag / injeksi). */
function json_for_script(mixed $data): string
{
    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** Inisial untuk avatar: "Ahmad Fauzan" -> "AF". */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

/**
 * Menu sidebar yang boleh dilihat pengguna saat ini.
 * @return array<int, array{label:?string, items:array}>
 */
function nav_groups(): array
{
    $current = App\Core\Request::current()?->path() ?? '/';
    $showPlanned = (bool) Config::get('app.show_planned_menu', false);
    $groups = [];

    // Hanya item dengan kecocokan TERPANJANG yang aktif (/tabungan/tambah tidak ikut menyorot /tabungan).
    $best = '';
    foreach ((array) Config::get('navigation', []) as $group) {
        foreach ($group['items'] as $item) {
            $p = $item['path'];
            if ($item['ready'] && ($current === $p || str_starts_with($current, $p . '/')) && strlen($p) > strlen($best)) {
                $best = $p;
            }
        }
    }

    foreach ((array) Config::get('navigation', []) as $group) {
        $items = [];
        foreach ($group['items'] as $item) {
            if (!Auth::can($item['perm']) || (!$item['ready'] && !$showPlanned)) {
                continue;
            }
            $item['active'] = $item['ready'] && $item['path'] === $best;
            $items[] = $item;
        }
        if ($items) {
            $groups[] = ['label' => $group['label'], 'items' => $items];
        }
    }
    return $groups;
}

/**
 * Tujuan cadangan tombol "Kembali" (halaman induk yang logis). Tombol memakai riwayat browser bila pengunjung datang dari
 * halaman lain di aplikasi ini, jadi tujuan ini hanya dipakai saat halaman dibuka langsung. null = tanpa tombol (Dashboard).
 * @return array{url:string,label:string}|null
 */
function back_target(): ?array
{
    $path = App\Core\Request::current()?->path() ?? '/';
    $rules = [
        '#^/dashboard$#'              => null,
        '#^/tabungan/alumni/\d+$#'    => ['/tabungan/alumni', 'Tabungan Alumni'],
        '#^/tabungan/santri/\d+$#'    => ['/tabungan', 'Riwayat Tabungan'],
        '#^/laporan/alumni$#'         => ['/laporan', 'Laporan Tabungan'],
        '#^/santri/kenaikan$#'        => ['/santri', 'Data Santri'],
    ];
    foreach ($rules as $re => $target) {
        if (preg_match($re, $path)) {
            return $target === null ? null : ['url' => $target[0], 'label' => $target[1]];
        }
    }
    return ['url' => '/dashboard', 'label' => 'Dashboard'];
}

/** Apakah halaman dengan path ini sudah dibangun (menurut config/navigation.php)? */
function nav_ready(string $path): bool
{
    foreach ((array) Config::get('navigation', []) as $group) {
        foreach ($group['items'] as $item) {
            if ($item['path'] === $path) {
                return (bool) $item['ready'];
            }
        }
    }
    return false;
}

/** Pengguna yang sedang login (tanpa hash password) atau null. */
function auth_user(): ?array
{
    return Auth::user();
}

/** Cek izin pengguna saat ini, mis. can('savings.edit'). */
function can(string $permission): bool
{
    return Auth::can($permission);
}

/** Pesan flash dari request sebelumnya. */
function flash(string $key, mixed $default = null): mixed
{
    return Session::flashed($key, $default);
}

/** Nilai input lama (setelah validasi gagal & redirect). */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::flashed('old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}
