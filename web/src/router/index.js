import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'
import AppShell from '../layouts/AppShell.vue'
import Login from '../pages/Login.vue'
import Register from '../pages/Register.vue'
import OrgDashboard from '../pages/OrgDashboard.vue'
import OrganizationPage from '../pages/OrganizationPage.vue'
import NotFoundPage from '../pages/NotFoundPage.vue'

import AppDashboard from '../pages/app/AppDashboard.vue'
import IssuesPage from '../pages/app/IssuesPage.vue'
import IssueDetailPage from '../pages/app/IssueDetailPage.vue'
import RequestsPage from '../pages/app/RequestsPage.vue'
import JobsPage from '../pages/app/JobsPage.vue'
import CommandsPage from '../pages/app/CommandsPage.vue'
import ScheduledTasksPage from '../pages/app/ScheduledTasksPage.vue'
import ExceptionsPage from '../pages/app/ExceptionsPage.vue'
import ExceptionDetailPage from '../pages/app/ExceptionDetailPage.vue'
import AggregateDetailPage from '../pages/app/AggregateDetailPage.vue'
import ResourceDetailPage from '../pages/app/ResourceDetailPage.vue'
import QueriesPage from '../pages/app/QueriesPage.vue'
import NotificationsPage from '../pages/app/NotificationsPage.vue'
import MailPage from '../pages/app/MailPage.vue'
import CachePage from '../pages/app/CachePage.vue'
import OutgoingRequestsPage from '../pages/app/OutgoingRequestsPage.vue'
import UsersPage from '../pages/app/UsersPage.vue'
import UserDetailPage from '../pages/app/UserDetailPage.vue'
import LogsPage from '../pages/app/LogsPage.vue'
import HealthPage from '../pages/app/HealthPage.vue'
import DataManagementPage from '../pages/app/DataManagementPage.vue'
import SettingsPage from '../pages/app/SettingsPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: Login, meta: { public: true } },
    { path: '/register', name: 'register', component: Register, meta: { public: true } },
    { path: '/', name: 'org-dashboard', component: OrgDashboard },
    { path: '/organization', name: 'organization', component: OrganizationPage },
    {
      path: '/dashboard/:appId',
      component: AppShell,
      children: [
        { path: '', name: 'app-dashboard', component: AppDashboard },
        { path: 'issues', name: 'issues', component: IssuesPage },
        { path: 'issues/:id', name: 'issue-detail', component: IssueDetailPage },
        // `meta.live` surfaces the top bar's LiveToggle (AppShell) — set on the
        // pages that actually wire useLivePoll: the AggregateListPage wrappers,
        // Logs, and the aggregate drill-down. Telemetry arrives on these; the
        // rest (settings, health, single-record detail) have nothing to stream.
        { path: 'requests', name: 'requests', component: RequestsPage, meta: { live: true } },
        { path: 'jobs', name: 'jobs', component: JobsPage, meta: { live: true } },
        { path: 'commands', name: 'commands', component: CommandsPage, meta: { live: true } },
        { path: 'scheduled-tasks', name: 'scheduled-tasks', component: ScheduledTasksPage, meta: { live: true } },
        { path: 'exceptions', name: 'exceptions', component: ExceptionsPage, meta: { live: true } },
        { path: 'exceptions/:key', name: 'exception-detail', component: ExceptionDetailPage },
        { path: 'queries', name: 'queries', component: QueriesPage, meta: { live: true } },
        { path: 'notifications', name: 'notifications', component: NotificationsPage, meta: { live: true } },
        { path: 'mail', name: 'mail', component: MailPage, meta: { live: true } },
        { path: 'cache', name: 'cache', component: CachePage, meta: { live: true } },
        { path: 'outgoing-requests', name: 'outgoing-requests', component: OutgoingRequestsPage, meta: { live: true } },
        { path: 'users', name: 'users', component: UsersPage, meta: { live: true } },
        { path: 'users/:userId', name: 'user-detail', component: UserDetailPage },
        { path: 'logs', name: 'logs', component: LogsPage, meta: { live: true } },
        { path: 'health', name: 'health', component: HealthPage },
        { path: 'data-management', name: 'data-management', component: DataManagementPage },
        { path: 'settings', name: 'settings', component: SettingsPage },
        // Per-item drill-down for the 8 clickable Activity aggregates. The
        // `:resource` param is constrained to those 8 so it never shadows a
        // static list/detail route (users/:userId, issues/:id, exceptions/:key).
        {
          path: ':resource(requests|outgoing-requests|jobs|commands|scheduled-tasks|queries|notifications|mail)/:key',
          name: 'aggregate-detail',
          component: AggregateDetailPage,
          meta: { live: true },
        },
        // Single-record detail (Telescope-style request-detail page) behind an
        // aggregate's Occurrences table / a Related tab row. A 3-segment path
        // so it can never shadow the 2-segment aggregate-detail route above.
        {
          path: ':resource(requests|outgoing-requests|jobs|commands|scheduled-tasks|queries|notifications|mail|logs|cache-events|exceptions)/record/:id',
          name: 'resource-detail',
          component: ResourceDetailPage,
        },
      ],
    },
    // Catch-all: without this, an unmatched URL renders nothing at all (see
    // NotFoundPage.vue's docblock). Public so it shows regardless of auth
    // state instead of the login guard redirecting a bad link to /login.
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundPage, meta: { public: true } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.checked) {
    await auth.fetchUser()
  }

  if (!to.meta.public && !auth.user) {
    return { name: 'login' }
  }

  if ((to.name === 'login' || to.name === 'register') && auth.user) {
    return '/'
  }
})

export default router
