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

function mountTable(props = {}, initialState = { app: { timezone: 'UTC', timeFormat: '24h' } }) {
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
    global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState })] },
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

  // resourceConfig declares ~11 `format: 'datetime'` columns that render through
  // this table, so the top-bar timezone selector has to reach the cells.
  it("renders a 'datetime' cell in the timezone/time-format held in the app store", () => {
    const wrapper = mountTable(
      {
        columns: [{ key: 'created_at', label: 'Time', format: 'datetime' }],
        rows: [{ id: 1, created_at: '2026-07-16T15:04:05Z' }],
      },
      { app: { timezone: 'UTC', timeFormat: '24h' } },
    )

    const cell = wrapper.find('tbody td')
    expect(cell.text()).toContain('15:04:05')
    expect(cell.text()).not.toMatch(/\b[AP]M\b/i)
  })

  // Function-format columns (aggregateConfig's jobs "Triggered" derives its
  // instant from the row, so it can't be a plain 'datetime' string format) must
  // reach the same timezone/time-format opts the string formats already get,
  // otherwise their timestamps silently ignore the top-bar selector.
  it('passes the store timezone/time-format opts as the 3rd arg to a function format', () => {
    const format = vi.fn(() => 'formatted')
    mountTable(
      {
        columns: [{ key: 'last_finished', label: 'Finished', format }],
        rows: [{ id: 1, last_finished: '2026-07-16T15:04:05Z' }],
      },
      { app: { timezone: 'UTC', timeFormat: '24h' } },
    )

    expect(format).toHaveBeenCalledWith(
      '2026-07-16T15:04:05Z',
      { id: 1, last_finished: '2026-07-16T15:04:05Z' },
      { timezone: 'UTC', format: '24h' },
    )
  })

  it('passes the current store opts to a function format when the store holds Local/12h', () => {
    const format = vi.fn(() => 'formatted')
    mountTable(
      {
        columns: [{ key: 'last_finished', label: 'Finished', format }],
        rows: [{ id: 1, last_finished: '2026-07-16T15:04:05Z' }],
      },
      { app: { timezone: 'Local', timeFormat: '12h' } },
    )

    expect(format.mock.calls[0][2]).toEqual({ timezone: 'Local', format: '12h' })
  })
})
