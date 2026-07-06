<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Resolves the dashboard period selector (1H/6H/24H/7D/14D/30D) or an
 * explicit from/to range into a [Carbon $from, Carbon $to] window, plus a
 * sensible chart bucket size. Shared by every summary/timeseries/aggregate
 * endpoint so "period" means the same thing everywhere.
 */
class Period
{
    /** period token → seconds */
    public const WINDOWS = [
        '1h' => 3600,
        '6h' => 21600,
        '24h' => 86400,
        '7d' => 604800,
        '14d' => 1209600,
        '30d' => 2592000,
    ];

    /**
     * @param  string  $defaultPeriod  fallback period token when the request
     *                                 carries neither an explicit period nor
     *                                 from/to (default '1h' everywhere except
     *                                 Rollups, which preserves its pre-Actions
     *                                 24h default lookback — see
     *                                 App\Actions\Rollups\IndexRollup).
     * @return array{0: Carbon, 1: Carbon, 2: string} [$from, $to, $period]
     */
    public static function resolve(Request $request, string $defaultPeriod = '1h'): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::now();

        if ($request->filled('from')) {
            $from = Carbon::parse($request->query('from'));

            return [$from, $to, 'custom'];
        }

        $period = (string) $request->query('period', $defaultPeriod);
        $seconds = self::WINDOWS[$period] ?? self::WINDOWS[$defaultPeriod] ?? self::WINDOWS['1h'];
        $period = array_key_exists($period, self::WINDOWS) ? $period : $defaultPeriod;

        return [$to->clone()->subSeconds($seconds), $to, $period];
    }

    /**
     * Bucket size (seconds) for a timeseries over [$from,$to] — aims for
     * ~24–60 buckets so charts stay readable across every window.
     */
    public static function bucketSeconds(Carbon $from, Carbon $to): int
    {
        $span = max(1, $to->getTimestamp() - $from->getTimestamp());
        // ~48 buckets, rounded to a friendly step.
        $target = $span / 48;
        $steps = [60, 300, 900, 1800, 3600, 10800, 21600, 43200, 86400];

        foreach ($steps as $step) {
            if ($target <= $step) {
                return $step;
            }
        }

        return end($steps);
    }
}
