# web/ — NightOwl dashboard SPA

Vue3 + Vite + Pinia. See root CLAUDE.md for how this fits into the monorepo.

## Styling

Always use Tailwind for styling. Do not write scoped/plain CSS (`<style>`
blocks, separate `.css` files, inline `style="..."` attributes) except for
the global reset/base layer in `src/style.css`. If Tailwind's utilities
can't express something, prefer Tailwind's `@apply` or arbitrary value
syntax over hand-written CSS. Support light **and** dark (class-based `.dark`)
— write paired `dark:` classes.

## Structure

- Routing is per-app: `/dashboard/:appId/*` under `layouts/AppShell.vue`
  (sidebar/nav from `src/nav.js`). `store/app.js` holds the current app +
  `period`/`timezone`/`timeFormat`; pages watch `app.period` and re-fetch.
- All API calls go through `services/api.js` (default `api`) to
  `/api/apps/${appId}/…` (Sanctum cookie auth — see `../docs/api-contract.md`).
- The aggregated list pages (requests/jobs/queries/…) are thin wrappers around
  `pages/app/AggregateListPage.vue` + `src/aggregateConfig.js` (columns/panels/
  scope per resource). Add a resource there rather than hand-writing a page.
- Reuse the shared components (`AggregateTable`, `StatPanel`, `Bar/LineChartPanel`,
  `PeriodSelector`, `JsonViewer`) and badge helpers/`BADGE` from `resourceConfig.js`
  + formatters from `utils/format.js`. Tests are colocated `*.test.js` (Vitest;
  mock `services/api`, memory router, `createTestingPinia`, stub `vue-chartjs`).
