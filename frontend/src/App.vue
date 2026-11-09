<template>
  <component :is="layout">
    <router-view />
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()

// 'admin' and 'auth' wrap the page; 'bare' (unsubscribe, 404) renders the
// view alone inside a plain div. Public pages live in the Astro site (web/).
const layout = computed(() => {
  const layouts = { admin: AdminLayout, auth: AuthLayout }
  return layouts[route.meta.layout] || 'div'
})
</script>
