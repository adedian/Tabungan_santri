<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\Setting;

/**
 * Pengaturan identitas lembaga: nama + logo.
 * Logo dikirim browser sebagai PNG (sudah diperkecil di canvas) berbentuk data URL. Server tetap memvalidasi sendiri:
 * tanda tangan PNG, dimensi, ukuran, dan penutup IEND. Berkas disimpan di storage/uploads (di luar web) dan hanya
 * disajikan lewat /brand/logo dengan Content-Type tetap, jadi tidak pernah dieksekusi.
 */
final class SettingsService
{
    public const NAME_MAX   = 100;
    public const LOGO_MAX_BYTES = 307200; // 300 KB setelah di-decode
    public const LOGO_MIN_PX = 16;
    public const LOGO_MAX_PX = 512;
    private const IEND = "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";

    public static function logoDir(): string
    {
        return BASE_PATH . '/storage/uploads';
    }

    public static function logoPath(): string
    {
        return self::logoDir() . '/logo.png';
    }

    /** Versi logo (untuk cache-busting) atau null bila tidak ada logo. */
    public static function logoVersion(): ?string
    {
        $v = Setting::get('logo_version');
        return ($v !== null && is_file(self::logoPath())) ? $v : null;
    }

    public static function current(): array
    {
        return [
            'school_name'  => (string) Setting::get('school_name', ''),
            'logo_version' => self::logoVersion(),
            'logo_max_px'  => 256,
        ];
    }

    /**
     * @param array{school_name?:mixed,logo?:mixed,remove_logo?:mixed} $in
     * @return array{ok:bool, errors?:array<string,string>, settings?:array}
     */
    public static function update(array $in, array $actor): array
    {
        $errors = [];

        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($in['school_name'] ?? '')));
        if ($name === '') {
            $errors['school_name'] = 'Nama lembaga wajib diisi.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors['school_name'] = 'Nama lembaga maksimal ' . self::NAME_MAX . ' karakter.';
        }

        $png = null;
        $logoIn = $in['logo'] ?? null;
        if (is_string($logoIn) && $logoIn !== '') {
            [$png, $err] = self::decodeLogo($logoIn);
            if ($err !== null) {
                $errors['logo'] = $err;
            }
        }
        $remove = $png === null && !empty($in['remove_logo']);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $oldName  = (string) Setting::get('school_name', '');
        $hadLogo  = self::logoVersion() !== null;
        $tmp      = null;

        if ($png !== null) {
            $dir = self::logoDir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Folder storage/uploads tidak dapat dibuat.');
            }
            $tmp = $dir . '/logo-' . bin2hex(random_bytes(6)) . '.tmp';
            if (file_put_contents($tmp, $png, LOCK_EX) === false) {
                throw new \RuntimeException('Logo tidak dapat disimpan ke storage/uploads.');
            }
        }

        $changes = [];
        try {
            Database::transaction(function () use ($name, $oldName, $png, $remove, $hadLogo, $actor, &$changes): void {
                if ($name !== $oldName) {
                    Setting::set('school_name', $name);
                    $changes[] = 'nama lembaga “' . ($oldName !== '' ? $oldName : '—') . '” → “' . $name . '”';
                }
                if ($png !== null) {
                    Setting::set('logo_version', (string) time());
                    $changes[] = $hadLogo ? 'logo diganti' : 'logo ditambahkan';
                } elseif ($remove && $hadLogo) {
                    Setting::set('logo_version', '');
                    $changes[] = 'logo dihapus';
                }
                if ($changes !== []) {
                    AuditLog::record('Mengubah pengaturan', 'Pengaturan', null, implode('; ', $changes), $actor);
                }
            });
        } catch (\Throwable $e) {
            if ($tmp !== null) {
                @unlink($tmp);
            }
            throw $e;
        }

        // Berkas diganti SETELAH basis data berhasil, agar keduanya tidak saling menyimpang.
        if ($tmp !== null) {
            if (!@rename($tmp, self::logoPath())) {
                @unlink($tmp);
                throw new \RuntimeException('Logo tidak dapat dipasang.');
            }
        } elseif ($remove) {
            @unlink(self::logoPath());
        }

        return ['ok' => true, 'settings' => self::current()];
    }

    /** @return array{0:?string,1:?string} [biner PNG, pesan galat] */
    private static function decodeLogo(string $dataUrl): array
    {
        if (strlen($dataUrl) > (int) (self::LOGO_MAX_BYTES * 1.4) + 64) {
            return [null, 'Logo terlalu besar (maksimal 300 KB).'];
        }
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $dataUrl, $m)) {
            return [null, 'Format logo tidak dikenal. Pilih berkas gambar PNG, JPG, WEBP, atau SVG.'];
        }
        $bin = base64_decode($m[1], true);
        if ($bin === false || $bin === '') {
            return [null, 'Berkas logo rusak.'];
        }
        if (strlen($bin) > self::LOGO_MAX_BYTES) {
            return [null, 'Logo terlalu besar (maksimal 300 KB).'];
        }
        $info = @getimagesizefromstring($bin);
        if ($info === false || $info[2] !== IMAGETYPE_PNG || !str_starts_with($bin, "\x89PNG\r\n\x1A\n")) {
            return [null, 'Berkas logo bukan gambar PNG yang valid.'];
        }
        [$w, $h] = $info;
        if ($w < self::LOGO_MIN_PX || $h < self::LOGO_MIN_PX || $w > self::LOGO_MAX_PX || $h > self::LOGO_MAX_PX) {
            return [null, 'Ukuran logo harus antara ' . self::LOGO_MIN_PX . '–' . self::LOGO_MAX_PX . ' piksel.'];
        }
        if (substr($bin, -12) !== self::IEND) { // tidak ada data tambahan setelah akhir PNG
            return [null, 'Berkas logo tidak utuh.'];
        }
        return [$bin, null];
    }
}
