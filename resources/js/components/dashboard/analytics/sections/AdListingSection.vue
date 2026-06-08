<template>
  <section class="ex-section">
    <div class="ex-section__head">
      <span class="ex-section__title">Listing Analytics</span>
    </div>

    <div v-if="loading" class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiSkeleton v-for="i in 10" :key="i" />
    </div>
    <div v-else class="ex-kpi-grid ex-kpi-grid--wide">
      <AdKpiCard label="Total listings" :value="listing.total_listings" icon="lucide:layers" :sparkline="trendValues" spark-color="#16a34a" />
      <AdKpiCard label="Active" :value="listing.active_listings" icon="lucide:check-circle" tone="green" />
      <AdKpiCard label="Pending approval" :value="listing.pending_approval" icon="lucide:clock" tone="orange" />
      <AdKpiCard label="Sold" :value="listing.sold_listings" icon="lucide:badge-check" tone="green" />
      <AdKpiCard label="Expired" :value="listing.expired_listings" icon="lucide:archive" />
      <AdKpiCard label="Total views" :value="listing.total_views" icon="lucide:eye" />
      <AdKpiCard label="Inquiries" :value="listing.inquiry_requests" icon="lucide:inbox" />
      <AdKpiCard label="Viewings" :value="listing.viewing_appointments" icon="lucide:calendar" />
      <AdKpiCard label="WhatsApp clicks" :value="listing.whatsapp_clicks" icon="lucide:message-circle" tone="green" />
      <AdKpiCard label="Saved" :value="listing.saved_listings" icon="lucide:bookmark" />
      <AdKpiCard label="Conversion" :value="listing.conversion_rate" suffix="%" format="percent" icon="lucide:percent" />
    </div>

    <div class="ex-charts-row">
      <AdPanel title="Listing activity" subtitle="Daily new listings">
        <AdLazyChart v-if="!loading && trendValues.length" type="line" :height="180" :series="[{ name: 'Listings', data: trendValues }]" :options="trendOpts" :dark="dark" />
        <AdChartSkeleton v-else />
      </AdPanel>
      <AdPanel title="Property types" subtitle="Distribution">
        <AdLazyChart v-if="!loading && typeValues.length" type="bar" :height="180" :series="[{ name: 'Count', data: typeValues }]" :options="typeOpts" :dark="dark" />
        <AdEmptyState v-else-if="!loading" title="No property data" />
        <AdChartSkeleton v-else />
      </AdPanel>
    </div>

    <AdPanel title="Top performing listings">
      <AdDataTable v-if="!loading" :columns="cols" :rows="listing.top_listings || []" />
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
import AdDataTable from '../widgets/AdDataTable.vue'

const props = defineProps({
  listing: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
})

const trendValues = computed(() => (props.listing?.trend || []).map((t) => t.value))
const typeLabels = computed(() => (props.listing?.property_types || []).map((t) => t.type))
const typeValues = computed(() => (props.listing?.property_types || []).map((t) => t.count))

const cols = [
  { key: 'title', label: 'Listing' },
  { key: 'price', label: 'Price', format: 'currency' },
  { key: 'views', label: 'Views' },
  { key: 'status', label: 'Status' },
]

const trendOpts = computed(() => ({
  colors: ['#16a34a'],
  xaxis: { categories: (props.listing?.trend || []).map((t) => t.label), labels: { style: { fontSize: '9px' } } },
}))

const typeOpts = computed(() => ({
  colors: ['#7c5cbf'],
  xaxis: { categories: typeLabels.value, labels: { style: { fontSize: '9px' } } },
  plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } },
}))
</script>
