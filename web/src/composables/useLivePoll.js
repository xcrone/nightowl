import { watch, onUnmounted } from 'vue'
import { useAppStore } from '../store/app'

// Drives the top bar's Live toggle (components/LiveToggle.vue): re-invokes
// `fn` every `app.liveInterval` ms while `app.live` is on. Pages pass a silent
// reload, so a tick refreshes data without flashing the loading skeleton.
//
// Deliberately never invokes `fn` on mount — every page already owns an
// initial load via its own period watcher, and firing here would double-fetch
// on each page load.
//
// Polling only, no push: nightowl_* rows land ~100ms after a request finishes
// (the agent's drain_interval_ms), so an interval this short is effectively
// real time without holding a php-fpm worker open per dashboard tab.
export function useLivePoll(fn) {
  const app = useAppStore()

  let timer = null
  // Guards against pile-up: a 30D aggregate can outlive a 3s interval, and
  // stacking those requests would multiply the load exactly when it's already
  // slow. A tick that lands mid-flight is dropped, not queued.
  let inFlight = false

  function stop() {
    if (timer === null) return
    clearInterval(timer)
    timer = null
  }

  function start() {
    stop()
    if (!app.live || document.hidden) return
    timer = setInterval(async () => {
      if (inFlight) return
      inFlight = true
      try {
        await fn()
      } finally {
        inFlight = false
      }
    }, app.liveInterval)
  }

  // A background tab has no reader to serve — keep it from polling until it
  // comes back to the foreground.
  function onVisibilityChange() {
    if (document.hidden) stop()
    else start()
  }

  document.addEventListener('visibilitychange', onVisibilityChange)

  // Restarts (rather than merely stops/starts) so an interval change takes
  // effect at the new cadence immediately.
  watch([() => app.live, () => app.liveInterval], start, { immediate: true })

  onUnmounted(() => {
    stop()
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })
}
