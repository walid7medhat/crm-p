<template>
  <span class="mk-avatar" :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.38)}px` }" :title="user?.name">
    <img v-if="user?.avatar_url && !failed" :src="user.avatar_url" :alt="user.name" @error="failed = true" />
    <span v-else>{{ initials(user?.name) }}</span>
    <span v-if="badge" class="mk-avatar__badge" :style="{ background: badgeColor }">
      <iconify-icon :icon="badge" />
    </span>
  </span>
</template>

<script setup>
import { ref, watch } from 'vue'
import { initials } from '@/config/marketingConfig.js'

const props = defineProps({
  user: { type: Object, default: null },
  size: { type: Number, default: 28 },
  badge: { type: String, default: '' },
  badgeColor: { type: String, default: 'var(--color-primary)' },
})

const failed = ref(false)
watch(() => props.user?.avatar_url, () => { failed.value = false })
</script>

<style scoped>
.mk-avatar {
  position: relative;
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 50%;
  font-weight: 700 !important;
  color: #fff;
  background: var(--gradient-primary);
  box-shadow: 0 0 0 2px var(--white);
}
.mk-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.mk-avatar__badge {
  position: absolute;
  right: -4px;
  bottom: -4px;
  width: 15px;
  height: 15px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 9px !important;
  color: #fff;
  box-shadow: 0 0 0 2px var(--white);
}
</style>
