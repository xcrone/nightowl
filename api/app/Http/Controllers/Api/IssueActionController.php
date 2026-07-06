<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesAppScope;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\IssueActivity;
use Illuminate\Http\Request;

class IssueActionController extends Controller
{
    use AuthorizesAppScope;

    /**
     * Full issue drill-down (docs/pages/issue-detail.md): representative
     * exception + stack trace, recent occurrences, per-environment breakdown,
     * and the activity feed. Occurrences correlate by group_hash (the dedup
     * key), falling back to exception class for older rows.
     */
    public function show(App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        $occurrenceQuery = fn () => ExceptionRecord::query()->forApp($app->app_id)
            ->when(
                $issue->group_hash,
                fn ($q) => $q->where('group_hash', $issue->group_hash),
                fn ($q) => $q->where('class', $issue->exception_class),
            );

        $representative = (clone $occurrenceQuery())->latest('created_at')->first();

        $occurrences = (clone $occurrenceQuery())->latest('created_at')->limit(20)->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'created_at' => optional($e->created_at)->toIso8601String(),
                'source' => $e->execution_source,
                'source_label' => $e->execution_preview,
                'message' => $e->message,
                'user_id' => $e->user_id,
            ]);

        $byEnv = (clone $occurrenceQuery())
            ->selectRaw('environment, COUNT(*) count')->groupBy('environment')->get()
            ->map(fn ($r) => ['environment' => $r->environment, 'count' => (int) $r->count]);

        return response()->json([
            'issue' => [
                'id' => $issue->id, 'type' => $issue->type, 'status' => $issue->status,
                'priority' => $issue->priority, 'exception_class' => $issue->exception_class,
                'exception_message' => $issue->exception_message,
                'file' => $representative?->file, 'line' => $representative?->line,
                'first_seen_at' => optional($issue->first_seen_at)->toIso8601String(),
                'last_seen_at' => optional($issue->last_seen_at)->toIso8601String(),
                'occurrences_count' => (int) $issue->occurrences_count,
                'users_count' => (int) $issue->users_count,
                'assigned_to' => $issue->assigned_to,
                'php_version' => $representative?->php_version,
                'laravel_version' => $representative?->laravel_version,
                'handled' => (bool) ($representative?->handled ?? false),
            ],
            'stack_frames' => $this->parseStackFrames($representative?->trace),
            'occurrences' => $occurrences,
            'occurrences_by_environment' => $byEnv,
            'activity' => $issue->activity()->get()->map(fn ($a) => [
                'id' => $a->id, 'actor_type' => $a->actor_type, 'actor_name' => $a->user_name,
                'action' => $a->action, 'old_value' => $a->old_value, 'new_value' => $a->new_value,
                'created_at' => optional($a->created_at)->toIso8601String(),
            ]),
        ]);
    }

    public function assign(Request $request, App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        $data = $request->validate(['assigned_to' => ['nullable', 'string']]);
        $old = $issue->assigned_to;
        $issue->update(['assigned_to' => $data['assigned_to'] ?? null]);
        $this->logActivity($request, $issue, 'assigned', $old, $issue->assigned_to);

        return response()->json($issue);
    }

    public function priority(Request $request, App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        $data = $request->validate(['priority' => ['required', 'string']]);
        $old = $issue->priority;
        $issue->update(['priority' => $data['priority']]);
        $this->logActivity($request, $issue, 'priority_changed', $old, $data['priority']);

        return response()->json($issue);
    }

    /** Light parse of a PHP stack-trace string into file:line frames. */
    private function parseStackFrames(?string $trace): array
    {
        if (! $trace) {
            return [];
        }

        $frames = [];
        foreach (preg_split('/\r?\n/', $trace) as $i => $line) {
            if (preg_match('/(#\d+\s+)?(.+?)\((\d+)\)(:\s*(.*))?/', $line, $m)) {
                $frames[] = [
                    'index' => $i,
                    'file' => trim($m[2]),
                    'line' => (int) $m[3],
                    'function' => trim($m[5] ?? ''),
                ];
            }
        }

        return $frames;
    }

    private function logActivity(Request $request, Issue $issue, string $action, $old, $new): void
    {
        $user = $request->user();

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

    public function resolve(Request $request, App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        return $this->transition($request, $issue, 'resolved');
    }

    public function ignore(Request $request, App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        return $this->transition($request, $issue, 'ignored');
    }

    public function reopen(Request $request, App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        return $this->transition($request, $issue, 'open');
    }

    public function comments(App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

        return response()->json($issue->comments()->get());
    }

    public function storeComment(Request $request, App $app, Issue $issue)
    {
        $this->authorizeAppOwned($app, $issue);

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
