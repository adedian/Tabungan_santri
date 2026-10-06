<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Savings;
use App\Models\Student;
use App\Models\SyncState;
use App\Services\PromotionService;
use App\Services\SavingsService;
use App\Services\StudentService;

final class SavingsController extends Controller
{
    /** GET /tabungan/tambah[?student_id=] */
    public function create(Request $request): Response
    {
        $pre = null;
        $sid = (int) $request->query('student_id', 0);
        if ($sid > 0 && ($s = Student::find($sid)) !== null && $s['status'] === 'aktif') {
            $pre = $s;
        }

        return $this->view('savings/create', ['initial' => [
            'rev'     => SyncState::revision(['savings']),
            'today'   => date('Y-m-d'),
            'month'   => (int) date('n'),
            'student' => $pre,
            'classes' => StudentService::classOptions(),
            'recent'  => Savings::recent(6),
        ]]);
    }

    /** POST /api/savings/create */
    public function store(Request $request): Response
    {
        $user = Auth::user();
        $r = SavingsService::create($request->post(), ['id' => (int) $user['id'], 'name' => (string) $user['name']]);

        if (!$r['ok']) {
            $extra = isset($r['saldo']) ? ['saldo' => $r['saldo']] : null;
            return Response::error($r['message'] ?? 'Periksa kembali isian Anda.', 422, $r['errors'] ?? [], $extra);
        }
        return $this->success(
            ['transaction' => $r['transaction'], 'summary' => $r['summary']],
            'Transaksi berhasil disimpan',
            201
        );
    }

    /** GET /tabungan — Riwayat Tabungan */
    public function index(Request $request): Response
    {
        return $this->view('savings/index', ['initial' => SavingsService::listing($request->query()) + [
            'can_edit'   => can('savings.edit'),
            'can_delete' => can('savings.delete'),
            'months'     => bulan_list(),
            'today'      => date('Y-m-d'),
        ]]);
    }

    /** GET /tabungan/santri/{id} — Detail Tabungan Santri */
    public function student(Request $request, int $id): Response
    {
        $profile = SavingsService::studentProfile($id);
        if ($profile === null) {
            throw new HttpException(404);
        }
        if ($profile['student']['status'] === 'alumni') { // alumni punya halaman sendiri (menu Tabungan Alumni)
            return $this->redirect('/tabungan/alumni/' . $id);
        }
        // student_id selalu dipaksa dari URL; filter lain mengikuti query string
        $list = SavingsService::listing(array_merge($request->query(), ['student_id' => $id]));

        return $this->view('savings/student', ['initial' => [
            'profile'    => $profile,
            'list'       => $list,
            'history'    => PromotionService::history($id),
            'is_alumni'  => false,
            'can_edit'   => can('savings.edit'),
            'can_delete' => can('savings.delete'),
            'can_create' => can('savings.create'),
            'months'     => bulan_list(),
            'today'      => date('Y-m-d'),
        ]]);
    }

    /** GET /api/savings/student/{id} */
    public function studentProfile(Request $request, int $id): Response
    {
        Session::close();
        $profile = SavingsService::studentProfile($id);
        return $profile === null ? $this->error('Santri tidak ditemukan.', 404) : $this->success($profile);
    }

    /** GET /api/savings/list */
    public function list(Request $request): Response
    {
        Session::close();
        return $this->success(SavingsService::listing($request->query()));
    }

    /** PUT /api/savings/{id} */
    public function update(Request $request, int $id): Response
    {
        $user = Auth::user();
        $r = SavingsService::update($id, $request->post(), ['id' => (int) $user['id'], 'name' => (string) $user['name']]);
        if (!empty($r['notfound'])) {
            return $this->error('Transaksi tidak ditemukan atau sudah dihapus.', 404);
        }
        if (!$r['ok']) {
            return $this->error($r['message'] ?? 'Periksa kembali isian Anda.', 422, $r['errors'] ?? []);
        }
        return $this->success(['transaction' => $r['transaction'], 'summary' => $r['summary']], 'Transaksi berhasil diperbarui');
    }

    /** DELETE /api/savings/{id} */
    public function destroy(Request $request, int $id): Response
    {
        $user = Auth::user();
        $r = SavingsService::delete($id, ['id' => (int) $user['id'], 'name' => (string) $user['name']]);
        if (!empty($r['notfound'])) {
            return $this->error('Transaksi tidak ditemukan atau sudah dihapus.', 404);
        }
        if (!$r['ok']) {
            return $this->error($r['message'], 422);
        }
        return $this->success(['summary' => $r['summary']], 'Transaksi berhasil dihapus');
    }

    /** POST /api/savings/bulk-delete  {ids: [..]} — hapus (soft delete) banyak transaksi, semua atau tidak sama sekali */
    public function bulkDestroy(Request $request): Response
    {
        $user = Auth::user();
        $r = SavingsService::bulkDelete(SavingsService::cleanIds($request->post('ids')), ['id' => (int) $user['id'], 'name' => (string) $user['name']]);
        if (!$r['ok']) {
            return $this->error($r['message'], 422);
        }
        return $this->success(['deleted' => $r['deleted']], $r['deleted'] . ' transaksi berhasil dihapus.');
    }

    /** GET /api/savings/balance?student_id= */
    public function balance(Request $request): Response
    {
        Session::close();
        $id = (int) $request->query('student_id', 0);
        if ($id < 1 || Student::find($id) === null) {
            return $this->error('Santri tidak ditemukan.', 404);
        }
        return $this->success(Savings::studentSummary($id));
    }

    /** GET /api/savings/recent?limit= */
    public function recent(Request $request): Response
    {
        Session::close();
        $limit = min(20, max(1, (int) $request->query('limit', 6)));
        return $this->success(['rev' => SyncState::revision(['savings']), 'items' => Savings::recent($limit)]);
    }
}
