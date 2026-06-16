<template>
  <div
    class="crm-dashboard-home crm-dashboard-home--unified ap-dashboard ap-dashboard--uni"
    :class="{ 'crm-dashboard-home--mobile': isMobileViewport }"
  >
    <header class="adx-header">
      <div class="adx-header__brand">
        <div class="adx-header__logo">
          <iconify-icon icon="lucide:layout-dashboard" width="20" height="20" />
        </div>
        <div>
          <h1 class="adx-header__title">Hello, {{ greetingName }} 👋</h1>
          <p class="adx-header__sub">{{ scopeLabel }} — leads, listings &amp; HR at a glance</p>
        </div>
      </div>
      <div class="adx-header__actions">
        <span class="adx-header__period">{{ periodLabel }}</span>
        <DashboardDateRangePicker
          v-model:date-from="dateFrom"
          v-model:date-to="dateTo"
          :label="dateRangeLabel"
          :icon-only="isMobileViewport"
          picker-class="dh-header-date"
          @apply="applyDateRange"
        />
      </div>
    </header>

    <div v-if="error" class="ap-error">
      <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
      <span>{{ error }}</span>
      <button type="button" @click="load(true)">Retry</button>
    </div>

    <div class="adx-uni-body">
      <!-- ── CRM: hero number + funnel bars + trend chart ── -->
      <section v-if="showCrm" class="adx-uni-band adx-uni-band--crm">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon"><iconify-icon icon="lucide:target" width="18" height="18" /></span>
            <div>
              <h2>CRM</h2>
              <p>Leads, pipeline &amp; conversion</p>
            </div>
          </div>
          <router-link to="/kanban" class="adx-uni-band__link">
            Open CRM <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>

        <div class="adx-uni-crm">
          <div class="adx-uni-crm__hero">
            <p class="adx-uni-eyebrow">Total leads</p>
            <p class="adx-uni-mega">{{ formatNumber(crm.total_leads) }}</p>
            <div class="adx-uni-crm__chips">
              <span class="adx-uni-chip adx-uni-chip--gold">{{ crm.conversion_rate || 0 }}% conversion</span>
              <span class="adx-uni-chip">{{ formatNumber(crm.new_leads) }} new</span>
              <span class="adx-uni-chip adx-uni-chip--hot">{{ formatNumber(crm.hot) }} hot</span>
            </div>
            <div class="adx-uni-crm__mini">
              <span><strong>{{ formatNumber(crm.converted) }}</strong> converted</span>
              <span><strong>{{ formatNumber(crm.lost) }}</strong> lost</span>
            </div>
          </div>

          <div class="adx-uni-crm__funnel">
            <p class="adx-uni-panel-title">Pipeline funnel</p>
            <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
            <div v-else class="adx-uni-funnel-scroll">
              <div
                v-for="(stage, i) in crmFunnelStages"
                :key="i"
                class="adx-uni-funnel-row"
              >
                <span class="adx-uni-funnel-row__label">{{ stage.label }}</span>
                <div class="adx-uni-funnel-row__track">
                  <div
                    class="adx-uni-funnel-row__fill"
                    :style="{ width: `${stage.pct}%`, background: stage.color }"
                  />
                </div>
                <span class="adx-uni-funnel-row__val">{{ formatNumber(stage.value) }}</span>
              </div>
              <p v-if="!crmFunnelStages.length" class="adx-uni-empty">No pipeline data yet</p>
            </div>
          </div>

          <div class="adx-uni-crm__trend">
            <p class="adx-uni-panel-title">Leads trend</p>
            <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--chart" />
            <div v-else ref="crmChartRef" class="adx-uni-chart" />
          </div>
        </div>
      </section>

      <!-- ── Listings: donut + progress rows + big callouts ── -->
      <section v-if="showListing" class="adx-uni-band adx-uni-band--listings">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon"><iconify-icon icon="lucide:building-2" width="18" height="18" /></span>
            <div>
              <h2>Listings</h2>
              <p>Properties &amp; performance</p>
            </div>
          </div>
          <router-link to="/alllisting" class="adx-uni-band__link">
            View Listings <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>

        <div class="adx-uni-list">
          <div class="adx-uni-list__donut">
            <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--round" />
            <template v-else>
              <div ref="listingChartRef" class="adx-uni-chart adx-uni-chart--donut" />
              <div class="adx-uni-list__donut-center">
                <strong>{{ formatNumber(listing.total_listings) }}</strong>
                <span>properties</span>
              </div>
            </template>
          </div>

          <div class="adx-uni-list__bars">
            <p class="adx-uni-panel-title adx-uni-panel-title--light">Status breakdown</p>
            <div
              v-for="item in listingBreakdown"
              :key="item.label"
              class="adx-uni-progress"
            >
              <div class="adx-uni-progress__head">
                <span class="adx-uni-progress__dot" :style="{ background: item.color }" />
                <span class="adx-uni-progress__label">{{ item.label }}</span>
                <strong class="adx-uni-progress__val">{{ formatNumber(item.value) }}</strong>
              </div>
              <div class="adx-uni-progress__track">
                <div
                  class="adx-uni-progress__fill"
                  :style="{ width: `${item.pct}%`, background: item.color }"
                />
              </div>
            </div>
            <p v-if="!loading && !listingHasData" class="adx-uni-empty">No listings in your portfolio yet</p>
          </div>

          <div class="adx-uni-list__callouts">
            <div class="adx-uni-callout adx-uni-callout--views">
              <iconify-icon icon="lucide:eye" width="22" height="22" />
              <div>
                <strong>{{ formatNumber(listing.total_views) }}</strong>
                <span>Total views</span>
              </div>
            </div>
            <div class="adx-uni-callout adx-uni-callout--inq">
              <iconify-icon icon="lucide:inbox" width="22" height="22" />
              <div>
                <strong>{{ formatNumber(listing.inquiry_requests) }}</strong>
                <span>Inquiries</span>
              </div>
            </div>
            <div class="adx-uni-callout adx-uni-callout--sold">
              <iconify-icon icon="lucide:badge-check" width="22" height="22" />
              <div>
                <strong>{{ listing.conversion_rate || 0 }}%</strong>
                <span>Sold rate</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── HR: ring gauge + presence strip + attendance chart ── -->
      <section v-if="showHr" class="adx-uni-band adx-uni-band--hr">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon"><iconify-icon icon="lucide:users-round" width="18" height="18" /></span>
            <div>
              <h2>HR</h2>
              <p>Workforce &amp; attendance</p>
            </div>
          </div>
          <router-link to="/hr" class="adx-uni-band__link">
            Open HR <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>

        <div class="adx-uni-hr">
          <div class="adx-uni-hr__ring">
            <svg class="adx-uni-ring" viewBox="0 0 120 120" aria-hidden="true">
              <circle class="adx-uni-ring__bg" cx="60" cy="60" r="52" />
              <circle
                class="adx-uni-ring__fg"
                cx="60" cy="60" r="52"
                :stroke-dasharray="`${hrRingDash} 327`"
              />
            </svg>
            <div class="adx-uni-hr__ring-center">
              <strong>{{ formatNumber(hr.active_employees) }}</strong>
              <span>active / {{ formatNumber(hr.total_employees) }}</span>
              <em>{{ hrActivePct }}%</em>
            </div>
          </div>

          <div class="adx-uni-hr__presence">
            <p class="adx-uni-panel-title adx-uni-panel-title--light">Today's presence</p>
            <div class="adx-uni-presence-bar">
              <div
                v-for="seg in hrPresence"
                :key="seg.label"
                class="adx-uni-presence-bar__seg"
                :style="{ flex: seg.value || 0.001, background: seg.color }"
                :title="`${seg.label}: ${seg.value}`"
              />
            </div>
            <ul class="adx-uni-presence-legend">
              <li v-for="seg in hrPresence" :key="seg.label">
                <span :style="{ background: seg.color }" />
                {{ seg.label }}
                <strong>{{ formatNumber(seg.value) }}</strong>
              </li>
            </ul>
            <div class="adx-uni-hr__prod">
              <span>Productivity</span>
              <strong>{{ hr.productivity_score || 0 }}%</strong>
            </div>
          </div>

          <div class="adx-uni-hr__attendance">
            <p class="adx-uni-panel-title">Attendance — last 7 days</p>
            <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--chart" />
            <div v-else ref="hrChartRef" class="adx-uni-chart" />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue'
import ApexCharts from 'apexcharts'
import DashboardDateRangePicker from '@/components/dashboard/home/DashboardDateRangePicker.vue'
import { useMobileNavigation } from '@/composables/useMobileNavigation.js'
import { useAnalyticsDashboard } from '@/composables/useAnalyticsDashboard.js'
import { useDashboardPermissions } from '@/composables/useDashboardPermissions.js'
import { parseToDate } from '@/composables/useAdvancedDateModel.js'

const PURPLE = '#7c5cbf'
const PURPLE_DARK = '#5b3d8f'
const GOLD = '#f59e0b'
const WHITE_MUTED = 'rgba(255,255,255,0.35)'

const FUNNEL_COLORS = [PURPLE, '#6d52b5', PURPLE_DARK, '#4a3278', '#3d2864']

const { isMobileViewport } = useMobileNavigation()
const { canViewModule, scopeLabel } = useDashboardPermissions()

const showCrm = computed(() => canViewModule('crm'))
const showListing = computed(() => canViewModule('listing') || canViewModule('crm'))
const showHr = computed(() => canViewModule('hr') || canViewModule('crm'))

const {
  loading, error, data, dateFrom, dateTo, periodLabel,
  load, setCustomRange,
} = useAnalyticsDashboard()

const crm = computed(() => data.value?.crm || {})
const listing = computed(() => data.value?.listing || {})
const hr = computed(() => data.value?.hr || {})

const greetingName = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    return (user?.name || 'User').split(' ')[0]
  } catch {
    return 'User'
  }
})

const dateRangeLabel = computed(() => {
  const fmt = (ymd) => {
    const d = parseToDate(ymd)
    if (!d) return ''
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
  }
  const a = fmt(dateFrom.value)
  const b = fmt(dateTo.value)
  if (a && b) return `${a} - ${b}`
  return periodLabel.value
})

const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)

const crmFunnelStages = computed(() => {
  const funnel = crm.value.funnel || {}
  const labels = funnel.labels || []
  const values = funnel.values || []
  const max = Math.max(...values.map((v) => Number(v) || 0), 1)
  return labels.slice(0, 5).map((label, i) => ({
    label,
    value: Number(values[i]) || 0,
    pct: Math.round(((Number(values[i]) || 0) / max) * 100),
    color: FUNNEL_COLORS[i % FUNNEL_COLORS.length],
  }))
})

const listingBreakdown = computed(() => {
  const items = [
    { label: 'Active', value: Number(listing.value.active_listings) || 0, color: PURPLE },
    { label: 'Sold', value: Number(listing.value.sold_listings) || 0, color: PURPLE_DARK },
    { label: 'Pending', value: Number(listing.value.pending_approval) || 0, color: GOLD },
    { label: 'Expired', value: Number(listing.value.expired_listings) || 0, color: WHITE_MUTED },
  ]
  const max = Math.max(...items.map((i) => i.value), 1)
  return items.map((i) => ({ ...i, pct: Math.round((i.value / max) * 100) }))
})

const listingHasData = computed(() => {
  const l = listing.value || {}
  return ['total_listings', 'active_listings', 'sold_listings', 'pending_approval']
    .some((key) => Number(l[key]) > 0)
})

const hrActivePct = computed(() => {
  const total = Number(hr.value.total_employees) || 1
  const active = Number(hr.value.active_employees) || 0
  return Math.min(100, Math.round((active / total) * 100))
})

const hrRingDash = computed(() => Math.round((hrActivePct.value / 100) * 327))

const hrPresence = computed(() => [
  { label: 'Active', value: Number(hr.value.active_employees) || 0, color: GOLD },
  { label: 'Absent', value: Number(hr.value.absent_employees) || 0, color: PURPLE_DARK },
  { label: 'On leave', value: Number(hr.value.on_leave) || 0, color: WHITE_MUTED },
])

function applyDateRange() {
  if (dateFrom.value && dateTo.value) {
    setCustomRange(dateFrom.value, dateTo.value)
  } else {
    load(true)
  }
}

const crmChartRef = ref(null)
const listingChartRef = ref(null)
const hrChartRef = ref(null)
let crmChart = null
let listingChart = null
let hrChart = null

function chartHeight() {
  const w = typeof window !== 'undefined' ? window.innerWidth : 1541
  if (isMobileViewport.value || w < 641) return 140
  if (w < 1280) return 120
  return 105
}

function renderCrmChart() {
  if (!crmChartRef.value) return
  const trend = crm.value.trend || []
  const categories = trend.map((t) => t.label)
  const values = trend.map((t) => Number(t.value) || 0)
  if (crmChart) crmChart.destroy()
  crmChart = new ApexCharts(crmChartRef.value, {
    series: [{ name: 'Leads', data: values }],
    chart: { type: 'area', height: chartHeight(), toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
    colors: [PURPLE],
    fill: {
      type: 'gradient',
      gradient: { shade: 'dark', type: 'vertical', opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] },
    },
    stroke: { curve: 'smooth', width: 2.5 },
    dataLabels: { enabled: false },
    xaxis: { categories, labels: { style: { fontSize: '10px', colors: 'rgba(255,255,255,0.55)', fontWeight: 600 } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { fontSize: '10px', colors: 'rgba(255,255,255,0.55)', fontWeight: 600 } } },
    grid: { borderColor: 'rgba(255,255,255,0.1)', strokeDashArray: 4 },
    tooltip: { theme: 'light' },
  })
  crmChart.render()
}

function renderListingChart() {
  if (!listingChartRef.value || !showListing.value) return
  const raw = [
    Number(listing.value.sold_listings) || 0,
    Number(listing.value.active_listings) || 0,
    Number(listing.value.pending_approval) || 0,
    Number(listing.value.expired_listings) || 0,
  ]
  const hasData = raw.some((v) => v > 0)
  const series = hasData ? raw : [1, 1, 1, 1]
  const colors = hasData ? [PURPLE_DARK, PURPLE, GOLD, WHITE_MUTED] : ['rgba(255,255,255,0.15)', 'rgba(255,255,255,0.15)', 'rgba(255,255,255,0.15)', 'rgba(255,255,255,0.15)']
  if (listingChart) listingChart.destroy()
  listingChartRef.value.innerHTML = ''
  const size = isMobileViewport.value ? 110 : 96
  listingChart = new ApexCharts(listingChartRef.value, {
    series,
    labels: ['Sold', 'Active', 'Pending', 'Expired'],
    colors,
    chart: { type: 'donut', height: size, width: size },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: false } } } },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: hasData },
    stroke: { width: 3, colors: ['rgba(42,21,72,0.6)'] },
  })
  listingChart.render()
}

function renderHrChart() {
  if (!hrChartRef.value) return
  const trend = hr.value.attendance_trend || []
  const categories = trend.map((t) => t.label)
  const present = trend.map((t) => Number(t.present) || 0)
  const absent = trend.map((t) => Number(t.absent) || 0)
  if (hrChart) hrChart.destroy()
  hrChart = new ApexCharts(hrChartRef.value, {
    series: [
      { name: 'Present', data: present },
      { name: 'Absent', data: absent },
    ],
    chart: { type: 'bar', height: chartHeight(), toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
    colors: [PURPLE, GOLD],
    plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    xaxis: { categories, labels: { style: { fontSize: '10px', colors: 'rgba(255,255,255,0.55)', fontWeight: 600 } }, axisBorder: { show: false } },
    yaxis: { labels: { style: { fontSize: '10px', colors: 'rgba(255,255,255,0.55)', fontWeight: 600 } } },
    grid: { borderColor: 'rgba(255,255,255,0.1)', strokeDashArray: 4 },
    legend: { show: true, position: 'top', fontSize: '11px', fontWeight: 600, labels: { colors: 'rgba(255,255,255,0.75)' } },
    tooltip: { theme: 'light' },
  })
  hrChart.render()
}

async function renderAllCharts() {
  if (loading.value) return
  await nextTick()
  requestAnimationFrame(() => {
    if (showCrm.value) renderCrmChart()
    if (showListing.value) renderListingChart()
    if (showHr.value) renderHrChart()
  })
}

watch([loading, crm, listing, hr, isMobileViewport, showListing], () => renderAllCharts(), { deep: true })

let resizeTimer = null
function onResize() {
  clearTimeout(resizeTimer)
  resizeTimer = setTimeout(() => renderAllCharts(), 150)
}

onMounted(() => {
  load(true).then(() => renderAllCharts())
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  clearTimeout(resizeTimer)
  crmChart?.destroy()
  listingChart?.destroy()
  hrChart?.destroy()
})
</script>
