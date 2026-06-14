<template>
  <article class="adx-stat" :class="[`adx-stat--${tone}`, { 'adx-stat--primary': primary }]">
    <div class="adx-stat__top">
      <span v-if="icon" class="adx-stat__icon">
        <iconify-icon :icon="icon" width="16" height="16" />
      </span>
      <span v-if="badge" class="adx-stat__badge">{{ badge }}</span>
    </div>
    <p class="adx-stat__label">{{ label }}</p>
    <p class="adx-stat__value">
      <span v-if="prefix">{{ prefix }}</span>{{ displayValue }}<span v-if="suffix">{{ suffix }}</span>
    </p>
    <p v-if="sub" class="adx-stat__sub">{{ sub }}</p>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { useAnimatedCounter } from '@/composables/useAnimatedCounter.js'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'default' },
  primary: { type: Boolean, default: false },
  badge: { type: String, default: '' },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  sub: { type: String, default: '' },
  format: { type: String, default: 'number' },
})

const animated = useAnimatedCounter(computed(() => Number(props.value) || 0))

const displayValue = computed(() => {
  const n = animated.value
  if (props.format === 'percent') return n.toFixed(1)
  if (props.format === 'raw') return String(props.value ?? '—')
  return new Intl.NumberFormat().format(n)
})
</script>
