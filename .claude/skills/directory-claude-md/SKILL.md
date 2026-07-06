---
name: directory-claude-md
description: Load the directory-specific CLAUDE.md (agent/CLAUDE.md, api/CLAUDE.md, or web/CLAUDE.md) before working on that side of the NightOwl repo. Use proactively — without being asked — the first time a session touches files under agent/, api/, or web/, before writing or editing code there, or before scoping a subagent to one side. The root CLAUDE.md carries only the shared big picture; the authoritative per-side runtime, layout, conventions, and "before declaring done" gates live in the nested files. Also use when the user asks to follow, apply, or check the agent/api/web conventions.
---

# Use the directory CLAUDE.md

NightOwl splits its guidance across four `CLAUDE.md` files. The root one is the **shared
big picture only** (why the repo is split into three projects, Docker/local dev, the
dashboard architecture, CI). Each side's authoritative rules live in its own nested file,
and they win for anything concrete about that side.

| Working under… | Read first | Covers |
|---|---|---|
| `agent/` | [`agent/CLAUDE.md`](../../agent/CLAUDE.md) | The ReactPHP telemetry ingest daemon — async server, SQLite buffer, Postgres drain, wire protocol, the PHPUnit Unit/Integration/System gate. No HTTP domains/Actions here — it's a background daemon package, not a Laravel app. |
| `api/` | [`api/CLAUDE.md`](../../api/CLAUDE.md) | Laravel 13 + Sanctum API — existing config-driven controllers, the DDD + Actions convention for **new** domain work, UUID-only public ids for new domains, the PHPUnit + Pint gate |
| `web/` | [`web/CLAUDE.md`](../../web/CLAUDE.md) | Vue 3 SPA — existing plain-JS pages + Pinia, the TypeScript/TanStack Query/VeeValidate target stack for **new** work, Tailwind-only styling, the Vitest gate |
| More than one (crossing a boundary) | root [`CLAUDE.md`](../../CLAUDE.md) *Why the split* section **+** the relevant nested file(s) | **Delegate each side to its own subagent** when a task spans api ↔ web (api first — it owns the contract — then web). Single-side tasks stay in the main session. |

## Workflow

1. **Detect the side.** Look at the paths the current task touches. Anything under `agent/` → agent side; `api/` → api side; `web/` → web side; more than one → cross-boundary.
2. **Read the relevant nested `CLAUDE.md`** with the Read tool *before* writing or editing code on that side — not the whole file if you already have it, but don't skip it on a fresh session. If scoping a subagent to one side, hand it that file (or grant it access to read it).
3. **For api ↔ web work, delegate to subagents.** Do not edit both sides yourself. Spawn an api-scoped subagent first (endpoint/Action/resource; it loads `api/CLAUDE.md` and passes its gate), then a web-scoped subagent that consumes the endpoint (it loads `web/CLAUDE.md` and passes its gate).
4. **Honour that side's per-side rules and its "before declaring done" gate** — the nested file names the exact commands. Don't call a change done until they pass.
5. **New domain/feature work also follows the target conventions**: UUID-only public ids and routes-as-shared-vocabulary (`.claude/rules/uuid-public-ids.md`, `.claude/rules/routes.md`) — both apply going forward, not retroactively to existing code.

## Guardrails

- The root `CLAUDE.md` is orientation; **defer to the nested file** whenever the two seem to differ on a concrete rule for that side.
- Don't invent routes/endpoints on the web — if one is missing it must be added on the api first.
- Each side also ships its own feature skills (`api-domain-dev`, `domain-doc`, `domain-readme-sync`; `web-feature-dev`) — reach for those once you're building the actual feature; this skill just makes sure the right `CLAUDE.md` is loaded first.
