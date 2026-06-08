<template>
  <section class="ex-section">
    <div class="ex-section__head">
      <span class="ex-section__title">CRM Analytics</span>
      <span v-if="overdue" class="ex-section__alert">
        <iconify-icon icon="lucide:alarm-clock" width="12" height="12" />
        {{ overdue }} overdue follow-ups
      </span>
    </div>

    <div v-if="loading" class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiSkeleton v-for="i in 12" :key="i" />
    </div>
    <div v-else class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiCard label="Total leads" :value="crm.total_leads" icon="lucide:users" :sparkline="trendValues" />
      <AdKpiCard label="New leads" :value="crm.new_leads" icon="lucide:sparkles" tone="blue" :trend="12" />
      <AdKpiCard label="Contacted" :value="crm.contacted" icon="lucide:phone-call" />
      <AdKpiCard label="No answer" :value="crm.no_answer" icon="lucide:phone-off" tone="orange" />
      <AdKpiCard label="Follow-up" :value="crm.follow_up" icon="lucide:calendar-clock" />
      <AdKpiCard label="Qualified" :value="crm.qualified" icon="lucide:badge-check" tone="green" />
      <AdKpiCard label="Cold" :value="crm.cold" icon="lucide:snowflake" tone="blue" />
      <AdKpiCard label="Warm" :value="crm.warm" icon="lucide:flame" tone="orange" />
      <AdKpiCard label="Hot" :value="crm.hot" icon="lucide:zap" tone="red" />
      <AdKpiCard label="Negotiation" :value="crm.negotiation" icon="lucide:handshake" />
      <AdKpiCard label="Converted" :value="crm.converted" icon="lucide:trophy" tone="green" />
      <AdKpiCard label="Lost" :value="crm.lost" icon="lucide:x-circle" />
      <AdKpiCard label="Conversion rate" :value="crm.conversion_rate" suffix="%" format="percent" icon="lucide:percent" tone="purple" />
      <AdKpiCard label="Revenue" :value="crm.revenue_from_leads" prefix="AED " format="currency" icon="lucide:banknote" />
      <AdKpiCard label="Avg response" :value="crm.avg_response_time_min" suffix=" min" icon="lucide:timer" />
      <AdKpiCard label="Calls answered" :value="crm.calls_answered" icon="lucide:phone-incoming" tone="green" />
    </div>

    <div class="ex-charts-row">
      <AdPanel title="Pipeline funnel" subtitle="Stage distribution">
        <AdLazyChart v-if="!loading && funnelValues.length" type="bar" :height="180" :series="[{ name: 'Leads', data: funnelValues }]" :options="funnelOpts" :dark="dark" />
        <AdEmptyState v-else-if="!loading" title="No funnel data" icon="lucide:git-merge" />
        <AdChartSkeleton v-else />
      </AdPanel>
      <AdPanel title="Lead trend" subtitle="Daily volume">
        <AdLazyChart v-if="!loading && trendValues.length" type="area" :height="180" :series="[{ name: 'Leads', data: trendValues }]" :options="trendOpts" :dark="dark" />
        <AdChartSkeleton v-else />
      </AdPanel>
    </div>

    <div class="ex-charts-row ex-charts-row--3">
      <AdPanel title="Lead sources">
        <AdLazyChart v-if="!loading && sourceValues.length" type="donut" :height="200" :series="sourceValues" :options="sourceOpts" :dark="dark" />
        <AdEmptyState v-else-if="!loading" title="No source data" />
        <AdChartSkeleton v-else />
      </AdPanel>
      <AdPanel title="Agent ranking">
        <AdDataTable v-if="!loading" :columns="agentCols" :rows="crm.agent_ranking || []" />
        <AdChartSkeleton v-else />
      </AdPanel>
    </div>

    <AdPanel v-if="!loading && crm.best_closer" title="Best closer" class="mt-2">
      <div class="ex-closer-card">
        <iconify-icon icon="lucide:award" width="22" height="22" />
        <div>
          <strong>{{ crm.best_closer.name }}</strong>
          <span>{{ crm.best_closer.converted }} conversions · {{ crm.best_closer.rate }}% rate</span>
        </div>
      </div>
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
import AdDataTable from '../widgets/AdDataTable.vue'

const props = defineProps({
  crm: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
})

const overdue = computed(() => props.crm?.follow_up_overdue || 0)
const trendValues = computed(() => (props.crm?.trend || []).map((t) => t.value))
const funnelValues = computed(() => props.crm?.funnel?.values || [])
const funnelLabels = computed(() => props.crm?.funnel?.labels || [])
const sourceLabels = computed(() => (props.crm?.lead_sources || []).map((s) => s.source))
const sourceValues = computed(() => (props.crm?.lead_sources || []).map((s) => s.count))

const agentCols = [
  { key: 'name', label: 'Agent' },
  { key: 'leads', label: 'Leads' },
  { key: 'converted', label: 'Won' },
  { key: 'rate', label: 'Rate', format: 'percent' },
]

const colors = ['#7c5cbf', '#5b3d8f', '#16a34a', '#f59e0b']

const funnelOpts = computed(() => ({
  colors,
  xaxis: { categories: funnelLabels.value, labels: { style: { fontSize: '9px' } } },
  plotOptions: { bar: { borderRadius: 6, columnWidth: '52%' } },
}))

const trendOpts = computed(() => ({
  colors: ['#7c5cbf'],
  fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
  xaxis: { categories: (props.crm?.trend || []).map((t) => t.label), labels: { style: { fontSize: '9px' } } },
}))

const sourceOpts = computed(() => ({
  labels: sourceLabels.value,
  colors,
  legend: { position: 'bottom', fontSize: '10px' },
}))
</script>

<style scoped>
.mt-2 { margin-top: 10px; }
</style>
