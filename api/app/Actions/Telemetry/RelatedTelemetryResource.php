<?php

namespace App\Actions\Telemetry;

use App\Models\App;
use App\Support\TelemetryQuery;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/{resource}/{id}/related — Telescope-style correlation:
 * what else happened in the same request/job/command/scheduled-task as this
 * record, and what originated it.
 *
 * See config/telemetry.php's "Trace correlation" block for how
 * `parent_key`/`parent_source`/`traces_to_parent` map the two sides of the
 * relationship (an origin's own identity vs. a child's
 * execution_source/execution_id pointer back to it).
 */
class RelatedTelemetryResource
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, string $resource, int|string $id)
    {
        $config = TelemetryQuery::resourceConfig($resource);
        $appId = $app->app_id;

        /** @var Model $record */
        $record = $config['model']::query()->where('app_id', $appId)->findOrFail($id);

        $originResources = array_filter(
            config('telemetry.resources'),
            fn (array $cfg) => isset($cfg['parent_key'])
        );

        $sourceToResource = [];
        foreach ($originResources as $key => $cfg) {
            $sourceToResource[$cfg['parent_source']] = $key;
        }

        $origin = null;

        if (($config['traces_to_parent'] ?? false) && $record->execution_source && $record->execution_id) {
            $originKey = $sourceToResource[$record->execution_source] ?? null;

            if ($originKey !== null) {
                $originConfig = $originResources[$originKey];
                $originRecord = $originConfig['model']::where('app_id', $appId)
                    ->where($originConfig['parent_key'], $record->execution_id)->first();

                if ($originRecord) {
                    $origin = ['resource' => $originKey, 'record' => $originRecord];
                }
            }
        }

        // Fallback: a processed job attempt has no execution_source/_id of
        // its own (it *is* an origin), but Nightwatch propagates the
        // dispatching request/command's trace_id through the queue, so it
        // can still be traced back to what originally queued it.
        if ($origin === null && $record->trace_id) {
            foreach (['requests', 'commands', 'scheduled-tasks'] as $originKey) {
                if ($originKey === $resource) {
                    continue;
                }

                $originConfig = $originResources[$originKey];
                $originRecord = $originConfig['model']::where('app_id', $appId)
                    ->where('trace_id', $record->trace_id)->first();

                if ($originRecord) {
                    $origin = ['resource' => $originKey, 'record' => $originRecord];
                    break;
                }
            }
        }

        $childrenFilter = null;

        if (isset($config['parent_key']) && $record->{$config['parent_key']} !== null) {
            // Origin record (request/command/scheduled-task, or a processed
            // job attempt): its own identity column is what children point at.
            $childrenFilter = [
                'execution_source' => $config['parent_source'],
                'execution_id' => $record->{$config['parent_key']},
            ];
        } elseif (($config['traces_to_parent'] ?? false) && $record->execution_source && $record->execution_id) {
            // Child record (query/cache-event/mail/notification/log/
            // exception/outgoing-request, or a job dispatch-event): siblings
            // share this record's own execution_source/execution_id.
            $childrenFilter = [
                'execution_source' => $record->execution_source,
                'execution_id' => $record->execution_id,
            ];
        }

        $children = [];

        if ($childrenFilter !== null) {
            foreach (config('telemetry.resources') as $key => $cfg) {
                if (! ($cfg['traces_to_parent'] ?? false)) {
                    continue;
                }

                $query = $cfg['model']::where('app_id', $appId)
                    ->where('execution_source', $childrenFilter['execution_source'])
                    ->where('execution_id', $childrenFilter['execution_id']);

                if ($key === $resource) {
                    $query->where($record->getKeyName(), '!=', $record->getKey());
                }

                $count = $query->count();

                if ($count > 0) {
                    $children[$key] = $count;
                }
            }
        }

        return response()->json([
            'origin' => $origin ? ['resource' => $origin['resource'], 'record' => $origin['record']] : null,
            'children_filter' => $childrenFilter,
            'children' => $children,
        ]);
    }
}
