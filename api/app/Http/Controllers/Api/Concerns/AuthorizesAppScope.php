<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\App;

trait AuthorizesAppScope
{
    /** A per-app child record's app_id must match the app resolved from the URL, or 404. */
    private function authorizeAppOwned(App $app, object $model): void
    {
        abort_unless($model->app_id === $app->app_id, 404);
    }
}
