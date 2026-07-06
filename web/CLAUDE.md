# web/ — NightOwl dashboard SPA

Vue3 + Vite + Pinia. See root CLAUDE.md for how this fits into the monorepo.

## Styling

Always use Tailwind for styling. Do not write scoped/plain CSS (`<style>`
blocks, separate `.css` files, inline `style="..."` attributes) except for
the global reset/base layer in `src/style.css`. If Tailwind's utilities
can't express something, prefer Tailwind's `@apply` or arbitrary value
syntax over hand-written CSS.
