<?php

namespace App\Support;

use App\Models\App;

/**
 * Shared by Actions (via `authorize()`) whose route resolves a per-app child
 * record ({app}/…/{child}): the child's app_id must match the {app} the URL
 * resolved, or it 404s — hides cross-app existence rather than
 * admitting-but-forbidding. Superseded
 * `App\Http\Controllers\Api\Concerns\AuthorizesAppScope` (identical
 * behavior); `IssueActionController` (Issues batch) was the old copy's last
 * consumer, so that file and the controller were both deleted once Issues
 * moved over — this is now the only copy.
 */
trait AuthorizesAppScope
{
    /** A per-app child record's app_id must match the app resolved from the URL, or 404. */
    private function authorizeAppOwned(App $app, object $model): void
    {
        abort_unless($model->app_id === $app->app_id, 404);
    }
}
