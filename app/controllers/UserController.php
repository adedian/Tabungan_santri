<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\UserService;

final class UserController extends Controller
{
    private const FORM_ERROR = 'Periksa kembali isian Anda.';

    /** GET /pengguna */
    public function index(Request $request): Response
    {
        return $this->view('users/index', ['initial' => UserService::listing($request->query())]);
    }

    /** GET /api/users */
    public function list(Request $request): Response
    {
        Session::close();
        return $this->success(UserService::listing($request->query()));
    }

    /** POST /api/users */
    public function store(Request $request): Response
    {
        $r = UserService::create($request->post(), $this->actor());
        return $r['ok'] ? $this->success($r['user'], 'Pengguna berhasil ditambahkan.', 201)
                        : $this->error(self::FORM_ERROR, 422, $r['errors']);
    }

    /** PUT /api/users/{id} */
    public function update(Request $request, int $id): Response
    {
        $r = UserService::update($id, $request->post(), $this->actor());
        if (!empty($r['notfound'])) {
            return $this->error('Pengguna tidak ditemukan.', 404);
        }
        return $r['ok'] ? $this->success($r['user'], 'Data pengguna berhasil diperbarui.')
                        : $this->error($r['message'] ?? self::FORM_ERROR, 422, $r['errors']);
    }

    /** PUT /api/users/{id}/status */
    public function status(Request $request, int $id): Response
    {
        $status = (string) $request->post('status', '');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            return $this->error('Status tidak valid.', 422);
        }
        $r = UserService::setStatus($id, $status, $this->actor());
        if (!empty($r['notfound'])) {
            return $this->error('Pengguna tidak ditemukan.', 404);
        }
        return $r['ok'] ? $this->success($r['user'], $status === 'aktif' ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.')
                        : $this->error($r['message'], 422);
    }

    /** PUT /api/users/{id}/password */
    public function password(Request $request, int $id): Response
    {
        $r = UserService::resetPassword($id, $request->post(), $this->actor());
        if (!empty($r['notfound'])) {
            return $this->error('Pengguna tidak ditemukan.', 404);
        }
        return $r['ok'] ? $this->success(null, 'Kata sandi berhasil diatur ulang.')
                        : $this->error(self::FORM_ERROR, 422, $r['errors']);
    }

    /** @return array{id:int,name:string} */
    private function actor(): array
    {
        $u = Auth::user();
        return ['id' => (int) $u['id'], 'name' => (string) $u['name']];
    }
}
