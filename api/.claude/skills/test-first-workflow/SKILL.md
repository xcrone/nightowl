---
name: test-first-workflow
description: MANDATORY test-first gate before writing or changing business logic in a NightOwl api domain. Use BEFORE coding whenever you add a new feature, add a new domain module, add or change business logic, or change conditions (validation, settings, permissions, calculations) under app/Domains/<Module>/. Codifies the required order — check existing tests, list test changes, get the user to confirm the tests AND the requirements, then build tests via a dedicated subagent before any API code.
---

# Test-first workflow (NightOwl api — new domain work)

For new domain work under `app/Domains/`, tests come **before** implementation. Any
change to what such a domain *does* — a new feature, a new domain module, new business
logic, or a change to conditions (validation rules, settings, permissions, calculations)
— MUST go through the gate below **before a single line of production API code is
written**. This is not optional and it is not something you decide to skip because the
change "looks small."

This does not retroactively apply to the generic, config-driven engines under
`app/Actions/` (`Telemetry`/`Aggregates`/`Rollups`/`Timeseries`/`Health` — formerly
`app/Http/Controllers/`, migrated but not a bounded domain) — that's a separate,
already-established pattern; use ordinary judgment there.

Pair with [`action-test-sync`](../action-test-sync/SKILL.md) (every Action stays covered) and
[`api-domain-dev`](../api-domain-dev/SKILL.md) (how the API code is actually built, *after* this gate).

## When this triggers

Before you touch production code, whenever the task is to:

- **Add a new feature** implemented as a new domain module.
- **Add a new domain module** under `app/Domains/<Module>/`.
- **Add new business logic** (a new Action, event, calculation, side effect) in an existing domain.
- **Change conditions** — validation (`rules()`), settings, permissions (`authorize()`/policies),
  or any branching behavior.

Not for: pure formatting, comments, docblocks, renames with no behavior change, or infra/docs.

## The required order — do NOT skip or reorder

### 1. Check the related unit test(s) first
Before anything else, look for the existing test(s) that cover the area you're about to change
(`tests/Feature/<Module>/…Test.php`, `tests/Unit/…`). Read them. Whether one exists or not is the
first thing you report.

### 2. List the test changes
Produce an explicit list of:
- **Tests that need to change** (name the file + case + what assertion changes and why).
- **New tests that might need to be added** (name each proposed test + the behavior it locks in).

Cover the whole lifecycle you expect to touch: happy path, `authorize()` (403/401), `rules()` (422 +
`assertJsonValidationErrors`), side effects.

### 3. Ask the user to confirm the tests
Present the list from step 2 and **ask the user whether the listed unit tests are correct.** Do not
proceed on assumption. Wait for their answer and adjust the list to match.

### 4. Confirm the business logic & requirements
**Always confirm the business logic and requirements from the user before starting API coding** —
validation, settings, permissions, defaults, edge cases. Surface the concrete requirements you're
assuming and get explicit confirmation. Steps 3 and 4 are both gates: you have not been cleared to
build until the user has confirmed **both** the tests and the requirements.

### 5. Plan
Once the user has confirmed the tests and the requirements, write the implementation plan.

### 6. Spawn ONE subagent that does only the tests
Spawn a **single** subagent whose sole job is the unit tests — it writes/updates the test files from
the confirmed list and **does nothing else**. It does not write Actions, migrations, models, or any
production code. Scope it explicitly to the test files.

### 7. Main agent builds test dependencies via a separate subagent
If the test subagent needs something to exist for the tests to run (an Action stub, a migration, a
model, a factory, a route), it reports that need back — it does **not** build it itself. The **main
agent** then spawns **another** subagent to produce exactly that dependency. Keep the test subagent
focused on tests; delegate every non-test artifact to its own builder subagent.

### 8. Only now write the actual API code
After the tests are in place, do the real API implementation to make them pass. **Do not touch the
unit tests during this phase** — no edits, no deletions, no "adjusting" assertions — unless the user
has explicitly given permission to change a test.

## Self-check before you start coding

> Did I (1) check existing tests, (2) list test changes + additions, (3) get the user to confirm the
> tests, and (4) get the user to confirm the business logic/requirements — **before** planning?
> Then: is the test work owned by a single test-only subagent, with any dependency it needs built by a
> separate subagent the main agent spawns?

If any answer is no, stop and go back — you are not cleared to write API code. Once tests are green,
the api gate still applies:

```bash
vendor/bin/phpunit              # tests (--filter=<Name> for one)
vendor/bin/pint                 # format
```
