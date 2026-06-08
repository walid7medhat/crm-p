<template>
  <article class="dh-panel dh-panel--chart dh-panel--chart-sm">
    <div class="dh-panel-head">
      <p class="dh-panel-title">{{ title }}</p>
      <span v-if="subtitle" class="dh-chart-period dh-chart-period--static">{{ subtitle }}</span>
    </div>
    <div v-if="loading" class="dh-chart-wrap dh-skeleton" />
    <div v-else ref="chartRef" class="dh-chart-wrap dh-chart-wrap--sm" />
  </article>
</template>

<script setup>
import { ref, watch, onBeforeUnmount, onMounted, nextTick } from 'vue'
import ApexCharts from 'apexcharts'

const props = defineProps({
  title: { type: String, default: 'Chart' },
  subtitle: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  values: { type: Array, default: () => [] },
  color: { type: String, default: '#7c5cbf' },
  horizontal: { type: Boolean, default: false },
})

const chartRef = ref(null)
let chart = null

function render() {
  if (!chartRef.value || !props.values?.length) return
  if (chart) chart.destroy()
  chart = new ApexCharts(chartRef.value, {
    series: [{ name: 'Count', data: props.values.map((v) => Number(v) || 0) }],
    chart: {
      type: 'bar',
      height: props.horizontal ? Math.max(200, props.categories.length * 28) : 200,
      toolbar: { show: false },
      fontFamily: 'Inter, system-ui, sans-serif',
    },
    colors: [props.color],
    plotOptions: {
      bar: {
        borderRadius: 6,
        horizontal: props.horizontal,
        columnWidth: props.horizontal ? undefined : '48%',
        barHeight: props.horizontal ? '65%' : undefined,
      },
    },
    dataLabels: { enabled: false },
    xaxis: props.horizontal
      ? { labels: { style: { fontSize: '10px', colors: '#9ca3af' } } }
      : {
          categories: props.categories,
          labels: { style: { fontSize: '10px', colors: '#9ca3af' } },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
    yaxis: props.horizontal
      ? {
          categories: props.categories,
          labels: { style: { fontSize: '10px', colors: '#9ca3af' }, maxWidth: 120 },
        }
      : { labels: { style: { fontSize: '10px', colors: '#9ca3af' } } },
    grid: { borderColor: '#e8eaef', strokeDashArray: 4 },
  })
  chart.render()
}

async function tryRender() {
  if (props.loading) return
  await nextTick()
  requestAnimationFrame(render)
}

watch(() => [props.loading, props.values, props.categories], tryRender, { deep: true })
onMounted(tryRender)
onBeforeUnmount(() => chart?.destroy())
</script>
