# Apps

## Purpose

The Org → Teams → Apps hierarchy that drives the dashboard's navigation and
the Org Dashboard's health cards (`docs/pages/org-dashboard.md`), plus the
single-App Dashboard summary (`docs/pages/app-dashboard.md`). Dashboard
folds into this domain rather than getting its own: it's a bespoke,
per-App report cohesive with `ListApps`'s `health()` computation (same
"recent-window rollup over one app's telemetry" shape, just a longer
window and richer breakdown) — it isn't config-driven like
`config/telemetry.php`/`config/aggregates.php`, so it doesn't belong in
Group A (`app/Actions/`, Batch 7) either.

## Models

`Org`, `Team`, and `App` live in `app/Models/` (not relocated into this
domain's own `Models/` folder — `App` in particular is consumed by 2+ other
domains and by Group A, so moving it would force cross-domain imports per
the migration plan's "models never physically relocate" rule).
`RequestRecord`, `ExceptionRecord`, `JobRecord`, `Issue`, `NightowlUser`
(all in `app/Models/Telemetry/`, owned by `nightowl/agent`'s migrations)
are read here too, for `ListApps`'s health cards and `ShowDashboard`'s
aggregates — none are written by this domain.

`Org`/`Team`/`Template` each got a `uuid` column this batch (`Schema::table`
retrofit migrations `2026_07_06_120000_add_uuid_to_orgs_table`,
`..._120001_add_uuid_to_teams_table`, `..._120002_add_uuid_to_templates_table`,
backfilled for existing rows, auto-generated on create via each model's
`booted()` `creating` hook) — before this batch, `org.id`/`team.id` were the
only identifiers `OrgController`/`AppController` ever serialized.
`Template` gets the same retrofit even though nothing in this batch reads
it yet (`AppSettingController`'s template endpoints move to `Settings` in
Batch 5) — doing it here keeps all three `app_management` tables' uuid
work in one migration set rather than splitting it across batches.
`App` itself needs **no** retrofit — it already binds/serializes on the
opaque `app_id` (`App::getRouteKeyName`), not the integer `id`.

`Org`, `Team`, and `User` (`app/Models/User.php`) now also bind their
`{org}`/`{team}`/`{user}` route parameters on `uuid`
(`getRouteKeyName(): string { return 'uuid'; }`) — added alongside the CRUD
Actions below, since this was the first batch to actually introduce those
route params. `App` still binds on its own opaque `app_id`, unaffected.

`orgs.owner_id`/`orgs.is_personal` (retrofit migration
`2026_07_07_120000_add_owner_to_orgs_table`) added an ownership concept on
top of the flat `org_user` membership pivot: `Org::owner()` (`BelongsTo`
`User`). Existing rows were backfilled with `owner_id` = the org's
first-attached member (raw `org_user` query), `is_personal` left at its
default `false` for all of them — see the Notes entry below for what these
two columns mean in practice and their known scope limits.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `ListOrgs` | Orgs the authenticated user belongs to (`$request->user()->orgs()`); a user with no membership at all gets their own true empty list — never another user's orgs. | `authorize()` always allows. This endpoint had **zero test coverage** before this migration; `tests/Feature/Apps/OrgApiTest.php` now covers both the "has membership" and "no membership → empty list" branches. |
| `ListApps` | The selected `Org`'s teams, each with its apps and a live 1h `health()` summary (error rate, 5xx count, exceptions, open issues, connected/disconnected) — drives the Org Dashboard cards + the org switcher. | `authorize()` always allows. `?org=<uuid>` (the org switcher) resolves via `$request->user()->orgs()->where('orgs.uuid', ...)->firstOrFail()` — 404s if the user isn't a member of that org, never leaking another org's data. Without the query param: `$request->user()->orgs()->first()`. If that's null (the user has no org membership at all), returns a clean `{org: null, teams: []}` (HTTP 200) instead of falling back to any other org — see the Notes entry below for why this replaced the old "fall back to the first org in the table" dev convenience. |
| `ShowApp` | One `App` (via `AppResource`) + its `team`/`org`, `loadMissing('team.org')`. | `authorize()` always allows. Route-bound `{app}` (opaque `app_id`) — 404s automatically if unknown. |
| `ShowDashboard` | App Dashboard summary: request volume/latency/status mix, exception counts, job throughput, most-active/most-impacted users — all via three Postgres-specific raw-SQL aggregate queries (`percentile_cont`, `::bigint` casts), preserved exactly from the pre-migration controller (not made portable). Period-aware (`Period::resolve()`). | `authorize()` always allows. No rules — route-bound `{app}`, period resolved from the query string. |
| `StoreOrg` | Founds a new `Org`, attaches the creator as its first member. | `authorize()` always allows (any authenticated user may found an org). `rules()`: `name` required, `account_email` required email. |
| `UpdateOrg` | Renames an `Org`/changes its billing contact email. | `authorize()`: membership via `AuthorizesOrgMembership::authorizeOrgMember()`. `rules()`: both fields `sometimes\|required`. |
| `DestroyOrg` | Deletes an `Org`. | `authorize()`: membership. Refuses (422, `"Delete this org's teams first."`) if it still has teams — won't cascade-wipe teams/apps silently. Runs inside `DB::transaction()` against a `lockForUpdate()`-locked re-fetch of the org row (see the Notes entry below on the TOCTOU fix), using the shared `RefusesCascadeDelete` trait for the "has children" check. |
| `ListOrgMembers` | The org's current members (`OrgMemberResource::collection`). | `authorize()`: membership. Added so the SPA can load the existing member list on page load instead of only building one up from `AddOrgMember`/`RemoveOrgMember` responses within a session. |
| `AddOrgMember` | Attaches an **existing** user account to an org by email, returns the single newly-added member (`(new OrgMemberResource($user))->resolve()`, HTTP 201). | `authorize()`: membership. `rules()`: `email` required, must `exists:users,email` — there is no invite-by-email infrastructure, so this cannot onboard a brand-new account, only grant an existing one access. Previously returned the org's *entire* member list re-queried/re-serialized after every add; changed to return just the added member (see Notes). |
| `RemoveOrgMember` | Detaches a member from an org. | `authorize()`: membership. `{user}` route param binds by `uuid` (never the integer id). |
| `TransferOrgOwnership` | Reassigns `$org->owner_id` to another existing member, looked up by email. | `authorize()`: **not** membership — only the *current owner* (`$org->owner_id === $request->user()?->id`), read off `$request->route('org')` same as the other Actions. `rules()`: `email` required, must `exists:users,email`. `handle()` 422s (`ValidationException` on `email`) if `$org->is_personal` (never transferable) or if the target isn't already a member of `$org`. |
| `StoreTeam` | Creates a `Team` under an `Org`. | `authorize()`: membership. `rules()`: `name` required. |
| `UpdateTeam` | Renames a `Team`. | `authorize()`: membership on `$team->org`. `rules()`: `name` required. `handle()` still type-hints unused `Org $org` — see the Notes entry below for why that's load-bearing, not just style. |
| `DestroyTeam` | Deletes a `Team`. | Same authorize as `UpdateTeam`. Refuses (422, `"Delete this team's apps first."`) if it still has apps. Same unused-`Org $org`-parameter note as `UpdateTeam`; same `DB::transaction()` + `lockForUpdate()` + `RefusesCascadeDelete` shape as `DestroyOrg`. |
| `StoreApp` | Creates an `App` under a `Team`, minting a fresh unique opaque `app_id` and an `agent_token` (`App::generateAgentToken()`, the same `'nwt_'`-prefixed format `Settings\Actions\RegenerateAppToken` issues). Returns `AppResource` (same shape `ShowApp` embeds). | `authorize()`: membership on `$team->org`. `rules()`: `name` required, `description`/`db_connection`/`environments` nullable. |
| `UpdateApp` | Updates an `App`'s display fields, returns `AppResource`. | `authorize()`: membership on `$app->team->org`. `rules()`: `name` sometimes\|required, rest nullable. |
| `DestroyApp` | Deletes an `App`. | Same authorize as `UpdateApp`. **Does not** cascade-delete the app's telemetry rows in the separate `nightowl_*` tables — different database, owned by `nightowl/agent`, out of scope here. |

All ten CRUD/membership Actions above (i.e. every one except
`TransferOrgOwnership`) share one `authorize()` idiom: read the route-bound
model off `$request->route(...)` (required — `authorize()`/`rules()` run
before the router's own binding reaches `handle()`), then check org
membership via `App\Support\AuthorizesOrgMembership`'s
`authorizeOrgMember(Org $org, ?User $user)` — an indexed
`$org->users()->whereKey($user->id)->exists()` check rather than loading
every member row just to call `contains()` on the collection. Mirrors
`App\Support\AuthorizesAppScope`'s role for the app_id-ownership check used
elsewhere in Settings/Issues. `TransferOrgOwnership` deliberately does
**not** use this trait — see its Notes entry below for why plain membership
isn't the right check for handing off ownership.

## Resources

- `OrgResource` — `id`, `uuid` (new, additive this pass — `id` stays so no
  existing consumer breaks; a follow-up coordinated with the web side will
  drop it once the SPA keys off `uuid` instead), `name`, `account_email`,
  `is_personal`, `owner_uuid` (the owner's `uuid` — never the raw
  `owner_id` integer FK, per `uuid-public-ids`; null for a pre-existing org
  backfilled with no candidate owner). Used by `ListOrgs` (as a collection)
  and embedded (via `->resolve()`) in `ListApps`'s `org` key and `ShowApp`'s
  `org` key. Every one of those call sites (plus `StoreOrg`, `UpdateOrg`,
  `TransferOrgOwnership`) `loadMissing('owner')`/`with('owner')` the org
  first so serializing `owner_uuid` doesn't N+1.
- `TeamResource` — `id`, `uuid` (same additive rule), `name`. Deliberately
  *only* the base Team fields: `ListApps`'s `apps_count`/`apps` keys are a
  computed `health()` summary per app, not a raw relation dump, so they're
  merged onto the base shape at the call site (`array_merge((new
  TeamResource($team))->resolve(), [...])`) rather than living on the
  Resource itself. `ShowApp`'s `team` key uses the same Resource without
  the extra keys.
- `AppResource` — `app_id`, `name`, `description`, `db_connection`,
  `environments`. Shared by `ShowApp` (merged with `team`/`org`, same
  spread-then-extend convention as `TeamResource` above), `StoreApp`, and
  `UpdateApp` — previously each of those three hand-built the same flat
  array independently.
- `OrgMemberResource` — `uuid`, `name`, `email` (no `id` — uuid-public-ids:
  the integer PK never serializes here). Used by `AddOrgMember`'s
  single-member response and `ListOrgMembers`'s collection so a raw `User`
  (or collection) is never serialized directly.
- `ListApps`'s per-app `health()` payload and `ShowDashboard`'s entire
  summary are pure computed aggregates (GROUP BY / window-function
  results), not 1:1 serialized models — no Resource for those, per the
  migration plan's "pure computed payloads don't need a Resource" rule.
  Neither embeds a raw model either (health() reads scalar `App` fields
  directly into a plain array — `app_id` is already the compliant public
  identifier, so no wrapping is needed there).

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| GET | `/api/orgs` | `ListOrgs` | `auth:sanctum` (root aggregator group) |
| POST | `/api/orgs` | `StoreOrg` | `auth:sanctum` |
| PUT | `/api/orgs/{org}` | `UpdateOrg` | `auth:sanctum` |
| PUT | `/api/orgs/{org}/owner` | `TransferOrgOwnership` | `auth:sanctum` |
| DELETE | `/api/orgs/{org}` | `DestroyOrg` | `auth:sanctum` |
| GET | `/api/orgs/{org}/members` | `ListOrgMembers` | `auth:sanctum` |
| POST | `/api/orgs/{org}/members` | `AddOrgMember` | `auth:sanctum` |
| DELETE | `/api/orgs/{org}/members/{user}` | `RemoveOrgMember` | `auth:sanctum` |
| POST | `/api/orgs/{org}/teams` | `StoreTeam` | `auth:sanctum` |
| PUT | `/api/orgs/{org}/teams/{team}` | `UpdateTeam` | `auth:sanctum` |
| DELETE | `/api/orgs/{org}/teams/{team}` | `DestroyTeam` | `auth:sanctum` |
| GET | `/api/apps` | `ListApps` | `auth:sanctum` (root aggregator group) |
| GET | `/api/apps/{app}` | `ShowApp` | `auth:sanctum` (root aggregator group) |
| POST | `/api/teams/{team}/apps` | `StoreApp` | `auth:sanctum` |
| PUT | `/api/apps/{app}` | `UpdateApp` | `auth:sanctum` |
| DELETE | `/api/apps/{app}` | `DestroyApp` | `auth:sanctum` |
| GET | `/api/apps/{app}/dashboard` | `ShowDashboard` | `auth:sanctum` (root aggregator group) |

## Events & cross-module contracts

None. No events emitted or consumed; no `app/Support/` interface used
beyond the telemetry models' own `forApp` scope and `App\Support\Period`.

## Notes

- Ownership: every `Org` now has an `owner_id`/`is_personal` in addition to
  the flat `org_user` membership pivot. `App\Domains\Auth\Actions\Register`
  marks a freshly-registered user's founding org `is_personal = true` with
  `owner_id` = that user — a personal org's owner is fixed for life,
  `TransferOrgOwnership` refuses (422 on `email`) to reassign it even to
  the owner's own request. `StoreOrg` sets a real, transferable `owner_id`
  (the creator) on every subsequently-founded org, `is_personal` staying at
  its default `false`. `TransferOrgOwnership` is the only Action gated on
  ownership rather than plain membership (`$org->owner_id ===
  $request->user()?->id`, not `AuthorizesOrgMembership`) — restricted to
  the current owner, and refuses (422 on `email`) a target email that isn't
  already a member of the org. **Deliberate, known scope limit:**
  `RemoveOrgMember` and `DestroyOrg` were *not* changed to check ownership
  — any member can still remove the org's owner as a member, or delete the
  org outright, exactly as before this change. That's an intentional
  smaller-scope decision (this batch only introduced ownership for the
  *transfer* flow), not an oversight — tightening those two Actions to
  respect ownership is a separate, not-yet-scoped follow-up.
- Full CRUD + membership management batch: `StoreOrg`/`UpdateOrg`/`DestroyOrg`,
  `AddOrgMember`/`RemoveOrgMember`, `StoreTeam`/`UpdateTeam`/`DestroyTeam`,
  `StoreApp`/`UpdateApp`/`DestroyApp` — before this batch the only way any
  `Org`/`Team`/`App`/membership row existed was a manual `OrgSeeder` (since removed;
  `db:seed` now only creates the admin user).
  Every destructive Action that has children (`DestroyOrg` vs. teams,
  `DestroyTeam` vs. apps) refuses with a 422 rather than relying on the DB's
  `cascadeOnDelete` to silently wipe multiple rows from one call — delete the
  children first. `DestroyApp` is the one exception: it deletes the `App` row
  outright, but explicitly does **not** touch the app's telemetry in the
  separate `nightowl_*` tables (different database, owned by `nightowl/agent`).
  New tests: `tests/Feature/Apps/OrgManagementApiTest.php`,
  `TeamManagementApiTest.php`, `AppManagementApiTest.php`.
- `App\Support\AuthorizesOrgMembership` (a trait, alongside the pre-existing
  `AuthorizesAppScope`) centralizes the "is this user a member of the org
  that owns this route model" check shared by all ten Actions above, instead
  of each repeating `$org->users->contains($request->user())` inline.
  `App::generateAgentToken()` similarly centralizes the `'nwt_'`-prefixed
  token format so `StoreApp` and `Settings\Actions\RegenerateAppToken` share
  one implementation instead of two copies.
- `UpdateTeam`/`DestroyTeam`'s `handle()` keeps an unused, type-hinted
  `Org $org` parameter ahead of `Team $team`. This looks like it should be
  removable (read `{team}` off `$request->route('team')` instead, like
  `authorize()` does) — that was tried during a cleanup pass and broke both
  Actions: Laravel's implicit route-model binding is resolved by reflecting
  on `handle()`'s own parameter types, not `authorize()`'s. Drop the `Org`
  type hint from `handle()` and `{org}` (and, empirically, `{team}` too) is
  never substituted for a model at all — `$request->route('team')` then
  returns the raw uuid string everywhere, including inside `authorize()`,
  500ing every call. So `handle()`'s signature isn't just fixing positional
  argument order, it's the mechanism that makes the route bindings exist in
  the first place; the unused `Org $org` parameter is load-bearing, not
  cosmetic.
- `AddOrgMember` has no email-invite infrastructure behind it — it can only
  attach an *existing* user account (looked up by email) to an org, never
  create one. Onboarding a brand-new teammate still requires them to register
  first (`App\Domains\Auth\Actions\Register`) before anyone can add them.
  Its response is a single member object, not a list — a follow-up on the web
  side updates the SPA to match (`{ uuid, name, email }`, HTTP 201).
- Code-review follow-up (post-CRUD-batch): `DestroyOrg`/`DestroyTeam`'s
  original "check `exists()`, then `delete()`" was a TOCTOU race — a
  concurrent insert of a new child row between the check and the delete
  would be silently cascade-deleted (`teams.org_id`/`apps.team_id` are both
  `cascadeOnDelete()`). Both now re-fetch the parent row with
  `lockForUpdate()` inside a `DB::transaction()` before the children-exist
  check, so a concurrent INSERT referencing that row via FK is blocked
  until this transaction commits/rolls back. The shared "refuse if it has
  children" check is factored into
  `App\Domains\Apps\Support\RefusesCascadeDelete` (a trait used by both
  Actions) instead of being duplicated. Same pass added `ListOrgMembers`
  (there was previously no way to fetch an org's existing member list —
  only `AddOrgMember`/`RemoveOrgMember` responses within a session) and
  dropped the leaked `id` key from `OrgMemberResource`.
- `ListApps::handle()` used to be `Org::query()->firstOrFail()` — literally
  the first `Org` row in the whole table, regardless of who was asking. That
  was carried forward unchanged from the pre-migration controller as an
  out-of-scope limitation, but the CRUD/membership batch above made it an
  active bug: a newly registered user (attached to their own, separate `Org`
  by `Register`) was shown/scoped to whichever `Org` happened to be first in
  the table instead of their own — invisible teams/apps on the dashboard,
  and every `Organization` page mutation 403ing since they weren't a member
  of that other `Org`. First fixed to `$request->user()->orgs()->first() ??
  Org::query()->firstOrFail()`, mirroring `ListOrgs`'s membership lookup +
  no-membership fallback at the time. `ListApps`'s non-app-scoped nature (it
  still only supports one `Org` per user, not a picker across several) is a
  separate, remaining limitation — see the `StoreOrg`/`AddOrgMember` note
  above for why a second `Org` still isn't reachable from the UI.
  New test: `AppApiTest::test_apps_endpoint_returns_the_current_users_own_org_not_the_first_one_in_the_table`.
- **Cross-tenant leak fix (post-CRUD-batch):** the `Org::query()->firstOrFail()`/
  "fall back to every org" no-membership fallbacks described in the two notes
  above (`ListApps` and `ListOrgs`) were themselves a data leak, not just a
  demo convenience — a user with zero org memberships (e.g. freshly
  registered, membership row not yet seeded, or removed from their only org)
  could see another user's private org, teams, and apps instead of a clean
  empty state, and in `ListApps`'s case could also 404 confusingly on an
  empty table. Both fallbacks were removed: `ListOrgs` now simply returns
  `$request->user()->orgs()->...->get()`, which may legitimately be an empty
  collection; `ListApps` now returns `response()->json(['org' => null, 'teams'
  => []])` (HTTP 200) when `$requestedOrg ?? $request->user()->orgs()->first()`
  is null, instead of ever substituting a different org. The `?org=<uuid>`
  query-param branch (member-of-a-known-org 404, unknown-uuid self-heal) and
  the "user has exactly one org" happy path are unaffected. New/updated
  tests: `OrgApiTest::test_returns_an_empty_list_when_the_user_has_no_membership`,
  `AppApiTest::test_apps_endpoint_returns_an_empty_org_when_the_user_has_no_membership`,
  `AppApiTest::test_apps_endpoint_does_not_leak_another_users_org_when_the_acting_user_has_no_membership`.
- Relocated tests (Batch 4 of the controllers → Actions migration):
  `tests/Feature/Apps/AppScopingTest.php`'s `test_apps_endpoint_returns_teams_and_apps`
  / `test_app_show_returns_the_app` / `test_unknown_app_is_not_found` now
  live in `tests/Feature/Apps/AppApiTest.php`. Likewise
  `tests/Feature/Apps/DashboardApiTest.php`'s
  `test_dashboard_summarizes_requests_and_exceptions` now lives in
  `tests/Feature/Apps/AppDashboardApiTest.php`.
  `tests/Feature/Apps/OrgApiTest.php` is new, fixing `ListOrgs`'s
  (`OrgController::index`'s) zero-coverage gap.
  (Batch 7 of the same migration then finished off both original files:
  `AppScopingTest.php`'s remaining telemetry-scoping case moved to
  `tests/Feature/Telemetry/TelemetryApiTest.php` and `DashboardApiTest.php`'s
  remaining timeseries cases moved to
  `tests/Feature/Timeseries/ShowTimeseriesTest.php`, emptying and removing
  both files — `TelemetryController`/`TimeseriesController` are now
  `App\Actions\Telemetry\*`/`App\Actions\Timeseries\ShowTimeseries`.)
