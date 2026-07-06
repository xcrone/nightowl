---
name: test-first-workflow
description: MANDATORY test-first gate before writing or changing any web feature, page, component, or user experience. Use BEFORE coding whenever you add a new feature, a new page, a new user experience, or change conditions (validation, settings, permissions, visibility, states, calculations) in the SPA. Codifies the required order — check existing tests, list test changes, get the user to confirm the tests AND the requirements, then build tests via a dedicated subagent before any UI code.
---

# Test-first workflow (NightOwl web)

For this codebase, tests come **before** implementation. Any change to what the SPA *does* —
a new feature, a new page, a new user experience, or a change to conditions
(validation rules, settings, permissions, visibility, states, calculations) — MUST go through the
gate below **before a single line of production UI code is written**. This is not optional and it
is not something you decide to skip because the change "looks small." The api side has the mirror of
this gate (`api/.claude/skills/test-first-workflow`, for new domain work); this is the web counterpart.

Pair with [`web-feature-dev`](../web-feature-dev/SKILL.md) (how the SPA code is actually built,
*after* this gate).

## When this triggers

Before you touch production code, whenever the task is to:

- **Add a new feature.**
- **Add a new page / view** under `src/pages/` or `src/pages/app/`.
- **Add a new user experience** — a new flow, screen, interaction, or reusable component under
  `src/components/`.
- **Change conditions** — form validation, settings, permission-gated UI,
  visibility/enablement, state, or any branching behavior a user can observe.

Not for: pure formatting, comments, renames with no behavior change, copy-only tweaks, or docs.

## The required order — do NOT skip or reorder

### 1. Check the related unit test(s) first
Before anything else, look for the existing test(s) that cover the area you're about to change —
the colocated `*.test.js` next to the page (e.g. `IssueDetailPage.test.js`) and any component test
under `src/components/`. Read them. Whether one exists or not is the first thing you report.

### 2. List the test changes
Produce an explicit list of:
- **Tests that need to change** (name the file + case + what assertion changes and why).
- **New tests that might need to be added** (name each proposed test + the behavior it locks in).

Cover the whole experience you expect to touch: cold-mount render, loading / error / empty states,
form validation messages, permission- or condition-gated UI (shown vs hidden/disabled), and
mutation/optimistic-update behavior.

### 3. Ask the user to confirm the tests
Present the list from step 2 and **ask the user whether the listed unit tests are correct.** Do not
proceed on assumption. Wait for their answer and adjust the list to match.

### 4. Confirm the business logic & requirements
**Always confirm the business logic and requirements from the user before starting UI coding** —
validation, settings, permissions, defaults, visibility conditions, edge cases, date/number
formatting, state transitions. Surface the concrete requirements you're assuming and get explicit
confirmation. Steps 3 and 4 are both gates: you have not been cleared to build until the user has
confirmed **both** the tests and the requirements.

### 5. Plan
Once the user has confirmed the tests and the requirements, write the implementation plan.

### 6. Spawn ONE subagent that does only the tests
Spawn a **single** subagent whose sole job is the unit tests — it writes/updates the Vitest test
files from the confirmed list and **does nothing else**. It does not write pages, components,
composables, or stores. Scope it explicitly to the test files.

### 7. Main agent builds test dependencies via a separate subagent
If the test subagent needs something to exist for the tests to run (a component stub, a composable, a
store, a request fn, an endpoint that must be added on the api first), it reports that
need back — it does **not** build it itself. The **main agent** then spawns **another** subagent to
produce exactly that dependency (an api-owned endpoint goes to an api-scoped subagent).
Keep the test subagent focused on tests; delegate every non-test artifact to its own builder subagent.

### 8. Only now write the actual UI code
After the tests are in place, do the real SPA implementation to make them pass. **Do not touch the
unit tests during this phase** — no edits, no deletions, no "adjusting" assertions — unless the user
has explicitly given permission to change a test.

## Self-check before you start coding

> Did I (1) check existing tests, (2) list test changes + additions, (3) get the user to confirm the
> tests, and (4) get the user to confirm the business logic/requirements — **before** planning?
> Then: is the test work owned by a single test-only subagent, with any dependency it needs built by a
> separate subagent the main agent spawns?

If any answer is no, stop and go back — you are not cleared to write UI code. Once tests are green,
the web gate still applies: `pnpm test`.
