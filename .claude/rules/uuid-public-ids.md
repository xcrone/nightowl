# Rule: UUIDs are the only public identifier (new domain work)

Shared api ↔ web invariant for anything built under the new DDD/Actions convention
(`api/app/Domains/<Module>/`). Imported by the root, `api/`, and `web/` `CLAUDE.md`.
**Strict** — enforced on the api by a `PreToolUse` hook
(`api/.claude/hooks/uuid-public-ids-block.sh`), which denies edits that leak the integer
PK from a new domain's Resources or migrations. Do not work around it.

This is separate from the existing `app_id`/`App` opaque-identifier scoping documented in
the root `CLAUDE.md` (multi-app telemetry scoping) — that mechanism is unrelated and
stays as-is.

The auto-increment integer `id` is internal only (joins, FKs) and must **never** cross
the api → web boundary for a new domain's models:

- Every user-facing table in a new domain has an indexed `uuid` column.
- Models bind routes on `uuid` (`getRouteKeyName`) and generate it on create.
- Resources serialize the `uuid` under a `uuid` key — never the integer PK. Request
  rules resolve identifiers against `uuid`.
- On the web, any identifier holding a UUID is named `uuid` or `<thing>Uuid`, not `id`.

See the api `uuid-public-ids` skill for the full api-side mechanics.
