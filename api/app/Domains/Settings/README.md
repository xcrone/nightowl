# Settings

## Purpose

The per-app "Settings" page (`docs/pages/settings.md`): the free-form
key/value settings map, detected environments + their color chips, the
agent token (masked everywhere except the one-shot regenerate response),
the onboarding-template system (snapshot this app's config, then clone it
onto another app), and the "Alerts" tab's alert-channel CRUD + toggle.

## Models

`App`, `Setting`, `Template`, and `AlertChannel` all stay in `app/Models/`
(`App`/`Template` in `app/Models/`, `Setting`/`AlertChannel` in
`app/Models/Telemetry/`) — not relocated into this domain's own `Models/`
folder, since `App` in particular is consumed by 2+ other domains and by
Group A (`app/Actions/`, not yet migrated), so moving it would force
cross-domain imports per the migration plan's "models never physically
relocate" rule.

- `Setting` (`nightowl_settings`) never serializes its own `id` — every
  read here goes through `Setting::forApp()->pluck('value', 'key')`, a
  plain key/value projection, never a raw model — so it needs **no** `uuid`
  retrofit (per the migration plan's explicit exclusion for this table).
- `Template` (api-owned `templates` table) already got its `uuid` column in
  the Apps batch (`2026_07_06_120002_add_uuid_to_templates_table`,
  `booted() creating` hook on the model) — this batch is the first to
  actually read it through a Resource (`TemplateResource`, new).
- `AlertChannel` (`nightowl_alert_channels`) is the one **cross-repo**
  retrofit in this batch: the table is created by `nightowl/agent`'s own
  migration
  (`agent/database/migrations/2024_01_01_000018_create_nightowl_alert_channels_table.php`),
  consumed here via the local Composer path repository and the `nightowl`
  connection. Its `uuid` column was added by an ALTER migration placed on
  the **api** side —
  `database/migrations/2026_07_06_130000_add_uuid_to_nightowl_alert_channels_table.php`
  — targeting the `nightowl` connection explicitly (`protected $connection
  = 'nightowl';` on the migration class, `Schema::connection($this
  ->connection)->table(...)`, never `Schema::create`), with the usual
  nullable-then-backfill pattern and a `creating` hook on the model. The
  user explicitly signed off on this cross-repo schema touch when approving
  the controllers → Actions migration plan; `agent/`'s own migrations are
  untouched.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `ShowAppSettings` | The settings KV map merged with `app_id`/`name`/`description`/`environments`/masked `agent_token`/current template summary. The masked token is serialized under the `agent_token` key (matching `docs/api-contract.md` and `RegenerateAppToken`'s plaintext response key — the mask is what distinguishes the read from the one-shot regenerate). | `authorize()` always allows. No rules — pure read. |
| `ShowAppStorage` | The live on-disk footprint of the `nightowl_*` telemetry tables, read from Postgres system catalogs (`pg_total_relation_size` incl. indexes + TOAST, `pg_class.reltuples` estimate). Returns `{ tables: [{ name, bytes, rows }], total_bytes }`, sorted largest-first. Physical table sizes are shared across every app in the one Postgres, so this reports the whole-database telemetry footprint (as the docs' "Total telemetry footprint: … including indexes" headline describes) — the `{app}` binding scopes the page/auth, not the numbers. | `authorize()` always allows. No rules — pure read. |
| `UpdateAppSetting` | Upserts one `Setting` row (`app_id`+`key` unique). | `authorize()` 422s if `{key}` is one of the reserved computed keys (`app_id`, `name`, `description`, `environments`, `agent_token`, `template`) — these live on the settings payload but aren't genuine settings. `rules()`: `value` required string. |
| `UpdateAppEnvironment` | Sets/adds one environment's color in `App.environments`. | `authorize()` always allows. `rules()`: `color` required string, max 9 chars (`#RRGGBBAA`). |
| `RegenerateAppToken` | Issues a fresh `nwt_`-prefixed agent token, returned in plaintext once. | `authorize()` always allows. No rules. |
| `ListAppTemplates` | This app's `Template`s, most recently synced first. | `authorize()` always allows. No rules. |
| `SyncAppTemplate` | Snapshots the app's current environment colors into a `Template`, upserted by name (default "Default Setup"). | `authorize()` always allows. `rules()`: `name` nullable string. |
| `ApplyAppTemplate` | Clones another app's environment colors onto this app (never the agent token). | `authorize()` always allows. `rules()`: `from_app_id` required string; 404s via `firstOrFail()` if unknown. |
| `ListAlertChannels` | This app's `AlertChannel`s, alphabetical by name. | `authorize()` always allows. No rules. |
| `StoreAlertChannel` | Creates an `AlertChannel` scoped to `{app}`. | `authorize()` always allows. `rules()` (`ValidatesAlertChannelConfig` trait, shared with `UpdateAlertChannel`): `name`/`type`/`enabled`/`config` base rules, plus `config.*` sub-rules that depend on `type` (`webhook_url` for slack/discord, `url`+`secret` for webhook, `recipients` for email). |
| `UpdateAlertChannel` | Updates an existing `AlertChannel`. | `authorize()` uses `AuthorizesAppScope` — 404s if the channel's `app_id` doesn't match `{app}`. `rules()` resolves `type` from the request or falls back to the existing channel's `type` (so a partial update without a `type` key still validates its existing `config` shape correctly) — same trait as `StoreAlertChannel`. This action, plus `DestroyAlertChannel` below, had **zero test coverage before this migration** (the plan's explicit gap call-out); see Notes. |
| `DestroyAlertChannel` | Deletes an `AlertChannel`. | `authorize()` uses `AuthorizesAppScope`. No rules. Zero test coverage before this migration — see Notes. |
| `ToggleAlertChannel` | Flips an `AlertChannel`'s `enabled` flag. | `authorize()` uses `AuthorizesAppScope`. No rules. |

`AuthorizesAppScope` (used by `UpdateAlertChannel`/`DestroyAlertChannel`/
`ToggleAlertChannel`) now lives at `app/Support/AuthorizesAppScope.php`
(moved out of `app/Http/Controllers/Api/Concerns/`, per the migration
plan) — same `abort_unless(..., 404)` behavior (hides cross-app existence
rather than admitting-but-forbidding). The old
`app/Http/Controllers/Api/Concerns/AuthorizesAppScope.php` copy and
`IssueActionController` (its last consumer) were deleted in the Issues
batch — see `app/Domains/Issues/README.md`.

## Resources

- `AlertChannelResource` — `id` (kept, additive `uuid` alongside it this
  pass — no existing consumer breaks; a follow-up coordinated with the web
  side will drop `id` once the SPA keys off `uuid` instead), `uuid` (new),
  `app_id` (the compliant opaque `App::app_id` string, not a PK — safe as
  serialized here, same as every other per-app telemetry payload),
  `name`, `type`, `config`, `enabled`, `created_at`, `updated_at`. `config`
  is never masked (matches the pre-migration controller's behavior exactly
  — it never masked `secret`/`webhook_url` either, so this is not a
  regression introduced here).
- `TemplateResource` — new this batch (the first Resource ever written for
  `Template`; the old controller dumped the raw model directly). `id`,
  `uuid`, `name`, `payload`, `synced_at`, `created_at`, `updated_at`.
  Deliberately **excludes** the model's own `app_id` column: unlike every
  other `app_id` in this codebase, `templates.app_id` is an internal
  integer FK to `apps.id` (`$table->foreignId('app_id')
  ->constrained('apps')`), not the opaque public `App::app_id` string —
  serializing it under that key name would be actively misleading, and
  nothing needs it (a template is always fetched already scoped to
  `{app}` in the URL). This domain doesn't import `Domains/Apps/Resources`
  (no cross-domain imports), so this is its own copy rather than a shared
  one, per the migration plan.
- `ShowAppSettings`'s settings KV map, `ShowAppStorage`'s catalog-stat
  `{tables, total_bytes}` array, `UpdateAppSetting`'s/
  `UpdateAppEnvironment`'s/`RegenerateAppToken`'s/`ApplyAppTemplate`'s
  payloads are pure computed arrays, not serialized models — no Resource
  for those, per the migration plan's "pure computed payloads don't need a
  Resource" rule. `ShowAppSettings`'s nested `template` key is a small
  ad-hoc `{name, synced_at}` projection (not a full `Template` dump, and
  never serializes `id`), carried forward unchanged from the pre-migration
  controller.

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| GET | `/api/apps/{app}/settings` | `ShowAppSettings` | `auth:sanctum` (root aggregator group) |
| GET | `/api/apps/{app}/settings/storage` | `ShowAppStorage` | `auth:sanctum` |
| PUT | `/api/apps/{app}/settings/{key}` | `UpdateAppSetting` | `auth:sanctum` |
| PUT | `/api/apps/{app}/environments/{name}` | `UpdateAppEnvironment` | `auth:sanctum` |
| POST | `/api/apps/{app}/token/regenerate` | `RegenerateAppToken` | `auth:sanctum` |
| GET | `/api/apps/{app}/templates` | `ListAppTemplates` | `auth:sanctum` |
| POST | `/api/apps/{app}/templates/sync` | `SyncAppTemplate` | `auth:sanctum` |
| POST | `/api/apps/{app}/templates/apply` | `ApplyAppTemplate` | `auth:sanctum` |
| GET | `/api/apps/{app}/alert-channels` | `ListAlertChannels` | `auth:sanctum` |
| POST | `/api/apps/{app}/alert-channels` | `StoreAlertChannel` | `auth:sanctum` |
| PUT/PATCH | `/api/apps/{app}/alert-channels/{alertChannel}` | `UpdateAlertChannel` | `auth:sanctum` |
| DELETE | `/api/apps/{app}/alert-channels/{alertChannel}` | `DestroyAlertChannel` | `auth:sanctum` |
| POST | `/api/apps/{app}/alert-channels/{alertChannel}/toggle` | `ToggleAlertChannel` | `auth:sanctum` |

Same URL shapes as the pre-migration `AppSettingController`/
`AlertChannelController` routes (the old `Route::apiResource('alert
-channels', ...)->except(['show'])->parameters([...])` registration is
replaced by explicit per-verb routes registering both `PUT` and `PATCH`
for update, same as `apiResource` used to).

## Events & cross-module contracts

None. No events emitted or consumed; no `app/Support/` interface used
beyond `App\Support\AuthorizesAppScope`.

## Notes

- **Coverage gap fixed** (per the migration plan's explicit call-out):
  `UpdateAlertChannel`/`DestroyAlertChannel` had zero direct test coverage
  before this migration (only store/index/toggle were asserted). Added
  `test_updates_an_alert_channel`, `test_updating_an_alert_channel_validates_input`,
  `test_destroys_an_alert_channel`, and
  `test_cannot_update_or_destroy_an_alert_channel_belonging_to_another_app`
  to `tests/Feature/Settings/AlertChannelApiTest.php`.
- Relocated tests (Batch 5 of the controllers → Actions migration):
  `tests/Feature/Apps/AppSettingsTest.php` is split into
  `tests/Feature/Settings/AppSettingsApiTest.php` (settings/environment/
  token/template coverage) and `tests/Feature/Settings/AlertChannelApiTest.php`
  (alert-channel coverage, plus the new update/destroy tests above).
- `ListApps::handle()`'s per-app `health()` payload
  (`App\Domains\Apps\Actions\ListApps`) already serializes `app_id`
  directly — this domain's `AlertChannelResource` follows the same
  convention rather than inventing a different shape for the same
  identifier.
