import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'
import AppShell from '../layouts/AppShell.vue'
import Login from '../pages/Login.vue'
import OrgDashboard from '../pages/OrgDashboard.vue'

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
    { path: '/', name: 'org-dashboard', component: OrgDashboard },
    {
      path: '/dashboard/:appId',
      component: AppShell,
      children: [
        { path: '', name: 'app-dashboard', component: AppDashboard },
        { path: 'issues', name: 'issues', component: IssuesPage },
        { path: 'issues/:id', name: 'issue-detail', component: IssueDetailPage },
        { path: 'requests', name: 'requests', component: RequestsPage },
        { path: 'jobs', name: 'jobs', component: JobsPage },
        { path: 'commands', name: 'commands', component: CommandsPage },
        { path: 'scheduled-tasks', name: 'scheduled-tasks', component: ScheduledTasksPage },
        { path: 'exceptions', name: 'exceptions', component: ExceptionsPage },
        { path: 'exceptions/:key', name: 'exception-detail', component: ExceptionDetailPage },
        { path: 'queries', name: 'queries', component: QueriesPage },
        { path: 'notifications', name: 'notifications', component: NotificationsPage },
        { path: 'mail', name: 'mail', component: MailPage },
        { path: 'cache', name: 'cache', component: CachePage },
        { path: 'outgoing-requests', name: 'outgoing-requests', component: OutgoingRequestsPage },
        { path: 'users', name: 'users', component: UsersPage },
        { path: 'users/:userId', name: 'user-detail', component: UserDetailPage },
        { path: 'logs', name: 'logs', component: LogsPage },
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
        },
      ],
    },
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

  if (to.name === 'login' && auth.user) {
    return '/'
  }
})

export default router
