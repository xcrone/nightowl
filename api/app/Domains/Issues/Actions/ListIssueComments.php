<?php

namespace App\Domains\Issues\Actions;

use App\Domains\Issues\Resources\IssueCommentResource;
use App\Models\App;
use App\Models\Telemetry\Issue;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/** GET /api/apps/{app}/issues/{issue}/comments — this issue's comments, oldest first. */
class ListIssueComments
{
    use AsAction;
    use AuthorizesAppScope;

    /** See ShowIssue's authorize() docblock re: the route-bound-model DI bug. */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('issue'));

        return true;
    }

    public function handle(App $app, Issue $issue)
    {
        return response()->json(IssueCommentResource::collection($issue->comments()->get()));
    }
}
