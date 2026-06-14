<template>
  <article class="ap-kpi" :class="[`ap-kpi--${variant}`, iconTone ? `ap-kpi--${iconTone}` : '']">
    <div class="ap-kpi__head">
      <div v-if="icon" class="ap-kpi__icon" :class="iconTone ? `ap-kpi__icon--${iconTone}` : ''">
        <iconify-icon :icon="icon" width="18" height="18" />
      </div>
      <span v-if="badge" class="ap-kpi__badge" :class="badgeClass">
        <iconify-icon v-if="badgeIcon" :icon="badgeIcon" width="10" height="10" />
        {{ badge }}
      </span>
    </div>
    <p class="ap-kpi__label">{{ label }}</p>
    <p class="ap-kpi__value">
      <span v-if="prefix">{{ prefix }}</span>{{ displayValue }}<span v-if="suffix">{{ suffix }}</span>
    </p>
    <p v-if="subtitle" class="ap-kpi__sub">{{ subtitle }}</p>
    <div v-if="sparkline?.length" class="ap-kpi__spark">
      <AdSparkline :points="sparkline" :color="sparkColor" :height="24" />
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { useAnimatedCounter } from '@/composables/useAnimatedCounter.js'
import AdSparkline from './AdSparkline.vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: '' },
  variant: { type: String, default: 'default' },
  iconTone: { type: String, default: '' },
  badge: { type: String, default: '' },
  badgeIcon: { type: String, default: '' },
  badgeTrend: { type: String, default: '' },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  format: { type: String, default: 'number' },
  sparkline: { type: Array, default: () => [] },
  sparkColor: { type: String, default: '#7c5cbf' },
})

const animated = useAnimatedCounter(computed(() => Number(props.value) || 0))

const displayValue = computed(() => {
  const n = animated.value
  if (props.format === 'percent') return n.toFixed(1)
  if (props.format === 'money') {
    return new Intl.NumberFormat('en-AE', { maximumFractionDigits: 0 }).format(n)
  }
  return new Intl.NumberFormat().format(n)
})

const badgeClass = computed(() => {
  if (props.badgeTrend === 'up') return 'ap-kpi__badge--up'
  if (props.badgeTrend === 'down') return 'ap-kpi__badge--down'
  return ''
})
</script>