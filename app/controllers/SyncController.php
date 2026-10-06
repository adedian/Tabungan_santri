<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\SyncState;

/** Endpoint polling realtime: satu pembacaan ringan atas tabel sync_state. */
final class SyncController extends Controller
{
    public function check(Request $request): Response
    {
        Session::close(); // lepas session lock: polling tidak boleh memblokir request lain
        $scopes = array_filter(explode(',', (string) $request->query('scopes', 'savings')));
        return $this->success(['rev' => SyncState::revision($scopes)]);
    }
}
