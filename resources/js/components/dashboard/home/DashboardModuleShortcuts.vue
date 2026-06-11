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
      custom
      v-slot="{ navigate, href }"
    >
      <a
        :href="href"
        class="top-module-btn"
        :class="{ active: isTopModuleItemActive(item) }"
        @click="navigate"
      >
        {{ item.label }}
      </a>
    </router-link>
  </nav>
</template>

<script setup>
import { computed, getCurrentInstance } from 'vue'
import { useRoute } from 'vue-router'
import { buildTopModuleNav } from '@/composables/useLayoutNavigation.js'
import { useLayoutActiveState } from '@/composables/useLayoutActiveState.js'

const route = useRoute()
const { proxy } = getCurrentInstance() || {}
const { isTopModuleItemActive } = useLayoutActiveState()

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

const items = computed(() => {
  void route.path
  return buildTopModuleNav({
    isAdmin: isAdmin.value,
    isSuperAdmin: isSuperAdmin.value,
    isShowOnlyListing: isShowOnlyListing.value,
    userId: Number(user.value?.id) || 0,
    canAccessListings: isAdmin.value || isShowOnlyListing.value,
    hasPermission: (p) => proxy?.$hasPermission?.(p) ?? true,
  })
})
</script>

<style scoped>
.dh-module-shortcuts .top-module-btn.active {
  background: transparent;
  color: rgba(255, 255, 255, 0.88);
  border-color: transparent;
  box-shadow: none;
}

.dh-module-shortcuts .top-module-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}
</style>
