<?php

namespace App\Domains\Issues\Actions;

use App\Domains\Issues\Resources\IssueCommentResource;
use App\Models\App;
use App\Models\Telemetry\Issue;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/issues/{issue}/comments — add a comment to an issue,
 * stamping the acting user's id/name/email and `actor_type: 'user'`.
 */
class StoreIssueComment
{
    use AsAction;
    use AuthorizesAppScope;

    /** See ShowIssue's authorize() docblock re: the route-bound-model DI bug. */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('issue'));

        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string']];
    }

    public function handle(App $app, Issue $issue, ActionRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $comment = $issue->comments()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'actor_type' => 'user',
            'body' => $data['body'],
        ]);

        return response()->json((new IssueCommentResource($comment))->resolve(), 201);
    }
}
