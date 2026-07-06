<?php

namespace App\Actions\Telemetry;

use App\Models\App;
use App\Support\TelemetryQuery;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/{resource}/{id} — single-record detail for any
 * `config/telemetry.php` resource. See IndexTelemetryResource's docblock for
 * the Group A rationale (no uuid retrofit, raw integer `id`).
 */
class ShowTelemetryResource
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, string $resource, int|string $id)
    {
        $config = TelemetryQuery::resourceConfig($resource);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return response()->json(
            $modelClass::query()->where('app_id', $app->app_id)->findOrFail($id)
        );
    }
}
