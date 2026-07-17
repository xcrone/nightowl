import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
import LiveToggle from './LiveToggle.vue'
import { useAppStore } from '../store/app'

function mountToggle(appState = {}) {
  const pinia = createTestingPinia({
    createSpy: vi.fn,
    initialState: { app: { live: false, liveInterval: 3000, ...appState } },
  })
  const wrapper = mount(LiveToggle, { global: { plugins: [pinia] } })
  return { wrapper, app: useAppStore(pinia) }
}

beforeEach(() => vi.clearAllMocks())

describe('LiveToggle', () => {
  it('renders paused by default', () => {
    const { wrapper } = mountToggle()

    const button = wrapper.get('button')
    expect(button.text()).toContain('Live')
    expect(button.attributes('aria-pressed')).toBe('false')
  })

  it('toggles the store live flag when clicked', async () => {
    const { wrapper, app } = mountToggle({ live: false })

    await wrapper.get('button').trigger('click')

    expect(app.setLive).toHaveBeenCalledWith(true)
  })

  it('reflects live with aria-pressed and an active indicator', () => {
    const paused = mountToggle({ live: false }).wrapper.get('button')
    const live = mountToggle({ live: true }).wrapper.get('button')

    expect(paused.attributes('aria-pressed')).toBe('false')
    expect(live.attributes('aria-pressed')).toBe('true')
    // Live is visually distinct, not only semantically — the button carries an
    // active-state class of its own when polling.
    expect(live.classes()).not.toEqual(paused.classes())
  })

  it('calls setLiveInterval with the numeric value when the interval select changes', async () => {
    const { wrapper, app } = mountToggle({ liveInterval: 3000 })

    const select = wrapper.get('select')
    expect(select.element.value).toBe('3000')
    expect(select.findAll('option').map((o) => [o.element.value, o.text()])).toEqual([
      ['3000', '3s'],
      ['10000', '10s'],
      ['30000', '30s'],
    ])

    await select.setValue('10000')

    expect(app.setLiveInterval).toHaveBeenCalledWith(10_000)
  })
})
