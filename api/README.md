# api/ — NightOwl JSON API

Laravel 13 + Sanctum. A read-mostly JSON API over the `nightowl_*` Postgres
tables (owned by `agent/`) plus a small app-management schema (orgs/teams/
apps) in its own primary connection. Consumed by the `web/` SPA.

See [../CLAUDE.md](../CLAUDE.md) for how this fits the monorepo and
[../docs/api-contract.md](../docs/api-contract.md) for the full endpoint
reference.

## Multi-app model

The dashboard is an **Org → Teams → Apps** hierarchy. Orgs/teams/apps live in
the api's own (sqlite) primary DB; every telemetry row carries an `app_id`
string (added by an `agent/` migration) so a single shared Postgres can hold
several apps. An `App` binds by its opaque `app_id`, and all per-app telemetry
is nested and scoped:

```
GET  /api/user                              current dashboard user
GET  /api/orgs                              orgs the user belongs to
GET  /api/apps                              apps grouped by team + 24h health
GET  /api/apps/{app}                        one app

# everything below is scoped `where app_id = {app}`:
GET  /api/apps/{app}/dashboard              summary (requests/duration/exc/jobs/users)
GET  /api/apps/{app}/timeseries/{metric}    bucketed charts (requests|duration|exceptions|jobs)
GET  /api/apps/{app}/aggregate/{resource}   per-key rollups (config/aggregates.php)
GET  /api/apps/{app}/{resource}[/{id}[/related]]   raw telemetry lists/detail (config/telemetry.php)
GET  /api/apps/{app}/issues/{issue}         issue detail (occurrences/activity/env/stack)
POST /api/apps/{app}/issues/{issue}/assign|priority
GET  /api/apps/{app}/users/{userId}         per-user drill-down
GET  /api/apps/{app}/health                 agent pipeline health (simulated)
POST /api/apps/{app}/data-management/preview
GET  /api/apps/{app}/settings   PUT .../environments/{name}   POST .../token/regenerate
GET/POST .../templates[/sync|/apply]
```

### Key pieces
- `config/telemetry.php` — registry driving the generic `TelemetryController`
  (12 raw resources: filters, search, trace correlation).
- `config/aggregates.php` — registry driving `AggregateController`. Aggregation
  is computed on the fly from raw tables (GROUP BY + Postgres `percentile_cont`),
  scoped by `app_id` + the period window — not the pre-`app_id` rollup tables.
- `App\Support\Period` — resolves `?period=1h|6h|24h|7d|14d|30d` (or `from`/`to`)
  into a `[from, to]` window + chart bucket size.
- `App\Models\Telemetry\TelemetryRecord::scopeForApp()` — the `where app_id`
  scope used everywhere.

## Auth

Sanctum **SPA cookie/session** auth (not tokens). Login/logout are in
`routes/web.php` (session + CSRF); `/api/*` is stateful. The SPA must call
`/sanctum/csrf-cookie` before the first mutating request. Use `localhost`
(not `127.0.0.1`) so the `localhost`-scoped session cookie is shared between
the SPA (`:5173`) and the api (`:8000`).

## Local run + seed

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan nightowl:migrate     # nightowl_* schema (incl. app_id column)
php artisan migrate              # api primary DB (users, orgs/teams/apps)
php artisan db:seed              # admin@example.com/password + Owlworks Agency + 4 apps
php artisan db:seed --class="Database\Seeders\TelemetrySeeder"   # demo telemetry for all apps
php artisan serve                # http://localhost:8000
```

`TelemetrySeeder` is dev-only (writes hundreds of rows to the shared Postgres);
it's not part of the default `DatabaseSeeder`.

## Tests

Feature tests run against a **real Postgres** (the shared `nightowl`
connection) — see `tests/Feature/Telemetry/TelemetryApiTest.php`'s
`$connectionsToTransact = ['sqlite','nightowl']` pattern and
`tests/TestCase::seedApp()` (seeds an Org→Team→App so `/api/apps/{app}/…`
route binding resolves). Telemetry factories default `app_id => 'test_app'`.

```bash
NIGHTOWL_DB_PORT=5433 vendor/bin/phpunit --testsuite Feature
```
