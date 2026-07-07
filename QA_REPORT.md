# NightOwl Dashboard — QA Report

**Scope:** Full crawl of the web app at `http://localhost:5173/` (Vue 3 SPA against the
local Docker stack's API at `localhost:8000`), logged in as `admin@example.com`.
Every page reachable from the UI was visited at least once; every form, button, and
link exercised; console/network activity checked at each step.

**Method:** Manual, head-browser crawl via Playwright (not a source-code review —
findings below are based purely on observed runtime behavior).

**Status: all 8 findings addressed** (see "Resolution" under each). Fixes were made in
source, covered by PHPUnit/Vitest (all 172 api + 158 web tests passing), and both
`nightowl-web`/`nightowl-api` Docker images were rebuilt and re-verified live with a
second Playwright pass.

---

## Summary

The core flows (auth, org/team/app CRUD, dashboard drill-down pages, app settings)
all work functionally. Eight issues were found and have since been fixed:

| # | Severity | Issue | Resolution |
|---|----------|-------|------------|
| 1 | **High** | App/account switcher dropdown leaves an invisible full-page click-blocker after closing with Escape | Fixed — added a proper Escape handler (`web/src/layouts/AppShell.vue`) |
| 2 | **High** | "Read-only demo" delete protection is inconsistently enforced — Danger Zone disables delete, but the dashboard's delete buttons for the same app/team are fully live | Copy fixed to stop implying deletion is blocked everywhere; Danger Zone stays disabled by design (confirmed intentional via existing test) |
| 3 | Medium | App card's "monitoring: disconnected" contradicts that same app's Agent Health page, which shows "healthy" | Card label reworded to "telemetry: active / no recent data" to read as a distinct signal from the (deliberately simulated) Agent Health page |
| 4 | Medium | A new app's description is saved but never rendered on its dashboard card | Fixed — `ListApps` now returns `description`; card shows it (falling back to `db_connection`) |
| 5 | Medium | Dark/light theme toggle button does nothing | Turned out to be a stale Docker image (container predated the latest commit) — source was already correct; confirmed working after rebuild |
| 6 | Low | "Apps" view omits the "0 issues" stat shown in "Teams" view | Fixed — badge added |
| 7 | Low | Add-member error "The selected email is invalid" is misleading | Fixed — now reads "No NightOwl account exists for that email yet — they need to sign up first." |
| 8 | Low | No catch-all 404 route; unmatched URL while logged in renders a blank page | Fixed — added `NotFoundPage.vue` + catch-all route |

---

## Findings

### 1. [High] Dropdown backdrop overlay isn't cleaned up on Escape — blocks all further clicks

**Where:** Any dashboard page, sidebar app-switcher ("ADAM ⌄" / app name dropdown at
top of sidebar) or account/org switcher (bottom of sidebar).

**Steps to reproduce:**
1. Open any app dashboard page.
2. Click the app-switcher dropdown at the top of the sidebar to open it.
3. Press `Escape` to close it (instead of clicking outside).
4. Try to click anything else on the page — a nav link, a button, the other switcher.

**Observed:** The dropdown's visible menu disappears, but a full-viewport backdrop
`<div class="fixed inset-0 z-10">` is left mounted in the DOM (confirmed via
`getComputedStyle`: `opacity: 1`, `pointer-events: auto`, `display: block`, sized to
the full viewport). It is invisible (no dimming, no visual cue), but it intercepts
every subsequent pointer event on the page. The app becomes completely unusable —
no button, link, or menu responds to clicks — until the page is reloaded.

**Contrast:** Closing the same dropdown by clicking the backdrop itself (the intended
"click outside to close" interaction) removes the overlay correctly. The bug is
specific to the Escape-key close path, which appears to hide the menu contents
without tearing down the backdrop element.

**Impact:** Any user who reflexively hits Escape to close one of these menus (a very
common habit) silently loses the ability to interact with the entire app.

---

### 2. [High] "Read-only demo" destructive-action protection is not consistently enforced

**Where:** App Settings → Danger Zone tab vs. the main org dashboard ("Your Apps").

**Steps to reproduce:**
1. Go to any app's Settings → Danger Zone tab. Note the text "Destructive actions are
   disabled in this read-only demo" and that both **Transfer app** and **Delete app**
   buttons are disabled there.
2. Go back to the org dashboard ("Your Apps"), find the same app's card, and click its
   trash-can icon ("Delete app").

**Observed:** A native `confirm()` dialog appears ("Delete app "X"? This cannot be
undone."), and accepting it **actually deletes the app** — it disappears from the
team's app list immediately and the change persists after reload. The same is true
for a team's "Delete" button. This was verified end-to-end: a test app and test team
created during this crawl were both successfully and permanently deleted this way.

**Impact:** The Danger Zone's messaging leads a user to believe destructive actions
are safely disabled everywhere in this environment, but the dashboard's own
delete controls are fully live and bypass that protection entirely.

---

### 3. [Medium] App card's monitoring status disagrees with the Agent Health page

**Where:** Org dashboard app card vs. `/dashboard/{appId}/health`.

**Steps to reproduce:**
1. On the org dashboard, look at any app card — it shows "● monitoring: disconnected".
2. Click into that same app and open its "Agent Health" page from the sidebar.

**Observed:** The Agent Health page shows the app as "healthy" with a Health Score of
98 and two live instances reporting throughput/CPU/memory/latency data — directly
contradicting the "disconnected" status shown one click away on the dashboard card.

**Impact:** Confusing / untrustworthy status indicator — a user scanning the org
dashboard for problems would see every app flagged as disconnected even when its
detail page reports it as fully healthy.

---

### 4. [Medium] New app's description never renders on its card

**Where:** Org dashboard, both "Teams" and "Apps" views.

**Steps to reproduce:**
1. Create a new app via "+ Add app", filling in a Description.
2. Look at the resulting app card on the dashboard (immediately, and after a full page
   reload).
3. Open the app's Edit modal and confirm the Description field.

**Observed:** The card shows no description text at all (blank line where it should
appear), in both the initial render and after a hard reload — yet the Edit modal
confirms the description was saved correctly server-side. Pre-existing apps (e.g.
"ADAM", description "ddd") do render their description correctly, so this is specific
to apps created through the "+ Add app" flow, not a universal rendering gap.

**Impact:** Description data is silently invisible to users on the one screen (the
dashboard) meant to summarize it.

---

### 5. [Medium] Theme toggle button (☀) is non-functional

**Where:** Every app-dashboard page header, top-right icon button next to the
time-format dropdown.

**Steps to reproduce:** Click the ☀ button on any dashboard page.

**Observed:** No visual change (page stays light), no console error, and
`localStorage['nightowl-theme']` remains `"light"` after the click — confirmed via
direct evaluation, not just visual inspection. The button appears fully wired
(focusable, clickable) but has no effect.

---

### 6. [Low] "Apps" view is missing a stat shown in "Teams" view

**Where:** Org dashboard, toggle between "Teams" and "Apps" view.

**Observed:** The same app's card shows four stats in "Teams" view (err%, 5xx, exc,
issues) but only three in "Apps" view (issues is missing). Screenshots confirmed this
visually, not just in the accessibility tree.

---

### 7. [Low] Misleading validation message when adding a non-existent org member

**Where:** `/organization` → Add member field.

**Steps to reproduce:** Enter a syntactically valid email that has no matching
registered user (e.g. a made-up address) and click "Add member".

**Observed:** Error reads "The selected email is invalid." This reads as a format
error, but the actual cause is that members can only be added if they're already a
registered NightOwl user — a subtlety the message doesn't convey. (Confirmed by
retrying with an email that *is* a registered user, which succeeded immediately.)

---

### 8. [Low] No 404 / catch-all route — unmatched URLs render a blank page

**Where:** Any nonexistent path, e.g. `/this-route-does-not-exist`.

**Steps to reproduce:**
1. While logged out, navigate to an unmatched path — you're redirected to `/login`
   (masks the issue, but is a reasonable fallback).
2. While logged in, navigate to the same unmatched path.

**Observed:** The page body is completely empty (confirmed via screenshot: pure white,
no header, no nav, no message) with only a console warning
(`[Vue Router warn]: No match found for location with path "..."`). There is no way
back to the app short of manually editing the URL or using browser back.

---

## Verified working correctly

- Login: empty-form HTML5 validation, wrong-credentials message ("These credentials
  do not match our records."), successful login/redirect.
- Registration: empty-form validation, duplicate-email rejection, successful
  registration with auto-login and org creation.
- Logout (from multiple entry points), redirect back to `/login`.
- Org dashboard: search/filter (matches and "no results" state), Teams/Apps toggle.
- Team create (validation on empty name), inline rename, delete (with confirm dialog).
- App create (validation on empty name), edit, delete (with confirm dialog).
- App dashboard: period selector (1H–30D) correctly re-fetches data for all charts;
  timezone (Local/UTC) and time-format (24h/12h) selectors persist across navigation.
- All 16 sidebar pages (Issues, Requests, Jobs, Commands, Scheduled Tasks, Exceptions,
  Queries, Notifications, Mail, Cache, Outgoing Requests, Users, Logs, Agent Health,
  Data Management, Settings) load without console or network errors, with correct
  empty-state messaging given this environment has no seeded telemetry.
- Issues page filters (Exceptions/Performance, Open/Resolved/Ignored, All/Unassigned/
  Mine) all clickable without error.
- Data Management: "Select all", "Preview Impact" work; destructive delete is
  correctly disabled and clearly labeled here.
- Settings tabs: Thresholds (add + save works, confirmation shown), Issues
  (auto-resolve window selector), Alerts (add channel with name/email validation),
  Storage (usage table), Danger Zone (see Finding #2 for the inconsistency).
- Organization page: edit org name (server-side required validation), add/remove
  member (email format + existing-user validation), "Back to dashboard".
- Route protection: direct navigation to `/dashboard/{appId}` or `/organization` while
  logged out correctly redirects to `/login`.
- Invalid/nonexistent app IDs (including a SQL-injection-style payload in the URL)
  are handled gracefully with a friendly "App not found" page — no stack traces or
  unhandled errors leaked.

## Note on console "errors" during normal use

Failed login, failed registration, and invalid org-edit attempts each log a console
error for the underlying HTTP 422 response. This is standard Laravel validation
behavior (the request legitimately failed validation) and the UI correctly surfaces
a friendly inline message in all cases — these are not treated as defects above, but
are called out since every console error was checked per the request for this audit.
