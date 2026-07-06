# web/ — NightOwl dashboard SPA

Vue 3 (`<script setup>`) + Vite + Pinia + vue-router + Tailwind v4. The
dashboard UI for the NightOwl monitoring stack. Talks to `api/` over Sanctum
cookie auth. See [CLAUDE.md](CLAUDE.md) for the styling rules and
[../docs/api-contract.md](../docs/api-contract.md) for the API it consumes.

## Structure

```
src/
  main.js, App.vue          bootstrap (theme applied pre-mount)
  router/index.js           /login, / (OrgDashboard), /dashboard/:appId/* (AppShell)
  layouts/AppShell.vue      per-app shell: sidebar groups, app/org switcher,
                            top bar (period selector, timezone, time format)
  nav.js                    sidebar groups + data-type list + period pills
  store/
    app.js                  current app, apps, period/timezone/timeFormat (drives fetches)
    org.js                  org + teams (org dashboard)
    auth.js, theme.js       session + light/dark
  services/api.js           axios (Sanctum cookie auth); call /api/apps/${appId}/…
  components/
    AppShell bits, StatPanel, PeriodSelector, StatusDot,
    BarChartPanel, LineChartPanel   (chart.js/vue-chartjs, theme-aware)
    AggregateTable.vue      presentational list: 2 stat panels + sortable/
                            searchable table (props columns/rows/…, emits sort/search/row-click)
    JsonViewer.vue
  aggregateConfig.js        per-resource column/panel/scope config for the aggregated lists
  resourceConfig.js         badge helpers (statusColor/methodColor/… + BADGE palette)
  utils/format.js           formatDuration, relativeTime, absoluteTime, formatPercent, …
  pages/
    OrgDashboard.vue        "Your Apps" (teams → app cards)
    app/                    the per-app pages (dashboard, the 11 aggregate lists,
                            logs, issues + detail, user detail, health,
                            data-management, settings)
```

Pages are period-reactive (they watch `app.period`) and theme-aware (paired
`dark:` classes). Aggregated list pages are all thin wrappers around
`AggregateListPage.vue` + `aggregateConfig.js`.

## Develop

```bash
pnpm install
cp .env.example .env        # VITE_API_URL=http://localhost:8000
pnpm dev                    # http://localhost:5173
```

Log in with `admin@example.com` / `password` (after the api is seeded — see
`../api/README.md`). Use **localhost** (not 127.0.0.1) so the session cookie
is shared with the api.

## Test / build

```bash
pnpm test        # Vitest (colocated *.test.js; mocks services/api, memory router, createTestingPinia)
pnpm build       # production build (also the CI compile-error check)
```
