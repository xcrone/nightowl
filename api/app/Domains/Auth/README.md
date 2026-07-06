# Auth

## Purpose

Session-based authentication for the SPA (Sanctum's "SPA authentication"
pattern, not API tokens): login, logout, and "who am I" for the
currently-authenticated user. The frontend calls `GET /sanctum/csrf-cookie`
first (Sanctum's own route, not this domain's), then `POST /login` with the
`X-XSRF-TOKEN` header, and finally reads the session cookie on every
subsequent request.

Login/logout are deliberately **not** registered on the `auth:sanctum` API
guard — they're plain Sanctum SPA session endpoints reachable only through
`routes/web.php`'s `'web'` middleware group (session + CSRF), exactly as
before this migration. Actions from `lorisleiva/laravel-actions` are plain
invokable classes, so `routes/web.php` references them directly
(`Route::post('/login', Login::class)`) instead of a `[Controller::class,
'method']` array — no controller layer needed either way.

## Models

This domain does not own a model folder — `App\Models\User` stays in
`app/Models/` (per the migration plan's "models never physically relocate"
rule: `User` predates this domain and isn't exclusive to it). It's Laravel's
default `Authenticatable` model, with a `uuid` column added by this batch's
retrofit migration (`database/migrations/2026_07_06_110331_add_uuid_to_users_table.php`)
and auto-generated on create via `User::booted()`'s `creating` hook.

## Business logic (Actions)

| Action | Does | Notes (auth, rules, invariants) |
|---|---|---|
| `Login` | Validates `email`/`password`, `Auth::attempt()`s them (honoring an optional `remember` boolean), regenerates the session on success, returns `{user: UserResource}`. | `authorize()` always allows (anyone may attempt to log in). `rules()`: `email` required email, `password` required string. Throws `ValidationException` (`email` field, `auth.failed` message) on bad credentials — surfaces as a 422, matching Laravel's default failed-login shape. |
| `Logout` | Logs the `web` guard out, invalidates the session, regenerates the CSRF token. Returns 204 No Content. | `authorize()` always allows — relies on `routes/web.php` having no additional guard; an unauthenticated caller just logs out a guest session harmlessly. |
| `ShowAuthenticatedUser` | Returns `{user: UserResource}` for `request->user()`. | Reached only through the `auth:sanctum` middleware group (root aggregator), so `$request->user()` is always present when this runs — a request without a valid session/token never reaches the handler (401 short-circuits first). |

## Resources

`UserResource` serializes `id`, `uuid`, `name`, `email`, `email_verified_at`,
`created_at`, `updated_at` — mirrors exactly what the raw `User` model
exposed before this migration (`password`/`remember_token` were already
hidden via the model's `#[Hidden]` attribute). Per the uuid retrofit plan,
`uuid` is **additive** this pass; `id` stays in the response too so no
existing consumer breaks — a follow-up (coordinated with the web side) will
drop `id` once the SPA is confirmed to key off `uuid` instead.

## Endpoints

| Method | URI | Action | Middleware |
|---|---|---|---|
| POST | `/login` | `Login` | `web` (session + CSRF, see `routes/web.php`) |
| POST | `/logout` | `Logout` | `web` (session + CSRF, see `routes/web.php`) |
| GET | `/api/user` | `ShowAuthenticatedUser` | `auth:sanctum` (root aggregator group) |

## Events & cross-module contracts

None. No events emitted or consumed; no `app/Support/` interface used.

## Notes

- `Login`/`Logout` are the one exception to "every domain's routes live in
  its own `Routes/api.php`, aggregated by the root loop": they're registered
  directly in `routes/web.php` because they need the `web` middleware group
  (session + CSRF), not `auth:sanctum`. `ShowAuthenticatedUser` follows the
  normal pattern (`app/Domains/Auth/Routes/api.php`, aggregated into
  `routes/api.php`'s `auth:sanctum` group).
- The CORS regression covered by
  `tests/Feature/Auth/LoginTest.php::test_login_route_sends_cors_headers_for_the_frontend_origin`
  and the no-Accept-header 401 regression
  (`test_unauthenticated_request_without_json_accept_header_still_gets_401`,
  tied to `AppServiceProvider::boot()`'s `Authenticate::redirectUsing()`) are
  both still exercised against these Actions — no behavior changed, only the
  entrypoint type.
