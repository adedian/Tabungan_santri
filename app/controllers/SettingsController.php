<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingsService;

final class SettingsController extends Controller
{
    /** GET /pengaturan */
    public function index(Request $request): Response
    {
        return $this->view('settings/index', ['initial' => SettingsService::current()]);
    }

    /** PUT /api/settings */
    public function update(Request $request): Response
    {
        $u = Auth::user();
        $r = SettingsService::update($request->post(), ['id' => (int) $u['id'], 'name' => (string) $u['name']]);
        return $r['ok'] ? $this->success($r['settings'], 'Pengaturan berhasil disimpan.')
                        : $this->error('Periksa kembali isian Anda.', 422, $r['errors']);
    }

    /** GET /brand/logo — publik (dipakai halaman login); isi tetap berupa PNG yang sudah divalidasi saat diunggah. */
    public function logo(Request $request): Response
    {
        if (SettingsService::logoVersion() === null || ($png = @file_get_contents(SettingsService::logoPath())) === false) {
            return Response::error('Logo belum diatur.', 404);
        }
        return (new Response($png, 200, [
            'Content-Type'            => 'image/png',
            'Content-Length'          => (string) strlen($png),
            'Cache-Control'           => 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]));
    }
}
