# NightOwl monorepo

The stack is split into three sibling projects:

```
agent/    nightowl/agent — the open-source telemetry ingest daemon (ReactPHP
          TCP/UDP server, SQLite buffer, Postgres COPY drain). See
          agent/CLAUDE.md for its internals.
api/      Laravel 13 + Sanctum — JSON API over the nightowl_* Postgres tables. See
          api/CLAUDE.md for its internals. Consumes agent/ via a local Composer path
          repository (api/composer.json: repositories -> { type: path, url: ../agent }),
          so edits to agent/ are live in api/ without a release/tag.
web/      Vue3 + Vite + Pinia SPA — the multi-app dashboard UI (org → teams →
          apps). See web/CLAUDE.md (always use Tailwind for styling).
```

## Why the split

The stack separates concerns: `agent/` stays a clean, publishable package;
`api/`+`web/` are a decoupled Sanctum API + SPA that can evolve independently
(auth, deployment, scaling) without touching the agent.

## Local development

```bash
cp .env.example .env                      # root — shared creds for docker-compose

docker compose up -d postgres pgbouncer   # Postgres + PgBouncer only
cd agent && composer install && vendor/bin/phpunit --testsuite Unit

cd api && composer install
cp .env.example .env && php artisan key:generate
php artisan nightowl:migrate              # creates the nightowl_* schema
php artisan migrate                       # api's own users/sessions tables (postgres: nightowl_app db)
php artisan serve --port=8001             # or 8000, if nothing else is using it

cd web && pnpm install
cp .env.example .env
pnpm dev                                  # http://localhost:5173
```

Or the whole stack via Docker:

```bash
cp .env.example .env                      # first time only
docker compose up -d --remove-orphans
```

(postgres, pgbouncer, the `agent` daemon, the `api` JSON API, and `web`).

See [README.md](README.md)'s "Environment files" section for what each of
the four `.env` files is for and why they aren't unified into one.
Note: `docker/Dockerfile`'s build context is the **repo root**, not `api/`
— it needs both `agent/` and `api/` in context so `composer install` (run
inside the image, not copied from the host) creates a symlink that actually
resolves inside the container.
Note: the `api` container has **no source volume mount** — it runs a
build-time copy of `agent/`+`api/`. Editing source and hitting
`localhost:8000` against the Docker stack will not reflect the change until
you rebuild: `docker compose up -d --build api`. (`php artisan serve`,
per the non-Docker steps above, doesn't have this problem.)

## Dashboard architecture

The product is an **Org → Teams → Apps** hierarchy that replicates the demo
documented in `docs/pages/*` (see `docs/README.md`); the full endpoint
reference is `docs/api-contract.md`.

- **Multi-tenancy by `app_id`.** Orgs/teams/apps live in `api/`'s primary DB
  — a `nightowl_app` Postgres database, on the same server as the agent's
  `nightowl` telemetry database but kept separate (`App` binds routes by its
  opaque `app_id`). Every `nightowl_*` telemetry row carries a nullable
  `app_id` (added by an `agent/` migration, backfilled to the seeded default
  app), so one shared Postgres instance holds several apps. Per-app
  telemetry is nested under `/api/apps/{app}/…` and scoped `where app_id = ?`
  (`TelemetryRecord::scopeForApp`).
- **Two config registries drive generic, cross-cutting Actions** (`api/app/Actions/`,
  deliberately not a DDD domain — see `api/CLAUDE.md`): `api/config/telemetry.php`
  (raw lists/detail/related via `App\Actions\Telemetry\*`) and
  `api/config/aggregates.php` (per-key aggregated lists via
  `App\Actions\Aggregates\IndexAggregate`, computed on the fly with GROUP BY + Postgres
  `percentile_cont`, not the pre-`app_id` rollup tables). Period windows resolve
  through `App\Support\Period`.
- **Frontend** mirrors this: `web/src/router` nests every page under
  `/dashboard/:appId`, `store/app.js` holds the current app + period (drives
  all fetches), and the aggregated list pages are thin wrappers over
  `AggregateListPage.vue` + `web/src/aggregateConfig.js`.
- **Seeding:** `db:seed` creates only the admin user (`admin@example.com`/`password`) —
  org/team/app and telemetry seeders have been removed. Agent Health is synthesized in
  `App\Actions\Health\ShowAgentHealth` (no data source). Auth is Sanctum SPA
  cookies — use `localhost`, not `127.0.0.1`.

## CI

Three independent workflows, each path-filtered to its own folder so an
`api/`-only change doesn't re-run `agent/`'s test matrix:

- `.github/workflows/agent-tests.yml` — agent/'s own PHPUnit suite (Unit +
  Integration, PHP 8.2–8.4 matrix).
- `.github/workflows/api-tests.yml` — api/'s feature tests against a real
  Postgres service container (mirrors how tests run locally — see
  `api/tests/Feature/Telemetry/TelemetryApiTest.php`'s
  `$connectionsToTransact` pattern for why a real Postgres is needed rather
  than sqlite).
- `.github/workflows/web-tests.yml` — web/'s Vitest suite + a production
  build (`pnpm build`) as a compile-error check.
