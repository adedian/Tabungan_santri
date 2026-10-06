<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/** Endpoint teknis. Sengaja tidak membocorkan versi/konfigurasi. */
final class SystemController extends Controller
{
    public function health(Request $request): Response
    {
        try {
            Database::fetchValue('SELECT 1');
            $dbOk = true;
        } catch (Throwable $e) {
            Logger::exception($e);
            $dbOk = false;
        }
        return Response::json(
            ['success' => $dbOk, 'message' => $dbOk ? 'OK' : 'Layanan bermasalah.', 'data' => ['database' => $dbOk]],
            $dbOk ? 200 : 503
        );
    }
}
