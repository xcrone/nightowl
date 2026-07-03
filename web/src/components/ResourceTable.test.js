import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ResourceTable from './ResourceTable.vue'
import api from '../services/api'

vi.mock('../services/api', () => ({
  default: { get: vi.fn() },
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:resource', component: { template: '<div />' } }, { path: '/:resource/:id', component: { template: '<div />' } }],
  })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ResourceTable', () => {
  it('fetches and renders rows for the given resource', async () => {
    api.get.mockResolvedValue({
      data: { data: [{ id: 1, method: 'GET', url: '/foo', status_code: 200, duration: 1000, exceptions: 0 }], last_page: 1 },
    })

    const router = makeRouter()
    router.push('/requests')
    await router.isReady()

    const wrapper = mount(ResourceTable, {
      props: { resource: 'requests' },
      global: { plugins: [router] },
    })

    await vi.waitUntil(() => wrapper.text().includes('/foo'))

    expect(api.get).toHaveBeenCalledWith('/api/requests', expect.objectContaining({ params: expect.objectContaining({ page: 1 }) }))
    expect(wrapper.text()).toContain('/foo')
  })

  it('toggling a flag filter re-fetches with that filter applied', async () => {
    api.get.mockResolvedValue({ data: { data: [], last_page: 1 } })

    const router = makeRouter()
    router.push('/requests')
    await router.isReady()

    const wrapper = mount(ResourceTable, {
      props: { resource: 'requests' },
      global: { plugins: [router] },
    })

    await vi.waitUntil(() => api.get.mock.calls.length >= 1)

    const failedButton = wrapper.findAll('button').find((b) => b.text() === 'Failed (5xx)')
    await failedButton.trigger('click')

    await vi.waitUntil(() => api.get.mock.calls.length >= 2)

    const lastCall = api.get.mock.calls.at(-1)
    expect(lastCall[1].params.failed).toBe(1)
  })
})
