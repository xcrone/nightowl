# NightOwl monorepo

The stack is split into three sibling projects:

```
agent/    nightowl/agent — the open-source telemetry ingest daemon (ReactPHP
          TCP/UDP server, SQLite buffer, Postgres COPY drain). See
          agent/CLAUDE.md for its internals.
api/      Laravel 13 + Sanctum — JSON API over the nightowl_* Postgres tables.
          Consumes agent/ via a local Composer path repository
          (api/composer.json: repositories -> { type: path, url: ../agent }),
          so edits to agent/ are live in api/ without a release/tag.
web/      Vue3 + Vite + Pinia SPA — the dashboard UI. See web/CLAUDE.md
          (always use Tailwind for styling).
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

See [README.md](README.md)'s "Environment files" section for what each of
the four `.env` files is for and why they aren't unified into one.
Note: `docker/Dockerfile`'s build context is the **repo root**, not `api/`
— it needs both `agent/` and `api/` in context so `composer install` (run
inside the image, not copied from the host) creates a symlink that actually
resolves inside the container.

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
