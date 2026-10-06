<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AlumniService;
use App\Services\PromotionService;
use App\Services\SavingsService;

/** Tabungan Alumni: hanya baca (alumni tidak menerima transaksi baru). */
final class AlumniController extends Controller
{
    /** GET /tabungan/alumni */
    public function index(Request $request): Response
    {
        return $this->view('alumni/index', ['initial' => AlumniService::listing($request->query()) + ['page_mode' => 'tarik']]);
    }

    /** GET /laporan/alumni — Rekap Alumni (halaman yang sama, langsung mode Rekap) */
    public function rekap(Request $request): Response
    {
        $q = array_merge($request->query(), ['mode' => 'rekap']);
        return $this->view('alumni/index', ['initial' => AlumniService::listing($q) + ['page_mode' => 'rekap']]);
    }

    /** GET /api/alumni */
    public function list(Request $request): Response
    {
        Session::close();
        return $this->success(AlumniService::listing($request->query()));
    }

    /** GET /tabungan/alumni/{id} — detail alumni + seluruh riwayat transaksinya */
    public function show(Request $request, int $id): Response
    {
        $profile = SavingsService::studentProfile($id);
        if ($profile === null || $profile['student']['status'] !== 'alumni') {
            throw new HttpException(404);
        }
        $list = SavingsService::listing(array_merge($request->query(), ['student_id' => $id]));

        return $this->view('savings/student', ['initial' => [
            'profile'    => $profile,
            'list'       => $list,
            'history'    => PromotionService::history($id),
            'is_alumni'  => true,
            'can_edit'   => can('savings.edit'),
            'can_delete' => can('savings.delete'),
            'can_create' => false,
            'months'     => bulan_list(),
            'today'      => date('Y-m-d'),
        ]]);
    }
}
