# Rule: Route names are the shared vocabulary (target convention)

Shared api ↔ web invariant. Imported by the root, `api/`, and `web/` `CLAUDE.md`.
**Not yet implemented** — this describes where the routes workflow is headed, not what
exists today. Today the SPA calls `/api/apps/{app}/...` endpoints directly through
`web/src/services/api.js`; there is no route-name/export pipeline yet.

Target state, once built:

- `api/routes/api.php` (a pure aggregator globbing each domain module's `Routes/api.php`)
  is the **single source of truth** for every route.
- After changing routes, run `composer routes:export` to regenerate a route map the SPA
  consumes (both the artisan command and the composer script still need to be written).
- The SPA reaches every endpoint via a `route('name', params)` helper — never a
  hardcoded URL, and the web never invents a route the api hasn't exported.

Reinforced by hooks: `api/.claude/hooks/routes-export-reminder.sh` (nudge to re-export)
and `web/.claude/hooks/generated-routes-reminder.sh` (don't hand-edit generated files) —
both are forward-looking and only fire once the corresponding files exist.
