<template>
  <section class="ex-section">
    <div class="ex-section__head">
      <span class="ex-section__title">Support Analytics</span>
    </div>

    <div v-if="loading" class="ex-kpi-grid">
      <AdKpiSkeleton v-for="i in 4" :key="i" />
    </div>
    <div v-else class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiCard label="Open tickets" :value="support.open_tickets" icon="lucide:ticket" tone="orange" />
      <AdKpiCard label="SLA breaches" :value="support.sla_breaches" icon="lucide:alert-octagon" tone="red" />
      <AdKpiCard label="Avg response" :value="support.avg_response_time_hrs" suffix=" hrs" icon="lucide:clock" />
      <AdKpiCard label="CSAT" :value="support.satisfaction" suffix="/5" icon="lucide:smile" tone="green" />
    </div>

    <AdPanel title="Ticket categories">
      <AdLazyChart v-if="!loading && catValues.length" type="donut" :height="200" :series="catValues" :options="catOpts" :dark="dark" />
      <AdEmptyState v-else-if="!loading" title="No ticket data" icon="lucide:pie-chart" />
      <AdChartSkeleton v-else />
    </AdPanel>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import AdKpiCard from '../widgets/AdKpiCard.vue'
import AdKpiSkeleton from '../widgets/AdKpiSkeleton.vue'
import AdPanel from '../widgets/AdPanel.vue'
import AdLazyChart from '../widgets/AdLazyChart.vue'
import AdEmptyState from '../widgets/AdEmptyState.vue'
import AdChartSkeleton from '../widgets/AdChartSkeleton.vue'

const props = defineProps({
  support: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
})

const catLabels = computed(() => (props.support?.categories || []).map((c) => c.name))
const catValues = computed(() => (props.support?.categories || []).map((c) => c.count))

const catOpts = computed(() => ({
  labels: catLabels.value,
  colors: ['#7c5cbf', '#16a34a', '#f59e0b', '#2563eb'],
  legend: { position: 'bottom', fontSize: '10px' },
}))
</script>
