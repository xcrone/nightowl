# api/ — NightOwl Laravel Sanctum API

Laravel 13 + Sanctum. See root `CLAUDE.md` for how this fits into the monorepo (the
Org → Teams → Apps dashboard architecture, multi-app scoping via `app_id`).

## Current architecture

- `app/Http/Controllers/` no longer holds any API entrypoints — every former
  controller (`TelemetryController`, `AggregateController`, `RollupController`,
  `TimeseriesController`, `AgentHealthController`, `OrgController`, `AppController`,
  `DashboardController`, `AppSettingController`, `AlertChannelController`,
  `IssueActionController`, `AuthController`, `NightowlUserController`,
  `UserDetailController`, `DataManagementController`) has been ported to a
  `lorisleiva/laravel-actions` Action. `app/Http/Controllers/Api/` was deleted once
  empty.
- `app/Domains/{DataManagement,Auth,Users,Apps,Settings,Issues}/` — DDD bounded-context
  modules. Every HTTP entrypoint is an Action (`authorize() → rules() → handle()`),
  routed from the module's own `Routes/api.php`, returning API Resources (never raw
  models). See the `api-domain-dev` skill for the folder shape and each domain's
  `README.md` for its endpoints/business logic.
- `app/Actions/{Telemetry,Aggregates,Rollups,Timeseries,Health}/` — 5 cross-cutting,
  config-driven engines, deliberately **not** a Domain (no distinct business rules, no
  api-owned schema — all `nightowl_*` tables belong to `agent/`). They serve every
  telemetry/aggregate resource off two registries: `config/telemetry.php` (raw
  lists/detail/related) and `config/aggregates.php` (per-key aggregated lists, computed
  on the fly with GROUP BY + Postgres `percentile_cont`). Period windows resolve
  through `App\Support\Period`. Extending either registry for a new resource type is
  still the sanctioned path — that alone is not a reason to create a new Domain.
- `app/Models/Telemetry/` — Eloquent models for the `nightowl_*` tables (owned by
  `nightowl/agent`'s migrations, consumed here via `App\Models\App::scopeForApp`).
  Models were not relocated into `app/Domains/`/`app/Actions/` folders — several are
  consumed by 2+ modules, so they stay in their shared namespace.
- Auth is Sanctum SPA cookies (`localhost`, not `127.0.0.1`).
- Tests: PHPUnit (not Pest) under `tests/Feature/<Module>` (mirrors the
  `app/Domains/`/`app/Actions/` split — e.g. `tests/Feature/Issues`,
  `tests/Feature/Aggregates`) and `tests/Unit` — see
  `api/tests/Feature/Telemetry/TelemetryApiTest.php`'s `$connectionsToTransact` pattern
  for why CI runs against a real Postgres rather than sqlite.

## New domain work — DDD + Actions

Anything that's a genuinely new bounded context — not just another telemetry resource
type that fits `app/Actions/`'s generic engines — is built under `app/Domains/<Module>/`
using `lorisleiva/laravel-actions` (already in `composer.json`; no HTTP controllers,
`authorize() → rules() → handle()` Actions) and UUID-only public identifiers. See the
`api-domain-dev`, `domain-doc`, `domain-readme-sync`, `action-test-sync`,
`uuid-public-ids`, and `test-first-workflow` skills for the full mechanics.

The `uuid-public-ids` convention is separate from the existing `app_id`/`App`
opaque-identifier scoping (root `CLAUDE.md`) — that mechanism stays as-is and applies to
telemetry, not to a new domain's own models.

## Routes

`routes/api.php` is now a pure aggregator: it globs and `require`s every domain's
`app/Domains/*/Routes/api.php`, then registers `app/Actions/`'s cross-cutting routes
directly afterward (preserving route-shadowing order — `issues/{issue}` and
`aggregate/{resource}` before the generic `{resource}/{id}` catch-all). New domain
modules are picked up automatically by the glob; no manual wiring needed.

`composer routes:export` (regenerating a route map for the SPA so it never hardcodes
URLs) is **still not built** — see `.claude/rules/routes.md`. Until it exists, the web
side keeps calling `/api/apps/{app}/...` endpoints directly.

## Before declaring done

```bash
vendor/bin/phpunit   # tests (--filter=<Name> for one)
vendor/bin/pint      # format
```

No PHPStan/Larastan is configured on this side.

## Never

Run `migrate:fresh`, `migrate:refresh`, or `db:wipe` against this stack (a `PreToolUse`
hook blocks them) — they drop every table and destroy existing data. Write a new
migration and run `php artisan migrate`, or undo with `migrate:rollback`.
