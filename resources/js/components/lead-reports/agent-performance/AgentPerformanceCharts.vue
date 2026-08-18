<template>
  <section class="ap-charts">
    <div class="ap-charts__grid">
      <article class="ap-chart-card">
        <header class="ap-chart-card__head">
          <div>
            <h3 class="ap-section-title">Commission by Agent</h3>
            <p class="ap-section-sub">Total commission earned in period</p>
          </div>
        </header>
        <AdLazyChart
          v-if="hasCommissionData"
          type="bar"
          :height="260"
          :series="commissionChart.series"
          :options="commissionOptions"
        />
        <AdEmptyState v-else title="No commission data" icon="lucide:bar-chart-2" />
      </article>

      <article class="ap-chart-card">
        <header class="ap-chart-card__head">
          <div>
            <h3 class="ap-section-title">Converted Deals by Agent</h3>
            <p class="ap-section-sub">Number of closed deals per agent</p>
          </div>
        </header>
        <AdLazyChart
          v-if="hasDealsData"
          type="bar"
          :height="260"
          :series="dealsChart.series"
          :options="dealsOptions"
        />
        <AdEmptyState v-else title="No deal data" icon="lucide:bar-chart-horizontal" />
      </article>

      <article class="ap-chart-card ap-chart-card--wide">
        <header class="ap-chart-card__head">
          <div>
            <h3 class="ap-section-title">Conversions Over Time</h3>
            <p class="ap-section-sub">Daily converted deals in selected range</p>
          </div>
        </header>
        <AdLazyChart
          v-if="hasTimelineData"
          type="area"
          :height="240"
          :series="timelineChart.series"
          :options="timelineOptions"
        />
        <AdEmptyState v-else title="No timeline data" icon="lucide:trending-up" />
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import AdLazyChart from '@/components/dashboard/analytics/widgets/AdLazyChart.vue'
import AdEmptyState from '@/components/dashboard/analytics/widgets/AdEmptyState.vue'

const props = defineProps({
  commissionChart: { type: Object, default: () => ({ categories: [], series: [] }) },
  dealsChart: { type: Object, default: () => ({ categories: [], series: [] }) },
  timelineChart: { type: Object, default: () => ({ categories: [], series: [] }) },
})

const chartColors = ['#020b38', '#3b68ff', '#27ae60']

const baseBarOptions = (categories) => ({
  colors: [chartColors[0]],
  plotOptions: {
    bar: { borderRadius: 6, columnWidth: '55%', horizontal: false },
  },
  xaxis: {
    categories,
    labels: { style: { fontSize: '11px', colors: '#6f7282' }, rotate: -35 },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      style: { fontSize: '11px', colors: '#6f7282' },
      formatter: (v) => new Intl.NumberFormat('en', { notation: 'compact' }).format(v),
    },
  },
  tooltip: {
    y: { formatter: (v) => new Intl.NumberFormat('en').format(v) },
  },
  grid: { strokeDashArray: 4, padding: { left: 8, right: 8 } },
})

const commissionOptions = computed(() => baseBarOptions(props.commissionChart.categories))
const dealsOptions = computed(() => ({
  ...baseBarOptions(props.dealsChart.categories),
  colors: [chartColors[2]],
}))

const timelineOptions = computed(() => ({
  colors: [chartColors[1]],
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] },
  },
  xaxis: {
    categories: props.timelineChart.categories,
    labels: { style: { fontSize: '10px', colors: '#6f7282' }, rotate: -45 },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', colors: '#6f7282' } },
    min: 0,
    forceNiceScale: true,
  },
  grid: { strokeDashArray: 4, padding: { left: 8, right: 8 } },
}))

const hasCommissionData = computed(() => props.commissionChart.series?.[0]?.data?.length > 0)
const hasDealsData = computed(() => props.dealsChart.series?.[0]?.data?.length > 0)
const hasTimelineData = computed(() => props.timelineChart.series?.[0]?.data?.length > 0)
</script>
