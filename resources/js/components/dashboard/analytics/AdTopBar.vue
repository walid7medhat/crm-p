<template>
  <header class="ad-topbar">
    <div class="ad-topbar__brand">
      <span class="ad-topbar__title">Analytics</span>
      <span class="ad-topbar__scope">{{ scopeLabel }}</span>
      <span class="ad-topbar__user">{{ greetingName }}</span>
    </div>

    <div class="ad-topbar__tools">
      <router-link to="/" class="ad-topbar__btn ad-topbar__btn--text" title="Classic dashboard">
        <iconify-icon icon="lucide:layout-dashboard" width="14" height="14" />
        <span>Classic</span>
      </router-link>
      <button type="button" class="ad-topbar__btn" :title="theme === 'dark' ? 'Light mode' : 'Dark mode'" @click="toggleTheme">
        <iconify-icon :icon="theme === 'dark' ? 'lucide:sun' : 'lucide:moon'" width="14" height="14" />
      </button>
      <div class="ad-notif-wrap">
        <button type="button" class="ad-topbar__btn" aria-label="Notifications" @click="notifOpen = !notifOpen">
          <iconify-icon icon="lucide:bell" width="14" height="14" />
          <span v-if="notifications.length" class="ad-topbar__badge">{{ notifications.length }}</span>
        </button>
        <div v-if="notifOpen" class="ad-notif-popover">
          <p class="ad-notif-popover__title">Alerts</p>
          <ul class="ad-notif-list">
            <li v-for="n in notifications" :key="n.id" class="ad-notif-item" :class="`ad-notif-item--${n.type}`">
              <p class="ad-notif-item__title">{{ n.title }}</p>
              <p class="ad-notif-item__msg">{{ n.message }}</p>
              <span class="ad-notif-item__time">{{ n.time }}</span>
            </li>
            <li v-if="!notifications.length" class="ad-notif-item ad-notif-item--empty">All clear</li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTheme } from '@/composables/useTheme.js'

defineProps({
  scopeLabel: { type: String, default: 'Analytics' },
  notifications: { type: Array, default: () => [] },
})

const { theme, toggleTheme } = useTheme()
const notifOpen = ref(false)

const greetingName = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    return (user?.name || 'User').split(' ')[0]
  } catch {
    return 'User'
  }
})
</script>
