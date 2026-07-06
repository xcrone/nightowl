---
name: action-test-sync
description: Whenever you add, change, or remove an Action in a NightOwl api domain (app/Domains/<Module>/Actions/), you MUST create or update its companion PHPUnit test in the same change. Use this any time you edit an Action's authorize(), rules(), or handle() so the test suite always covers every Action's behavior. Applies to new DDD/Actions domain work, not the existing app/Http/Controllers/ code.
---

# Keep every Action covered by a test, in the same change

Every Action under `app/Domains/<Module>/Actions/` is an HTTP entrypoint with an
`authorize() → rules() → handle()` lifecycle. That behavior only stays trustworthy if a
PHPUnit test exercises it — and only stays in sync if the test is written or updated **in
the same change** that touches the Action. This skill is that discipline: touch an Action
→ touch its test.

Pair this with [`api-domain-dev`](../api-domain-dev/SKILL.md) (how Actions are built) and
[`domain-readme-sync`](../domain-readme-sync/SKILL.md) (the README half of the same reflex).

## When this triggers

Any edit under `app/Domains/<Module>/Actions/` that changes what an Action does:

- **Added** an Action → it needs a new test file.
- **Changed** `authorize()`, `rules()`, or `handle()` — new/renamed rule, auth condition,
  return shape, side effect, event dispatched → update the existing test.
- **Removed** an Action → delete its test (and any route that targeted it).

Pure formatting, comments, or docblock-only edits don't require a test change.

## Where the test lives

Tests are PHPUnit `TestCase` classes mirroring the domain, **one file per Action**, named
after the Action class, matching the existing convention under `api/tests/Feature/`
(e.g. `api/tests/Feature/Apps/AppSettingsTest.php`):

```
app/Domains/<Module>/Actions/CreateThing.php   →   tests/Feature/<Module>/CreateThingTest.php
```

- Put Action tests under `tests/Feature/<Module>/` — they drive the Action through its
  route (`$this->postJson('/api/...', …)`). Reserve `tests/Unit/` for pure logic with no
  framework/HTTP surface.
- Extend `Tests\TestCase`, use factories, `RefreshDatabase` when the test hits the
  database, and `declare(strict_types=1)`.

## What to cover for each Action

Reflect the change, don't just append. Aim to cover the whole lifecycle:

1. **Happy path** — a valid request returns the expected status, JSON shape (Resource),
   and persisted/side-effect state.
2. **`authorize()`** — an unauthorized caller gets `403` (or `401` when unauthenticated);
   a public Action stays reachable.
3. **`rules()`** — missing/invalid input returns `422` with the right
   `assertJsonValidationErrors([...])`, and the failed path performs no side effect.

When you **change** an Action, update the matching cases (don't leave a test asserting the
old status, shape, or rule); when you **remove** one, delete its test file.

## Self-check before you call the change done

> Did I add / change / remove an Action under `app/Domains/<X>/Actions/`?
> → Then does `tests/Feature/<X>/…Test.php` now exercise its current behavior, with no
>   stale case asserting the old contract and no test left for a deleted Action?

If yes to the first and no to the second, the change is not finished. Then run the gate:

```bash
vendor/bin/phpunit              # tests (--filter=<Name> for one)
vendor/bin/pint                 # format
```

## Enforcement

This skill is the guidance; a `PostToolUse` hook
(`api/.claude/hooks/action-test-reminder.sh`, wired in `api/.claude/settings.json`) nudges
you to write/update the test the moment you edit an Action. There is no CI gate enforcing
this yet (the existing workflows are `agent-tests.yml`, `api-tests.yml`, `web-tests.yml` —
none of them check Action/test pairing) — the hook is the only mechanical backstop today.
