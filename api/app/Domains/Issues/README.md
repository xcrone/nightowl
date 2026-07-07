# Issues

## Purpose

The per-app issue-detail drill-down and workflow (`docs/pages/issue-detail.md`):
a representative exception + parsed stack trace, recent occurrences, a
per-environment breakdown, the activity feed, assignment/priority controls,
status transitions (resolve/ignore/reopen), and comments. Ported from
`IssueActionController` (8 endpoints).

## Models

`Issue`, `IssueActivity`, `IssueComment` (all `app/Models/Telemetry/`, tables
`nightowl_issues`/`nightowl_issue_activity`/`nightowl_issue_comments`) and
`ExceptionRecord` (`app/Models/Telemetry/`, table `nightowl_exceptions`, used
read-only here to correlate occurrences) all **stay** in `app/Models/Telemetry/`
— not relocated into this domain's own `Models/` folder, per the migration
plan's "models never physically relocate" rule (these are consumed by 2+
domains/Group A, or relocating would touch factories/seed data for no
behavioral benefit).

- `Issue`/`IssueActivity`/`IssueComment` are the **cross-repo** uuid retrofit
  in this batch: all three tables are created by `nightowl/agent`'s own
  migrations (`agent/database/migrations/2024_01_01_000014_create_nightowl_issues_table.php`
  and `..._000017_add_issues_comments_and_activity.php`), consumed here via
  the local Composer path repository and the `nightowl` connection. Their
  `uuid` columns were added by one combined ALTER migration on the **api**
  side — `database/migrations/2026_07_06_140000_add_uuid_to_nightowl_issues_tables.php`
  — targeting the `nightowl` connection explicitly (`protected $connection =
  'nightowl';`, `Schema::connection($this->connection)->table(...)`, never
  `Schema::create`), looping the same nullable-then-backfill pattern across
  all three tables (combined into one file rather than three, since all three
  get identical treatment — see the migration's own docblock). Each model
  gets a `creating` hook (`booted()`) that stamps a fresh `uuid` on create;
  existing rows were backfilled by the migration itself. The user explicitly
  signed off on this cross-repo schema touch when approving the controllers
  → Actions migration plan; `agent/`'s own migrations are untouched.
- **Route binding stays on `id`** for `/issues/{issue}` this pass (`Issue`
  doesn't override `getRouteKeyName()`) — the migration plan's uuid retrofit
  is additive this pass (`uuid` is a new column, serialized alongside the
  existing `id`), not a route-binding cutover. A follow-up coordinated with
  the web side will switch the URL shape and drop `id` from the Resources.
- `ExceptionRecord` (`nightowl_exceptions`) is **not** part of this uuid
  retrofit (explicitly out of scope per the migration plan's table) — it's a
  Group A/telemetry-owned table, not migrated yet. `ShowIssue`'s `occurrences`
  array keeps serializing `ExceptionRecord.id` as a curated projection, same
  as the pre-migration controller.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `ShowIssue` | Full drill-down: representative exception (latest by `created_at`, correlated by `group_hash` falling back to `exception_class`), last-20 occurrences, per-environment counts, the issue payload, parsed stack frames, and the activity feed. | `authorize()` uses `AuthorizesAppScope` (see Notes re: the DI bug). No rules — pure read. |
| `AssignIssue` | Sets/clears `assigned_to`, logs an `assigned` activity row. | `authorize()` uses `AuthorizesAppScope`. `rules()`: `assigned_to` nullable string. |
| `SetIssuePriority` | Sets `priority`, logs a `priority_changed` activity row. | `authorize()` uses `AuthorizesAppScope`. `rules()`: `priority` required string. |
| `TransitionIssueStatus` | Backs `resolve`/`ignore`/`reopen` — one Action taking a `$newStatus` param (`'resolved'`/`'ignored'`/`'open'`), since all three shared identical `authorize()`/`rules()`/transition logic in the old controller. No-op if the status is unchanged; else updates `status` and logs a `status_changed` activity row. | **Not** routed as a normal AsAction controller — see Notes. |
| `ListIssueComments` | This issue's comments, oldest first. | `authorize()` uses `AuthorizesAppScope`. No rules. |
| `StoreIssueComment` | Creates a comment, stamping the acting user's `user_id`/`user_name`/`user_email` and `actor_type: 'user'`. | `authorize()` uses `AuthorizesAppScope`. `rules()`: `body` required string. |

`AuthorizesAppScope` (`app/Support/AuthorizesAppScope.php`) is reused
identically from Settings — `abort_unless($model->app_id === $app->app_id,
404)`, hiding cross-app existence rather than admitting-but-forbidding. This
batch was the **last consumer** of the old
`app/Http/Controllers/Api/Concerns/AuthorizesAppScope.php` copy
(`IssueActionController`'s own trait use) — both that file and the
controller were deleted in this batch; `app/Support/AuthorizesAppScope.php`
(moved there in the Settings batch) is now the only copy.

`LogsIssueActivity` (`Actions/Concerns/LogsIssueActivity.php`) is a small
shared trait (`AssignIssue`/`SetIssuePriority`/`TransitionIssueStatus`) that
creates the `IssueActivity` row, exactly as the old controller's private
`logActivity()`/`transition()` did.

## Resources

- `IssueResource` — full `Issue` attribute set (`id` kept, additive `uuid`
  alongside it this pass — no existing consumer breaks), matching the same
  shape the pre-migration `assign`/`priority`/`resolve`/`ignore`/`reopen`
  endpoints returned via a raw `response()->json($issue)` model dump, minus
  `search_vector` (the internal Postgres tsvector generated column — dropping
  its accidental leak is a minor, behavior-safe cleanup, not a functional
  regression for any known consumer). Used by `AssignIssue`, `SetIssuePriority`,
  `TransitionIssueStatus`.
- `IssueActivityResource` — the activity-feed projection (`id`, `uuid`,
  `actor_type`, `actor_name` aliasing `user_name`, `action`, `old_value`,
  `new_value`, `created_at` formatted `->toIso8601String()`), same curated
  shape `ShowIssue` returns exactly as the old controller did. Used by
  `ShowIssue`'s `activity` key.
- `IssueCommentResource` — full `IssueComment` attribute set (`id` kept,
  additive `uuid`), matching the pre-migration `comments`/`storeComment`
  raw model dumps. Used by `ListIssueComments`, `StoreIssueComment`.
- `ShowIssue`'s `issue`/`stack_frames`/`occurrences`/`occurrences_by_environment`
  payload nodes stay hand-built arrays (not `IssueResource`) — the `issue`
  node merges `Issue` columns with computed fields sourced from the
  *representative exception* (`file`/`line`/`php_version`/`laravel_version`/
  `handled`), so it isn't a 1:1 `Issue` projection; per the migration plan's
  "pure computed payloads don't need a Resource" carve-out. `uuid` was added
  to that node directly for retrofit consistency. The `stack_frames` node is
  parsed by the shared `App\Support\StackTrace::parse` (the single source of
  truth also used by `App\Actions\Exceptions\ShowExceptionGroup`) — `ShowIssue`
  no longer carries its own inlined copy of that parser.

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| GET | `/api/apps/{app}/issues/{issue}` | `ShowIssue` | `auth:sanctum` |
| POST | `/api/apps/{app}/issues/{issue}/assign` | `AssignIssue` | `auth:sanctum` |
| POST | `/api/apps/{app}/issues/{issue}/priority` | `SetIssuePriority` | `auth:sanctum` |
| POST | `/api/apps/{app}/issues/{issue}/resolve` | `TransitionIssueStatus` (`$newStatus = 'resolved'`) | `auth:sanctum` |
| POST | `/api/apps/{app}/issues/{issue}/ignore` | `TransitionIssueStatus` (`$newStatus = 'ignored'`) | `auth:sanctum` |
| POST | `/api/apps/{app}/issues/{issue}/reopen` | `TransitionIssueStatus` (`$newStatus = 'open'`) | `auth:sanctum` |
| GET | `/api/apps/{app}/issues/{issue}/comments` | `ListIssueComments` | `auth:sanctum` |
| POST | `/api/apps/{app}/issues/{issue}/comments` | `StoreIssueComment` | `auth:sanctum` |

Same URL shapes as the pre-migration `IssueActionController` routes.
Registered before the generic `/{resource}/{id}` catch-all (Group A,
`app/Actions/`, not yet migrated) so `/issues/{issue}` returns the full
drill-down rather than the raw `Issue` row.

## Events & cross-module contracts

None. No events emitted or consumed; no `app/Support/` interface used beyond
`App\Support\AuthorizesAppScope`.

## Notes

- **Batch 5's DI bug, checked and handled for every Action**:
  `lorisleiva/laravel-actions`'s `authorize()`/`rules()` are resolved via
  plain container `call()`, which instantiates **empty** model instances for
  route-bound Eloquent params rather than the router's real bound ones (only
  `handle()` gets the genuine bound model). `ShowIssue`, `AssignIssue`,
  `SetIssuePriority`, `ListIssueComments`, `StoreIssueComment` all read the
  route-bound `App`/`Issue` off `$request->route('app')`/`$request->route('issue')`
  inside `authorize()` instead of type-hinting them as method parameters —
  same fix as Settings. Explicit cross-app-404 tests exist for all 8
  endpoints (see `tests/Feature/Issues/IssueApiTest.php`) to prove it, not
  just trust it compiles.
- **`TransitionIssueStatus` sidesteps the bug differently, by design**: three
  fixed statuses (`resolved`/`ignored`/`open`) need to share one Action
  class, and neither routing directly to the Action class with a
  `Route::defaults()` value nor any other lorisleiva mechanism cleanly
  threads a per-route scalar into an AsAction controller's `handle()`
  (`Route::defaults()` populates `$route->defaults`, which
  `Route::parameters()`/`parametersWithoutNulls()` never merges in — so a
  type-hinted `string $newStatus` `handle()` arg would never actually receive
  it via lorisleiva's `ControllerDecorator::resolveFromRouteAndCall`).
  Instead, `Routes/api.php` registers 3 thin closures — one per fixed status
  — each type-hinting `App $app`/`Issue $issue` directly; Laravel's own
  router performs real implicit route-model binding on those closure
  parameters (the same mechanism a controller method gets), so `handle()`
  always receives the genuine bound models regardless of lorisleiva's
  container-`call()` quirk. `authorize()`/`rules()` are never invoked for
  this Action (the closures call `TransitionIssueStatus::run($app, $issue,
  $status)` directly, not the HTTP controller pipeline) — the app-scope check
  happens inline in `handle()` via `AuthorizesAppScope`, using the same real
  models the closure was already given. This avoids the DI bug entirely
  rather than working around it.
- Relocated tests (Batch 6 of the controllers → Actions migration):
  `tests/Feature/Apps/IssueUserDetailTest.php`'s Issues-half content moved to
  `tests/Feature/Issues/IssueApiTest.php` (the UserDetail-half was already
  relocated to `tests/Feature/Users/` in Batch 3, leaving the old file with
  only Issues content, so the old file is deleted rather than emptied-out).
  The relocated file's original cross-app-404 coverage only checked `show`/
  `resolve`/`assign`/`comments` (get+post) — `priority`, `ignore`, `reopen`
  had no explicit cross-app-404 test before this migration; added them here
  given the DI-bug risk called out above.
