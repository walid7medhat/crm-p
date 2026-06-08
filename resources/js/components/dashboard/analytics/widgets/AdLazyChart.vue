<template>
  <apexchart
    v-if="ready"
    :type="type"
    :height="height"
    :width="width"
    :options="mergedOptions"
    :series="series"
  />
  <AdChartSkeleton v-else />
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import AdChartSkeleton from './AdChartSkeleton.vue'

const props = defineProps({
  type: { type: String, default: 'area' },
  height: { type: [Number, String], default: 220 },
  width: { type: [Number, String], default: '100%' },
  series: { type: Array, default: () => [] },
  options: { type: Object, default: () => ({}) },
  dark: { type: Boolean, default: false },
})

const ready = ref(false)

onMounted(() => {
  requestAnimationFrame(() => {
    ready.value = true
  })
})

const mergedOptions = computed(() => ({
  chart: {
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'Inter, system-ui, sans-serif',
    animations: { enabled: true, speed: 500 },
    ...(props.options.chart || {}),
  },
  theme: { mode: props.dark ? 'dark' : 'light' },
  grid: {
    borderColor: props.dark ? 'rgba(255,255,255,0.06)' : 'rgba(42,21,72,0.06)',
    strokeDashArray: 4,
    ...(props.options.grid || {}),
  },
  stroke: { curve: 'smooth', width: 2, ...(props.options.stroke || {}) },
  dataLabels: { enabled: false, ...(props.options.dataLabels || {}) },
  ...props.options,
}))
</script>
