---
name: domain-readme-sync
description: Whenever business logic changes inside a NightOwl api domain (app/Domains/<Module>/) — adding, changing, or removing an Action, event, policy, job, or model behavior, or route — you MUST update that same domain's README.md in the same change. Use this any time you edit files under app/Domains/<Module>/ that affect what the domain does. Applies to new DDD/Actions domain work, not the existing app/Http/Controllers/ code.
---

# Keep the domain README in sync with business-logic changes

The per-domain `README.md` (see the [`domain-doc`](../domain-doc/SKILL.md) skill for its required
shape) documents a domain's **current** business logic. It only stays true if it is updated **in the
same change** that alters the logic. This skill is that discipline: touch the logic → touch the
README.

## When this triggers

Any edit under `app/Domains/<Module>/` that changes what the domain does, i.e. adds/changes/removes:

- an **Action** (`Actions/`) — the most common case,
- an **event** (`Events/`),
- a **policy** (`Policies/`), **job** (`Jobs/`), or **notification** (`Notifications/`),
- a **route** (`Routes/api.php`) — new/renamed/removed endpoint or middleware,
- **model behavior** that changes an invariant, cast, or relationship.

Pure formatting, comments, or test-only edits don't require a README change.

## What to do (same change, not later)

1. Identify which domain(s) the edit touches (`app/Domains/<Module>/`).
2. Open that domain's `README.md`.
3. Reflect the change — mirror the edit, don't just append:
   - **Added** logic → add the Action row / endpoint / event.
   - **Changed** logic → update the existing entry (behavior, auth/rules, invariants).
   - **Removed** logic → delete the entry. A README listing a deleted Action is a bug.
4. Keep the [`domain-doc`](../domain-doc/SKILL.md) section structure and stay concise.

## Self-check before you call the change done

> Did I add/change/remove business logic under `app/Domains/<X>/`?
> → Then does `app/Domains/<X>/README.md` now match the code, with nothing stale left behind?

If yes to the first and no to the second, the change is not finished.

## Note

A skill is guidance Claude follows when it recognizes the change — it is not an automatic gate. For
hard, mechanical enforcement (fail the build when a domain's code changed but its `README.md` did
not), ask for a PHPUnit test or a PostToolUse hook instead.
