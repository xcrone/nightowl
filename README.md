# NightOwl

NightOwl is a self-hosted application monitoring stack for Laravel: an
open-source telemetry ingest agent, a JSON API, and a dashboard SPA.

This repo is being restructured from a single Laravel+Filament app into
three sibling projects:

```
agent/    nightowl/agent — the open-source telemetry ingest daemon (ReactPHP
          TCP/UDP server, SQLite buffer, Postgres COPY drain). See
          agent/CLAUDE.md for its internals.
api/      Laravel 13 + Sanctum — JSON API over the nightowl_* Postgres tables.
          Consumes agent/ via a local Composer path repository
          (api/composer.json: repositories -> { type: path, url: ../agent }),
          so edits to agent/ are live in api/ without a release/tag.
app/      The original Laravel 13 + Filament 5 dashboard this is replacing.
          Kept only as a reference during the migration — not wired into
          docker-compose.yml anymore. Remove once api/+web/ reach parity.
web/      Vue3 + Vite + Pinia SPA — the new dashboard UI, replacing app/'s
          Filament panel.
```

## Why the split

`app/` was a read-only Filament admin panel bundled with the agent's Composer
dependency, mixing a distributable open-source package with an application in
one repo. The restructure separates concerns: `agent/` stays a clean,
publishable package; `api/`+`web/` are a decoupled Sanctum API + SPA that can
evolve independently (auth, deployment, scaling) without touching the agent.

## Local development

```bash
cp .env.example .env                      # root — shared creds for docker-compose

docker compose up -d postgres pgbouncer   # Postgres + PgBouncer only
cd agent && composer install && vendor/bin/phpunit --testsuite Unit

cd api && composer install
cp .env.example .env && php artisan key:generate
php artisan nightowl:migrate              # creates the nightowl_* schema
php artisan migrate                       # api's own users/sessions tables (sqlite)
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

### Environment files

There's no single shared `.env` — Laravel and Vite each expect one in their
own project root, so each subproject keeps its own:

| File          | Read by              | Purpose                                                                                                                                                              |
| ------------- | --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `.env` (root) | `docker-compose.yml`  | nightowl DB credentials, shared `APP_KEY`, dev URLs — substituted into the `postgres`/`agent`/`api` services so they're not hardcoded in three places.                  |
| `api/.env`    | Laravel (`api/`)       | Full app config — app key, sessions, and (for host-side dev outside Docker) `NIGHTOWL_DB_*` pointed directly at Postgres on port 5433.                                  |
| `web/.env`    | Vite (`web/`)          | `VITE_API_URL` — where the SPA sends its API requests.                                                                                                                   |
| *(none)*      | `agent/`               | It's a Composer package, not an app, so it has no `.env`. Integration/System tests read `NIGHTOWL_TEST_DB_*` via `getenv()` (defaults match docker-compose's Postgres); at runtime its DB config comes from whichever app consumes it (`api/`'s `NIGHTOWL_DB_*`). |

Each file above (except agent/, which has none) has a tracked
`.env.example` — copy it to `.env` before first run.

See [CLAUDE.md](CLAUDE.md) for CI details and more context on the migration.
