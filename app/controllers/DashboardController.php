<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('dashboard/index', [
            'initial' => [
                'summary'  => DashboardService::summary(),
                'activity' => DashboardService::activity('week'),
            ],
        ]);
    }

    public function summary(Request $request): Response
    {
        Session::close();
        return $this->success(DashboardService::summary());
    }

    public function activity(Request $request): Response
    {
        Session::close();
        $range = (string) $request->query('range', 'week');
        if (!in_array($range, DashboardService::RANGES, true)) {
            return $this->error('Rentang waktu tidak valid.', 422);
        }
        return $this->success(DashboardService::activity($range));
    }
}
