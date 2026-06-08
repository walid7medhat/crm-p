<template>
  <nav
    v-if="items.length"
    class="top-module-nav dh-module-shortcuts"
    aria-label="Main modules"
  >
    <router-link
      v-for="item in items"
      :key="item.id"
      :to="item.path"
      class="top-module-btn"
      :class="{ active: isActive(item) }"
    >
      {{ item.label }}
    </router-link>
  </nav>
</template>

<script setup>
import { computed, getCurrentInstance } from 'vue'
import { useRoute } from 'vue-router'
import {
  buildTopModuleNav,
  isTopModuleNavActive,
  resolveActiveModule,
} from '@/composables/useLayoutNavigation.js'

const route = useRoute()
const { proxy } = getCurrentInstance() || {}

const user = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')
  } catch {
    return null
  }
})

const isAdmin = computed(() => {
  const u = user.value
  if (!u) return false
  return u.roles?.includes('super_admin') || u.roles?.includes('admin') || proxy?.$hasPermission?.('admin')
})

const isSuperAdmin = computed(() => user.value?.roles?.includes('super_admin') ?? false)
const isShowOnlyListing = computed(() => user.value?.roles?.includes('only show listings') ?? false)

const items = computed(() =>
  buildTopModuleNav({
    isAdmin: isAdmin.value,
    isSuperAdmin: isSuperAdmin.value,
    isShowOnlyListing: isShowOnlyListing.value,
    userId: Number(user.value?.id) || 0,
    canAccessListings: isAdmin.value || isShowOnlyListing.value,
    hasPermission: (p) => proxy?.$hasPermission?.(p) ?? true,
  }),
)

const activeModule = computed(() => resolveActiveModule(route.path))

function isActive(item) {
  return isTopModuleNavActive(route.path, activeModule.value, item)
}
</script>
