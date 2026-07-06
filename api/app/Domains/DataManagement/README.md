# DataManagement

## Purpose

Read-only data-volume preview for the "Data Management" settings tab
(`docs/pages/data-management.md`): given a date window and a set of
telemetry-type chips, report how many rows of each type fall inside that
window before a user commits to a (currently unimplemented) delete. This
domain does **not** own an actual delete/purge action yet — the UI's delete
button is a no-op demo banner, and there is no `Actions/DeleteDataManagement`
or similar to build against.

It deliberately does not own the 11 telemetry models it counts — see
"Models" below.

## Models

This domain defines no models of its own. It reads (never writes) 11
telemetry models that live in `app/Models/Telemetry/` and are owned by
`nightowl/agent`'s migrations (the `nightowl_*` Postgres tables):
`RequestRecord`, `QueryRecord`, `ExceptionRecord`, `CommandRecord`,
`JobRecord`, `CacheEvent`, `MailRecord`, `NotificationRecord`,
`OutgoingRequest`, `ScheduledTask`, `LogRecord`. Each is scoped to the
current app via `forApp($app->app_id)` (`TelemetryRecord::scopeForApp`).

The chip-name → model mapping is a fixed `PreviewDataManagement::TYPES`
const (mirrors the sidebar's Activity group + Logs); it is not
config-driven and not persisted anywhere.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `PreviewDataManagement` | For each requested type in `TYPES`, counts rows of that model scoped to the app and `created_at` between `from` (default: epoch) and `to`; returns `{counts: {type => n}, total: sum(counts)}`. Unknown type keys (not in `TYPES`) are silently skipped. | `authorize()` always allows (any authenticated user on the app can preview — no extra ownership check beyond the `auth:sanctum` middleware + `{app}` route binding). `rules()`: `from` nullable date, `to` required date, `types` required non-empty array of strings. No delete/side effect — pure read. |

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| POST | `/api/apps/{app}/data-management/preview` | `PreviewDataManagement` | `auth:sanctum` (applied by the root aggregator group) |

## Events & cross-module contracts

None yet. No events emitted or consumed; no `app/Support/` interface used
beyond the telemetry models' own `forApp` scope.

## Notes

- No Resource class — `handle()` returns a plain computed array
  (`{counts, total}`), not a serialized Eloquent model, so there's nothing
  to wrap in a `JsonResource` (per the migration plan's Resource-class
  strategy: mandatory only when an Eloquent model crosses the boundary).
- No `uuid` retrofit applies here — this domain doesn't expose any model's
  identifier at all.
- The actual delete workflow (if/when built) belongs in this same domain,
  not a new one — update this README when it lands.
