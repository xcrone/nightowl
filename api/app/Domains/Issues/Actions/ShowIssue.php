<?php

namespace App\Domains\Issues\Actions;

use App\Domains\Issues\Resources\IssueActivityResource;
use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Support\AuthorizesAppScope;
use App\Support\StackTrace;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/issues/{issue} — full issue drill-down
 * (docs/pages/issue-detail.md): representative exception + stack trace,
 * recent occurrences, per-environment breakdown, and the activity feed.
 * Occurrences correlate by `group_hash` (the dedup key), falling back to
 * exception class for older rows.
 */
class ShowIssue
{
    use AsAction;
    use AuthorizesAppScope;

    /**
     * `authorize()` is resolved via plain container `call()`
     * (lorisleiva/laravel-actions), which does NOT have access to the
     * router's already-substituted route-model bindings — a type-hinted
     * `App $app`/`Issue $issue` parameter here would silently receive a
     * fresh, empty model instead of the real one (the Batch 5 DI bug).
     * Reading them off `$request->route(...)` instead gets the actual bound
     * models, same ones `handle()` receives via its own (route-aware)
     * resolution path.
     */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('issue'));

        return true;
    }

    public function handle(App $app, Issue $issue)
    {
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
                'id' => $issue->id, 'uuid' => $issue->uuid, 'type' => $issue->type, 'status' => $issue->status,
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
            'stack_frames' => StackTrace::parse($representative?->trace),
            'occurrences' => $occurrences,
            'occurrences_by_environment' => $byEnv,
            'activity' => IssueActivityResource::collection($issue->activity()->get()),
        ]);
    }
}
