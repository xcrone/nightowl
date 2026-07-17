import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'

import { useRowHighlight } from './useRowHighlight'

// Hosted in a component for the same reason as useLivePoll: it owns a timer
// (the 1500ms auto-clear) and is called from a page's setup().
function mountHighlight(keyField = 'id') {
  let result
  const wrapper = mount({
    template: '<div />',
    setup() {
      result = useRowHighlight(keyField)
      return {}
    },
  })
  return { wrapper, ...result }
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers()
})

afterEach(() => vi.useRealTimers())

describe('useRowHighlight', () => {
  // A cold mount has no "before" to diff against — flashing every row on the
  // first paint would make every page load look like a burst of new traffic.
  it('only records a baseline on the first track and highlights nothing', () => {
    const { highlightKeys, track } = mountHighlight()

    track([{ id: 1, total: 1 }, { id: 2, total: 2 }])

    expect(highlightKeys.value).toEqual([])
  })

  it('highlights a row whose key has not been seen before', () => {
    const { highlightKeys, track } = mountHighlight()

    track([{ id: 1, total: 1 }])
    track([{ id: 2, total: 2 }, { id: 1, total: 1 }])

    expect(highlightKeys.value).toEqual([2])
  })

  it('highlights a row whose content changed since the previous track', () => {
    const { highlightKeys, track } = mountHighlight()

    track([{ id: 1, total: 1 }, { id: 2, total: 2 }])
    track([{ id: 1, total: 9 }, { id: 2, total: 2 }])

    expect(highlightKeys.value).toEqual([1])
  })

  it('does not highlight unchanged rows', () => {
    const { highlightKeys, track } = mountHighlight()

    track([{ id: 1, total: 1 }, { id: 2, total: 2 }])
    track([{ id: 1, total: 1 }, { id: 2, total: 2 }])

    expect(highlightKeys.value).toEqual([])
  })

  it('auto-clears the highlights after 1500ms', async () => {
    const { highlightKeys, track } = mountHighlight()

    track([{ id: 1, total: 1 }])
    track([{ id: 1, total: 9 }])
    expect(highlightKeys.value).toEqual([1])

    await vi.advanceTimersByTimeAsync(1499)
    expect(highlightKeys.value).toEqual([1])

    await vi.advanceTimersByTimeAsync(1)
    expect(highlightKeys.value).toEqual([])
  })
})
