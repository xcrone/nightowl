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
 * POST /api/apps/{app}/issues/{issue}/priority — set an issue's priority,
 * logging a `priority_changed` activity row.
 */
class SetIssuePriority
{
    use AsAction;
    use AuthorizesAppScope;
    use LogsIssueActivity;

    /** See ShowIssue's authorize() docblock re: the route-bound-model DI bug. */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('issue'));

        return true;
    }

    public function rules(): array
    {
        return ['priority' => ['required', 'string']];
    }

    public function handle(App $app, Issue $issue, ActionRequest $request)
    {
        $data = $request->validated();

        $old = $issue->priority;
        $issue->update(['priority' => $data['priority']]);
        $this->logActivity($issue, $request->user(), 'priority_changed', $old, $data['priority']);

        return response()->json((new IssueResource($issue))->resolve());
    }
}
