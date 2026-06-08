<template>
  <div class="ad-stat-chip" :class="[`ad-stat-chip--${tone}`, { 'ad-stat-chip--active': active }]">
    <span class="ad-stat-chip__label">{{ label }}</span>
    <span class="ad-stat-chip__value">{{ formatted }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  tone: { type: String, default: 'default' },
  active: { type: Boolean, default: false },
})

const formatted = computed(() => new Intl.NumberFormat().format(Number(props.value) || 0))
</script>
