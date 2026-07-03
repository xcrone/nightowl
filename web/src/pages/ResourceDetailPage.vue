<script setup>
import { useRoute } from 'vue-router'
import ResourceDetail from '../components/ResourceDetail.vue'

const route = useRoute()
</script>

<template>
  <ResourceDetail :resource="route.params.resource" :id="route.params.id" :key="`${route.params.resource}-${route.params.id}`" />
</template>
