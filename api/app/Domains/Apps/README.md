# Apps

## Purpose

The Org → Teams → Apps hierarchy that drives the dashboard's navigation and
the Org Dashboard's health cards (`docs/pages/org-dashboard.md`), plus the
single-App Dashboard summary (`docs/pages/app-dashboard.md`). Dashboard
folds into this domain rather than getting its own: it's a bespoke,
per-App report cohesive with `ListApps`'s `health()` computation (same
"recent-window rollup over one app's telemetry" shape, just a longer
window and richer breakdown) — it isn't config-driven like
`config/telemetry.php`/`config/aggregates.php`, so it doesn't belong in
Group A (`app/Actions/`, Batch 7) either.

## Models

`Org`, `Team`, and `App` live in `app/Models/` (not relocated into this
domain's own `Models/` folder — `App` in particular is consumed by 2+ other
domains and by Group A, so moving it would force cross-domain imports per
the migration plan's "models never physically relocate" rule).
`RequestRecord`, `ExceptionRecord`, `JobRecord`, `Issue`, `NightowlUser`
(all in `app/Models/Telemetry/`, owned by `nightowl/agent`'s migrations)
are read here too, for `ListApps`'s health cards and `ShowDashboard`'s
aggregates — none are written by this domain.

`Org`/`Team`/`Template` each got a `uuid` column this batch (`Schema::table`
retrofit migrations `2026_07_06_120000_add_uuid_to_orgs_table`,
`..._120001_add_uuid_to_teams_table`, `..._120002_add_uuid_to_templates_table`,
backfilled for existing rows, auto-generated on create via each model's
`booted()` `creating` hook) — before this batch, `org.id`/`team.id` were the
only identifiers `OrgController`/`AppController` ever serialized.
`Template` gets the same retrofit even though nothing in this batch reads
it yet (`AppSettingController`'s template endpoints move to `Settings` in
Batch 5) — doing it here keeps all three `app_management` tables' uuid
work in one migration set rather than splitting it across batches.
`App` itself needs **no** retrofit — it already binds/serializes on the
opaque `app_id` (`App::getRouteKeyName`), not the integer `id`.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `ListOrgs` | Orgs the authenticated user belongs to (`$request->user()->orgs()`); falls back to every `Org` if the user has no membership (demo/dev convenience so the dashboard is never empty). | `authorize()` always allows. This endpoint had **zero test coverage** before this migration; `tests/Feature/Apps/OrgApiTest.php` now covers both branches. |
| `ListApps` | The first `Org`'s teams, each with its apps and a live 1h `health()` summary (error rate, 5xx count, exceptions, open issues, connected/disconnected) — drives the Org Dashboard cards. | `authorize()` always allows. `Org::query()->firstOrFail()` — single-org assumption carried forward unchanged from the pre-migration controller (out of scope to fix here). |
| `ShowApp` | One `App` + its `team`/`org`, `loadMissing('team.org')`. | `authorize()` always allows. Route-bound `{app}` (opaque `app_id`) — 404s automatically if unknown. |
| `ShowDashboard` | App Dashboard summary: request volume/latency/status mix, exception counts, job throughput, most-active/most-impacted users — all via three Postgres-specific raw-SQL aggregate queries (`percentile_cont`, `::bigint` casts), preserved exactly from the pre-migration controller (not made portable). Period-aware (`Period::resolve()`). | `authorize()` always allows. No rules — route-bound `{app}`, period resolved from the query string. |

## Resources

- `OrgResource` — `id`, `uuid` (new, additive this pass — `id` stays so no
  existing consumer breaks; a follow-up coordinated with the web side will
  drop it once the SPA keys off `uuid` instead), `name`, `account_email`.
  Used by `ListOrgs` (as a collection) and embedded (via `->resolve()`) in
  `ListApps`'s `org` key and `ShowApp`'s `org` key.
- `TeamResource` — `id`, `uuid` (same additive rule), `name`. Deliberately
  *only* the base Team fields: `ListApps`'s `apps_count`/`apps` keys are a
  computed `health()` summary per app, not a raw relation dump, so they're
  merged onto the base shape at the call site (`array_merge((new
  TeamResource($team))->resolve(), [...])`) rather than living on the
  Resource itself. `ShowApp`'s `team` key uses the same Resource without
  the extra keys.
- `ListApps`'s per-app `health()` payload and `ShowDashboard`'s entire
  summary are pure computed aggregates (GROUP BY / window-function
  results), not 1:1 serialized models — no Resource for those, per the
  migration plan's "pure computed payloads don't need a Resource" rule.
  Neither embeds a raw model either (health() reads scalar `App` fields
  directly into a plain array — `app_id` is already the compliant public
  identifier, so no wrapping is needed there).

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| GET | `/api/orgs` | `ListOrgs` | `auth:sanctum` (root aggregator group) |
| GET | `/api/apps` | `ListApps` | `auth:sanctum` (root aggregator group) |
| GET | `/api/apps/{app}` | `ShowApp` | `auth:sanctum` (root aggregator group) |
| GET | `/api/apps/{app}/dashboard` | `ShowDashboard` | `auth:sanctum` (root aggregator group) |

## Events & cross-module contracts

None. No events emitted or consumed; no `app/Support/` interface used
beyond the telemetry models' own `forApp` scope and `App\Support\Period`.

## Notes

- `ListApps::handle()`'s single-`Org` assumption (`Org::query()->firstOrFail()`)
  and its non-app-scoped nature are pre-existing limitations carried
  forward unchanged from `AppController::index()` — not introduced or
  fixed by this migration.
- Relocated tests (Batch 4 of the controllers → Actions migration):
  `tests/Feature/Apps/AppScopingTest.php`'s `test_apps_endpoint_returns_teams_and_apps`
  / `test_app_show_returns_the_app` / `test_unknown_app_is_not_found` now
  live in `tests/Feature/Apps/AppApiTest.php`. Likewise
  `tests/Feature/Apps/DashboardApiTest.php`'s
  `test_dashboard_summarizes_requests_and_exceptions` now lives in
  `tests/Feature/Apps/AppDashboardApiTest.php`.
  `tests/Feature/Apps/OrgApiTest.php` is new, fixing `ListOrgs`'s
  (`OrgController::index`'s) zero-coverage gap.
  (Batch 7 of the same migration then finished off both original files:
  `AppScopingTest.php`'s remaining telemetry-scoping case moved to
  `tests/Feature/Telemetry/TelemetryApiTest.php` and `DashboardApiTest.php`'s
  remaining timeseries cases moved to
  `tests/Feature/Timeseries/ShowTimeseriesTest.php`, emptying and removing
  both files — `TelemetryController`/`TimeseriesController` are now
  `App\Actions\Telemetry\*`/`App\Actions\Timeseries\ShowTimeseries`.)
