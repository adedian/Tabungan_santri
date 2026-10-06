<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Services\Export\PdfWriter;
use App\Services\Export\XlsxWriter;
use App\Services\ReportService;

final class ReportController extends Controller
{
    /** GET /laporan */
    public function index(Request $request): Response
    {
        $q = $request->query();
        return $this->view('reports/index', ['initial' => ReportService::build($q) + [
            'students'   => ReportService::students($q),
            'can_export' => can('reports.export'),
            'months'     => bulan_list(),
            'today'      => date('Y-m-d'),
            'datasets'   => ReportService::DATASETS,
        ]]);
    }

    /** GET /api/reports/summary */
    public function summary(Request $request): Response
    {
        Session::close();
        return $this->success(ReportService::build($request->query()));
    }

    /** GET /api/reports/students */
    public function students(Request $request): Response
    {
        Session::close();
        return $this->success(ReportService::students($request->query()));
    }

    /** GET /laporan/export?dataset=transaksi|santri&format=xlsx|pdf + filter */
    public function export(Request $request): Response
    {
        $dataset = (string) $request->query('dataset', 'transaksi');
        $format  = (string) $request->query('format', 'xlsx');
        if (!isset(ReportService::DATASETS[$dataset]) || !in_array($format, ['xlsx', 'pdf'], true)) {
            return $this->error('Jenis atau format ekspor tidak dikenal.', 422);
        }

        $user = Auth::user();
        $res  = ReportService::exportTable($dataset, $request->query(), (string) $user['name']);
        if ($res['over']) {
            return $this->error('Data terlalu banyak (' . number_format($res['rows'], 0, ',', '.') . ' baris; maksimal '
                . number_format(ReportService::EXPORT_MAX_ROWS, 0, ',', '.') . '). Persempit filter terlebih dahulu.', 422);
        }

        if ($format === 'pdf' && $res['rows'] > PdfWriter::MAX_ROWS) {
            return $this->error('Data terlalu banyak untuk PDF (' . number_format($res['rows'], 0, ',', '.') . ' baris; maksimal '
                . number_format(PdfWriter::MAX_ROWS, 0, ',', '.') . '). Persempit filter atau gunakan Excel.', 422);
        }

        $table = $res['table'];
        $body  = $format === 'pdf' ? PdfWriter::render($table, (string) config('app.name', 'Tabungan Santri')) : XlsxWriter::render($table);
        $mime  = $format === 'pdf' ? PdfWriter::MIME : XlsxWriter::MIME;
        $name  = 'laporan-tabungan-' . ($dataset === 'santri' ? 'rekap-santri' : 'transaksi') . '-' . date('Ymd-His') . '.' . $format;

        AuditLog::record('Mengekspor laporan', 'Laporan', null,
            ReportService::DATASETS[$dataset] . ' (' . $format . ') — ' . $res['rows'] . ' baris — ' . ReportService::filterLabel(ReportService::filters($request->query())));

        return (new Response($body, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length'      => (string) strlen($body),
        ]));
    }
}
