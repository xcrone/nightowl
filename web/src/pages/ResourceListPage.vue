<script setup>
import { useRoute } from 'vue-router'
import ResourceTable from '../components/ResourceTable.vue'

const route = useRoute()
</script>

<template>
  <ResourceTable :resource="route.params.resource" :key="route.params.resource" />
</template>
