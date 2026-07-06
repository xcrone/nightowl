<?php

use App\Domains\Issues\Actions\AssignIssue;
use App\Domains\Issues\Actions\ListIssueComments;
use App\Domains\Issues\Actions\SetIssuePriority;
use App\Domains\Issues\Actions\ShowIssue;
use App\Domains\Issues\Actions\StoreIssueComment;
use App\Domains\Issues\Actions\TransitionIssueStatus;
use App\Models\App;
use App\Models\Telemetry\Issue;
use Illuminate\Support\Facades\Route;

// Rich issue detail + workflow (docs/pages/issue-detail.md). Registered
// before the generic /{resource}/{id} catch-all (app/Actions/, not yet
// migrated) so /issues/{issue} returns the full drill-down (occurrences/
// activity/env) rather than the raw Issue row.
Route::prefix('apps/{app}')->group(function () {
    Route::get('/issues/{issue}', ShowIssue::class);
    Route::post('/issues/{issue}/assign', AssignIssue::class);
    Route::post('/issues/{issue}/priority', SetIssuePriority::class);

    // TransitionIssueStatus backs all 3 of these with a fixed $newStatus per
    // route — see the Action's docblock for why these are thin closures
    // (real route-model binding on the closure's own typed params) rather
    // than 3 direct `Route::post(..., TransitionIssueStatus::class)`
    // registrations.
    Route::post('/issues/{issue}/resolve', fn (App $app, Issue $issue) => TransitionIssueStatus::run($app, $issue, 'resolved'));
    Route::post('/issues/{issue}/ignore', fn (App $app, Issue $issue) => TransitionIssueStatus::run($app, $issue, 'ignored'));
    Route::post('/issues/{issue}/reopen', fn (App $app, Issue $issue) => TransitionIssueStatus::run($app, $issue, 'open'));

    Route::get('/issues/{issue}/comments', ListIssueComments::class);
    Route::post('/issues/{issue}/comments', StoreIssueComment::class);
});
