<template>
  <article class="dh-panel dh-panel--listings dh-panel--donut-compact">
    <div class="dh-panel-head dh-listings-head">
      <p class="dh-panel-title">{{ title }}</p>
    </div>
    <div v-if="loading" class="dh-listings-content dh-skeleton" />
    <div v-else class="dh-listings-content">
      <div class="dh-listings-legend">
        <div v-for="leg in legend" :key="leg.label" class="dh-legend-block">
          <span class="dh-legend-sq" :class="`dh-legend-sq--${leg.color}`" />
          <div class="dh-legend-block-text">
            <strong>{{ leg.value }}</strong>
            <span>{{ leg.label }}</span>
          </div>
        </div>
      </div>
      <div class="dh-donut-chart-wrap">
        <div ref="donutRef" class="dh-donut-chart" />
        <div class="dh-donut-center">
          <p class="dh-donut-center-value">{{ centerValue }}</p>
          <p class="dh-donut-center-label">{{ centerLabel }}</p>
        </div>
      </div>
    </div>
  </article>
</template>

<script setup>
import { ref, watch, onBeforeUnmount, onMounted, nextTick } from 'vue'
import ApexCharts from 'apexcharts'

const props = defineProps({
  title: { type: String, default: 'Breakdown' },
  loading: { type: Boolean, default: false },
  legend: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  centerValue: { type: String, default: '0' },
  centerLabel: { type: String, default: 'Total' },
})

const donutRef = ref(null)
let donutChart = null

function render() {
  if (!donutRef.value) return
  const raw = props.series.map((v) => Number(v) || 0)
  const hasData = raw.some((v) => v > 0)
  const data = hasData ? raw : [1, 1, 1]
  const colors = hasData ? ['#7c5cbf', '#84cc16', '#ff9f43'] : ['#e8e4ef', '#e8e4ef', '#e8e4ef']
  if (donutChart) donutChart.destroy()
  donutRef.value.innerHTML = ''
  donutChart = new ApexCharts(donutRef.value, {
    series: data,
    labels: props.legend.map((l) => l.label),
    colors,
    chart: { type: 'donut', height: 100, width: 100, animations: { speed: 400 } },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: false } } } },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: hasData },
    stroke: { width: 2, colors: ['#fff'] },
  })
  donutChart.render()
}

async function tryRender() {
  if (props.loading) return
  await nextTick()
  requestAnimationFrame(render)
}

watch(() => [props.loading, props.series], tryRender, { deep: true })
onMounted(tryRender)
onBeforeUnmount(() => donutChart?.destroy())
</script>
