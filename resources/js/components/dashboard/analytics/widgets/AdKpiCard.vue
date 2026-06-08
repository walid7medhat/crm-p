<template>
  <article class="ex-kpi" :class="[`ex-kpi--${tone}`, { 'ex-kpi--inline': inline }]">
    <template v-if="inline">
      <span class="ex-kpi__label">{{ label }}</span>
      <span class="ex-kpi__value">
        <span v-if="prefix">{{ prefix }}</span>{{ formattedValue }}<span v-if="suffix">{{ suffix }}</span>
      </span>
    </template>
    <template v-else>
      <div class="ex-kpi__row">
        <div v-if="icon" class="ex-kpi__icon">
          <iconify-icon :icon="icon" width="13" height="13" />
        </div>
        <span v-if="trend != null" class="ex-kpi__trend" :class="trend >= 0 ? 'is-up' : 'is-down'">
          <iconify-icon :icon="trend >= 0 ? 'lucide:trending-up' : 'lucide:trending-down'" width="10" height="10" />
          {{ Math.abs(trend) }}%
        </span>
      </div>
      <p class="ex-kpi__label">{{ label }}</p>
      <p class="ex-kpi__value">
        <span v-if="prefix">{{ prefix }}</span>{{ formattedValue }}<span v-if="suffix">{{ suffix }}</span>
      </p>
      <div v-if="sparkline?.length" class="ex-kpi__spark">
        <AdSparkline :points="sparkline" :color="sparkColor" :height="22" />
      </div>
    </template>
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
  tone: { type: String, default: 'default' },
  trend: { type: Number, default: null },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  format: { type: String, default: 'number' },
  sparkline: { type: Array, default: () => [] },
  sparkColor: { type: String, default: '#7c5cbf' },
  inline: { type: Boolean, default: false },
})

const animated = useAnimatedCounter(computed(() => Number(props.value) || 0))

const formattedValue = computed(() => {
  const n = animated.value
  if (props.format === 'currency') {
    return new Intl.NumberFormat('en-AE', { maximumFractionDigits: 0 }).format(n)
  }
  if (props.format === 'percent') return n.toFixed(1)
  return new Intl.NumberFormat().format(n)
})
</script>
