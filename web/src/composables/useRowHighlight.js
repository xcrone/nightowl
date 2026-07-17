import { ref, onUnmounted } from 'vue'

const HIGHLIGHT_MS = 1500

// Flashes rows that arrived or changed on a live tick (see useLivePoll), so a
// count ticking up or a new occurrence landing is noticeable without the
// reader diffing the table by eye.
//
// `keyField` is the row's identity (an aggregate's group-by key, a raw row's
// id). Rows are compared by serialized content, which covers both cases the
// pages care about: a brand-new key, and an existing key whose counts moved.
//
// Pass `{ highlight: false }` for a load the user asked for (a period or scope
// change re-aggregates everything, and flashing all 34 rows would read as a
// burst of traffic rather than the filter they just changed). The baseline
// still moves, so the next live tick diffs against what's actually on screen.
export function useRowHighlight(keyField) {
  const highlightKeys = ref([])

  // `null` until the first track() — a cold mount has no "before" to diff
  // against, and flashing every row on first paint would make an ordinary page
  // load look like a burst of live traffic.
  let previous = null
  let timer = null

  function track(rows, { highlight = true } = {}) {
    const next = new Map(rows.map((row) => [row[keyField], JSON.stringify(row)]))

    if (previous === null || !highlight) {
      previous = next
      // A user-driven reload replaces the rows wholesale; anything still
      // flashing from a prior tick is pointing at data that's now gone.
      clearTimeout(timer)
      highlightKeys.value = []
      return
    }

    const changed = []
    for (const [key, signature] of next) {
      if (previous.get(key) !== signature) changed.push(key)
    }
    previous = next

    clearTimeout(timer)
    highlightKeys.value = changed
    if (!changed.length) return

    timer = setTimeout(() => {
      highlightKeys.value = []
    }, HIGHLIGHT_MS)
  }

  onUnmounted(() => clearTimeout(timer))

  return { highlightKeys, track }
}
