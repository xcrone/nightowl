<?php

namespace App\Actions\Telemetry;

use App\Models\App;
use App\Support\Period;
use App\Support\TelemetryQuery;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/{resource} — generic paginated list for any
 * `config/telemetry.php` resource. Cross-cutting engine (23 resource types
 * across Group A), not a bounded context — see app/Actions/'s carve-out in
 * the api-domain-dev skill. No uuid retrofit: these nightowl_* tables are
 * agent-owned and this Resource intentionally keeps serializing the
 * integer `id` (see the migration plan's Group A rationale).
 */
class IndexTelemetryResource
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, string $resource, ActionRequest $request)
    {
        $config = TelemetryQuery::resourceConfig($resource);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query()->where('app_id', $app->app_id);

        // Optional period window (logs/issues lists carry the period selector).
        if ($request->filled('period') || $request->filled('from') || $request->filled('to')) {
            [$from, $to] = Period::resolve($request);
            $query->whereBetween('created_at', [$from, $to]);
        }

        foreach ($config['filters'] as $key => $filter) {
            TelemetryQuery::applyFilter($query, $filter, $key, $request);
        }

        // Structural correlation filter (not a per-resource "business"
        // filter, so it isn't declared in $config['filters']): links back to
        // ResourceDetail's "Related" panel — see RelatedTelemetryResource.
        // Both params are required together since execution_id alone is
        // ambiguous across execution sources.
        if (($config['traces_to_parent'] ?? false)
            && $request->filled('execution_source')
            && $request->filled('execution_id')) {
            $query->where('execution_source', $request->query('execution_source'))
                ->where('execution_id', $request->query('execution_id'));
        }

        TelemetryQuery::applySearch($query, $config, $request);
        TelemetryQuery::applySort($query, $config, $request);

        $perPage = min((int) $request->query('per_page', 25), 100);

        return response()->json($query->paginate($perPage)->withQueryString());
    }
}
