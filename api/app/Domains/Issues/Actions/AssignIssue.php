<?php

namespace App\Domains\Issues\Actions;

use App\Domains\Issues\Actions\Concerns\LogsIssueActivity;
use App\Domains\Issues\Resources\IssueResource;
use App\Models\App;
use App\Models\Telemetry\Issue;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/issues/{issue}/assign — set/clear an issue's assignee,
 * logging an `assigned` activity row.
 */
class AssignIssue
{
    use AsAction;
    use AuthorizesAppScope;
    use LogsIssueActivity;

    /**
     * See ShowIssue's authorize() docblock: reads the route-bound models off
     * `$request->route(...)` rather than type-hinting them directly, since
     * `authorize()`/`rules()` are resolved via plain container `call()` (no
     * access to the router's already-substituted bindings) — a type-hinted
     * `App $app`/`Issue $issue` parameter here would silently receive a
     * fresh, empty model instead of the real one.
     */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('issue'));

        return true;
    }

    public function rules(): array
    {
        return ['assigned_to' => ['nullable', 'string']];
    }

    public function handle(App $app, Issue $issue, ActionRequest $request)
    {
        $data = $request->validated();

        $old = $issue->assigned_to;
        $issue->update(['assigned_to' => $data['assigned_to'] ?? null]);
        $this->logActivity($issue, $request->user(), 'assigned', $old, $issue->assigned_to);

        return response()->json((new IssueResource($issue))->resolve());
    }
}
