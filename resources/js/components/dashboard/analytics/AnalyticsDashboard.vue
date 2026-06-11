<template>
  <div
    class="crm-dashboard-home crm-dashboard-home--analytics crm-dashboard-home--unified"
    :class="{ 'crm-dashboard-home--mobile': isMobileViewport }"
  >
    <header class="dh-header" :class="{ 'dh-header--mobile': isMobileViewport }">
      <template v-if="!isMobileViewport">
        <div class="dh-header-text">
          <p class="dh-greeting">Hello, {{ greetingName }} 👋</p>
          <p class="dh-greeting-sub">Leads, listings & HR — one unified dashboard.</p>
        </div>
        <div class="dh-header-actions">
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
          <div class="dh-mob-greeting">
            <p class="dh-greeting">Hello, {{ greetingName }} 👋</p>
            <p class="dh-greeting-sub">Unified dashboard</p>
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
          </div>
        </div>
      </template>
    </header>

    <DashboardModuleShortcuts v-if="isMobileViewport" class="dh-module-shortcuts--mob" />

    <div class="dh-analytics-period-bar">
      <button
        v-for="p in periodPresets"
        :key="p.id"
        type="button"
        class="dh-date-range-preset"
        :class="{ 'dh-date-range-preset--active': period === p.id }"
        @click="setPeriod(p.id)"
      >
        {{ p.label }}
      </button>
    </div>

    <div v-if="error" class="dh-analytics-error">
      <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
      <span>{{ error }}</span>
      <button type="button" @click="load(true)">Retry</button>
    </div>

    <div class="dh-unified-dashboard">
      <!-- Part 1: Leads & CRM -->
      <section v-if="canViewModule('crm')" class="dh-unified-section dh-unified-section--leads">
        <div class="dh-unified-section-head">
          <h2 class="dh-unified-section-title">Leads & CRM</h2>
          <router-link to="/kanban" class="dh-panel-link dh-panel-link--accent">Open CRM &gt;</router-link>
        </div>

        <div class="dh-layout dh-layout--unified-analytics dh-layout--section-leads">
          <section class="dh-layout__ua-metrics">
            <div class="dh-metrics-carousel">
              <article class="dh-metric-card dh-metric-card--primary">
                <div class="dh-metric-head">
                  <div class="dh-metric-icon dh-metric-icon--white">
                    <iconify-icon icon="lucide:users" width="22" height="22" />
                  </div>
                  <span class="dh-metric-badge">
                    <iconify-icon icon="lucide:trending-up" width="11" height="11" />
                    {{ crm.conversion_rate }}%
                  </span>
                </div>
                <p class="dh-metric-label">Total Leads</p>
                <div class="dh-metric-value-row dh-metric-value-row--stacked">
                  <p class="dh-metric-value">{{ formatNumber(crm.total_leads) }}</p>
                  <p class="dh-metric-vs">{{ formatNumber(crm.converted) }} converted</p>
                </div>
              </article>
            </div>
          </section>

          <article class="dh-panel dh-panel--task dh-layout__ua-pipeline">
            <div class="dh-panel-head">
              <p class="dh-panel-title">Pipeline</p>
              <span class="dh-chart-period dh-chart-period--static">{{ periodLabel }}</span>
            </div>
            <div class="dh-task-cards dh-task-cards--compact">
              <div v-for="pill in crmTaskPills" :key="pill.label" class="dh-task-pill">
                <p class="dh-task-pill-label">{{ pill.label }}</p>
                <p class="dh-task-pill-value">{{ pill.value }}</p>
              </div>
            </div>
          </article>

          <article class="dh-panel dh-panel--sales-stats dh-layout__ua-funnel">
            <div class="dh-panel-head">
              <p class="dh-panel-title">Sales Performance</p>
              <span class="dh-chart-period dh-chart-period--static">{{ periodLabel }}</span>
            </div>
            <div v-if="loading" class="dh-sales-stats-grid dh-skeleton" style="min-height: 120px" />
            <div v-else class="dh-sales-stats-grid">
              <div class="dh-sales-stat dh-sales-stat--primary">
                <div class="dh-sales-stat-icon">
                  <iconify-icon icon="lucide:banknote" width="20" height="20" />
                </div>
                <p class="dh-sales-stat-label">Total Sale</p>
                <p class="dh-sales-stat-value">{{ formatMoney(crm.total_sale) }}</p>
              </div>
              <div class="dh-sales-stat">
                <div class="dh-sales-stat-icon dh-sales-stat-icon--green">
                  <iconify-icon icon="lucide:percent" width="20" height="20" />
                </div>
                <p class="dh-sales-stat-label">Total Commission</p>
                <p class="dh-sales-stat-value">{{ formatMoney(crm.total_commission) }}</p>
              </div>
            </div>
          </article>

          <div class="dh-layout__ua-donut">
            <AnalyticsDonutPanel
              title="Lead Temperature"
              :loading="loading"
              :legend="crmDonutLegend"
              :series="crmDonutSeries"
              :center-value="formatNumber(crm.total_leads)"
              center-label="Total Leads"
            />
          </div>

          <article class="dh-panel dh-panel--schedule dh-layout__ua-alerts">
            <div class="dh-schedule-head">
              <p class="dh-schedule-date">Alerts & Insights</p>
            </div>
            <ul v-if="crmAlerts.length" class="dh-insights-list dh-insights-list--compact">
              <li
                v-for="(item, idx) in crmAlerts"
                :key="idx"
                class="dh-insight-item"
                :class="item.tone ? `dh-insight-item--${item.tone}` : ''"
              >
                <strong v-if="item.title">{{ item.title }}</strong>
                {{ item.text }}
              </li>
            </ul>
            <div v-else class="dh-empty dh-empty--compact">
              <p class="dh-empty-text">No alerts for this period.</p>
            </div>
            <div v-if="crm.best_closer" class="dh-closer-mini">
              <span>Best closer</span>
              <strong>{{ crm.best_closer.name }}</strong>
              <em>{{ crm.best_closer.rate }}%</em>
            </div>
          </article>

          <article class="dh-panel dh-layout__ua-analysis">
            <div class="dh-panel-head">
              <p class="dh-panel-title">Lead Analysis</p>
              <span class="dh-chart-period dh-chart-period--static">{{ periodLabel }}</span>
            </div>
            <div class="dh-analytics-kpi-grid dh-analytics-kpi-grid--compact">
              <div v-for="k in crmAllKpis" :key="k.label" class="dh-status-cell">
                <span>{{ k.label }}</span>
                <strong>{{ k.value }}</strong>
              </div>
            </div>
            <div v-if="crmAgents.length" class="dh-agents-list dh-agents-list--compact">
              <div v-for="(agent, idx) in crmAgents.slice(0, 5)" :key="agent.id" class="dh-agent-row">
                <div class="dh-agent-rank">{{ idx + 1 }}</div>
                <div class="dh-agent-info">
                  <p class="dh-agent-name">{{ agent.name }}</p>
                  <p class="dh-agent-office">{{ agent.subtitle }}</p>
                </div>
                <span class="dh-agent-role">{{ agent.role }}</span>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- Part 2: Listings -->
      <section v-if="showListing" class="dh-unified-section dh-unified-section--listings">
        <div class="dh-unified-section-head">
          <h2 class="dh-unified-section-title">Listings</h2>
          <router-link to="/alllisting" class="dh-panel-link dh-panel-link--accent">View Listings &gt;</router-link>
        </div>

        <div class="dh-layout dh-layout--unified-analytics dh-layout--section-listings">
          <section class="dh-layout__ua-metrics">
            <div class="dh-metrics-carousel">
              <article class="dh-metric-card dh-metric-card--light">
                <div class="dh-metric-head">
                  <div class="dh-metric-icon dh-metric-icon--soft">
                    <iconify-icon icon="lucide:building-2" width="22" height="22" />
                  </div>
                  <span class="dh-metric-badge">{{ listing.conversion_rate }}%</span>
                </div>
                <p class="dh-metric-label">Total Listings</p>
                <div class="dh-metric-value-row dh-metric-value-row--stacked">
                  <p class="dh-metric-value">{{ formatNumber(listing.total_listings) }}</p>
                  <p class="dh-metric-vs">{{ formatNumber(listing.active_listings) }} active</p>
                </div>
              </article>
              <article class="dh-metric-card dh-metric-card--light">
                <div class="dh-metric-head">
                  <div class="dh-metric-icon dh-metric-icon--soft">
                    <iconify-icon icon="lucide:eye" width="22" height="22" />
                  </div>
                </div>
                <p class="dh-metric-label">Total Views</p>
                <div class="dh-metric-value-row dh-metric-value-row--stacked">
                  <p class="dh-metric-value">{{ formatNumber(listing.total_views) }}</p>
                  <p class="dh-metric-vs">{{ formatNumber(listing.inquiry_requests) }} inquiries</p>
                </div>
              </article>
            </div>
          </section>

          <article class="dh-panel dh-layout__ua-analysis">
            <div class="dh-panel-head">
              <p class="dh-panel-title">Listing Performance</p>
              <span class="dh-chart-period dh-chart-period--static">{{ periodLabel }}</span>
            </div>
            <div class="dh-analytics-kpi-grid dh-analytics-kpi-grid--compact">
              <div v-for="k in listingAllKpis" :key="k.label" class="dh-status-cell">
                <span>{{ k.label }}</span>
                <strong>{{ k.value }}</strong>
              </div>
            </div>
            <div v-if="listingAgents.length" class="dh-agents-list dh-agents-list--compact">
              <div v-for="(item, idx) in listingAgents.slice(0, 5)" :key="item.id" class="dh-agent-row">
                <div class="dh-agent-rank">{{ idx + 1 }}</div>
                <div class="dh-agent-info">
                  <p class="dh-agent-name">{{ item.name }}</p>
                  <p class="dh-agent-office">{{ item.subtitle }}</p>
                </div>
                <span class="dh-agent-role">{{ item.role }}</span>
              </div>
            </div>
            <div v-else-if="listingAlerts.length" class="dh-insights-list dh-insights-list--compact">
              <li
                v-for="(item, idx) in listingAlerts"
                :key="idx"
                class="dh-insight-item"
                :class="item.tone ? `dh-insight-item--${item.tone}` : ''"
              >
                {{ item.text }}
              </li>
            </div>
          </article>
        </div>
      </section>

      <!-- Part 3: HR -->
      <section v-if="showHr" class="dh-unified-section dh-unified-section--hr">
        <div class="dh-unified-section-head">
          <h2 class="dh-unified-section-title">HR</h2>
          <router-link to="/hr" class="dh-panel-link dh-panel-link--accent">Open HR &gt;</router-link>
        </div>

        <div class="dh-layout dh-layout--unified-analytics dh-layout--section-hr">
          <section class="dh-layout__ua-metrics">
            <div class="dh-metrics-carousel">
              <article class="dh-metric-card dh-metric-card--light">
                <div class="dh-metric-head">
                  <div class="dh-metric-icon dh-metric-icon--soft">
                    <iconify-icon icon="lucide:briefcase" width="22" height="22" />
                  </div>
                  <span class="dh-metric-badge">{{ hr.productivity_score }}%</span>
                </div>
                <p class="dh-metric-label">Employees</p>
                <div class="dh-metric-value-row dh-metric-value-row--stacked">
                  <p class="dh-metric-value">{{ formatNumber(hr.total_employees) }}</p>
                  <p class="dh-metric-vs">{{ formatNumber(hr.active_employees) }} active</p>
                </div>
              </article>
              <article class="dh-metric-card dh-metric-card--light">
                <div class="dh-metric-head">
                  <div class="dh-metric-icon dh-metric-icon--soft">
                    <iconify-icon icon="lucide:calendar-clock" width="22" height="22" />
                  </div>
                </div>
                <p class="dh-metric-label">On Leave</p>
                <div class="dh-metric-value-row dh-metric-value-row--stacked">
                  <p class="dh-metric-value">{{ formatNumber(hr.on_leave) }}</p>
                  <p class="dh-metric-vs">{{ formatNumber(hr.vacation_requests) }} requests</p>
                </div>
              </article>
            </div>
          </section>

          <article class="dh-panel dh-layout__ua-analysis">
            <div class="dh-panel-head">
              <p class="dh-panel-title">HR Analysis</p>
              <span class="dh-chart-period dh-chart-period--static">{{ periodLabel }}</span>
            </div>
            <div class="dh-analytics-kpi-grid dh-analytics-kpi-grid--compact">
              <div v-for="k in hrAllKpis" :key="k.label" class="dh-status-cell">
                <span>{{ k.label }}</span>
                <strong>{{ k.value }}</strong>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import DashboardDateRangePicker from '@/components/dashboard/home/DashboardDateRangePicker.vue'
import DashboardModuleShortcuts from '@/components/dashboard/home/DashboardModuleShortcuts.vue'
import AnalyticsDonutPanel from './AnalyticsDonutPanel.vue'
import { useMobileNavigation } from '@/composables/useMobileNavigation.js'
import { useAnalyticsDashboard } from '@/composables/useAnalyticsDashboard.js'
import { useDashboardPermissions } from '@/composables/useDashboardPermissions.js'
import { parseToDate } from '@/composables/useAdvancedDateModel.js'

const { isMobileViewport } = useMobileNavigation()
const { canViewModule } = useDashboardPermissions()
const showListing = computed(() => canViewModule('listing') || canViewModule('crm'))
const showHr = computed(() => canViewModule('hr') || canViewModule('crm'))

const {
  loading, error, data, period, dateFrom, dateTo, periodLabel,
  load, setPeriod, setCustomRange,
} = useAnalyticsDashboard()

const periodPresets = [
  { id: 'today', label: 'Today' },
  { id: 'weekly', label: 'Weekly' },
  { id: 'monthly', label: 'Monthly' },
  { id: 'yearly', label: 'Yearly' },
]

const crm = computed(() => data.value?.crm || {})
const listing = computed(() => data.value?.listing || {})
const hr = computed(() => data.value?.hr || {})
const aiInsights = computed(() => data.value?.ai_insights || [])

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
const formatMoney = (n) => `AED ${formatNumber(n)}`

function applyDateRange() {
  if (dateFrom.value && dateTo.value) {
    setCustomRange(dateFrom.value, dateTo.value)
  } else {
    load(true)
  }
}

function kpi(label, value) {
  return { label, value }
}

const crmAllKpis = computed(() => [
  kpi('Total leads', formatNumber(crm.value.total_leads)),
  kpi('New leads', formatNumber(crm.value.new_leads)),
  kpi('Contacted', formatNumber(crm.value.contacted)),
  kpi('No answer', formatNumber(crm.value.no_answer)),
  kpi('Follow-up', formatNumber(crm.value.follow_up)),
  kpi('Qualified', formatNumber(crm.value.qualified)),
  kpi('Cold', formatNumber(crm.value.cold)),
  kpi('Warm', formatNumber(crm.value.warm)),
  kpi('Hot', formatNumber(crm.value.hot)),
  kpi('Negotiation', formatNumber(crm.value.negotiation)),
  kpi('Converted', formatNumber(crm.value.converted)),
  kpi('Lost', formatNumber(crm.value.lost)),
  kpi('Conversion rate', `${crm.value.conversion_rate}%`),
  kpi('Revenue', formatMoney(crm.value.revenue_from_leads)),
  kpi('Avg response', `${crm.value.avg_response_time_min} min`),
  kpi('Calls answered', formatNumber(crm.value.calls_answered)),
  kpi('Calls no answer', formatNumber(crm.value.calls_no_answer)),
  kpi('Follow-up overdue', formatNumber(crm.value.follow_up_overdue)),
])

const crmTaskPills = computed(() => [
  { label: 'New', value: formatNumber(crm.value.new_leads) },
  { label: 'Contacted', value: formatNumber(crm.value.contacted) },
  { label: 'Qualified', value: formatNumber(crm.value.qualified) },
  { label: 'Follow-up', value: formatNumber(crm.value.follow_up) },
  { label: 'Converted', value: formatNumber(crm.value.converted) },
  { label: 'Lost', value: formatNumber(crm.value.lost) },
])

const crmDonutLegend = computed(() => [
  { label: 'Cold', value: formatNumber(crm.value.cold), color: 'purple' },
  { label: 'Warm', value: formatNumber(crm.value.warm), color: 'green' },
  { label: 'Hot', value: formatNumber(crm.value.hot), color: 'orange' },
])
const crmDonutSeries = computed(() => [crm.value.cold, crm.value.warm, crm.value.hot])

const crmAgents = computed(() =>
  (crm.value.agent_ranking || []).map((a) => ({
    id: a.id,
    name: a.name,
    subtitle: `${formatNumber(a.leads)} leads · ${formatNumber(a.converted)} won`,
    role: `${a.rate}%`,
  })),
)

const listingAllKpis = computed(() => [
  kpi('Total listings', formatNumber(listing.value.total_listings)),
  kpi('Active', formatNumber(listing.value.active_listings)),
  kpi('Pending', formatNumber(listing.value.pending_approval)),
  kpi('Sold', formatNumber(listing.value.sold_listings)),
  kpi('Expired', formatNumber(listing.value.expired_listings)),
  kpi('Total views', formatNumber(listing.value.total_views)),
  kpi('Inquiries', formatNumber(listing.value.inquiry_requests)),
  kpi('Viewings', formatNumber(listing.value.viewing_appointments)),
  kpi('Conversion', `${listing.value.conversion_rate}%`),
])

const listingAgents = computed(() =>
  (listing.value.top_listings || []).map((l, i) => ({
    id: l.id || i,
    name: l.title,
    subtitle: `${formatMoney(l.price)} · ${l.views} views`,
    role: l.status,
  })),
)

const hrAllKpis = computed(() => [
  kpi('Total employees', formatNumber(hr.value.total_employees)),
  kpi('Active', formatNumber(hr.value.active_employees)),
  kpi('Late', formatNumber(hr.value.late_employees)),
  kpi('Absent', formatNumber(hr.value.absent_employees)),
  kpi('On leave', formatNumber(hr.value.on_leave)),
  kpi('Vacation req.', formatNumber(hr.value.vacation_requests)),
  kpi('Productivity', `${hr.value.productivity_score}%`),
])

const crmAlerts = computed(() => {
  const items = []
  if (crm.value.follow_up_overdue > 0) {
    items.push({ title: 'Overdue', text: `${formatNumber(crm.value.follow_up_overdue)} leads need follow-up`, tone: 'warning' })
  }
  if (crm.value.hot > 0) {
    items.push({ title: 'Hot', text: `${formatNumber(crm.value.hot)} hot leads today`, tone: 'positive' })
  }
  ;(aiInsights.value || []).forEach((i) => items.push({ text: i.text, tone: i.tone }))
  return items.slice(0, 4)
})

const listingAlerts = computed(() => {
  const items = []
  if (listing.value.pending_approval > 0) {
    items.push({ text: `${formatNumber(listing.value.pending_approval)} listings pending approval`, tone: 'neutral' })
  }
  return items
})

onMounted(() => load(true))
</script>

<style scoped>
.dh-agent-rank {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--dh-purple-100, #ede8f7);
  color: var(--dh-purple-700, #5b3d8f);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dh-unified-dashboard {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.dh-unified-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.dh-unified-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0 0.25rem;
}

.dh-unified-section-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.95);
  letter-spacing: -0.02em;
}

.dh-layout--section-leads,
.dh-layout--section-listings,
.dh-layout--section-hr {
  margin-top: 0;
}
</style>
