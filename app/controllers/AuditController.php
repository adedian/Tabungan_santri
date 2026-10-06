<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

final class AuditController extends Controller
{
    /** GET /audit — Audit Log (hanya baca; catatan tidak dapat diubah atau dihapus dari aplikasi) */
    public function index(Request $request): Response
    {
        return $this->view('audit/index', ['initial' => AuditService::listing($request->query()) + [
            'today' => date('Y-m-d'),
        ]]);
    }

    /** GET /api/audit/list */
    public function list(Request $request): Response
    {
        Session::close();
        return $this->success(AuditService::listing($request->query()));
    }
}
