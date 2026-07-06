---
name: api-domain-dev
description: Build new api features in NightOwl's Laravel 13 + Sanctum API using DDD bounded contexts with lorisleiva/laravel-actions. Use when adding a new domain module under app/Domains/<Module>/ — models, Actions, Resources, routes, policies, events. This is the convention for ALL api entrypoints now; the generic, config-driven telemetry/aggregate engines live in app/Actions/ (deliberately not a Domain) and are the one carve-out for new telemetry resource types.
---

# API domain development (NightOwl API)

Guidance for building **new** features in `api/` as Laravel 13 DDD bounded contexts. This
is now the established convention — see the "Current vs. target" note below for exactly
what's built and what still isn't.

## Current vs. target — read this first

- **What exists today**: `api/app/Http/Controllers/Api/` no longer exists — every
  controller has been migrated to a `lorisleiva/laravel-actions` Action (already in
  `composer.json`). Real bounded contexts live under
  `app/Domains/{DataManagement,Auth,Users,Apps,Settings,Issues}/`, each with its own
  `README.md`, `Routes/api.php`, `Actions/`, and `Resources/`. The 5 generic,
  config-driven telemetry/aggregate engines (formerly `TelemetryController`,
  `AggregateController`, `RollupController`, `TimeseriesController`,
  `AgentHealthController`) live in `app/Actions/{Telemetry,Aggregates,Rollups,Timeseries,Health}/`
  — deliberately **not** a Domain (no distinct business rules, no api-owned schema), still
  serving every resource off `config/telemetry.php` / `config/aggregates.php`. Root
  `routes/api.php` is a pure aggregator: it globs `app/Domains/*/Routes/api.php`, then
  registers `app/Actions/`'s routes directly afterward.
- **What's new work should do**: any new bounded context (a genuinely new area of
  business logic, not another telemetry resource type that fits `app/Actions/`'s generic
  engines) is built under `app/Domains/<Module>/` per this skill.
- **Extending `app/Actions/`'s registries is still the right move** for a new telemetry
  resource type — add a key to `config/telemetry.php` / `config/aggregates.php`, don't
  spin up a new Domain for it. Reach for `app/Domains/` only for new, distinct business
  logic (e.g. a feature that isn't "one more telemetry table").

## When to use

- Adding a new domain module under `app/Domains/<Module>/`.
- Wiring routes, Actions, Resources, policies, or events for that new domain.

Not for: adding another telemetry/aggregate resource type to the existing `app/Actions/`
engines (extend `config/telemetry.php` / `config/aggregates.php` instead), web work,
or agent/ (the ReactPHP daemon) work.

## Golden rules

- **No HTTP controllers in a domain.** Every HTTP entrypoint is a
  `lorisleiva/laravel-actions` Action used as an invokable controller:
  `Route::get('/thing', DoThing::class)`. Actions follow the
  `authorize() → rules() → handle()` lifecycle; keep them focused.
- **Business logic lives in Actions**, not fat route closures or services.
- **Return API Resources, never raw models** (`Illuminate\Http\Resources\Json\JsonResource`
  under `Resources/`).
- **UUIDs are the only public identifier** for a new domain's models — never serialize
  the auto-increment `id`. Give user-facing tables a `uuid` column, bind routes on it, and
  expose the uuid to the SPA. See the [`uuid-public-ids`](../uuid-public-ids/SKILL.md) skill.
- **No cross-domain imports.** A domain never does `use App\Domains\OtherModule\…`.
  Cross-module communication is events, shared `app/Support/` interfaces, or API calls only.

## Domain folder shape

```
app/Domains/<Module>/
├── README.md          # MANDATORY — what the domain is + its current business logic (see domain-doc skill)
├── Models/
├── Migrations/        # php artisan migrate --path=…
├── Routes/api.php     # aggregated by root routes/api.php once the aggregator is set up (see routes.md rule)
├── Resources/         # API Resources for output — NO Controllers
├── Actions/           # AsAction classes — the HTTP entrypoints + business logic
├── Policies/
├── Events/
├── Jobs/
└── Notifications/
```

Every domain endpoint belongs to a domain — no global/non-domain endpoints for domain
work. If a genuinely cross-cutting endpoint is ever needed, create `app/Actions/` (still
Action-based, still no controllers) and route it directly from `routes/api.php`.

## Routes workflow

1. Add routes in the module's own `app/Domains/<Module>/Routes/api.php`, targeting Action
   classes. The root `routes/api.php` is already a pure aggregator — a
   `glob(app_path('Domains/*/Routes/api.php'))` loop that `require`s every domain route
   file — so a new domain's routes are picked up automatically; no manual wiring needed.
2. `composer routes:export` (regenerating a route map for the SPA) is a target convention
   that hasn't been built yet — see `.claude/rules/routes.md`. Until it exists, the web
   side keeps calling the endpoint directly.

## Build-a-module checklist

1. [ ] Create the folder skeleton above.
2. [ ] Models + migrations (user-facing tables get a `uuid` column, models bind/route on
       `uuid` — see the **`uuid-public-ids`** skill).
3. [ ] Actions for each use case (`authorize → rules → handle`).
4. [ ] Resources (`Resources/`) for output — serialize the `uuid`, never the integer `id`.
5. [ ] Routes in `Routes/api.php` (targets = Actions).
6. [ ] Policies / events / jobs as needed.
7. [ ] `README.md` describing the domain + its current business logic (see the
       **`domain-doc`** skill) — write it as you build, not after.
8. [ ] PHPUnit test per Action (`tests/Feature/<Module>/…Test.php`) — see the
       **`action-test-sync`** skill; keep it in sync whenever an Action changes.
9. [ ] Web consumes it via `services/api.js` (separate pass) — through a `route()` helper
       once that exists.

## Quality gates (must pass before done)

```bash
vendor/bin/phpunit              # tests (--filter=<name> for one)
vendor/bin/pint                 # format
```
