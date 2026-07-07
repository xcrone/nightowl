import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import NotFoundPage from './NotFoundPage.vue'
import router from '../router'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: NotFoundPage },
    ],
  })
}

describe('NotFoundPage', () => {
  it('shows a clear message and a way back to the app', async () => {
    const testRouter = makeRouter()
    await testRouter.push('/this-does-not-exist')
    await testRouter.isReady()
    const wrapper = mount(NotFoundPage, { global: { plugins: [testRouter] } })

    expect(wrapper.text()).toContain('Page not found')
    const link = wrapper.find('a')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/')
  })
})

describe('router catch-all', () => {
  it('resolves any unmatched URL to the public not-found route', () => {
    const resolved = router.resolve('/this-route-does-not-exist')
    expect(resolved.name).toBe('not-found')
    expect(resolved.meta.public).toBe(true)
  })
})
