<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Student;
use App\Services\StudentService;

final class StudentController extends Controller
{
    private const FORM_ERROR = 'Periksa kembali isian Anda.';

    public function index(Request $request): Response
    {
        return $this->view('students/index', [
            'initial' => StudentService::listing($request->query()) + ['can_manage' => can('students.manage')],
        ]);
    }

    /** GET /api/students — daftar berhalaman (polling realtime juga memakai ini). */
    public function list(Request $request): Response
    {
        Session::close();
        return $this->success(StudentService::listing($request->query()));
    }

    /** GET /api/students/search?q=&limit=&jenjang=&status= — untuk pilihan santri di form/filter. */
    public function search(Request $request): Response
    {
        Session::close();
        $q       = mb_substr((string) $request->query('q', ''), 0, 100);
        $limit   = min(20, max(1, (int) $request->query('limit', 8)));
        $jenjang = in_array($request->query('jenjang'), ['TK', 'SD'], true) ? (string) $request->query('jenjang') : null;
        $active  = $request->query('status', 'aktif') !== 'semua';

        return $this->success(['items' => Student::search($q, $limit, $active, $jenjang)]);
    }

    public function store(Request $request): Response
    {
        $r = StudentService::create($request->post());
        if (!$r['ok']) {
            return $this->error(self::FORM_ERROR, 422, $r['errors']);
        }
        return $this->success($r['student'], 'Santri berhasil ditambahkan.', 201);
    }

    public function update(Request $request, int $id): Response
    {
        $r = StudentService::update($id, $request->post());
        if (!empty($r['notfound'])) {
            return $this->error('Data santri tidak ditemukan.', 404);
        }
        if (!$r['ok']) {
            return $this->error(self::FORM_ERROR, 422, $r['errors']);
        }
        return $this->success($r['student'], 'Data santri berhasil diperbarui.');
    }

    public function status(Request $request, int $id): Response
    {
        $status = (string) $request->post('status', '');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            return $this->error('Status tidak valid.', 422);
        }
        $r = StudentService::setStatus($id, $status);
        if (!empty($r['notfound'])) {
            return $this->error('Data santri tidak ditemukan.', 404);
        }
        return $this->success($r['student'], $status === 'aktif' ? 'Santri diaktifkan.' : 'Santri dinonaktifkan.');
    }
}
