---
description: Run the web "before declaring done" gate — Vitest — and report pass/fail.
allowed-tools: Bash
---

Run the NightOwl web SPA gate and report the result:

1. `pnpm test`

If arguments were provided (`$ARGUMENTS`), pass them to Vitest (`pnpm test $ARGUMENTS`).

`pnpm lint` and `pnpm typecheck` are **not wired up yet** — `package.json` has no `lint`
or `typecheck` script (ESLint is installed as a devDependency but not scripted, and there
is no TypeScript toolchain). Don't invent or run commands that don't exist; if the task
at hand would benefit from either, flag it to the user instead of silently skipping.

Summarize: pass/fail for `pnpm test`, and for any failure the concise list of offending
specs. Package manager is pnpm — never npm/yarn.
