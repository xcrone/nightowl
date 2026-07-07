<script setup>
// Catch-all for any URL that doesn't match a route — without this, Vue
// Router silently renders nothing (see router/index.js's catch-all route),
// leaving a blank page with no way back for a stale or mistyped link.
</script>

<template>
  <div
    class="flex min-h-screen flex-col items-center justify-center gap-3 bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
  >
    <p class="text-lg font-semibold">Page not found</p>
    <p class="text-sm text-gray-500 dark:text-gray-400">The page you're looking for doesn't exist.</p>
    <RouterLink to="/" class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700">
      Back to your apps
    </RouterLink>
  </div>
</template>
