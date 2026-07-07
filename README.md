# NightOwl

NightOwl is a self-hosted application monitoring stack for Laravel: an
open-source telemetry ingest agent, a JSON API, and a dashboard SPA.

The stack is split into three sibling projects:

```
agent/    nightowl/agent — the open-source telemetry ingest daemon (ReactPHP
          TCP/UDP server, SQLite buffer, Postgres COPY drain). See
          agent/CLAUDE.md for its internals.
api/      Laravel 13 + Sanctum — JSON API over the nightowl_* Postgres tables.
          Consumes agent/ via a local Composer path repository
          (api/composer.json: repositories -> { type: path, url: ../agent }),
          so edits to agent/ are live in api/ without a release/tag.
web/      Vue3 + Vite + Pinia SPA — the multi-app dashboard UI (org → teams →
          apps; per-app health, activity, monitoring, issues, settings).
```

## The dashboard

NightOwl models an **Org → Teams → Apps** hierarchy. After login you land on
"Your Apps" (the org dashboard), pick an app, and drop into its per-app shell
(`/dashboard/<app-id>/…`) with a period selector (1H…30D) and:

- **Dashboard** — request volume/latency, exceptions, job throughput, users.
- **Activity** — Requests, Jobs, Commands, Scheduled Tasks, Exceptions,
  Queries, Notifications, Mail, Cache, Outgoing Requests (each an aggregated,
  sortable, searchable list with stat/chart panels).
- **Monitoring** — Users (+ per-user drill-down), Logs, Agent Health.
- **Admin** — Data Management (retention preview), Settings (environments,
  templates, agent token).

Every telemetry row is scoped by an opaque `app_id`, so one shared Postgres
can hold several apps. Endpoints are documented in
[docs/api-contract.md](docs/api-contract.md); the reference these pages
replicate is documented in [docs/README.md](docs/README.md).

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
php artisan nightowl:migrate              # creates the nightowl_* schema (incl. app_id)
php artisan migrate                       # api's own users + orgs/teams/apps tables (postgres: nightowl_app db)
php artisan db:seed                       # admin@example.com/password + Owlworks Agency + 4 apps
php artisan db:seed --class="Database\Seeders\TelemetrySeeder"   # demo telemetry so pages have data
php artisan serve --port=8000             # or 8001, if 8000 is in use

cd web && pnpm install
cp .env.example .env
pnpm dev                                  # http://localhost:5173 (log in: admin@example.com / password)
```

Open the SPA at **http://localhost:5173** — use `localhost`, not
`127.0.0.1`, so the `localhost`-scoped session cookie is shared with the api.

Or the whole stack via Docker:

```bash
cp .env.example .env                      # first time only
docker compose up -d --remove-orphans

# seed the account structure + demo telemetry (first run):
docker compose exec api php artisan migrate --force
docker compose exec api php artisan db:seed --force
docker compose exec api php artisan db:seed --class="Database\Seeders\TelemetrySeeder" --force
```

(postgres, pgbouncer, the `agent` daemon, the `api` JSON API, and `web`).
Note the `api`/`web` images bundle built code — after changing `api/`/`web/`,
`docker compose build api web` before `up` to pick up the changes.

### Environment files

There's no single shared `.env` — Laravel and Vite each expect one in their
own project root, so each subproject keeps its own:

| File          | Read by              | Purpose                                                                                                                                                              |
| ------------- | --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `.env` (root) | `docker-compose.yml`  | nightowl DB credentials, shared `APP_KEY`, dev URLs — substituted into the `postgres`/`agent`/`api` services so they're not hardcoded in three places.                  |
| `api/.env`    | Laravel (`api/`)       | Full app config — app key, sessions, and (for host-side dev outside Docker) `DB_*`/`NIGHTOWL_DB_*` both pointed directly at Postgres on port 5433, at two separate databases (`nightowl_app` vs `nightowl`) on the same server. |
| `web/.env`    | Vite (`web/`)          | `VITE_API_URL` — where the SPA sends its API requests.                                                                                                                   |
| *(none)*      | `agent/`               | It's a Composer package, not an app, so it has no `.env`. Integration/System tests read `NIGHTOWL_TEST_DB_*` via `getenv()` (defaults match docker-compose's Postgres); at runtime its DB config comes from whichever app consumes it (`api/`'s `NIGHTOWL_DB_*`). |

Each file above (except agent/, which has none) has a tracked
`.env.example` — copy it to `.env` before first run.

See [CLAUDE.md](CLAUDE.md) for CI details and more context on the migration.
