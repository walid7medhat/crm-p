<template>
  <div class="ali-state" :class="`ali-state--${variant}`" role="status">
    <div class="ali-state__icon">
      <Icon :icon="iconName" />
    </div>
    <div class="ali-state__body">
      <strong class="ali-state__title">{{ title }}</strong>
      <p v-if="message" class="ali-state__msg">{{ message }}</p>
      <button
        v-if="showRetry"
        type="button"
        class="ali-btn ali-btn--ghost ali-state__retry"
        @click="$emit('retry')"
      >
        Try again
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'empty',
    validator: (v) =>
      ['loading', 'empty', 'error', 'forbidden', 'unavailable', 'refreshing'].includes(v),
  },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  showRetry: { type: Boolean, default: false },
})

defineEmits(['retry'])

const iconName = computed(() => {
  const map = {
    loading: 'lucide:loader-2',
    refreshing: 'lucide:refresh-cw',
    empty: 'lucide:inbox',
    error: 'lucide:alert-circle',
    forbidden: 'lucide:lock',
    unavailable: 'lucide:sparkles',
  }
  return map[props.variant] || 'lucide:info'
})
</script>
