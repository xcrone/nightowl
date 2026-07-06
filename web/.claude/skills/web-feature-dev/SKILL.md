---
name: web-feature-dev
description: Build features in the NightOwl Vue 3 SPA. Use when adding or changing a dashboard page under src/pages/, adding a reusable UI component under src/components/, or wiring API calls, TanStack Query, Pinia stores, or VeeValidate/Zod forms on the web. Existing pages are plain JavaScript + Pinia + Chart.js; this skill also covers the target stack (TypeScript, TanStack Query, VeeValidate/Zod, Reka UI) for new work.
---

# Web feature development (NightOwl SPA)

Guidance for building features in `web/` — a Vite + Vue 3.5 + Pinia SPA, the Org → Teams
→ Apps dashboard. See [`../../CLAUDE.md`](../../CLAUDE.md) for the authoritative
per-side rules (styling, structure). Package manager is **pnpm**.

## Current vs. target — read this first

- **What exists today**: plain JavaScript (no TypeScript, no `tsconfig`), Pinia for all
  state (`store/app.js` holds the current app + period/timezone/timeFormat, persisted to
  `localStorage`; `store/theme.js` for dark/light/system), Chart.js via `vue-chartjs`, and
  a flat `src/pages/` + `src/pages/app/` layout (not per-domain folders). API calls go
  through `services/api.js` hitting `/api/apps/${appId}/...` directly — there is no
  `route()` helper or generated route map. None of this is broken; don't propose
  rewriting it wholesale.
- **What new work should do**: TypeScript, TanStack Query for server state (Pinia stays
  for client/UI state), VeeValidate + Zod for any new form, Reka UI primitives + `cva`/`cn`
  for new reusable components. None of these packages are installed yet (no `typescript`,
  `@tanstack/vue-query`, `vee-validate`, `zod`, `reka-ui`, or `class-variance-authority` in
  `package.json`) — add exactly what a piece of new work needs, when it needs it. Don't
  bulk-install the whole stack speculatively, and don't convert existing `.vue`/`.js`
  files to satisfy this convention unless a task specifically asks for that migration.

Not for: api contract changes. The SPA only *consumes* the API — if an endpoint doesn't
exist yet, it must be added on the api first. See the `api-domain-dev` skill.

## Golden rules

- **New files may use TypeScript** (`<script setup lang="ts">` / `.ts`) once the
  toolchain is wired up (see above); existing `.vue`/`.js` files stay as they are.
- **Routes**: no hardcoded-URL avoidance mechanism exists yet — new endpoint calls follow
  the existing pattern in `services/api.js` (`/api/apps/${appId}/...`). A `route('name',
  params)` helper backed by a generated route map is the target (see
  `.claude/rules/routes.md`) but isn't built; don't invent calls to a `route()` function
  that doesn't exist.
- **Server state via TanStack Query (once installed); client/UI state via Pinia.** Keep
  them separate — don't cache server data in a store, don't put UI toggles in Query.
  Until TanStack Query is added, follow the existing pattern (direct `services/api.js`
  calls from page components).
- **Pinia stores persist to `localStorage` where it makes sense** — this app already does
  (`period`/`timezone`/`timeFormat` in `store/app.js`, theme in `store/theme.js`). Keep
  new persisted state in a Pinia store the same way; there's no restriction to a single
  "token only" value here.
- **Forms via VeeValidate + Zod schemas**, once installed, for any new form with
  non-trivial validation. Dates via whatever the page already uses; don't add a new date
  library without checking what's already a dependency.
- **Mobile-first, always.** Design and build for the smallest screen first: unprefixed
  Tailwind utilities target mobile, then layer `sm:`/`md:`/`lg:` breakpoints upward —
  never the reverse.
- **Dark theme, always.** See `web/CLAUDE.md` — light/dark is class-based (`.dark` via
  `@custom-variant` in `src/style.css`), driven by `store/theme.js`. Pair every
  surface/text/border color utility with its `dark:` variant. Never inline `style=""`,
  `<style>` blocks, or raw hex — Tailwind utilities only (see `web/CLAUDE.md`).
- **New reusable components** may adopt Reka UI primitives + `cva`/`cn` once installed;
  existing components don't need to be rewritten to match.

## Pages — current layout

```
src/pages/
  Login.vue, IssueDetailPage.vue, ...   # a few flat, non-app-scoped pages
  OrgDashboard.vue                      # org-level landing
  app/
    AppDashboard.vue, IssuesPage.vue, RequestsPage.vue, ...
    AggregateListPage.vue               # shared wrapper for aggregate resource pages,
                                         # configured per-resource via src/aggregateConfig.js
```

Add a new aggregate-style resource page by extending `aggregateConfig.js` rather than
hand-writing a new page — see `web/CLAUDE.md`. For a genuinely new kind of page, add it
under `src/pages/` or `src/pages/app/` matching this existing flat convention; don't
introduce a new `src/pages/<Domain>/` nested structure without discussing it first, since
that would be a structural change affecting every existing page.

## Components — by type, generic only

`src/components/` is already grouped by reusable kind (`AggregateTable`, `StatPanel`,
`Bar/LineChartPanel`, `PeriodSelector`, `JsonViewer`, …) rather than by domain. Follow that:
new reusable components go in `src/components/`, domain-specific composite UI stays
co-located with the page that uses it.

## Add-a-page checklist

1. [ ] Confirm the api endpoint exists.
2. [ ] Add the page under `src/pages/` or `src/pages/app/` (or extend
       `aggregateConfig.js` if it's an aggregate resource).
3. [ ] Fetch data via `services/api.js` (or TanStack Query, once installed).
4. [ ] Forms with VeeValidate + Zod once installed; otherwise match the existing
       validation approach on that page.
5. [ ] Register the route in `src/router/`.
6. [ ] Add a Vitest test (see the existing colocated `*.test.js` pattern — mock
       `services/api`, memory router, `createTestingPinia`, stub `vue-chartjs`).

## Quality gates (must pass before done)

```bash
pnpm test         # Vitest — the only gate script wired up today
```

`pnpm lint` and `pnpm typecheck` aren't set up yet (ESLint is a devDependency but has no
`lint` script; there's no TypeScript toolchain). Don't claim they ran if they don't exist
— flag it instead if a task's scope implies they should be added.
