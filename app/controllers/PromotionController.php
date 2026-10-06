<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PromotionService;

/** Kenaikan Kelas (Admin ke atas). Tidak pernah otomatis: selalu Review → Pilih → Konfirmasi → Proses. */
final class PromotionController extends Controller
{
    /** GET /santri/kenaikan */
    public function index(Request $request): Response
    {
        return $this->view('promotions/index', ['initial' => PromotionService::options()]);
    }

    /** GET /api/promotions/candidates?from_year=&jenjang=&kelas= */
    public function candidates(Request $request): Response
    {
        Session::close();
        $r = PromotionService::candidates(
            (string) $request->query('from_year', ''),
            (string) $request->query('jenjang', ''),
            (string) $request->query('kelas', '')
        );
        return $r['ok'] ? $this->success($r) : $this->error($r['message'], 422);
    }

    /** POST /api/promotions */
    public function process(Request $request): Response
    {
        $user = Auth::user();
        $r = PromotionService::process($request->post(), ['id' => (int) $user['id'], 'name' => (string) $user['name']]);
        if (!$r['ok']) {
            return $this->error($r['message'], $r['status'] ?? 422);
        }
        $x = $r['result'];
        $parts = [];
        if ($x['naik'] > 0)    { $parts[] = $x['naik'] . ' santri naik kelas'; }
        if ($x['lulus'] > 0)   { $parts[] = $x['lulus'] . ' santri lulus (menjadi alumni)'; }
        if ($x['tinggal'] > 0) { $parts[] = $x['tinggal'] . ' santri tidak naik'; }
        return $this->success($x, 'Kenaikan kelas diproses: ' . implode(', ', $parts) . '.');
    }
}
