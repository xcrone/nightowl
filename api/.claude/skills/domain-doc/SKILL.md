---
name: domain-doc
description: Every NightOwl api bounded context under app/Domains/<Module>/ must carry a README.md that describes what the module is for and details its CURRENT business logic. Use when creating a new domain, or when adding/changing Actions, models, policies, or routes in a domain — the domain's README.md is part of the change and must stay in sync. Applies to new DDD/Actions domain work, not the existing app/Http/Controllers/ code.
---

# Per-domain README.md

Every domain module under `app/Domains/` is self-documenting. `app/Domains/<Module>/README.md`
is **mandatory** and is part of the same change that touches the domain — a change that
adds or alters business logic without updating the domain README is incomplete.

## The rule

1. **Every `app/Domains/<Module>/` has a `README.md`.** Creating a domain without one is not done.
2. **It describes what the module is about** — its purpose, the slice of the business it owns, and
   (briefly) what it explicitly does *not* own.
3. **It details the business logic the domain currently has** — not aspirations. Every Action,
   policy, and route that exists today is reflected. When you add, change, or remove logic,
   update the README in the same change so it never drifts.

Keep it concise and current over exhaustive and stale. If the code and the README disagree, the
README is the bug.

## Required sections

```markdown
# <Module>

## Purpose
What this bounded context is responsible for, in 1–3 sentences. What it deliberately does NOT own.

## Models
Key models and any notable invariants.

## Business logic (Actions)
One entry per Action — what it does, its authorize/rules gist, and any invariants it enforces.
| Action | Does | Notes (auth, rules, invariants) |

## Endpoints
Route → Action mapping (method, URI, route name, middleware).

## Events & cross-module contracts
Events this domain emits, events it reacts to, and any `app/Support/` interfaces it relies
on. "None yet" is a valid, honest entry.

## Notes
Anything non-obvious: external integrations, edge cases, deliberate simplifications.
```

Omit a section only if it is genuinely empty — and prefer writing "None yet" so readers know it was
considered, not forgotten.

## Definition of done

- [ ] `app/Domains/<Module>/README.md` exists.
- [ ] Purpose is stated; the domain boundary is clear.
- [ ] Every current Action / event / route is listed and matches the code.
- [ ] The README was updated in the **same change** as any business-logic edit.

This complements [`api-domain-dev`](../api-domain-dev/SKILL.md), which covers building the
domain itself, and [`domain-readme-sync`](../domain-readme-sync/SKILL.md), which enforces updating
this README in the same change as any business-logic edit.
