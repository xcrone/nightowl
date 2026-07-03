<script setup>
import { onMounted } from 'vue'
import { useThemeStore } from './store/theme'

onMounted(() => useThemeStore().init())
</script>

<template>
  <RouterView />
</template>
