<?php

namespace App\Actions\Exceptions;

use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Support\AggregateKey;
use App\Support\EnvironmentScope;
use App\Support\Period;
use App\Support\StackTrace;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/exception-groups/{key} — the row-level drill-down for the
 * exceptions list (docs/pages/exception-detail.md). Sibling of
 * App\Actions\Aggregates\ShowAggregateDetail, but structurally different (part
 * of the error-tracking family, not the Activity-aggregate family): no
 * percentile toggle, adds the exception detail card (stack trace + runtime
 * badges), an Info JSON block, and a cross-link to the deduplicated Issue.
 *
 * `{key}` is the base64url-encoded exception class (see App\Support\AggregateKey)
 * — the same key the exceptions aggregate list groups by. Occurrences are the
 * raw rows for that class within the period; the associated Issue is resolved
 * via `group_hash` (the dedup key the TelemetrySeeder wires up), falling back
 * to the class when a row predates group hashing.
 */
class ShowExceptionGroup
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, string $key, ActionRequest $request)
    {
        $class = AggregateKey::decode($key);
        [$from, $to, $period] = Period::resolve($request);

        $appId = $app->app_id;
        // Bake the ?environment= page-scope filter into the base query so every
        // clone below (representative, counts, occurrences, info) inherits it.
        $forClass = function () use ($appId, $class, $request) {
            $q = ExceptionRecord::query()->forApp($appId)->where('class', $class);
            EnvironmentScope::apply($q, $request);

            return $q;
        };

        // Representative: latest occurrence in the window, else latest all-time.
        $representative = (clone $forClass())->whereBetween('created_at', [$from, $to])
            ->latest('created_at')->latest('id')->first()
            ?? (clone $forClass())->latest('created_at')->latest('id')->first();

        abort_if($representative === null, 404);

        // Occurrences panel (Handled/Unhandled) scoped to the period window.
        $counts = (clone $forClass())->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) total,
                SUM(CASE WHEN handled THEN 1 ELSE 0 END) handled,
                SUM(CASE WHEN handled THEN 0 ELSE 1 END) unhandled')
            ->first();

        // Paginated occurrence rows (raw) within the window. Floor at 1 so a
        // ?per_page=0 (or negative) can't reach paginate(0) -> DivisionByZero.
        $perPage = max(min((int) $request->query('per_page', 25), 100), 1);
        $occurrences = (clone $forClass())->whereBetween('created_at', [$from, $to])
            ->latest('created_at')->paginate($perPage)->withQueryString();

        return response()->json([
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
            'key' => $key,
            'class' => $class,
            'message' => $representative->message,
            'handled' => (bool) $representative->handled,
            'file' => $representative->file,
            'line' => $representative->line,
            'php_version' => $representative->php_version,
            'laravel_version' => $representative->laravel_version,
            'stack_frames' => StackTrace::parse($representative->trace),
            'issue' => $this->issue($appId, $representative),
            'panels' => [
                'occurrences' => [
                    'total' => (int) ($counts->total ?? 0),
                    'handled' => (int) ($counts->handled ?? 0),
                    'unhandled' => (int) ($counts->unhandled ?? 0),
                ],
            ],
            'info' => $this->info($appId, $class, $forClass),
            'occurrences' => $occurrences,
        ]);
    }

    /** The deduplicated Issue this exception belongs to (for "View issue"). */
    private function issue(string $appId, ExceptionRecord $representative): ?array
    {
        $issue = Issue::query()->forApp($appId)
            ->when(
                $representative->group_hash,
                fn ($q) => $q->where('group_hash', $representative->group_hash),
                fn ($q) => $q->where('exception_class', $representative->class),
            )
            ->first();

        return $issue ? ['id' => $issue->id, 'uuid' => $issue->uuid] : null;
    }

    /** The Info JSON block (docs/pages/exception-detail.md). */
    private function info(string $appId, string $class, callable $forClass): array
    {
        $now = Carbon::now();

        // One conditional-aggregate pass: the class span (first/last/impacted)
        // plus both windowed occurrence counts, instead of three scans.
        $span = (clone $forClass())
            ->selectRaw(
                'MIN(created_at) first_seen, MAX(created_at) last_seen,
                COUNT(DISTINCT user_id) impacted_users,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) occurrences_24h,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) occurrences_7d',
                [$now->copy()->subDay(), $now->copy()->subDays(7)]
            )
            ->first();

        $first = (clone $forClass())->oldest('created_at')->first();

        return [
            'first_seen' => optional($span->first_seen ? Carbon::parse($span->first_seen) : null)->toIso8601String(),
            'last_seen' => optional($span->last_seen ? Carbon::parse($span->last_seen) : null)->toIso8601String(),
            'first_reported_in' => $first && $first->execution_source
                ? trim($first->execution_source.': '.($first->execution_preview ?? ''))
                : null,
            'impacted_users' => (int) ($span->impacted_users ?? 0),
            'occurrences_24h' => (int) ($span->occurrences_24h ?? 0),
            'occurrences_7d' => (int) ($span->occurrences_7d ?? 0),
            'servers' => (clone $forClass())->whereNotNull('server')->distinct()->pluck('server')->all(),
        ];
    }
}
