<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Alumni;
use App\Models\SyncState;

/** Tabungan Alumni: "Tarik Data" dalam bentuk Detail (per alumni) atau Rekap (agregat). Hanya baca. */
final class AlumniService
{
    public const PER_PAGE = [10, 25, 50];

    public static function filtersFromQuery(array $q): array
    {
        $sort = in_array($q['sort'] ?? '', ['name', 'year', 'kelas', 'masuk', 'keluar', 'saldo'], true) ? (string) $q['sort'] : 'year';
        $per  = (int) ($q['per_page'] ?? 25);
        $year = (int) ($q['year'] ?? 0);
        $ay   = (string) ($q['academic_year'] ?? '');

        return [
            'mode'          => ($q['mode'] ?? '') === 'rekap' ? 'rekap' : 'detail',
            'q'             => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($q['q'] ?? ''))), 0, 100),
            'year'          => ($year >= 2000 && $year <= 2100) ? $year : 0,
            'academic_year' => ClassLadder::parseYear($ay) !== null ? $ay : '',
            'sort'          => $sort,
            'dir'           => strtolower((string) ($q['dir'] ?? ($sort === 'year' ? 'desc' : 'asc'))) === 'desc' ? 'desc' : 'asc',
            'page'          => max(1, (int) ($q['page'] ?? 1)),
            'per_page'      => in_array($per, self::PER_PAGE, true) ? $per : 25,
        ];
    }

    public static function listing(array $query): array
    {
        $rev = SyncState::revision(['students', 'savings']);
        $f   = self::filtersFromQuery($query);

        $out = [
            'rev'     => $rev,
            'filters' => $f,
            'options' => Alumni::options(),
            'summary' => Alumni::summary($f),
            'items'   => [],
            'recap'   => [],
            'recap_by' => $f['year'] > 0 ? 'kelas' : 'tahun',
            'total'   => 0,
            'page'    => 1,
            'pages'   => 1,
        ];

        if ($f['mode'] === 'rekap') {
            $out['recap'] = Alumni::recap($f, $out['recap_by'] === 'kelas' ? 'kelas' : 'year');
            return $out;
        }

        $res   = Alumni::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        $pages = max(1, (int) ceil($res['total'] / $f['per_page']));
        if ($f['page'] > $pages) {
            $f['page'] = $pages;
            $res = Alumni::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
            $out['filters'] = $f;
        }
        return [
            'items' => $res['items'], 'total' => $res['total'], 'page' => $f['page'], 'pages' => $pages,
        ] + $out;
    }
}
