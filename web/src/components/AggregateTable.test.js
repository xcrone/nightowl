import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'
import AggregateTable from './AggregateTable.vue'
import { statusCodeColor } from '../resourceConfig'
import { formatDuration } from '../utils/format'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: { template: '<div />' } }],
  })
}

function mountTable(props = {}) {
  const router = makeRouter()
  return mount(AggregateTable, {
    props: {
      resource: 'requests',
      columns: [
        { key: 'route_path', label: 'Route' },
        { key: 'total', label: 'Total', align: 'right' },
        { key: 'status', label: 'Status', badge: statusCodeColor },
        { key: 'avg', label: 'Avg', format: formatDuration },
      ],
      rows: [
        { id: 1, route_path: '/orders', total: 42, status: 200, avg: 14_310 },
        { id: 2, route_path: '/checkout', total: 7, status: 500, avg: 1_570_000 },
      ],
      ...props,
    },
    global: { plugins: [router, createTestingPinia({ createSpy: vi.fn })] },
  })
}

beforeEach(() => vi.clearAllMocks())

describe('AggregateTable', () => {
  it('renders the provided rows with formatted + badged cells', () => {
    const wrapper = mountTable()
    expect(wrapper.text()).toContain('/orders')
    expect(wrapper.text()).toContain('/checkout')
    // format function applied
    expect(wrapper.text()).toContain('14.31ms')
    expect(wrapper.text()).toContain('1.57s')
  })

  it('shows an empty state when there are no rows', () => {
    const wrapper = mountTable({ rows: [], emptyText: 'Nothing here' })
    expect(wrapper.text()).toContain('Nothing here')
  })

  it('shows a loading state', () => {
    const wrapper = mountTable({ loading: true })
    expect(wrapper.text()).toContain('Loading…')
  })

  it('emits sort descending on first header click, then ascending', async () => {
    const wrapper = mountTable()
    const totalHeader = wrapper.findAll('th').find((th) => th.text().includes('Total'))

    await totalHeader.trigger('click')
    expect(wrapper.emitted('sort').at(-1)).toEqual(['-total'])

    // parent applies the sort; reflect it back so the toggle flips to asc
    await wrapper.setProps({ sort: '-total' })
    await totalHeader.trigger('click')
    expect(wrapper.emitted('sort').at(-1)).toEqual(['total'])
  })

  it('debounces the search input before emitting search', async () => {
    vi.useFakeTimers()
    const wrapper = mountTable()

    await wrapper.find('input[type="text"]').setValue('orders')
    expect(wrapper.emitted('search')).toBeFalsy()

    await vi.advanceTimersByTimeAsync(300)
    expect(wrapper.emitted('search').at(-1)).toEqual(['orders'])

    vi.useRealTimers()
  })

  it('emits row-click with the clicked row', async () => {
    const wrapper = mountTable()
    await wrapper.findAll('tbody tr')[0].trigger('click')
    expect(wrapper.emitted('row-click').at(-1)[0]).toMatchObject({ route_path: '/orders' })
  })
})
