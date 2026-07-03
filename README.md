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
docker compose up -d postgres pgbouncer   # Postgres + PgBouncer only
cd agent && composer install && vendor/bin/phpunit --testsuite Unit

cd api && composer install
cp .env.example .env && php artisan key:generate
php artisan nightowl:migrate              # creates the nightowl_* schema
php artisan migrate                       # api's own users/sessions tables (sqlite)
php artisan serve --port=8001             # or 8000, if nothing else is using it

cd web && pnpm install
pnpm dev                                  # http://localhost:5173
```

Or the whole stack via Docker: `docker compose up -d --remove-orphans`
(postgres, pgbouncer, the `agent` daemon, the `api` JSON API, and `web`).

See [CLAUDE.md](CLAUDE.md) for CI details and more context on the migration.
