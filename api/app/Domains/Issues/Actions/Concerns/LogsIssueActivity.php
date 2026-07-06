<?php

namespace App\Domains\Issues\Actions\Concerns;

use App\Models\Telemetry\Issue;
use App\Models\Telemetry\IssueActivity;
use App\Models\User;

/**
 * Shared by AssignIssue/SetIssuePriority/TransitionIssueStatus — creates the
 * `IssueActivity` row an issue-mutating action leaves behind, exactly as
 * `IssueActionController::logActivity()`/`transition()` did before this
 * migration.
 */
trait LogsIssueActivity
{
    private function logActivity(Issue $issue, User $user, string $action, mixed $old, mixed $new): void
    {
        IssueActivity::create([
            'issue_id' => $issue->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'actor_type' => 'user',
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'created_at' => now(),
        ]);
    }
}
