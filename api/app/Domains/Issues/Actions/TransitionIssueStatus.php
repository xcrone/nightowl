<?php

namespace App\Domains\Issues\Actions;

use App\Domains\Issues\Actions\Concerns\LogsIssueActivity;
use App\Domains\Issues\Resources\IssueResource;
use App\Models\App;
use App\Models\Telemetry\Issue;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Backs POST /api/apps/{app}/issues/{issue}/{resolve,ignore,reopen} — the
 * old `IssueActionController::resolve/ignore/reopen` all delegated to the
 * same private `transition($request, $issue, $newStatus)`; this Action is
 * that shared logic, parameterized by `$newStatus`. No-op (returns the issue
 * unchanged) if the status doesn't actually change, else updates `status`
 * and logs a `status_changed` activity row.
 *
 * Deliberately **not** routed as a normal AsAction controller (no
 * `authorize()`/`rules()` invoked by lorisleiva's pipeline): three fixed
 * statuses need to reuse one Action class, and neither lorisleiva route
 * registration nor Laravel's `Route::defaults()` cleanly thread a
 * per-route-registration scalar into an AsAction controller's `handle()`
 * (`Route::defaults()` values live in `$route->defaults`, which
 * `Route::parameters()`/`parametersWithoutNulls()` never merges in — so a
 * type-hinted `string $newStatus` handle() arg would never actually receive
 * it). Instead, `Routes/api.php` registers 3 thin closures, one per fixed
 * status, each type-hinting `App $app`/`Issue $issue` directly — Laravel's
 * own router performs real implicit route-model binding on those closure
 * parameters (the same mechanism a controller method gets), so `handle()`
 * always receives the genuine bound models. This sidesteps the Batch 5 DI
 * bug entirely (by never going through lorisleiva's container-`call()`-based
 * `authorize()`/`rules()` resolution) rather than working around it — the
 * app-scope check happens directly in `handle()` via `AuthorizesAppScope`,
 * using the same real models the closure was already given.
 */
class TransitionIssueStatus
{
    use AsAction;
    use AuthorizesAppScope;
    use LogsIssueActivity;

    public function handle(App $app, Issue $issue, string $newStatus)
    {
        $this->authorizeAppOwned($app, $issue);

        $oldStatus = $issue->status;

        if ($oldStatus === $newStatus) {
            return response()->json((new IssueResource($issue))->resolve());
        }

        $issue->update(['status' => $newStatus]);
        $this->logActivity($issue, request()->user(), 'status_changed', $oldStatus, $newStatus);

        return response()->json((new IssueResource($issue))->resolve());
    }
}
