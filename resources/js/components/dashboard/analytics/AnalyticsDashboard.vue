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
          <p class="adx-header__sub">{{ scopeLabel }} — leads, deals, listings &amp; HR</p>
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
      <!-- 1. Leads -->
      <section v-if="showLeads" class="adx-uni-band adx-uni-band--leads">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--leads">
              <iconify-icon icon="lucide:users" width="18" height="18" />
            </span>
            <div>
              <h2>Leads</h2>
              <p>Pipeline, sources, agents &amp; activity</p>
            </div>
          </div>
          <router-link to="/kanban" class="adx-uni-band__link">
            Open Leads <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>

        <div class="adx-uni-leads">
          <!-- KPI strip -->
          <div class="adx-uni-leads__kpis">
            <div class="adx-uni-kpi adx-uni-kpi--hero">
              <span class="adx-uni-kpi__label">Total leads</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.total_leads) }}</strong>
              <span class="adx-uni-kpi__sub">{{ crm.conversion_rate || 0 }}% conversion</span>
            </div>
            <div class="adx-uni-kpi">
              <span class="adx-uni-kpi__label">New</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.new_leads) }}</strong>
            </div>
            <div class="adx-uni-kpi">
              <span class="adx-uni-kpi__label">Contacted</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.contacted) }}</strong>
            </div>
            <div class="adx-uni-kpi">
              <span class="adx-uni-kpi__label">Qualified</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.qualified) }}</strong>
            </div>
            <div class="adx-uni-kpi adx-uni-kpi--warn">
              <span class="adx-uni-kpi__label">Follow-ups</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.follow_up) }}</strong>
              <span v-if="crm.follow_up_overdue" class="adx-uni-kpi__sub">{{ formatNumber(crm.follow_up_overdue) }} overdue</span>
            </div>
            <div class="adx-uni-kpi adx-uni-kpi--hot">
              <span class="adx-uni-kpi__label">Hot</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.hot) }}</strong>
              <span class="adx-uni-kpi__sub">{{ formatNumber(crm.warm) }} warm · {{ formatNumber(crm.cold) }} cold</span>
            </div>
            <div class="adx-uni-kpi adx-uni-kpi--success">
              <span class="adx-uni-kpi__label">Converted</span>
              <strong class="adx-uni-kpi__value">{{ formatNumber(crm.converted) }}</strong>
              <span class="adx-uni-kpi__sub">{{ formatNumber(crm.lost) }} lost</span>
            </div>
          </div>

          <div class="adx-uni-leads__main">
            <!-- Pipeline stages -->
            <div class="adx-uni-leads__stages">
              <p class="adx-uni-panel-title">Lead pipeline</p>
              <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
              <div v-else class="adx-uni-stage-grid">
                <div
                  v-for="pill in leadStatusPills"
                  :key="pill.label"
                  class="adx-uni-stage-pill"
                  :class="`adx-uni-stage-pill--${pill.tone}`"
                >
                  <span class="adx-uni-stage-pill__val">{{ formatNumber(pill.value) }}</span>
                  <span class="adx-uni-stage-pill__label">{{ pill.label }}</span>
                </div>
              </div>
            </div>

            <!-- Lead sources -->
            <div class="adx-uni-leads__sources">
              <p class="adx-uni-panel-title">Lead sources</p>
              <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--round" />
              <template v-else>
                <div ref="leadSourcesChartRef" class="adx-uni-chart adx-uni-chart--donut" />
                <ul v-if="leadSourceRows.length" class="adx-uni-source-list">
                  <li v-for="src in leadSourceRows.slice(0, 4)" :key="src.source">
                    <span class="adx-uni-source-list__dot" :style="{ background: src.color }" />
                    <span class="adx-uni-source-list__name">{{ src.source }}</span>
                    <strong>{{ formatNumber(src.count) }}</strong>
                  </li>
                </ul>
                <p v-else class="adx-uni-empty">No source data yet</p>
              </template>
            </div>

            <!-- Activity -->
            <div class="adx-uni-leads__activity">
              <p class="adx-uni-panel-title">Activity</p>
              <div class="adx-uni-activity-cards">
                <div class="adx-uni-activity-card">
                  <iconify-icon icon="lucide:phone-incoming" width="18" height="18" />
                  <div>
                    <strong>{{ formatNumber(crm.calls_answered) }}</strong>
                    <span>Calls answered</span>
                  </div>
                </div>
                <div class="adx-uni-activity-card">
                  <iconify-icon icon="lucide:phone-missed" width="18" height="18" />
                  <div>
                    <strong>{{ formatNumber(crm.calls_no_answer) }}</strong>
                    <span>No answer</span>
                  </div>
                </div>
                <div class="adx-uni-activity-card">
                  <iconify-icon icon="lucide:timer" width="18" height="18" />
                  <div>
                    <strong>{{ crm.avg_response_time_min || 0 }}m</strong>
                    <span>Avg response</span>
                  </div>
                </div>
                <div class="adx-uni-activity-card adx-uni-activity-card--health">
                  <iconify-icon icon="lucide:activity" width="18" height="18" />
                  <div>
                    <strong>{{ pipelineHealth }}%</strong>
                    <span>{{ pipelineHealthLabel }}</span>
                  </div>
                </div>
              </div>
              <div v-if="crm.best_closer" class="adx-uni-closer">
                <iconify-icon icon="lucide:trophy" width="16" height="16" />
                <div>
                  <span>Best closer</span>
                  <strong>{{ crm.best_closer.name }}</strong>
                </div>
                <em>{{ crm.best_closer.rate }}%</em>
              </div>
            </div>
          </div>

          <div class="adx-uni-leads__bottom">
            <!-- Funnel -->
            <div class="adx-uni-leads__funnel">
              <p class="adx-uni-panel-title">Conversion funnel</p>
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

            <!-- Trend -->
            <div class="adx-uni-leads__trend">
              <p class="adx-uni-panel-title">Leads trend — last 7 days</p>
              <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--chart" />
              <div v-else ref="leadsChartRef" class="adx-uni-chart" />
            </div>

            <!-- Agent ranking -->
            <div class="adx-uni-leads__agents">
              <p class="adx-uni-panel-title">Top agents</p>
              <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
              <ul v-else-if="leadAgents.length" class="adx-uni-agent-list">
                <li v-for="(agent, idx) in leadAgents" :key="agent.id" class="adx-uni-agent" :class="{ 'adx-uni-agent--top': idx < 3 }">
                  <span class="adx-uni-agent__rank">{{ idx + 1 }}</span>
                  <div class="adx-uni-agent__info">
                    <span class="adx-uni-agent__name">{{ agent.name }}</span>
                    <span class="adx-uni-agent__meta">{{ formatNumber(agent.leads) }} leads · {{ formatNumber(agent.converted) }} won</span>
                  </div>
                  <strong class="adx-uni-agent__rate">{{ agent.rate }}%</strong>
                </li>
              </ul>
              <p v-else class="adx-uni-empty">No agent data yet</p>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Deals -->
      <section v-if="showDeals" class="adx-uni-band adx-uni-band--deals">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--deals">
              <iconify-icon icon="lucide:handshake" width="18" height="18" />
            </span>
            <div>
              <h2>Deals</h2>
              <p>Sales pipeline &amp; revenue</p>
            </div>
          </div>
          <router-link to="/kanban_deal" class="adx-uni-band__link">
            Open Deals <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>

        <div class="adx-uni-crm">
          <div class="adx-uni-crm__hero adx-uni-crm__hero--deals">
            <p class="adx-uni-eyebrow">Total deals</p>
            <p class="adx-uni-mega">{{ formatNumber(deals.total_deals) }}</p>
            <div class="adx-uni-crm__chips">
              <span class="adx-uni-chip">{{ formatNumber(deals.primary) }} primary</span>
              <span class="adx-uni-chip">{{ formatNumber(deals.secondary) }} secondary</span>
              <span class="adx-uni-chip adx-uni-chip--gold">{{ formatNumber(deals.rental) }} rental</span>
            </div>
            <div class="adx-uni-crm__mini">
              <span><strong>{{ formatCurrency(deals.total_sale) }}</strong> sales</span>
              <span><strong>{{ formatCurrency(deals.total_commission) }}</strong> commission</span>
            </div>
          </div>

          <div class="adx-uni-crm__funnel">
            <p class="adx-uni-panel-title">Deal stages</p>
            <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
            <div v-else class="adx-uni-funnel-scroll">
              <div
                v-for="(stage, i) in dealStageRows"
                :key="`deal-${i}`"
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
              <p v-if="!dealStageRows.length" class="adx-uni-empty">No deals in pipeline yet</p>
            </div>
          </div>

          <div class="adx-uni-crm__trend">
            <p class="adx-uni-panel-title">Deals trend</p>
            <div v-if="loading" class="adx-uni-skeleton adx-uni-skeleton--chart" />
            <div v-else ref="dealsChartRef" class="adx-uni-chart" />
          </div>
        </div>
      </section>

      <!-- 3. Listings -->
      <section v-if="showListing" class="adx-uni-band adx-uni-band--listings">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--listings">
              <iconify-icon icon="lucide:building-2" width="18" height="18" />
            </span>
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
            <p class="adx-uni-panel-title">Status breakdown</p>
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
            <div class="adx-uni-callout">
              <iconify-icon icon="lucide:eye" width="22" height="22" />
              <div>
                <strong>{{ formatNumber(listing.total_views) }}</strong>
                <span>Total views</span>
              </div>
            </div>
            <div class="adx-uni-callout">
              <iconify-icon icon="lucide:inbox" width="22" height="22" />
              <div>
                <strong>{{ formatNumber(listing.inquiry_requests) }}</strong>
                <span>Inquiries</span>
              </div>
            </div>
            <div class="adx-uni-callout">
              <iconify-icon icon="lucide:badge-check" width="22" height="22" />
              <div>
                <strong>{{ listing.conversion_rate || 0 }}%</strong>
                <span>Sold rate</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 4. HR -->
      <section v-if="showHr" class="adx-uni-band adx-uni-band--hr">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--hr">
              <iconify-icon icon="lucide:users-round" width="18" height="18" />
            </span>
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
            <p class="adx-uni-panel-title">Today's presence</p>
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
const SLATE = '#94a3b8'

const CHART_AXIS = '#64748b'
const CHART_GRID = '#e2e8f0'

const FUNNEL_COLORS = [PURPLE, '#8b6fd4', PURPLE_DARK, GOLD, '#a78bfa']
const SOURCE_COLORS = [PURPLE, PURPLE_DARK, '#22c55e', GOLD, '#a78bfa', SLATE]

const { isMobileViewport } = useMobileNavigation()
const { canViewModule, scopeLabel } = useDashboardPermissions()

const showLeads = computed(() => canViewModule('crm'))
const showDeals = computed(() => canViewModule('crm'))
const showListing = computed(() => canViewModule('listing') || canViewModule('crm'))
const showHr = computed(() => canViewModule('hr') || canViewModule('crm'))

const {
  loading, error, data, dateFrom, dateTo, periodLabel,
  load, setCustomRange,
} = useAnalyticsDashboard()

const crm = computed(() => data.value?.crm || {})
const deals = computed(() => data.value?.deals || {})
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

const formatCurrency = (n) =>
  new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(Number(n) || 0)

const crmFunnelStages = computed(() => {
  const funnel = crm.value.funnel || {}
  const labels = funnel.labels || []
  const values = funnel.values || []
  const max = Math.max(...values.map((v) => Number(v) || 0), 1)
  return labels.slice(0, 6).map((label, i) => ({
    label,
    value: Number(values[i]) || 0,
    pct: Math.round(((Number(values[i]) || 0) / max) * 100),
    color: FUNNEL_COLORS[i % FUNNEL_COLORS.length],
  }))
})

const leadStatusPills = computed(() => [
  { label: 'New', value: crm.value.new_leads, tone: 'new' },
  { label: 'Contacted', value: crm.value.contacted, tone: 'contacted' },
  { label: 'Qualified', value: crm.value.qualified, tone: 'qualified' },
  { label: 'Follow-up', value: crm.value.follow_up, tone: 'follow' },
  { label: 'Negotiation', value: crm.value.negotiation, tone: 'negotiation' },
  { label: 'Converted', value: crm.value.converted, tone: 'converted' },
  { label: 'Lost', value: crm.value.lost, tone: 'lost' },
])

const leadSourceRows = computed(() => {
  const sources = crm.value.lead_sources || []
  return sources.map((s, i) => ({
    source: s.source || 'Unknown',
    count: Number(s.count) || 0,
    color: SOURCE_COLORS[i % SOURCE_COLORS.length],
  }))
})

const leadAgents = computed(() => (crm.value.agent_ranking || []).slice(0, 5))

const pipelineHealth = computed(() => {
  const total = Number(crm.value.total_leads) || 0
  if (!total) return 0
  const active = (Number(crm.value.qualified) || 0) + (Number(crm.value.hot) || 0) + (Number(crm.value.negotiation) || 0)
  return Math.min(100, Math.round((active / total) * 100))
})

const pipelineHealthLabel = computed(() => {
  const h = pipelineHealth.value
  if (h >= 70) return 'Excellent pipeline'
  if (h >= 40) return 'Healthy pipeline'
  return 'Needs attention'
})

const dealStageRows = computed(() => {
  const stages = deals.value.stages || []
  const max = Math.max(...stages.map((s) => Number(s.count) || 0), 1)
  return stages.slice(0, 6).map((s, i) => ({
    label: s.label,
    value: Number(s.count) || 0,
    pct: Math.round(((Number(s.count) || 0) / max) * 100),
    color: FUNNEL_COLORS[i % FUNNEL_COLORS.length],
  }))
})

const listingBreakdown = computed(() => {
  const items = [
    { label: 'Active', value: Number(listing.value.active_listings) || 0, color: PURPLE },
    { label: 'Sold', value: Number(listing.value.sold_listings) || 0, color: PURPLE_DARK },
    { label: 'Pending', value: Number(listing.value.pending_approval) || 0, color: GOLD },
    { label: 'Expired', value: Number(listing.value.expired_listings) || 0, color: SLATE },
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
  { label: 'Active', value: Number(hr.value.active_employees) || 0, color: PURPLE },
  { label: 'Absent', value: Number(hr.value.absent_employees) || 0, color: GOLD },
  { label: 'On leave', value: Number(hr.value.on_leave) || 0, color: SLATE },
])

function applyDateRange() {
  if (dateFrom.value && dateTo.value) {
    setCustomRange(dateFrom.value, dateTo.value)
  } else {
    load(true)
  }
}

const leadsChartRef = ref(null)
const leadSourcesChartRef = ref(null)
const dealsChartRef = ref(null)
const listingChartRef = ref(null)
const hrChartRef = ref(null)
let leadsChart = null
let leadSourcesChart = null
let dealsChart = null
let listingChart = null
let hrChart = null

function chartHeight() {
  const w = typeof window !== 'undefined' ? window.innerWidth : 1541
  if (isMobileViewport.value || w < 641) return 140
  if (w < 1280) return 120
  return 105
}

function lineChartOptions(categories, values, color, name) {
  return {
    series: [{ name, data: values }],
    chart: { type: 'area', height: chartHeight(), toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
    colors: [color],
    fill: {
      type: 'gradient',
      gradient: { shade: 'light', type: 'vertical', opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] },
    },
    stroke: { curve: 'smooth', width: 2.5 },
    dataLabels: { enabled: false },
    xaxis: {
      categories,
      labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 600 } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 600 } } },
    grid: { borderColor: CHART_GRID, strokeDashArray: 4 },
    tooltip: { theme: 'light' },
  }
}

function renderLeadsChart() {
  if (!leadsChartRef.value) return
  const trend = crm.value.trend || []
  const categories = trend.map((t) => t.label)
  const values = trend.map((t) => Number(t.value) || 0)
  if (leadsChart) leadsChart.destroy()
  leadsChart = new ApexCharts(leadsChartRef.value, lineChartOptions(categories, values, PURPLE, 'Leads'))
  leadsChart.render()
}

function renderLeadSourcesChart() {
  if (!leadSourcesChartRef.value) return
  const rows = leadSourceRows.value
  const hasData = rows.some((r) => r.count > 0)
  const series = hasData ? rows.map((r) => r.count) : [1]
  const labels = hasData ? rows.map((r) => r.source) : ['No data']
  const colors = hasData ? rows.map((r) => r.color) : ['#e2e8f0']
  if (leadSourcesChart) leadSourcesChart.destroy()
  leadSourcesChartRef.value.innerHTML = ''
  const size = isMobileViewport.value ? 100 : 88
  leadSourcesChart = new ApexCharts(leadSourcesChartRef.value, {
    series,
    labels,
    colors,
    chart: { type: 'donut', height: size, width: size },
    plotOptions: { pie: { donut: { size: '68%', labels: { show: false } } } },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: hasData, theme: 'light' },
    stroke: { width: 2, colors: ['#fff'] },
  })
  leadSourcesChart.render()
}

function renderDealsChart() {
  if (!dealsChartRef.value) return
  const trend = deals.value.trend || []
  const categories = trend.map((t) => t.label)
  const values = trend.map((t) => Number(t.value) || 0)
  if (dealsChart) dealsChart.destroy()
  dealsChart = new ApexCharts(dealsChartRef.value, lineChartOptions(categories, values, PURPLE_DARK, 'Deals'))
  dealsChart.render()
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
  const colors = hasData ? [PURPLE_DARK, PURPLE, GOLD, SLATE] : ['#e2e8f0', '#e2e8f0', '#e2e8f0', '#e2e8f0']
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
    stroke: { width: 2, colors: ['#fff'] },
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
    xaxis: {
      categories,
      labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 600 } },
      axisBorder: { show: false },
    },
    yaxis: { labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 600 } } },
    grid: { borderColor: CHART_GRID, strokeDashArray: 4 },
    legend: { show: true, position: 'top', fontSize: '11px', fontWeight: 600, labels: { colors: CHART_AXIS } },
    tooltip: { theme: 'light' },
  })
  hrChart.render()
}

async function renderAllCharts() {
  if (loading.value) return
  await nextTick()
  requestAnimationFrame(() => {
    if (showLeads.value) {
      renderLeadsChart()
      renderLeadSourcesChart()
    }
    if (showDeals.value) renderDealsChart()
    if (showListing.value) renderListingChart()
    if (showHr.value) renderHrChart()
  })
}

watch([loading, crm, deals, listing, hr, isMobileViewport, showListing], () => renderAllCharts(), { deep: true })

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
  leadsChart?.destroy()
  leadSourcesChart?.destroy()
  dealsChart?.destroy()
  listingChart?.destroy()
  hrChart?.destroy()
})
</script>
