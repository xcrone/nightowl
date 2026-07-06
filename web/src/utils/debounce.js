// Simple trailing-edge debounce — no existing debounce utility or
// lodash/vueuse dependency in this project, so a small local one avoids
// adding a dependency for a single function.
export function debounce(fn, delayMs = 300) {
  let timer = null
  return (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delayMs)
  }
}
