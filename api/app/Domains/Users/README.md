# Users

## Purpose

Telemetry-derived end-user directory and per-user drill-down
(`docs/pages/user-detail.md`): a legacy top-level user list/lookup carried
forward from before this migration, plus the app-scoped single-user
drill-down (identity, request status mix, top/slowest routes, top queued
jobs) shown on `UserDetailPage.vue`.

## Models

This domain defines no models of its own. It reads `NightowlUser`,
`RequestRecord`, and `JobRecord`, all of which live in
`app/Models/Telemetry/` and are owned by `nightowl/agent`'s migrations (the
`nightowl_*` Postgres tables) — they stay put per the migration plan's
"models never physically relocate" rule (each is also consumed by other
domains/`app/Actions/`).

`NightowlUser`'s primary key **is** its `user_id` column (a string — the
monitored app's own user identifier, e.g. sourced from Nightwatch), not an
auto-increment integer; there is no separate internal PK to leak, so this
domain needs **no `uuid` retrofit** — `NightowlUserResource` already
satisfies `uuid-public-ids` by serializing `user_id` (never any future
internal column) instead of the routing/lookup key.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `ListNightowlUsers` | Paginated list of `NightowlUser` (cap 100/page), optional `?q=` search (ILIKE over `name`/`email`, escaped via `App\Support\SearchTerm`), ordered by `updated_at` desc. | `authorize()` always allows. **Legacy, NOT app-scoped** — queries every app's users together; this is a pre-existing limitation carried forward, not introduced by this migration. |
| `ShowNightowlUser` | `NightowlUser::query()->findOrFail($userId)` — 404s if the id doesn't exist. | `authorize()` always allows. **Legacy, NOT app-scoped** — same limitation as above (no `forApp()` call). This endpoint had zero test coverage before this migration; `tests/Feature/Users/NightowlUserApiTest.php` now covers both the 200 and 404 cases. |
| `ShowUserDetail` | App-scoped (`{app}/users/{userId}`), period-aware (`Period::resolve()`): looks up the `NightowlUser` row (nullable — a user can have driven telemetry before their `nightowl_users` upsert lands), plus `RequestRecord`/`JobRecord` aggregates for the window — request status-code buckets (`c2xx`/`c4xx`/`c5xx`), top 10 routes by count, top 10 slowest routes by p95 duration, top 10 jobs by count. | `authorize()` always allows (any authenticated user on the app). No rules — route-bound `{app}`/`{userId}`, `period` resolved from the query string by `Period::resolve()`. |

## Resources

`NightowlUserResource` serializes `user_id`, `name`, `email`, `created_at`,
`updated_at` — never a raw `NightowlUser` model. Used by both
`ListNightowlUsers`'s paginated collection and `ShowNightowlUser`, and
reused inside `ShowUserDetail`'s `user` key when a matching `NightowlUser`
row exists (falls back to a plain `{user_id, name: null, email: null,
created_at: null, updated_at: null}` array when it doesn't, since a user can
generate telemetry before their identity record is upserted).

`ShowUserDetail`'s `requests`/`top_routes`/`slowest_routes`/`top_jobs` keys
are pure computed aggregates (GROUP BY + `percentile_cont`), not serialized
models — no Resource for those, per the migration plan's "pure computed
payloads don't need a Resource" rule.

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| GET | `/api/users` | `ListNightowlUsers` | `auth:sanctum` (root aggregator group). **Legacy — NOT app-scoped.** |
| GET | `/api/users/{userId}` | `ShowNightowlUser` | `auth:sanctum` (root aggregator group). **Legacy — NOT app-scoped.** |
| GET | `/api/apps/{app}/users/{userId}` | `ShowUserDetail` | `auth:sanctum` (root aggregator group) |

## Events & cross-module contracts

None. No events emitted or consumed; no `app/Support/` interface used
beyond the telemetry models' own `forApp` scope and `App\Support\Period`.

## Notes

- `ListNightowlUsers`/`ShowNightowlUser`'s lack of `{app}` scoping is a
  **known, pre-existing limitation** carried forward from before this
  migration (see `App\Domains\Apps` and telemetry's per-`{app}` routes for
  the scoped equivalents elsewhere in the API) — it is explicitly out of
  this batch's scope to add scoping here, since that would be a behavior
  change, not a straight port. If/when these are scoped, update this README
  and the two legacy routes in the same change.
- Relocated tests (Batch 3 of the controllers → Actions migration):
  `tests/Feature/Telemetry/NewScopeApiTest.php`'s `test_lists_nightowl_users`
  / `test_users_search_matches_name_or_email` and
  `tests/Feature/Apps/IssueUserDetailTest.php`'s
  `test_user_detail_aggregates_requests_and_routes` now live in
  `tests/Feature/Users/NightowlUserApiTest.php` /
  `tests/Feature/Users/UserDetailApiTest.php`, alongside the new
  `ShowNightowlUser` 200/404 coverage.
