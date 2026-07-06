---
name: claude-md-sync
description: Use this skill proactively, without waiting to be asked, whenever a change in this session touches something a CLAUDE.md documents about the NightOwl repo's stack or rules — e.g. edits to api/composer.json or agent/composer.json (require/require-dev), web/package.json (deps or scripts), api/config/telemetry.php or api/config/aggregates.php (the resource registries), phpunit.xml (testsuite/excludes), pint.json, eslint.config.js, .github/workflows/*.yml (CI gates), api/config/database.php (connections), or the addition/removal of an api/app/Domains/<Name> module. Also use when the user explicitly asks to "check", "sync", "update", or "align" CLAUDE.md, or after a task that changed how the project is built, tested, linted, deployed, or reviewed. Not for routine feature work that doesn't touch these files.
---

# CLAUDE.md Sync

Keep the root `CLAUDE.md` and the three nested ones (`agent/CLAUDE.md`, `api/CLAUDE.md`,
`web/CLAUDE.md`) accurate as NightOwl's stack, tooling, and rules evolve. This is a
targeted diff-and-patch pass, not a full rewrite.

## When to run this

Run it at the point you notice — mid-task or at wrap-up — that a change you just made falls into one of these buckets:

| Change | CLAUDE.md section affected |
|---|---|
| `api/composer.json` or `agent/composer.json` require/require-dev added/removed/majorly bumped | Architecture, Commands (e.g. adding `lorisleiva/laravel-actions` for the first domain module) |
| `web/package.json` scripts or key deps changed | Commands (Web) |
| `api/config/telemetry.php` or `api/config/aggregates.php` registries — a resource key added/removed | api/CLAUDE.md's resource list, if one exists |
| First `api/app/Domains/<Name>/` created, or one that deviates from the documented shape | api/CLAUDE.md > domain structure section |
| `phpunit.xml` testsuite/exclude list changed (agent or api) | Commands note about test suites |
| `pint.json`, `eslint.config.js` rules changed materially | Commands (format/lint commands only; don't enumerate individual rules) |
| `.github/workflows/*.yml` — path filters, matrix, gates | CI section |
| `api/config/database.php` connections, or the `nightowl` connection name | Architecture > multi-tenancy / database section |

If none of these apply, don't run this skill.

## Workflow

1. **Scope the diff.** Use `git diff` / `git show` on the specific file(s) that changed, not a full repo re-scan. You already know what changed from the current task — don't re-derive it.
2. **Read the relevant CLAUDE.md section(s)** (grep for the section header, don't reread the whole file if you don't need to).
3. **Decide if the doc is now wrong or stale** — a command that no longer works, a list that's out of date, a rule that changed. If it's still accurate (e.g. a minor patch bump, or a change that doesn't affect anything documented), do nothing and say so briefly.
4. **Make a targeted edit** with the `Edit` tool — change only the affected lines/section. Do not restructure unrelated sections, do not add new sections speculatively, do not pad with generic advice.
5. **Match the existing house style**: concise, copy-paste-ready commands, no restating of what's obvious from the code, no comment-like filler. If in doubt, say less.
6. **Report what changed** in one or two sentences (what section, why) — don't dump a full diff unless asked.

## Guardrails

- Never invent commands or facts — verify against the actual file (e.g. re-check `composer.json`/`package.json` scripts before documenting one).
- Don't turn this into a full CLAUDE.md audit each time — that's a much bigger job than "does this one change need reflecting."
- If a change is ambiguous or repo-wide (e.g. a large refactor of the domain pattern itself), flag it to the user instead of guessing at a rewrite.
