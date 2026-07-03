import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'
import AppLayout from '../layouts/AppLayout.vue'
import Login from '../pages/Login.vue'
import ResourceListPage from '../pages/ResourceListPage.vue'
import ResourceDetailPage from '../pages/ResourceDetailPage.vue'
import IssueDetailPage from '../pages/IssueDetailPage.vue'
import NightowlUsersPage from '../pages/NightowlUsersPage.vue'
import AlertChannelsPage from '../pages/AlertChannelsPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'
import RollupsPage from '../pages/RollupsPage.vue'
import { resources } from '../resourceConfig'

const resourceKeys = Object.keys(resources).join('|')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: Login, meta: { public: true } },
    {
      path: '/',
      component: AppLayout,
      children: [
        { path: '', redirect: '/issues' },
        { path: 'nightowl-users', component: NightowlUsersPage },
        { path: 'alert-channels', component: AlertChannelsPage },
        { path: 'settings', component: SettingsPage },
        { path: 'rollups', component: RollupsPage },
        { path: `issues/:id(\\d+)`, component: IssueDetailPage },
        { path: `:resource(${resourceKeys})/:id(\\d+)`, component: ResourceDetailPage },
        { path: `:resource(${resourceKeys})`, component: ResourceListPage },
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
