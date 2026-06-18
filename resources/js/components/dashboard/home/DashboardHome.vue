<template>
  <div class="crm-dashboard-home" :class="{ 'crm-dashboard-home--mobile': isMobileViewport }">
    <header class="dh-header" :class="{ 'dh-header--mobile': isMobileViewport }">
      <template v-if="!isMobileViewport">
        <div class="dh-header-text">
          <p class="dh-greeting">Hello, {{ greetingName }} 👋</p>
          <p class="dh-greeting-sub">Here's your activity today, take a moment to have a look at this.</p>
        </div>
        <div class="dh-header-actions">
          <router-link to="/home" class="dh-header-analytics-link" title="Executive analytics dashboard">
            <iconify-icon icon="lucide:bar-chart-3" width="16" height="16" />
            <span>Analytics</span>
          </router-link>
          <DashboardDateRangePicker
            v-model:date-from="dateFrom"
            v-model:date-to="dateTo"
            :label="dateRangeLabel"
            picker-class="dh-header-date"
            @apply="applyDateRange"
          />
        </div>
      </template>

      <template v-else>
        <div class="dh-mob-top">
          <div class="dh-mob-top__lead">
            <button
              type="button"
              class="dh-mob-menu"
              aria-label="Open navigation menu"
              @click="toggleMobileMenu"
            >
              <iconify-icon icon="heroicons:bars-3-solid" width="22" height="22" />
            </button>
            <div class="dh-mob-greeting">
              <p class="dh-greeting">Hello, {{ greetingName }} 👋</p>
              <p class="dh-greeting-sub">Here's your activity today,</p>
            </div>
          </div>
          <div class="dh-mob-actions">
            <DashboardDateRangePicker
              v-model:date-from="dateFrom"
              v-model:date-to="dateTo"
              :label="dateRangeLabel"
              icon-only
              picker-class="dh-mob-date"
              @apply="applyDateRange"
            />
            <button
              v-if="isAdminUser"
              type="button"
              class="dh-mob-action-btn"
              aria-label="Settings"
              @click="router.push('/system-overview')"
            >
              <iconify-icon icon="lucide:settings" width="20" height="20" />
            </button>
            <NotificationBell class="dh-mob-notification" :sound-enabled="true" :browser-notifications-enabled="true" />
            <router-link to="/view-profile" class="dh-mob-avatar-link" aria-label="Profile">
              <img :src="userAvatar" alt="" class="dh-mob-avatar" />
            </router-link>
          </div>
        </div>
      </template>
    </header>

    <div class="dh-layout">
      <section class="dh-layout__metrics" ref="metricsSectionRef">
        <div class="dh-metrics-carousel">
        <article class="dh-metric-card dh-metric-card--primary">
          <div class="dh-metric-head">
            <div class="dh-metric-icon dh-metric-icon--white">
              <iconify-icon icon="flowbite:users-group-outline" width="22" height="22" />
            </div>
            <span class="dh-metric-badge">
              <iconify-icon icon="lucide:trending-up" width="11" height="11" />
              {{ trendPercent(stats.agents_change, stats.total_agents) }} %
            </span>
          </div>
          <p class="dh-metric-label">Total Agents</p>
          <div class="dh-metric-value-row">
            <p class="dh-metric-value">{{ formatNumber(stats.total_agents) }}</p>
            <p class="dh-metric-vs">Agents vs Last Month</p>
          </div>
        </article>

        <router-link to="/owners" class="dh-metric-link">
          <article class="dh-metric-card dh-metric-card--light">
            <div class="dh-metric-head">
              <div class="dh-metric-icon dh-metric-icon--soft">
                <iconify-icon icon="flowbite:users-group-outline" width="22" height="22" />
              </div>
              <span class="dh-metric-badge">
                <iconify-icon icon="lucide:trending-up" width="11" height="11" />
                {{ trendPercent(stats.owners_change, stats.owners) }} %
              </span>
            </div>
            <p class="dh-metric-label">Total Owners</p>
            <div class="dh-metric-value-row">
              <p class="dh-metric-value">{{ formatNumber(stats.owners) }}</p>
              <p class="dh-metric-vs">Owners vs Last Month</p>
            </div>
          </article>
        </router-link>

        <article class="dh-metric-card dh-metric-card--light">
          <div class="dh-metric-head">
            <div class="dh-metric-icon dh-metric-icon--soft">
              <iconify-icon icon="lucide:git-branch" width="22" height="22" />
            </div>
            <span class="dh-metric-badge">
              <iconify-icon icon="lucide:trending-up" width="11" height="11" />
              {{ trendPercent(stats.requests_change + stats.orders_change, requestsTotal) }} %
            </span>
          </div>
          <p class="dh-metric-label">Requests</p>
          <div class="dh-metric-value-row dh-metric-value-row--requests">
            <p class="dh-metric-value">{{ formatNumber(requestsTotal) }}</p>
            <div class="dh-metric-io">
              <span>Inbound : {{ formatNumber(stats.my_requests) }}</span>
              <span>Outbound : {{ formatNumber(stats.my_orders) }}</span>
            </div>
          </div>
        </article>
        </div>
        <div v-if="showMetricCarouselDots" class="dh-metrics-dots" aria-hidden="true">
          <button
            v-for="(_, idx) in metricSlideCount"
            :key="idx"
            type="button"
            class="dh-metrics-dot"
            :class="{ 'dh-metrics-dot--active': activeMetricSlide === idx }"
            @click="scrollToMetric(idx)"
          />
        </div>
      </section>

      <article class="dh-panel dh-panel--task dh-layout__task">
        <div class="dh-panel-head">
          <p class="dh-panel-title">Task Summary</p>
          <router-link to="/kanban" class="dh-panel-link dh-panel-link--accent">
            View Leads &gt;
          </router-link>
        </div>
        <div class="dh-task-cards">
          <div class="dh-task-pill">
            <p class="dh-task-pill-label">New</p>
            <p class="dh-task-pill-value">{{ formatNumber(taskSummary.new) }}</p>
          </div>
          <div class="dh-task-pill">
            <p class="dh-task-pill-label">Assigned</p>
            <p class="dh-task-pill-value">{{ formatNumber(taskSummary.assigned) }}</p>
          </div>
          <div class="dh-task-pill">
            <p class="dh-task-pill-label">Deal Won</p>
            <p class="dh-task-pill-value">{{ formatNumber(taskSummary.deal_won) }}</p>
          </div>
        </div>
      </article>

      <article class="dh-panel dh-panel--chart dh-layout__chart">
        <div class="dh-panel-head">
          <p class="dh-panel-title">Top Listing Performance</p>
          <label class="dh-chart-period">
            <iconify-icon icon="lucide:calendar" width="15" height="15" />
            <select v-model="chartPeriod" class="dh-chart-period-select" @change="reloadChart">
              <option value="monthly">Last Month</option>
              <option value="weekly">Last Week</option>
              <option value="yearly">Yearly</option>
            </select>
            <iconify-icon icon="lucide:chevrons-up-down" width="14" height="14" />
          </label>
        </div>
        <div v-if="loading" class="dh-chart-wrap dh-skeleton" />
        <div v-else ref="lineChartRef" class="dh-chart-wrap" />
      </article>

      <article class="dh-panel dh-panel--listings dh-layout__listings">
        <div class="dh-panel-head dh-listings-head">
          <p class="dh-panel-title">Listings</p>
          <router-link to="/listings/overview" class="dh-listings-link">
            All Properties
            <iconify-icon icon="lucide:chevron-right" width="14" height="14" class="dh-listings-link-icon" />
          </router-link>
        </div>
        <div v-if="loading" class="dh-listings-content dh-skeleton" />
        <div v-else class="dh-listings-content">
          <div class="dh-listings-legend">
            <div class="dh-legend-block">
              <span class="dh-legend-sq dh-legend-sq--purple" />
              <div class="dh-legend-block-text">
                <strong>{{ formatNumber(listingsRing.sold_out) }}</strong>
                <span>Sold Out</span>
              </div>
            </div>
            <div class="dh-legend-block">
              <span class="dh-legend-sq dh-legend-sq--green" />
              <div class="dh-legend-block-text">
                <strong>{{ formatNumber(listingsRing.active) }}</strong>
                <span>Active</span>
              </div>
            </div>
            <div class="dh-legend-block">
              <span class="dh-legend-sq dh-legend-sq--orange" />
              <div class="dh-legend-block-text">
                <strong>{{ formatNumber(listingsRing.inactive) }}</strong>
                <span>Inactive</span>
              </div>
            </div>
          </div>
          <div class="dh-donut-chart-wrap">
            <div ref="donutChartRef" class="dh-donut-chart" />
            <div class="dh-donut-center">
              <p class="dh-donut-center-value">{{ formatNumber(listingsTotal) }}</p>
              <p class="dh-donut-center-label">Total Listings</p>
            </div>
          </div>
        </div>
      </article>

      <article class="dh-panel dh-panel--schedule dh-layout__schedule">
        <div class="dh-schedule-head">
          <p class="dh-schedule-date">{{ schedule.label || todayLabel }}</p>
          <div class="dh-schedule-nav">
            <button type="button" aria-label="Previous"><iconify-icon icon="lucide:chevron-left" width="16" /></button>
            <button type="button" aria-label="Next"><iconify-icon icon="lucide:chevron-right" width="16" /></button>
          </div>
        </div>
        <div class="dh-schedule-days">
          <div
            v-for="day in weekDays"
            :key="day.key"
            class="dh-schedule-day"
            :class="{ 'dh-schedule-day--active': day.isToday }"
          >
            <span class="dh-schedule-day-num">{{ day.num }}</span>
            {{ day.label }}
          </div>
        </div>
        <div class="dh-schedule-timeline">
          <template v-if="schedule.items?.length">
            <div v-for="item in schedule.items" :key="item.id" class="dh-schedule-item">
              <span class="dh-schedule-time">{{ item.time }}</span>
              <div class="dh-schedule-card">
                <div class="dh-schedule-card-body">
                  <p class="dh-schedule-card-title">{{ item.title }}</p>
                  <button type="button" class="dh-schedule-card-btn">Mark Complete</button>
                </div>
                <img
                  v-if="item.user?.avatar"
                  :src="avatarUrl(item.user.avatar)"
                  alt=""
                  class="dh-agent-avatar dh-agent-avatar--sm"
                />
              </div>
            </div>
          </template>
          <div v-else class="dh-empty dh-empty--compact">
            <p class="dh-empty-text">No events scheduled for today.</p>
          </div>
        </div>
        <router-link to="/my-viewings" class="dh-schedule-add">
          <iconify-icon icon="lucide:plus" width="16" height="16" />
          Add New Schedule
        </router-link>
      </article>

      <article class="dh-panel dh-panel--agents dh-layout__agents">
        <div class="dh-panel-head">
          <p class="dh-panel-title">Top Agent Performance</p>
          <router-link to="/users" class="dh-panel-link dh-panel-link--accent">View Agents &gt;</router-link>
        </div>
        <div v-if="loading" class="dh-agents-list">
          <div v-for="i in 4" :key="i" class="dh-agent-row">
            <div class="dh-skeleton dh-skeleton--avatar" />
            <div class="flex-grow-1">
              <div class="dh-skeleton mb-1" style="height: 12px; width: 60%" />
              <div class="dh-skeleton" style="height: 10px; width: 40%" />
            </div>
          </div>
        </div>
        <div v-else-if="topAgents.length" class="dh-agents-list">
          <div
            v-for="(agent, idx) in topAgents"
            :key="agent.id"
            class="dh-agent-row"
            :class="{ 'dh-agent-row--active': idx === 2 }"
          >
            <img :src="avatarUrl(agent.avatar)" :alt="agent.name" class="dh-agent-avatar" />
            <div class="dh-agent-info">
              <p class="dh-agent-name">{{ agent.name }}</p>
              <p class="dh-agent-office">{{ agentOfficeLine(agent) }}</p>
            </div>
            <span class="dh-agent-role">{{ formatRole(agent.role) }}</span>
            <button type="button" class="dh-agent-menu" aria-label="Options">
              <iconify-icon icon="lucide:more-vertical" width="18" height="18" />
            </button>
          </div>
        </div>
        <div v-else class="dh-empty dh-empty--compact">
          <p class="dh-empty-title">No agent data</p>
        </div>
      </article>

      <article class="dh-panel dh-panel--announce dh-layout__announce">
        <div class="dh-panel-head">
          <p class="dh-panel-title">Announcements</p>
        </div>
        <div class="dh-empty dh-empty--bell">
          <iconify-icon icon="lucide:bell-off" class="dh-empty-icon" width="48" height="48" />
          <p class="dh-empty-title">No Recent Announcements</p>
          <p class="dh-empty-text">Looks quiet for now. Check back later for new company announcements and alerts.</p>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount, onMounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import ApexCharts from 'apexcharts'
import DashboardDateRangePicker from '@/components/dashboard/home/DashboardDateRangePicker.vue'
import NotificationBell from '@/components/NotificationBell.vue'
import userAvatarPlaceholder from '@/assets/images/users/user1.png'
import { useMobileNavigation } from '@/composables/useMobileNavigation.js'
import { useDashboardHome } from '@/composables/useDashboardHome.js'

const router = useRouter()
const { isMobileViewport, toggleMobileMenu } = useMobileNavigation()

const {
  loading,
  stats,
  listingsChart,
  performanceChart,
  listingsRing,
  taskSummary,
  topAgents,
  schedule,
  chartPeriod,
  dateFrom,
  dateTo,
  dateRangeLabel,
  greetingName,
  user,
  isAdminUser,
  formatNumber,
  trendPercent,
  avatarUrl,
  reloadChart,
  applyDateRange,
  loadAll,
} = useDashboardHome()

const requestsTotal = computed(
  () => (Number(stats.value.my_requests) || 0) + (Number(stats.value.my_orders) || 0)
)

const listingsTotal = computed(() => {
  const s = listingsRing.value
  const sum =
    (Number(s.sold_out) || 0) + (Number(s.active) || 0) + (Number(s.inactive) || 0)
  return sum || Number(s.total) || 0
})

const todayLabel = computed(() => {
  const d = new Date()
  return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })
})

const metricsSectionRef = ref(null)
const activeMetricSlide = ref(0)
const metricSlideCount = 3

const userAvatar = computed(() => {
  const raw = user.value?.avatar
  return avatarUrl(raw) || userAvatarPlaceholder
})

const lineChartRef = ref(null)
const donutChartRef = ref(null)
let lineChart = null
let donutChart = null
let layoutResizeObserver = null

const MOBILE_CHART_HEIGHT = 200
const STACKED_CHART_HEIGHT = 240

const isStackedHomeLayout = ref(false)
const isMetricCarouselLayout = ref(false)

const showMetricCarouselDots = computed(
  () => isMobileViewport.value || (isStackedHomeLayout.value && isMetricCarouselLayout.value)
)

function updateStackedHomeLayout() {
  if (typeof window === 'undefined') return
  isStackedHomeLayout.value =
    isMobileViewport.value || window.matchMedia('(max-width: 1540px)').matches
  isMetricCarouselLayout.value =
    isMobileViewport.value || window.matchMedia('(max-width: 899px)').matches
}

function onDashboardHomeResize() {
  updateStackedHomeLayout()
  resizeCharts()
}

const chartHeightFrom = (el, fallback = 200) => {
  if (isMobileViewport.value) return MOBILE_CHART_HEIGHT
  if (isStackedHomeLayout.value) return STACKED_CHART_HEIGHT
  if (!el?.parentElement) return fallback
  const h = el.parentElement.clientHeight
  return h > 60 && h < 400 ? Math.floor(h) : fallback
}

const resizeCharts = () => {
  if (lineChart && lineChartRef.value) {
    const h = chartHeightFrom(lineChartRef.value, 220)
    lineChart.updateOptions({ chart: { height: h } }, false, false)
  }
  if (donutChart && donutChartRef.value) {
    const size = donutSizePx()
    donutChart.updateOptions({ chart: { height: size, width: size } }, false, false)
  }
}

const donutSizePx = () => {
  const wrap = donutChartRef.value?.parentElement
  const stacked = isMobileViewport.value || isStackedHomeLayout.value
  if (!wrap) return stacked ? 112 : 140
  const w = wrap.clientWidth || 140
  const h = wrap.clientHeight || 140
  const cap = stacked ? 112 : 160
  const floor = stacked ? 96 : 100
  return Math.max(floor, Math.min(Math.floor(Math.min(w, h)), cap))
}

const weekDays = computed(() => {
  const today = new Date()
  const start = new Date(today)
  start.setDate(today.getDate() - 3)
  const labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    return {
      key: d.toISOString(),
      num: d.getDate(),
      label: labels[d.getDay()].slice(0, 3),
      isToday: d.toDateString() === today.toDateString(),
    }
  })
})

const formatRole = (role) => {
  if (!role) return 'Sales'
  const r = String(role).replace(/_/g, ' ')
  if (r.toLowerCase().includes('admin')) return 'Admin'
  if (r.toLowerCase().includes('sales')) return 'Sales'
  return r.replace(/\b\w/g, (c) => c.toUpperCase())
}

const agentOfficeLine = (agent) => {
  const office = agent.office || 'Head Office'
  const dept = agent.department || office
  return `${office} | ${dept}`
}

const renderLineChart = () => {
  const perf = performanceChart.value
  if (!lineChartRef.value || !perf?.values?.length) return
  if (lineChart) lineChart.destroy()
  const chartH = chartHeightFrom(lineChartRef.value, 220)
  const points = perf.points || []

  lineChart = new ApexCharts(lineChartRef.value, {
    series: [{ name: 'Listings', data: perf.values }],
    chart: {
      type: 'area',
      height: chartH,
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'Inter, system-ui, sans-serif',
    },
    colors: ['#f59e0b'],
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.02,
        stops: [0, 90, 100],
        colorStops: [
          { offset: 0, color: '#ffcc66', opacity: 0.55 },
          { offset: 100, color: '#ffffff', opacity: 0 },
        ],
      },
    },
    stroke: {
      curve: 'smooth',
      width: 2,
      colors: ['#f59e0b'],
      dashArray: 6,
    },
    dataLabels: { enabled: false },
    xaxis: {
      categories: perf.categories,
      title: {
        text: perf.x_title || '(Agents)',
        style: { fontSize: '11px', fontWeight: 500, color: '#9ca3af' },
        offsetY: 4,
      },
      labels: { style: { fontSize: '11px', colors: '#9ca3af' } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      min: 0,
      max: perf.y_max || 250,
      tickAmount: 5,
      labels: { style: { fontSize: '11px', colors: '#9ca3af' } },
    },
    grid: {
      borderColor: '#e8eaef',
      strokeDashArray: 4,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: true } },
      padding: { left: 12, right: 12, top: 8, bottom: 4 },
    },
    tooltip: {
      enabled: true,
      custom({ dataPointIndex }) {
        const p = points[dataPointIndex] || {}
        const agents = p.agents_label ?? perf.categories[dataPointIndex]
        const listings = p.listings ?? perf.values[dataPointIndex]
        return `<div class="dh-chart-tooltip">
          <p class="dh-chart-tooltip-title">${agents} Agents</p>
          <p class="dh-chart-tooltip-sub">${formatNumber(listings)} Listings</p>
        </div>`
      },
    },
    markers: {
      size: 0,
      hover: {
        size: 6,
        sizeOffset: 0,
        strokeColors: '#f59e0b',
        strokeWidth: 2,
        fillColors: '#fff',
      },
    },
  })
  lineChart.render()
  resizeCharts()
}

const renderDonutChart = () => {
  if (!donutChartRef.value) return
  const s = listingsRing.value
  const raw = [Number(s.sold_out) || 0, Number(s.active) || 0, Number(s.inactive) || 0]
  const hasData = raw.some((v) => v > 0)
  const series = hasData ? raw : [1, 1, 1]
  const size = donutSizePx()
  if (donutChart) donutChart.destroy()
  donutChartRef.value.innerHTML = ''
  donutChart = new ApexCharts(donutChartRef.value, {
    series,
    labels: ['Sold Out', 'Active', 'Inactive'],
    colors: hasData ? ['#7c5cbf', '#84cc16', '#ff9f43'] : ['#e8e4ef', '#e8e4ef', '#e8e4ef'],
    chart: {
      type: 'donut',
      height: size,
      width: size,
      fontFamily: 'Inter, system-ui, sans-serif',
      animations: { enabled: true, speed: 400 },
    },
    plotOptions: {
      pie: {
        expandOnClick: false,
        startAngle: 0,
        endAngle: 360,
        donut: {
          size: '70%',
          labels: { show: false },
        },
      },
    },
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: false },
    stroke: { show: true, width: 2, colors: ['#fff'] },
  })
  donutChart.render()
}

watch([loading, performanceChart], async () => {
  if (!loading.value) {
    await nextTick()
    renderLineChart()
  }
})

watch([loading, listingsRing], async () => {
  if (!loading.value) {
    await nextTick()
    requestAnimationFrame(() => renderDonutChart())
  }
})

watch(isMobileViewport, async () => {
  updateStackedHomeLayout()
  if (!loading.value) {
    await nextTick()
    requestAnimationFrame(() => {
      renderDonutChart()
      resizeCharts()
    })
  }
})

watch(isMetricCarouselLayout, async () => {
  if (!loading.value) {
    await nextTick()
    requestAnimationFrame(resizeCharts)
  }
})

function updateMetricSlideFromScroll() {
  const el = metricsSectionRef.value?.querySelector('.dh-metrics-carousel')
  if (!el) return
  const card = el.querySelector('.dh-metric-card, .dh-metric-link')
  const cardW = card?.getBoundingClientRect().width || 1
  const gap = 12
  activeMetricSlide.value = Math.min(
    metricSlideCount - 1,
    Math.max(0, Math.round(el.scrollLeft / (cardW + gap)))
  )
}

function scrollToMetric(index) {
  const el = metricsSectionRef.value?.querySelector('.dh-metrics-carousel')
  const card = el?.querySelector('.dh-metric-card, .dh-metric-link')
  if (!el || !card) return
  const gap = 12
  el.scrollTo({ left: index * (card.offsetWidth + gap), behavior: 'smooth' })
  activeMetricSlide.value = index
}

onMounted(() => {
  loadAll()
  updateStackedHomeLayout()
  window.addEventListener('resize', onDashboardHomeResize, { passive: true })
  const metricsEl = metricsSectionRef.value?.querySelector('.dh-metrics-carousel')
  metricsEl?.addEventListener('scroll', updateMetricSlideFromScroll, { passive: true })
  if (typeof ResizeObserver === 'undefined') return
  layoutResizeObserver = new ResizeObserver(() => resizeCharts())
  const root = document.querySelector('.crm-dashboard-home .dh-layout')
  if (root) layoutResizeObserver.observe(root)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onDashboardHomeResize)
  const metricsEl = metricsSectionRef.value?.querySelector('.dh-metrics-carousel')
  metricsEl?.removeEventListener('scroll', updateMetricSlideFromScroll)
  layoutResizeObserver?.disconnect()
  if (lineChart) lineChart.destroy()
  if (donutChart) donutChart.destroy()
})
</script>
