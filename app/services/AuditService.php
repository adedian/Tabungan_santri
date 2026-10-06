<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use DateTimeImmutable;

final class AuditService
{
    public const PER_PAGE = [25, 50, 100];

    /** Normalisasi query string. Nilai tak sah diabaikan (bukan error); rentang terbalik ditukar. */
    public static function filtersFromQuery(array $q): array
    {
        $date = static function (mixed $v): string {
            $v = is_string($v) ? trim($v) : '';
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : '';
        };
        $from = $date($q['from'] ?? '');
        $to   = $date($q['to'] ?? '');
        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $sort = in_array($q['sort'] ?? '', ['time', 'user', 'module', 'action'], true) ? (string) $q['sort'] : 'time';
        $dir  = strtolower((string) ($q['dir'] ?? ($sort === 'time' ? 'desc' : 'asc'))) === 'desc' ? 'desc' : 'asc';
        $per  = (int) ($q['per_page'] ?? 50);

        return [
            'q'        => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($q['q'] ?? ''))), 0, 100),
            'module'   => mb_substr(trim((string) ($q['module'] ?? '')), 0, 50),
            'user_id'  => max(0, (int) ($q['user_id'] ?? 0)),
            'from'     => $from,
            'to'       => $to,
            'sort'     => $sort,
            'dir'      => $dir,
            'page'     => max(1, (int) ($q['page'] ?? 1)),
            'per_page' => in_array($per, self::PER_PAGE, true) ? $per : 50,
        ];
    }

    /** Data halaman Audit Log (dipakai render awal dan API). */
    public static function listing(array $query): array
    {
        $f   = self::filtersFromQuery($query);
        $res = AuditLog::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);

        $pages = max(1, (int) ceil($res['total'] / $f['per_page']));
        if ($f['page'] > $pages) {
            $f['page'] = $pages;
            $res = AuditLog::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        }

        return [
            'items'   => $res['items'],
            'total'   => $res['total'],
            'summary' => $res['summary'],
            'page'    => $f['page'],
            'pages'   => $pages,
            'filters' => $f,
            'options' => AuditLog::filterOptions(),
        ];
    }
}
