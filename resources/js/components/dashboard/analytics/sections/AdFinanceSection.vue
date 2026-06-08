<template>
  <section class="ex-section">
    <div class="ex-section__head">
      <span class="ex-section__title">Finance Analytics</span>
    </div>

    <div v-if="loading" class="ex-kpi-grid">
      <AdKpiSkeleton v-for="i in 5" :key="i" />
    </div>
    <div v-else class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiCard label="Revenue" :value="finance.revenue" prefix="AED " format="currency" icon="lucide:trending-up" tone="green" />
      <AdKpiCard label="Expenses" :value="finance.expenses" prefix="AED " format="currency" icon="lucide:trending-down" tone="orange" />
      <AdKpiCard label="Profit" :value="finance.profit" prefix="AED " format="currency" icon="lucide:piggy-bank" />
      <AdKpiCard label="Outstanding" :value="finance.outstanding_invoices" prefix="AED " format="currency" icon="lucide:file-warning" tone="red" />
      <AdKpiCard label="Forecast" :value="finance.forecast" prefix="AED " format="currency" icon="lucide:telescope" tone="blue" />
    </div>

    <AdPanel title="Cash flow" subtitle="Monthly in vs out">
      <AdLazyChart v-if="!loading && cashIn.length" type="area" :height="200" :series="cashSeries" :options="cashOpts" :dark="dark" />
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
import AdChartSkeleton from '../widgets/AdChartSkeleton.vue'

const props = defineProps({
  finance: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
})

const cashIn = computed(() => (props.finance?.cash_flow || []).map((r) => r.in))
const cashOut = computed(() => (props.finance?.cash_flow || []).map((r) => r.out))

const cashSeries = computed(() => [
  { name: 'Inflow', data: cashIn.value },
  { name: 'Outflow', data: cashOut.value },
])

const cashOpts = computed(() => ({
  colors: ['#16a34a', '#dc2626'],
  fill: { type: 'gradient', gradient: { opacityFrom: 0.2, opacityTo: 0.02 } },
  xaxis: { categories: (props.finance?.cash_flow || []).map((r) => r.label), labels: { style: { fontSize: '9px' } } },
}))
</script>
