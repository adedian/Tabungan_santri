<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/** Katalog komponen UI (khusus Super Admin). Data di view murni contoh statis. */
final class StyleguideController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('styleguide/index');
    }
}
