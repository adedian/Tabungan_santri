<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Student;
use App\Services\PrintService;
use App\Services\ReportService;

/** Pratinjau & cetak. Dokumen dicetak oleh browser (window.print) memakai CSS @media print khusus. */
final class PrintController extends Controller
{
    /** GET /print/rekap/{id} — Rekap Tabungan per Nama (satu santri) */
    public function rekap(Request $request, int $id): Response
    {
        $student = Student::find($id);
        if ($student === null) {
            throw new HttpException(404);
        }
        $p = PrintService::params($request->query());
        $doc = PrintService::rekap($student, $p['from'], $p['to'], $p['rows']);

        return $this->view('print/rekap', [
            'docs'  => [$doc],
            'p'     => $p,
            'mode'  => 'single',
            'back'  => ['/tabungan/santri/' . $id, 'Detail Santri'],
            'action' => url('/print/rekap/' . $id),
            'logRef' => 'santri:' . $id,
            'student' => $student,
        ], 'layouts/print');
    }

    /** GET /print/rekap?jenjang=&kelas=&inactive=1&skip_empty=1 — cetak massal satu kelas */
    public function batch(Request $request): Response
    {
        $jenjang = in_array($request->query('jenjang'), ['TK', 'SD'], true) ? (string) $request->query('jenjang') : '';
        $kelas   = mb_substr(mb_strtoupper(trim((string) $request->query('kelas', ''))), 0, 20);
        if ($kelas === '' && $jenjang === '') {
            throw new HttpException(422, 'Pilih jenjang atau kelas terlebih dahulu untuk cetak massal.');
        }
        $p = PrintService::params($request->query());
        $inactive  = !empty($request->query('inactive'));
        $skipEmpty = !empty($request->query('skip_empty'));

        $batch = PrintService::batchStudents($jenjang, $kelas, $inactive);
        if ($batch['over']) {
            throw new HttpException(422, 'Terlalu banyak santri (' . $batch['total'] . '). Maksimal ' . PrintService::MAX_BATCH . ' per cetak; pilih kelas yang lebih spesifik.');
        }
        $docs = [];
        foreach ($batch['students'] as $s) {
            $doc = PrintService::rekap($s, $p['from'], $p['to'], $p['rows']);
            if ($skipEmpty && $doc['count'] === 0 && $doc['opening'] === null) {
                continue;
            }
            $docs[] = $doc;
        }

        $query = array_filter(['jenjang' => $jenjang, 'kelas' => $kelas], static fn ($v) => $v !== '');
        return $this->view('print/rekap', [
            'docs'   => $docs,
            'p'      => $p,
            'mode'   => 'batch',
            'back'   => ['/santri' . ($query ? '?' . http_build_query($query) : ''), 'Data Santri'],
            'action' => url('/print/rekap'),
            'logRef' => 'kelas:' . trim($jenjang . ' ' . $kelas),
            'student' => null,
            'hidden' => $query + ($inactive ? ['inactive' => 1] : []) + ($skipEmpty ? ['skip_empty' => 1] : []),
            'inactive' => $inactive, 'skipEmpty' => $skipEmpty,
        ], 'layouts/print');
    }

    /**
     * GET /print/laporan — cetak laporan umum. FORMAT SEMENTARA: menunggu contoh cetakan dari pengguna.
     * Struktur (layout, pratinjau, tombol cetak, log) sudah siap; isi tabel mudah disesuaikan di views/print/laporan.php.
     */
    public function laporan(Request $request): Response
    {
        $f   = ReportService::filters($request->query());
        $sum = Report::summary($f);
        $user = Auth::user();

        return $this->view('print/laporan', [
            'f'       => $f,
            'sum'     => $sum,
            'period'  => ReportService::periodLabel($f, $sum),
            'filter'  => ReportService::filterLabel($f),
            'classes' => Report::byClass($f),
            'rows'    => Report::allStudents($f),
            'by'      => (string) $user['name'],
            'query'   => array_filter(array_intersect_key($request->query(), array_flip(['from', 'to', 'jenjang', 'kelas', 'month', 'year', 'mutation', 'student_id'])), static fn ($v) => $v !== '' && $v !== '0'),
            'logRef'  => 'laporan',
        ], 'layouts/print');
    }

    /** POST /api/print/log — dicatat saat pengguna menekan Cetak (audit). */
    public function log(Request $request): Response
    {
        $ref  = (string) $request->post('ref', '');
        $user = Auth::user();
        $desc = null;

        if (preg_match('/^santri:(\d+)$/', $ref, $m) && ($s = Student::find((int) $m[1])) !== null) {
            $desc = 'Rekap Tabungan — ' . $s['name'] . ' (' . $s['jenjang'] . ' ' . $s['kelas'] . ')';
        } elseif (preg_match('/^kelas:([A-Za-z0-9 .\-\/]{1,30})$/', $ref, $m)) {
            $desc = 'Rekap Tabungan satu kelas — ' . trim($m[1]);
        } elseif ($ref === 'laporan') {
            $desc = 'Laporan Tabungan';
        }
        if ($desc === null) {
            return $this->error('Referensi cetak tidak dikenal.', 422);
        }
        AuditLog::record('Mencetak', 'Cetak', null, $desc . ' oleh ' . $user['name']);
        return $this->success(null, 'Tercatat');
    }
}
