<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\IssueActivity;
use App\Models\Telemetry\IssueComment;
use Illuminate\Http\Request;

class IssueActionController extends Controller
{
    public function resolve(Request $request, Issue $issue)
    {
        return $this->transition($request, $issue, 'resolved');
    }

    public function ignore(Request $request, Issue $issue)
    {
        return $this->transition($request, $issue, 'ignored');
    }

    public function reopen(Request $request, Issue $issue)
    {
        return $this->transition($request, $issue, 'open');
    }

    public function comments(Issue $issue)
    {
        return response()->json($issue->comments()->get());
    }

    public function storeComment(Request $request, Issue $issue)
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $user = $request->user();

        $comment = $issue->comments()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'actor_type' => 'user',
            'body' => $data['body'],
        ]);

        return response()->json($comment, 201);
    }

    protected function transition(Request $request, Issue $issue, string $newStatus)
    {
        $oldStatus = $issue->status;

        if ($oldStatus === $newStatus) {
            return response()->json($issue);
        }

        $issue->update(['status' => $newStatus]);

        $user = $request->user();

        IssueActivity::create([
            'issue_id' => $issue->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'actor_type' => 'user',
            'action' => 'status_changed',
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'created_at' => now(),
        ]);

        return response()->json($issue);
    }
}
