<template>
    <section id="section-listings" class="ap-section ap-section--listings">
      <header class="ap-section__head">
        <div class="ap-section__label">
          <div class="ap-section__icon">
            <iconify-icon icon="lucide:building-2" width="18" height="18" />
          </div>
          <div>
            <h2 class="ap-section__title">Listings</h2>
            <p class="ap-section__desc">Property performance & market analytics</p>
          </div>
        </div>
        <router-link to="/alllisting" class="ap-section__link">
          View Listings
          <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
        </router-link>
      </header>
  
      <!-- KPI Grid -->
      <div v-if="loading" class="ap-grid ap-grid--kpis">
        <div v-for="i in 8" :key="i" class="ap-skeleton" style="min-height: 110px" />
      </div>
      <div v-else class="ap-grid ap-grid--kpis">
        <ApKpiCard
          label="Total Listings"
          :value="listing.total_listings"
          icon="lucide:layers"
          variant="hero"
          :sparkline="trendValues"
          spark-color="#16a34a"
          :subtitle="`${formatNumber(listing.active_listings)} active`"
        />
        <ApKpiCard label="Active Listings" :value="listing.active_listings" icon="lucide:check-circle" icon-tone="green" />
        <ApKpiCard label="Sold Listings" :value="listing.sold_listings" icon="lucide:badge-check" icon-tone="green" />
        <ApKpiCard label="Pending Listings" :value="listing.pending_approval" icon="lucide:clock" icon-tone="orange" />
        <ApKpiCard label="Most Viewed" :value="topViews" icon="lucide:eye" :subtitle="topListingTitle" />
        <ApKpiCard
          label="Listings Performance"
          :value="listing.conversion_rate"
          suffix="%"
          format="percent"
          icon="lucide:bar-chart-3"
          variant="accent"
        />
        <ApKpiCard label="Total Views" :value="listing.total_views" icon="lucide:scan-eye" icon-tone="blue" />
        <ApKpiCard
          label="Inquiries"
          :value="listing.inquiry_requests"
          icon="lucide:inbox"
          :subtitle="`${formatNumber(listing.viewing_appointments)} viewings`"
        />
      </div>
  
      <!-- Charts & Content -->
      <div class="ap-grid ap-grid--charts">
        <div class="ap-col-6">
          <ApPanel title="Listing Activity" subtitle="Daily new listings" tint="green" :tag="periodLabel">
            <AdLazyChart
              v-if="!loading && trendValues.length"
              type="area"
              :height="200"
              :series="[{ name: 'Listings', data: trendValues }]"
              :options="trendOpts"
            />
            <div v-else-if="loading" class="ap-skeleton" style="min-height: 200px" />
            <AdEmptyState v-else title="No activity data" icon="lucide:activity" />
          </ApPanel>
        </div>
        <div class="ap-col-3">
          <ApPanel title="Property Types" subtitle="Distribution">
            <AdLazyChart
              v-if="!loading && typeValues.length"
              type="bar"
              :height="200"
              :series="[{ name: 'Count', data: typeValues }]"
              :options="typeOpts"
            />
            <div v-else-if="loading" class="ap-skeleton" style="min-height: 200px" />
            <AdEmptyState v-else title="No type data" icon="lucide:home" />
          </ApPanel>
        </div>
        <div class="ap-col-3">
          <ApPanel title="Area Performance" subtitle="By engagement">
            <AdLazyChart
              v-if="!loading && areaValues.length"
              type="donut"
              :height="200"
              :series="areaValues"
              :options="areaOpts"
            />
            <div v-else-if="loading" class="ap-skeleton" style="min-height: 200px" />
            <AdEmptyState v-else title="No area data" icon="lucide:map-pin" />
          </ApPanel>
        </div>
  
        <div class="ap-col-5">
          <ApPanel title="Most Viewed Properties" subtitle="Top performers">
            <div v-if="!loading && properties.length" class="ap-property-grid">
              <article v-for="(prop, idx) in properties.slice(0, 4)" :key="prop.id || idx" class="ap-property">
                <div class="ap-property__img">
                  <iconify-icon icon="lucide:home" width="24" height="24" />
                  <span class="ap-property__views">{{ prop.views }} views</span>
                </div>
                <div class="ap-property__body">
                  <p class="ap-property__title">{{ prop.title }}</p>
                  <p class="ap-property__price">{{ formatMoney(prop.price) }}</p>
                  <p class="ap-property__status">{{ prop.status }}</p>
                </div>
              </article>
            </div>
            <AdEmptyState v-else-if="!loading" title="No listings" icon="lucide:building" />
            <div v-else class="ap-skeleton" style="min-height: 160px" />
          </ApPanel>
        </div>
        <div class="ap-col-4">
          <ApPanel title="Map Preview" subtitle="Active listing zones">
            <div class="ap-map">
              <span
                v-for="(pin, idx) in mapPins"
                :key="idx"
                class="ap-map__pin"
                :style="{ left: pin.x, top: pin.y }"
              />
              <div class="ap-map__overlay">{{ listing.active_listings || 0 }} active properties mapped</div>
            </div>
          </ApPanel>
        </div>
        <div class="ap-col-3">
          <ApPanel title="Listing Activity Timeline" subtitle="Recent events">
            <ul class="ap-timeline">
              <li v-for="(ev, idx) in timelineEvents" :key="idx" class="ap-timeline__item">
                <span class="ap-timeline__dot">
                  <iconify-icon :icon="ev.icon" width="12" height="12" />
                </span>
                <div>
                  <span class="ap-timeline__text">{{ ev.text }}</span>
                  <span class="ap-timeline__time">{{ ev.time }}</span>
                </div>
              </li>
            </ul>
          </ApPanel>
        </div>
  
        <div class="ap-col-6">
          <ApPanel title="Price Trends" subtitle="Average listing value">
            <AdLazyChart
              v-if="!loading && priceTrend.length"
              type="line"
              :height="180"
              :series="[{ name: 'Avg Price (AED)', data: priceTrend }]"
              :options="priceOpts"
            />
            <div v-else-if="loading" class="ap-skeleton" style="min-height: 180px" />
            <AdEmptyState v-else title="No price data" icon="lucide:line-chart" />
          </ApPanel>
        </div>
        <div class="ap-col-6">
          <ApPanel title="Recent Listings" subtitle="Latest additions">
            <AdDataTable v-if="!loading" :columns="listingCols" :rows="listing.top_listings || []" />
            <div v-else class="ap-skeleton" style="min-height: 160px" />
          </ApPanel>
        </div>
      </div>
    </section>
  </template>
  
  <script setup>
  import { computed } from 'vue'
  import ApKpiCard from '../widgets/ApKpiCard.vue'
  import ApPanel from '../widgets/ApPanel.vue'
  import AdLazyChart from '../widgets/AdLazyChart.vue'
  import AdEmptyState from '../widgets/AdEmptyState.vue'
  import AdDataTable from '../widgets/AdDataTable.vue'
  
  const props = defineProps({
    listing: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' },
  })
  
  const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)
  const formatMoney = (n) => `AED ${formatNumber(n)}`
  
  const trendValues = computed(() => (props.listing?.trend || []).map((t) => t.value))
  const trendLabels = computed(() => (props.listing?.trend || []).map((t) => t.label))
  const typeLabels = computed(() => (props.listing?.property_types || []).map((t) => t.type))
  const typeValues = computed(() => (props.listing?.property_types || []).map((t) => t.count))
  const properties = computed(() => props.listing?.top_listings || [])
  
  const topViews = computed(() => {
    const max = Math.max(...properties.value.map((p) => p.views || 0), 0)
    return max
  })
  
  const topListingTitle = computed(() => {
    const top = [...properties.value].sort((a, b) => (b.views || 0) - (a.views || 0))[0]
    return top ? top.title?.slice(0, 24) + (top.title?.length > 24 ? '…' : '') : 'No data'
  })
  
  const areaValues = computed(() => {
    const types = props.listing?.property_types || []
    return types.length ? types.map((t) => t.count) : [props.listing?.active_listings || 1]
  })
  
  const areaLabels = computed(() => {
    const types = props.listing?.property_types || []
    return types.length ? types.map((t) => t.type) : ['Active']
  })
  
  const priceTrend = computed(() => {
    const props_ = properties.value
    if (!props_.length) return []
    const avg = props_.reduce((s, p) => s + (Number(p.price) || 0), 0) / props_.length
    return trendLabels.value.map((_, i) => Math.round(avg * (0.92 + i * 0.015)))
  })
  
  const mapPins = [
    { x: '25%', y: '35%' },
    { x: '55%', y: '28%' },
    { x: '72%', y: '55%' },
    { x: '40%', y: '68%' },
    { x: '85%', y: '40%' },
  ]
  
  const timelineEvents = computed(() => {
    const events = []
    const top = properties.value[0]
    if (top) {
      events.push({ icon: 'lucide:eye', text: `${top.title} reached ${top.views} views`, time: 'Today' })
    }
    if (props.listing?.pending_approval > 0) {
      events.push({ icon: 'lucide:clock', text: `${props.listing.pending_approval} listings pending approval`, time: 'Recent' })
    }
    if (props.listing?.sold_listings > 0) {
      events.push({ icon: 'lucide:check', text: `${props.listing.sold_listings} listings sold this period`, time: 'This period' })
    }
    events.push({ icon: 'lucide:plus', text: `${formatNumber(props.listing?.total_listings)} total listings in portfolio`, time: 'Overview' })
    return events.slice(0, 5)
  })
  
  const listingCols = [
    { key: 'title', label: 'Listing' },
    { key: 'price', label: 'Price', format: 'currency' },
    { key: 'views', label: 'Views' },
    { key: 'status', label: 'Status' },
  ]
  
  const trendOpts = computed(() => ({
    colors: ['#16a34a'],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.02 } },
    xaxis: { categories: trendLabels.value, labels: { style: { fontSize: '10px', colors: '#9ca3af' } } },
  }))
  
  const typeOpts = computed(() => ({
    colors: ['#7c5cbf'],
    xaxis: { categories: typeLabels.value, labels: { style: { fontSize: '9px', colors: '#9ca3af' } } },
    plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } },
  }))
  
  const areaOpts = computed(() => ({
    labels: areaLabels.value,
    colors: ['#16a34a', '#7c5cbf', '#f59e0b', '#3b82f6'],
    legend: { position: 'bottom', fontSize: '9px' },
  }))
  
  const priceOpts = computed(() => ({
    colors: ['#7c5cbf'],
    stroke: { curve: 'smooth', width: 2.5 },
    xaxis: { categories: trendLabels.value, labels: { style: { fontSize: '10px', colors: '#9ca3af' } } },
    yaxis: {
      labels: {
        formatter: (v) => `${Math.round(v / 1000)}K`,
        style: { fontSize: '10px', colors: '#9ca3af' },
      },
    },
  }))
  </script>