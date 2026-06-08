<template>
  <div class="dh-analytics-block">
    <p v-if="tag" class="dh-analytics-tag">{{ tag }}</p>
    <div class="dh-layout dh-layout--analytics-block">
      <!-- Top metrics (3 cards) -->
      <section class="dh-layout__metrics">
        <div class="dh-metrics-carousel">
          <article
            v-for="(m, i) in metrics"
            :key="i"
            class="dh-metric-card"
            :class="m.variant === 'primary' ? 'dh-metric-card--primary' : 'dh-metric-card--light'"
          >
            <div class="dh-metric-head">
              <div
                class="dh-metric-icon"
                :class="m.variant === 'primary' ? 'dh-metric-icon--white' : 'dh-metric-icon--soft'"
              >
                <iconify-icon :icon="m.icon" width="22" height="22" />
              </div>
              <span v-if="m.badge" class="dh-metric-badge">
                <iconify-icon icon="lucide:trending-up" width="11" height="11" />
                {{ m.badge }}
              </span>
            </div>
            <p class="dh-metric-label">{{ m.label }}</p>
            <div class="dh-metric-value-row" :class="{ 'dh-metric-value-row--requests': m.subRows?.length }">
              <p class="dh-metric-value">{{ m.value }}</p>
              <p v-if="m.subtitle && !m.subRows?.length" class="dh-metric-vs">{{ m.subtitle }}</p>
              <div v-if="m.subRows?.length" class="dh-metric-io">
                <span v-for="(row, ri) in m.subRows" :key="ri">{{ row.label }} : {{ row.value }}</span>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- Task summary -->
      <article class="dh-panel dh-panel--task dh-layout__task">
        <div class="dh-panel-head">
          <p class="dh-panel-title">{{ taskTitle }}</p>
          <router-link v-if="taskLink" :to="taskLink.to" class="dh-panel-link dh-panel-link--accent">
            {{ taskLink.label }} &gt;
          </router-link>
        </div>
        <div class="dh-task-cards">
          <div v-for="pill in taskPills" :key="pill.label" class="dh-task-pill">
            <p class="dh-task-pill-label">{{ pill.label }}</p>
            <p class="dh-task-pill-value">{{ pill.value }}</p>
          </div>
        </div>
      </article>

      <!-- Line / area chart -->
      <article class="dh-panel dh-panel--chart dh-layout__chart">
        <div class="dh-panel-head">
          <p class="dh-panel-title">{{ chartTitle }}</p>
          <span v-if="chartPeriodLabel" class="dh-chart-period dh-chart-period--static">
            <iconify-icon icon="lucide:calendar" width="15" height="15" />
            {{ chartPeriodLabel }}
          </span>
        </div>
        <div v-if="loading" class="dh-chart-wrap dh-skeleton" />
        <div v-else ref="lineRef" class="dh-chart-wrap" />
      </article>

      <!-- Donut + legend -->
      <article class="dh-panel dh-panel--listings dh-layout__listings">
        <div class="dh-panel-head dh-listings-head">
          <p class="dh-panel-title">{{ donutTitle }}</p>
          <router-link v-if="donutLink" :to="donutLink.to" class="dh-listings-link">
            {{ donutLink.label }}
            <iconify-icon icon="lucide:chevron-right" width="14" height="14" class="dh-listings-link-icon" />
          </router-link>
        </div>
        <div v-if="loading" class="dh-listings-content dh-skeleton" />
        <div v-else class="dh-listings-content">
          <div class="dh-listings-legend">
            <div v-for="leg in donutLegend" :key="leg.label" class="dh-legend-block">
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
              <p class="dh-donut-center-value">{{ donutCenterValue }}</p>
              <p class="dh-donut-center-label">{{ donutCenterLabel }}</p>
            </div>
          </div>
        </div>
      </article>

      <!-- Sidebar (insights / alerts) -->
      <article class="dh-panel dh-panel--schedule dh-layout__schedule">
        <div class="dh-schedule-head">
          <p class="dh-schedule-date">{{ sidebarTitle }}</p>
        </div>
        <ul v-if="sidebarItems?.length" class="dh-insights-list">
          <li
            v-for="(item, idx) in sidebarItems"
            :key="idx"
            class="dh-insight-item"
            :class="item.tone ? `dh-insight-item--${item.tone}` : ''"
          >
            <strong v-if="item.title">{{ item.title }}</strong>
            {{ item.text || item.message }}
          </li>
        </ul>
        <div v-else class="dh-empty dh-empty--compact">
          <p class="dh-empty-text">{{ sidebarEmpty }}</p>
        </div>
      </article>

      <!-- Agent / ranking list -->
      <article class="dh-panel dh-panel--agents dh-layout__agents">
        <div class="dh-panel-head">
          <p class="dh-panel-title">{{ agentsTitle }}</p>
          <router-link v-if="agentsLink" :to="agentsLink.to" class="dh-panel-link dh-panel-link--accent">
            {{ agentsLink.label }} &gt;
          </router-link>
        </div>
        <div v-if="loading" class="dh-agents-list">
          <div v-for="i in 3" :key="i" class="dh-agent-row">
            <div class="dh-skeleton dh-skeleton--avatar" />
            <div class="flex-grow-1">
              <div class="dh-skeleton mb-1" style="height: 12px; width: 60%" />
              <div class="dh-skeleton" style="height: 10px; width: 40%" />
            </div>
          </div>
        </div>
        <div v-else-if="agents?.length" class="dh-agents-list">
          <div
            v-for="(agent, idx) in agents"
            :key="agent.id || idx"
            class="dh-agent-row"
            :class="{ 'dh-agent-row--active': idx === 0 }"
          >
            <img v-if="agent.avatar" :src="agent.avatar" :alt="agent.name" class="dh-agent-avatar" />
            <div v-else class="dh-agent-rank">{{ idx + 1 }}</div>
            <div class="dh-agent-info">
              <p class="dh-agent-name">{{ agent.name }}</p>
              <p class="dh-agent-office">{{ agent.subtitle }}</p>
            </div>
            <span class="dh-agent-role">{{ agent.role }}</span>
          </div>
        </div>
        <div v-else class="dh-empty dh-empty--compact">
          <p class="dh-empty-title">No data</p>
        </div>
      </article>

      <!-- Bottom panel (status / extras) -->
      <article class="dh-panel dh-panel--announce dh-layout__announce">
        <div class="dh-panel-head">
          <p class="dh-panel-title">{{ bottomTitle }}</p>
        </div>
        <div v-if="bottomCells?.length" class="dh-status-grid" :class="{ 'dh-status-grid--triple': bottomCells.length > 4 }">
          <div v-for="cell in bottomCells" :key="cell.label" class="dh-status-cell">
            <span>{{ cell.label }}</span>
            <strong>{{ cell.value }}</strong>
          </div>
        </div>
        <div v-else class="dh-empty dh-empty--compact">
          <p class="dh-empty-text">{{ bottomEmpty }}</p>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount, onMounted, nextTick } from 'vue'
import ApexCharts from 'apexcharts'

const props = defineProps({
  tag: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  metrics: { type: Array, default: () => [] },
  taskTitle: { type: String, default: 'Summary' },
  taskLink: { type: Object, default: null },
  taskPills: { type: Array, default: () => [] },
  chartTitle: { type: String, default: 'Performance' },
  chartPeriodLabel: { type: String, default: '' },
  chartCategories: { type: Array, default: () => [] },
  chartValues: { type: Array, default: () => [] },
  chartColor: { type: String, default: '#f59e0b' },
  chartType: { type: String, default: 'area' },
  donutTitle: { type: String, default: 'Breakdown' },
  donutLink: { type: Object, default: null },
  donutLegend: { type: Array, default: () => [] },
  donutSeries: { type: Array, default: () => [] },
  donutCenterValue: { type: String, default: '0' },
  donutCenterLabel: { type: String, default: 'Total' },
  sidebarTitle: { type: String, default: 'Insights' },
  sidebarItems: { type: Array, default: () => [] },
  sidebarEmpty: { type: String, default: 'Nothing to show.' },
  agentsTitle: { type: String, default: 'Top Performance' },
  agentsLink: { type: Object, default: null },
  agents: { type: Array, default: () => [] },
  bottomTitle: { type: String, default: 'Details' },
  bottomCells: { type: Array, default: () => [] },
  bottomEmpty: { type: String, default: 'No details.' },
})

const lineRef = ref(null)
const donutRef = ref(null)
let lineChart = null
let donutChart = null

function renderLine() {
  if (!lineRef.value || !props.chartValues?.length) return
  if (lineChart) lineChart.destroy()

  if (props.chartType === 'bar') {
    lineChart = new ApexCharts(lineRef.value, {
      series: [{ name: 'Count', data: props.chartValues.map((v) => Number(v) || 0) }],
      chart: { type: 'bar', height: 220, toolbar: { show: false }, fontFamily: 'Inter, system-ui, sans-serif' },
      colors: [props.chartColor],
      plotOptions: { bar: { borderRadius: 6, columnWidth: '52%', horizontal: true } },
      dataLabels: { enabled: false },
      xaxis: {
        categories: props.chartCategories,
        labels: { style: { fontSize: '10px', colors: '#9ca3af' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: { labels: { style: { fontSize: '10px', colors: '#9ca3af' } } },
      grid: { borderColor: '#e8eaef', strokeDashArray: 4 },
    })
  } else {
    lineChart = new ApexCharts(lineRef.value, {
      series: [{ name: 'Count', data: props.chartValues }],
      chart: { type: 'area', height: 220, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'Inter, system-ui, sans-serif' },
      colors: [props.chartColor],
      fill: {
        type: 'gradient',
        gradient: {
          opacityFrom: 0.45,
          opacityTo: 0.02,
          colorStops: [
            { offset: 0, color: props.chartColor, opacity: 0.5 },
            { offset: 100, color: '#ffffff', opacity: 0 },
          ],
        },
      },
      stroke: { curve: 'smooth', width: 2, dashArray: 6, colors: [props.chartColor] },
      dataLabels: { enabled: false },
      xaxis: {
        categories: props.chartCategories,
        labels: { style: { fontSize: '11px', colors: '#9ca3af' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: { labels: { style: { fontSize: '11px', colors: '#9ca3af' } } },
      grid: { borderColor: '#e8eaef', strokeDashArray: 4 },
    })
  }
  lineChart.render()
}

function renderDonut() {
  if (!donutRef.value) return
  const raw = props.donutSeries.map((v) => Number(v) || 0)
  const hasData = raw.some((v) => v > 0)
  const series = hasData ? raw : [1, 1, 1]
  const colors = hasData ? ['#7c5cbf', '#84cc16', '#ff9f43'] : ['#e8e4ef', '#e8e4ef', '#e8e4ef']
  if (donutChart) donutChart.destroy()
  donutRef.value.innerHTML = ''
  donutChart = new ApexCharts(donutRef.value, {
    series,
    labels: props.donutLegend.map((l) => l.label),
    colors,
    chart: { type: 'donut', height: 120, width: 120, animations: { speed: 400 } },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: false } } } },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: hasData },
    stroke: { width: 2, colors: ['#fff'] },
  })
  donutChart.render()
}

async function renderCharts() {
  if (props.loading) return
  await nextTick()
  requestAnimationFrame(() => {
    renderLine()
    renderDonut()
  })
}

watch(
  () => [props.loading, props.chartValues, props.donutSeries, props.chartType, props.chartCategories],
  () => renderCharts(),
  { deep: true },
)

onMounted(() => renderCharts())

onBeforeUnmount(() => {
  lineChart?.destroy()
  donutChart?.destroy()
})
</script>

<style scoped>
.dh-agent-rank {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--dh-purple-100, #ede8f7);
  color: var(--dh-purple-700, #5b3d8f);
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
