<template>
  <section id="section-crm" class="ap-section ap-section--crm">
    <header class="ap-section__head">
      <div class="ap-section__label">
        <div class="ap-section__icon">
          <iconify-icon icon="lucide:target" width="18" height="18" />
        </div>
        <div>
          <h2 class="ap-section__title">Leads & CRM</h2>
          <p class="ap-section__desc">Conversion, agents, sources & pipeline insights</p>
        </div>
      </div>
      <router-link to="/kanban" class="ap-section__link">
        Open CRM
        <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
      </router-link>
    </header>

    <div v-if="loading" class="ap-grid ap-grid--kpis-wide">
      <div v-for="i in 8" :key="i" class="ap-skeleton" style="min-height: 110px" />
    </div>
    <div v-else class="ap-grid ap-grid--kpis-wide">
      <ApKpiCard
        label="Total Leads"
        :value="crm.total_leads"
        icon="lucide:users"
        variant="hero"
        :badge="`${crm.conversion_rate || 0}%`"
        badge-icon="lucide:trending-up"
        :sparkline="trendValues"
        :subtitle="`${formatNumber(crm.converted)} converted`"
      />
      <ApKpiCard
        label="Conversion Rate"
        :value="crm.conversion_rate"
        suffix="%"
        format="percent"
        icon="lucide:percent"
        variant="accent"
        :subtitle="`+${formatNumber(crm.converted)} deals`"
      />
      <ApKpiCard
        label="Hot Leads"
        :value="crm.hot"
        icon="lucide:zap"
        icon-tone="orange"
        :subtitle="`${formatNumber(crm.warm)} warm · ${formatNumber(crm.cold)} cold`"
      />
      <ApKpiCard
        label="Follow Ups"
        :value="crm.follow_up"
        icon="lucide:calendar-clock"
        icon-tone="red"
        :subtitle="`${formatNumber(crm.follow_up_overdue)} overdue`"
      />
      <ApKpiCard
        label="Deals Closed"
        :value="crm.converted"
        icon="lucide:trophy"
        icon-tone="green"
        :subtitle="`${formatNumber(crm.lost)} lost · ${crm.conversion_rate}% win rate`"
      />
      <ApKpiCard
        label="Revenue Generated"
        :value="crm.revenue_from_leads"
        format="money"
        prefix="AED "
        icon="lucide:banknote"
        icon-tone="blue"
        subtitle="from closed deals"
      />
      <ApKpiCard
        label="Pipeline Health"
        :value="pipelineHealth"
        suffix="%"
        format="percent"
        icon="lucide:activity"
        :subtitle="pipelineHealthLabel"
      />
      <ApKpiCard
        label="Lead Sources"
        :value="sourceCount"
        icon="lucide:git-branch"
        :subtitle="topSource"
      />
    </div>

    <div class="ap-grid ap-grid--charts">
      <div class="ap-col-8">
        <ApPanel title="Leads Growth" subtitle="Daily lead volume" :tag="periodLabel">
          <AdLazyChart
            v-if="!loading && trendValues.length"
            type="area"
            :height="220"
            :series="[{ name: 'Leads', data: trendValues }]"
            :options="trendOpts"
          />
          <div v-else-if="loading" class="ap-skeleton" style="min-height: 220px" />
          <AdEmptyState v-else title="No trend data" icon="lucide:activity" />
        </ApPanel>
      </div>
      <div class="ap-col-4">
        <ApPanel title="Lead Sources" subtitle="Top channels">
          <AdLazyChart
            v-if="!loading && sourceValues.length"
            type="donut"
            :height="220"
            :series="sourceValues"
            :options="sourceOpts"
          />
          <div v-else-if="loading" class="ap-skeleton" style="min-height: 220px" />
          <AdEmptyState v-else title="No source data" icon="lucide:pie-chart" />
        </ApPanel>
      </div>

      <div class="ap-col-5">
        <ApPanel title="Conversion Funnel" subtitle="Stage distribution">
          <AdLazyChart
            v-if="!loading && funnelValues.length"
            type="bar"
            :height="200"
            :series="[{ name: 'Leads', data: funnelValues }]"
            :options="funnelOpts"
          />
          <div v-else-if="loading" class="ap-skeleton" style="min-height: 200px" />
          <AdEmptyState v-else title="No funnel stages" icon="lucide:git-merge" />
        </ApPanel>
      </div>
      <div class="ap-col-4">
        <ApPanel title="Sales Pipeline" subtitle="Active stages">
          <div class="ap-pipeline">
            <div v-for="pill in pipelinePills" :key="pill.label" class="ap-pipeline__stage">
              <p class="ap-pipeline__label">{{ pill.label }}</p>
              <p class="ap-pipeline__value">{{ pill.value }}</p>
            </div>
          </div>
        </ApPanel>
      </div>
      <div class="ap-col-3">
        <ApPanel title="Activity Heatmap" subtitle="Weekly intensity">
          <div class="ap-heatmap">
            <div
              v-for="(cell, idx) in heatmapCells"
              :key="idx"
              class="ap-heatmap__cell"
              :style="{ '--heat': cell.heat }"
              :title="`${cell.label}: ${cell.value} leads`"
            />
          </div>
          <div class="ap-heatmap__labels">
            <span v-for="d in weekDays" :key="d" class="ap-heatmap__label">{{ d }}</span>
          </div>
        </ApPanel>
      </div>

      <div class="ap-col-6">
        <ApPanel title="Agent Performance Ranking" tag="Live">
          <ul v-if="!loading && agents.length" class="ap-agent-list">
            <li
              v-for="(agent, idx) in agents.slice(0, 6)"
              :key="agent.id"
              class="ap-agent"
              :class="{ 'ap-agent--top': idx < 3 }"
            >
              <span class="ap-agent__rank">{{ idx + 1 }}</span>
              <div class="ap-agent__info">
                <p class="ap-agent__name">{{ agent.name }}</p>
                <p class="ap-agent__meta">{{ formatNumber(agent.leads) }} leads · {{ formatNumber(agent.converted) }} won</p>
              </div>
              <span class="ap-agent__score">{{ agent.rate }}%</span>
            </li>
          </ul>
          <AdEmptyState v-else-if="!loading" title="No agent data" icon="lucide:users" />
          <div v-else class="ap-skeleton" style="min-height: 180px" />
        </ApPanel>
      </div>
      <div class="ap-col-6">
        <ApPanel title="Sales Performance" :tag="periodLabel">
          <div class="ap-grid" style="grid-template-columns: 1fr 1fr; gap: 10px">
            <div class="ap-kpi ap-kpi--accent" style="min-height: 90px">
              <p class="ap-kpi__label">Total Sale</p>
              <p class="ap-kpi__value" style="font-size: 20px">{{ formatMoney(crm.total_sale) }}</p>
            </div>
            <div class="ap-kpi ap-kpi--accent" style="min-height: 90px">
              <p class="ap-kpi__label">Commission</p>
              <p class="ap-kpi__value" style="font-size: 20px">{{ formatMoney(crm.total_commission) }}</p>
            </div>
          </div>
          <div
            v-if="crm.best_closer"
            class="ap-kpi ap-kpi--accent"
            style="margin-top: 10px; min-height: 70px; flex-direction: row; align-items: center; justify-content: space-between; gap: 10px"
          >
            <div>
              <p class="ap-kpi__label">Best Closer</p>
              <p class="ap-kpi__value" style="font-size: 16px">{{ crm.best_closer.name }}</p>
              <p class="ap-kpi__sub">{{ formatNumber(crm.best_closer.converted) }} conversions</p>
            </div>
            <span class="ap-kpi__badge">{{ crm.best_closer.rate }}% rate</span>
          </div>
        </ApPanel>
      </div>
    </div>

    <div class="ap-grid ap-grid--insights">
      <article
        v-for="item in insightCards"
        :key="item.title"
        class="ap-insight"
        :class="`ap-insight--${item.type}`"
      >
        <span class="ap-insight__icon">
          <iconify-icon :icon="item.icon" width="16" height="16" />
        </span>
        <div>
          <p class="ap-insight__title">{{ item.title }}</p>
          <p class="ap-insight__text">{{ item.text }}</p>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import ApKpiCard from '../widgets/ApKpiCard.vue'
import ApPanel from '../widgets/ApPanel.vue'
import AdLazyChart from '../widgets/AdLazyChart.vue'
import AdEmptyState from '../widgets/AdEmptyState.vue'

const props = defineProps({
  crm: { type: Object, default: () => ({}) },
  aiInsights: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  periodLabel: { type: String, default: '' },
})

const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)
const formatMoney = (n) => `AED ${formatNumber(n)}`

const trendValues = computed(() => (props.crm?.trend || []).map((t) => t.value))
const trendLabels = computed(() => (props.crm?.trend || []).map((t) => t.label))
const funnelValues = computed(() => props.crm?.funnel?.values || [])
const funnelLabels = computed(() => props.crm?.funnel?.labels || [])
const sourceValues = computed(() => (props.crm?.lead_sources || []).map((s) => s.count))
const sourceLabels = computed(() => (props.crm?.lead_sources || []).map((s) => s.source))
const sourceCount = computed(() => props.crm?.lead_sources?.length || 0)
const topSource = computed(() => {
  const top = props.crm?.lead_sources?.[0]
  return top ? `Top: ${top.source}` : 'No sources'
})
const agents = computed(() => props.crm?.agent_ranking || [])

const pipelineHealth = computed(() => {
  const total = Number(props.crm?.total_leads) || 0
  if (!total) return 0
  const active = (Number(props.crm?.qualified) || 0) + (Number(props.crm?.hot) || 0) + (Number(props.crm?.negotiation) || 0)
  return Math.min(100, Math.round((active / total) * 100))
})

const pipelineHealthLabel = computed(() => {
  const h = pipelineHealth.value
  if (h >= 70) return 'Excellent pipeline'
  if (h >= 40) return 'Healthy pipeline'
  return 'Needs attention'
})

const pipelinePills = computed(() => [
  { label: 'New', value: formatNumber(props.crm?.new_leads) },
  { label: 'Contacted', value: formatNumber(props.crm?.contacted) },
  { label: 'Qualified', value: formatNumber(props.crm?.qualified) },
  { label: 'Follow-up', value: formatNumber(props.crm?.follow_up) },
  { label: 'Converted', value: formatNumber(props.crm?.converted) },
  { label: 'Lost', value: formatNumber(props.crm?.lost) },
])

const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const heatmapCells = computed(() => {
  const trend = props.crm?.trend || []
  const max = Math.max(...trend.map((t) => t.value), 1)
  const cells = trend.slice(-7)
  while (cells.length < 7) cells.unshift({ label: '', value: 0 })
  return cells.map((c) => ({
    label: c.label,
    value: c.value,
    heat: Math.max(0.12, (c.value || 0) / max),
  }))
})

const insightCards = computed(() => {
  const cards = []
  const aiText = props.aiInsights[0]?.text
    || `Conversion rate at ${props.crm?.conversion_rate}% — hot leads need focus.`
  cards.push({ type: 'ai', icon: 'lucide:sparkles', title: 'AI Insights', text: aiText })

  if (props.crm?.follow_up_overdue > 0) {
    cards.push({
      type: 'alert',
      icon: 'lucide:alarm-clock',
      title: 'Alerts',
      text: `${formatNumber(props.crm.follow_up_overdue)} leads have overdue follow-ups.`,
    })
  } else {
    cards.push({ type: 'positive', icon: 'lucide:check-circle', title: 'Alerts', text: 'All follow-ups on track.' })
  }

  cards.push({
    type: 'neutral',
    icon: 'lucide:lightbulb',
    title: 'Recommendations',
    text: props.crm?.hot > 0
      ? `Focus on ${formatNumber(props.crm.hot)} hot leads today.`
      : 'Boost outreach to warm leads.',
  })

  cards.push({
    type: 'neutral',
    icon: 'lucide:sun',
    title: 'Daily Summary',
    text: `${formatNumber(props.crm?.new_leads)} new leads · ${formatNumber(props.crm?.calls_answered)} calls · avg ${props.crm?.avg_response_time_min} min`,
  })

  return cards
})

const trendOpts = computed(() => ({
  colors: ['#7c5cbf'],
  fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
  xaxis: { categories: trendLabels.value, labels: { style: { fontSize: '10px', colors: '#9ca3af' } } },
  grid: { borderColor: 'rgba(42, 21, 72, 0.06)' },
}))

const sourceOpts = computed(() => ({
  labels: sourceLabels.value,
  colors: ['#7c5cbf', '#5b3d8f', '#22c55e', '#f59e0b'],
  legend: { position: 'bottom', fontSize: '10px' },
}))

const funnelOpts = computed(() => ({
  colors: ['#5b3d8f'],
  plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '55%' } },
  xaxis: { categories: funnelLabels.value, labels: { style: { fontSize: '9px', colors: '#9ca3af' } } },
}))
</script>
