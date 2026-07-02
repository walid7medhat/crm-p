<template>
  <div class="dashboard-main-body asi-page" :class="{ 'asi-page--dark': isDark }">
    <header class="asi-header">
      <div class="asi-header__title">
        <Icon icon="lucide:brain-circuit" class="asi-header__icon" />
        <div>
          <h1 class="asi-title-lg">AI Sales Intelligence</h1>
          <p class="asi-subtitle">Behavioral sales manager — pipeline discipline, follow-up quality, and lead neglect detection.</p>
        </div>
      </div>
      <div class="asi-header__actions">
        <select v-model="selectedAgentId" class="asi-select" @change="onAgentFilter">
          <option value="">All agents</option>
          <option v-for="a in agentOptions" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <button type="button" class="asi-btn asi-btn--ghost" @click="activeView = activeView === 'scoring' ? 'dashboard' : 'scoring'">
          {{ activeView === 'scoring' ? 'Dashboard' : 'Scoring Rules' }}
        </button>
        <button type="button" class="asi-btn asi-btn--ghost" :disabled="recalculating" @click="toggleDark">
          <Icon :icon="isDark ? 'lucide:sun' : 'lucide:moon'" />
        </button>
        <button type="button" class="asi-btn asi-btn--primary" :disabled="recalculating" @click="runRecalculate">
          {{ recalculating ? 'Recalculating…' : 'Recalculate' }}
        </button>
      </div>
    </header>

    <p v-if="error" class="asi-alert">{{ error }}</p>
    <p v-if="saveMsg" class="asi-success">{{ saveMsg }}</p>
    <p v-if="loading" class="asi-loading">Loading AI intelligence…</p>

    <!-- Scoring customization -->
    <section v-if="activeView === 'scoring' && !loading" class="asi-panel asi-scoring">
      <div class="asi-scoring__head">
        <div>
          <h2 class="asi-title">AI Scoring Rules</h2>
          <p class="asi-subtitle">Default weights used for every agent. Adjust and save, then recalculate.</p>
        </div>
        <div class="asi-scoring__actions">
          <button type="button" class="asi-btn asi-btn--ghost" :disabled="rulesSaving" @click="resetRules">Reset defaults</button>
          <button type="button" class="asi-btn asi-btn--primary" :disabled="rulesSaving" @click="saveRules">
            {{ rulesSaving ? 'Saving…' : 'Save rules' }}
          </button>
        </div>
      </div>

      <div v-for="group in scoringGroups" :key="group.key" class="asi-scoring__group">
        <h3 class="asi-title-sm">{{ group.label }}</h3>
        <p v-if="group.hint" class="asi-subtitle">{{ group.hint }}</p>
        <div class="asi-scoring__grid">
          <article v-for="rule in group.rules" :key="rule.id" class="asi-rule-card">
            <div class="asi-rule-card__top">
              <span class="asi-rule-card__name">{{ rule.label }}</span>
              <span class="asi-rule-card__val">{{ formatRuleValue(rule) }}</span>
            </div>
            <p v-if="rule.description" class="asi-rule-card__desc">{{ rule.description }}</p>
            <input
              v-model.number="rule.weight"
              type="range"
              :min="group.key === 'overall' || group.key === 'behavior' ? 0 : 0"
              :max="group.key === 'overall' || group.key === 'behavior' ? 1 : 100"
              :step="group.key === 'overall' || group.key === 'behavior' ? 0.01 : 1"
              class="asi-range"
            />
          </article>
        </div>
      </div>
    </section>

    <template v-else-if="dashboard">
      <!-- Summary -->
      <section class="asi-summary">
        <article v-for="card in summaryCards" :key="card.key" class="asi-card">
          <span class="asi-card__label">{{ card.label }}</span>
          <strong class="asi-card__value">{{ card.value }}</strong>
          <span class="asi-card__hint">{{ card.hint }}</span>
        </article>
      </section>

      <!-- Agent Health Overview + Team Ranking -->
      <div class="asi-grid asi-grid--2">
        <section class="asi-panel">
          <h2 class="asi-title">Agent Health Overview</h2>
          <div class="asi-table-wrap">
            <table class="asi-table">
              <thead>
                <tr>
                  <th>Agent</th>
                  <th>AI Score</th>
                  <th>Status</th>
                  <th>Risk</th>
                  <th>Behavior</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="agent in agentsList"
                  :key="agent.user_id"
                  :class="{ 'asi-table__row--active': selectedAgentId == agent.user_id }"
                  @click="selectAgent(agent.user_id)"
                >
                  <td>{{ agent.user?.name || `User #${agent.user_id}` }}</td>
                  <td><span class="asi-score" :class="scoreClass(agent.overall_ai_score)">{{ agent.overall_ai_score }}</span></td>
                  <td><span class="asi-badge" :class="`asi-badge--${agent.status}`">{{ formatStatus(agent.status) }}</span></td>
                  <td><span class="asi-risk" :class="`asi-risk--${agent.risk_level}`">{{ agent.risk_level }}</span></td>
                  <td>{{ agent.scores?.behavior ?? '—' }}</td>
                  <td><button type="button" class="asi-link" @click.stop="selectAgent(agent.user_id)">View</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="asi-panel">
          <h2 class="asi-title">Team Ranking</h2>
          <div class="asi-ranking">
            <div v-for="(r, idx) in topRankings" :key="r.user_id" class="asi-ranking__row">
              <span class="asi-ranking__pos">#{{ r.overall_rank || idx + 1 }}</span>
              <span class="asi-ranking__name">{{ rankingName(r.user_id) }}</span>
              <span class="asi-ranking__score">{{ r.scores?.overall_ai_score ?? '—' }}</span>
            </div>
            <p v-if="!topRankings.length" class="asi-empty">Run recalculation to populate rankings.</p>
          </div>
        </section>
      </div>

      <!-- Risk Alerts -->
      <section class="asi-panel">
        <h2 class="asi-title">Risk Alerts</h2>
        <div class="asi-alerts">
          <article v-for="alert in alertsList" :key="alert.id" class="asi-alert-card" :class="`asi-alert-card--${alert.severity}`">
            <strong>{{ alert.title }}</strong>
            <p>{{ alert.message }}</p>
            <span class="asi-alert-card__meta">{{ alert.user?.name }} · {{ formatDate(alert.created_at) }}</span>
          </article>
          <p v-if="!alertsList.length" class="asi-empty">No active risk alerts.</p>
        </div>
      </section>

      <!-- Agent detail sections -->
      <template v-if="agentDetail">
        <section class="asi-panel asi-panel--highlight">
          <h2 class="asi-title">AI Executive Summary</h2>
          <p class="asi-summary-text">{{ agentDetail.agent?.executive_summary || 'No summary yet.' }}</p>
        </section>

        <div class="asi-grid asi-grid--3">
          <section class="asi-panel" v-for="block in metricBlocks" :key="block.key">
            <h3 class="asi-title-sm">{{ block.title }}</h3>
            <ul class="asi-metrics-list">
              <li v-for="item in block.items" :key="item.label">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </li>
            </ul>
          </section>
        </div>

        <div class="asi-grid asi-grid--2">
          <section class="asi-panel">
            <h2 class="asi-title">AI Behavior Analysis</h2>
            <ul class="asi-obs-list">
              <li v-for="(obs, i) in observations" :key="i" :class="`asi-obs--${obs.severity}`">
                {{ obs.observation }}
              </li>
              <li v-if="!observations.length" class="asi-empty">No observations for this agent.</li>
            </ul>
          </section>

          <section class="asi-panel">
            <h2 class="asi-title">AI Coaching</h2>
            <div class="asi-coaching">
              <article v-for="(card, i) in coachingCards" :key="i" class="asi-coach-card" :class="`asi-coach-card--${card.priority}`">
                <h4>{{ card.title }}</h4>
                <p>{{ card.body }}</p>
              </article>
              <p v-if="!coachingCards.length" class="asi-empty">No coaching cards yet.</p>
            </div>
          </section>
        </div>

        <section class="asi-panel">
          <h2 class="asi-title">Lead Neglect Detection</h2>
          <div class="asi-table-wrap">
            <table class="asi-table">
              <thead>
                <tr>
                  <th>Lead</th>
                  <th>Stage</th>
                  <th>Reasons</th>
                  <th>Last update</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="lead in neglectedLeads" :key="lead.lead_id">
                  <td>{{ lead.lead_name || lead.lead_number }}</td>
                  <td>{{ lead.stage_name }}</td>
                  <td><span v-for="r in lead.reasons" :key="r" class="asi-tag">{{ r }}</span></td>
                  <td>{{ formatDate(lead.updated_at) }}</td>
                </tr>
                <tr v-if="!neglectedLeads.length">
                  <td colspan="4" class="asi-empty">No neglected leads detected.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="asi-panel">
          <h2 class="asi-title">Lead Drilldown</h2>
          <div class="asi-drilldown">
            <div v-for="group in drilldownGroups" :key="group.key" class="asi-drilldown__group">
              <h4>{{ group.label }} ({{ group.leads.length }})</h4>
              <ul>
                <li v-for="l in group.leads.slice(0, 5)" :key="l.lead_id">{{ l.lead_name || l.lead_number }}</li>
              </ul>
            </div>
          </div>
        </section>

        <section class="asi-panel">
          <h2 class="asi-title">Weekly Trends</h2>
          <ApexChart type="bar" height="260" :options="weeklyChartOptions" :series="weeklyChartSeries" />
        </section>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import ApexChart from 'vue3-apexcharts'
import { aiSalesIntelligenceApi } from '@/services/aiSalesIntelligenceApi'

const loading = ref(true)
const error = ref('')
const saveMsg = ref('')
const recalculating = ref(false)
const rulesSaving = ref(false)
const isDark = ref(false)
const activeView = ref('dashboard')
const dashboard = ref(null)
const agentDetail = ref(null)
const selectedAgentId = ref('')
const agentOptions = ref([])
const scoringRules = ref([])

const summary = computed(() => dashboard.value?.summary || {})
const agentsList = computed(() => {
  const agents = dashboard.value?.agents || []
  return Array.isArray(agents) ? agents : agents.data || []
})
const rankings = computed(() => dashboard.value?.rankings || [])
const alertsList = computed(() => dashboard.value?.alerts || [])
const observations = computed(() => agentDetail.value?.observations || [])
const coachingCards = computed(() => agentDetail.value?.agent?.coaching_cards || [])
const neglectedLeads = computed(() => agentDetail.value?.agent?.neglect_metrics?.neglected_leads || [])

const topRankings = computed(() => rankings.value.slice(0, 8))

const scoringGroups = computed(() => {
  const rules = scoringRules.value || []
  const byGroup = (key, label, hint = '') => ({
    key,
    label,
    hint,
    rules: rules.filter((r) => r.rule_group === key),
  })
  return [
    byGroup('overall', 'Overall AI Score Weights', 'Must reflect how much each area affects the final 0–100 score.'),
    byGroup('behavior', 'Behavior Score Weights', 'Sub-weights inside the behavior composite.'),
    byGroup('status', 'Status Thresholds', 'Minimum overall score for each status label.'),
    byGroup('risk', 'Risk Thresholds', 'Risk score levels for Low / Medium / High.'),
  ]
})

function formatRuleValue(rule) {
  if (rule.rule_group === 'overall' || rule.rule_group === 'behavior') {
    return `${Math.round(Number(rule.weight || 0) * 100)}%`
  }
  return Math.round(Number(rule.weight || 0))
}

async function loadScoringRules() {
  try {
    const data = await aiSalesIntelligenceApi.scoringRules()
    scoringRules.value = (data.rules || []).map((r) => ({ ...r }))
  } catch {
    scoringRules.value = []
  }
}

async function saveRules() {
  rulesSaving.value = true
  saveMsg.value = ''
  error.value = ''
  try {
    await aiSalesIntelligenceApi.updateScoringRules({
      rules: scoringRules.value.map((r) => ({ id: r.id, weight: r.weight, thresholds: r.thresholds })),
    })
    saveMsg.value = 'Scoring rules saved. Click Recalculate to apply to all agents.'
    await loadScoringRules()
  } catch (e) {
    error.value = e.message || 'Failed to save rules'
  } finally {
    rulesSaving.value = false
  }
}

async function resetRules() {
  if (!confirm('Reset all scoring rules to system defaults?')) return
  rulesSaving.value = true
  saveMsg.value = ''
  error.value = ''
  try {
    await aiSalesIntelligenceApi.resetScoringRules()
    saveMsg.value = 'Defaults restored. Click Recalculate to refresh agent scores.'
    await loadScoringRules()
  } catch (e) {
    error.value = e.message || 'Failed to reset rules'
  } finally {
    rulesSaving.value = false
  }
}

const summaryCards = computed(() => {
  const s = summary.value
  return [
    { key: 'agents', label: 'Agents tracked', value: s.agents_tracked ?? 0, hint: 'Active sales team' },
    { key: 'avg', label: 'Avg AI score', value: s.avg_ai_score ?? '—', hint: 'Behavior-weighted' },
    { key: 'excellent', label: 'Excellent', value: s.excellent ?? 0, hint: 'Score ≥ 85' },
    { key: 'risk', label: 'High risk', value: s.high_risk ?? 0, hint: 'Needs manager review' },
  ]
})

const metricBlocks = computed(() => {
  const a = agentDetail.value?.agent
  if (!a) return []
  const pm = a.pipeline_metrics || {}
  const rm = a.response_metrics || {}
  const fm = a.followup_metrics || {}
  const qm = a.qualification_metrics || {}
  const cm = a.communication_metrics || {}
  const dp = a.daily_performance || {}

  return [
    {
      key: 'pipeline',
      title: 'Pipeline Discipline',
      items: [
        { label: 'Assigned leads', value: pm.assigned_leads ?? 0 },
        { label: 'Active leads', value: pm.active_leads ?? 0 },
        { label: 'Qualified', value: pm.qualified_leads ?? 0 },
        { label: 'Forward move rate', value: pct(pm.forward_movement_rate) },
        { label: 'Stuck leads', value: pm.stuck_leads ?? 0 },
        { label: 'Cleanliness', value: pct(pm.pipeline_cleanliness_score) },
      ],
    },
    {
      key: 'response',
      title: 'Assignment Response',
      items: [
        { label: 'Avg first activity', value: mins(rm.avg_minutes_to_first_activity) },
        { label: 'Avg first comment', value: mins(rm.avg_minutes_to_first_comment) },
        { label: 'Avg first contact', value: mins(rm.avg_minutes_to_first_contact) },
        { label: 'Not contacted', value: rm.not_contacted_count ?? 0 },
      ],
    },
    {
      key: 'followup',
      title: 'Follow-up Discipline',
      items: [
        { label: 'Created', value: fm.followups_created ?? 0 },
        { label: 'Completed', value: fm.followups_completed ?? 0 },
        { label: 'Overdue', value: fm.overdue_followups ?? 0 },
        { label: 'Completion rate', value: pct(fm.reminder_completion_rate) },
        { label: 'No future follow-up', value: fm.leads_without_future_followup ?? 0 },
      ],
    },
    {
      key: 'qualification',
      title: 'Qualification Quality',
      items: [
        { label: 'Qualified rate', value: pct(qm.qualified_rate) },
        { label: 'Qualified → Deal', value: pct(qm.qualified_to_deal_rate) },
        { label: 'Inactive qualified', value: qm.qualified_then_inactive ?? 0 },
      ],
    },
    {
      key: 'communication',
      title: 'Communication Quality',
      items: [
        { label: 'Comments / lead', value: cm.comments_per_lead ?? 0 },
        { label: 'Answered rate', value: pct(cm.answered_rate) },
        { label: 'No answer rate', value: pct(cm.no_answer_rate) },
        { label: 'Zero comments', value: cm.leads_with_zero_comments ?? 0 },
      ],
    },
    {
      key: 'daily',
      title: 'Daily Performance',
      items: [
        { label: 'Assignments today', value: dp.assignments ?? 0 },
        { label: 'Contacts today', value: dp.contacts ?? 0 },
        { label: 'Comments today', value: dp.comments ?? 0 },
        { label: 'Qualified today', value: dp.qualified ?? 0 },
        { label: 'Converted today', value: dp.converted ?? 0 },
      ],
    },
  ]
})

const drilldownGroups = computed(() => {
  const d = agentDetail.value?.agent?.neglect_metrics?.drilldown || {}
  return [
    { key: 'needs_contact', label: 'Needs Contact', leads: d.needs_contact || [] },
    { key: 'needs_followup', label: 'Needs Follow-up', leads: d.needs_followup || [] },
    { key: 'inactive', label: 'Inactive', leads: d.inactive || [] },
    { key: 'future_expired', label: 'Future Expired', leads: d.future_expired || [] },
  ]
})

const weeklyChartSeries = computed(() => {
  const weeks = agentDetail.value?.agent?.weekly_trends?.weeks || []
  return [
    { name: 'Comments', data: weeks.map((w) => w.comments) },
    { name: 'Activities', data: weeks.map((w) => w.activities) },
    { name: 'Follow-ups done', data: weeks.map((w) => w.followups_completed) },
  ]
})

const weeklyChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, background: 'transparent' },
  theme: { mode: isDark.value ? 'dark' : 'light' },
  xaxis: { categories: (agentDetail.value?.agent?.weekly_trends?.weeks || []).map((w) => w.week) },
  colors: ['#733e87', '#5b8def', '#2ecc71'],
  dataLabels: { enabled: false },
}))

function pct(v) {
  if (v == null || v === '') return '—'
  return `${v}%`
}

function mins(v) {
  if (v == null) return '—'
  if (v < 60) return `${Math.round(v)}m`
  return `${(v / 60).toFixed(1)}h`
}

function formatStatus(s) {
  return (s || '').replace(/_/g, ' ')
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString()
}

function scoreClass(score) {
  if (score >= 85) return 'asi-score--excellent'
  if (score >= 70) return 'asi-score--good'
  if (score >= 50) return 'asi-score--warn'
  return 'asi-score--critical'
}

function rankingName(userId) {
  const a = agentsList.value.find((x) => x.user_id === userId)
  return a?.user?.name || `User #${userId}`
}

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const params = selectedAgentId.value ? { agent_id: selectedAgentId.value } : {}
    dashboard.value = await aiSalesIntelligenceApi.dashboard(params)
  } catch (e) {
    error.value = e.message || 'Failed to load dashboard'
  } finally {
    loading.value = false
  }
}

async function loadAgentDetail(userId) {
  if (!userId) {
    agentDetail.value = null
    return
  }
  try {
    agentDetail.value = await aiSalesIntelligenceApi.showAgent(userId)
  } catch (e) {
    error.value = e.message || 'Failed to load agent'
  }
}

function selectAgent(userId) {
  selectedAgentId.value = String(userId)
  loadAgentDetail(userId)
}

function onAgentFilter() {
  loadAgentDetail(selectedAgentId.value || null)
  if (!selectedAgentId.value) loadDashboard()
}

async function runRecalculate() {
  recalculating.value = true
  try {
    const payload = selectedAgentId.value ? { user_id: Number(selectedAgentId.value) } : {}
    await aiSalesIntelligenceApi.recalculate(payload)
    await loadDashboard()
    if (selectedAgentId.value) await loadAgentDetail(selectedAgentId.value)
  } catch (e) {
    error.value = e.message || 'Recalculation failed'
  } finally {
    recalculating.value = false
  }
}

function toggleDark() {
  isDark.value = !isDark.value
}

onMounted(async () => {
  try {
    agentOptions.value = await aiSalesIntelligenceApi.agentOptions()
  } catch {
    agentOptions.value = []
  }
  await Promise.all([loadDashboard(), loadScoringRules()])
})
</script>

<style scoped>
.asi-page {
  --asi-bg: #f0edf3;
  --asi-panel: #ffffff;
  --asi-text: #1a1520;
  --asi-panel-text: #1a1520;
  --asi-muted: #6b6570;
  --asi-accent: #733e87;
  --asi-border: #e8e2ec;
  padding: 1rem 1.25rem 1.5rem;
  min-height: 100%;
  background: var(--asi-bg);
  color: var(--asi-text);
}
.asi-page--dark {
  --asi-bg: #121018;
  --asi-panel: #1e1a24;
  --asi-text: #f2edf5;
  --asi-panel-text: #f2edf5;
  --asi-muted: #a89db2;
  --asi-border: #2e2836;
}

/* Override global CRM heading styles */
.asi-page :is(h1, h2, h3, h4, .asi-title-lg, .asi-title, .asi-title-sm) {
  color: var(--asi-text) !important;
  font-weight: 600 !important;
  line-height: 1.3 !important;
  letter-spacing: normal !important;
  text-transform: none !important;
}
.asi-title-lg {
  font-size: 1.05rem !important;
  margin: 0 0 0.15rem !important;
}
.asi-title {
  font-size: 0.875rem !important;
  margin: 0 0 0.6rem !important;
}
.asi-title-sm {
  font-size: 0.8125rem !important;
  margin: 0 0 0.5rem !important;
}
.asi-subtitle {
  margin: 0;
  color: var(--asi-muted) !important;
  font-size: 0.78rem;
  line-height: 1.4;
}
.asi-panel :is(h2, h3, h4, .asi-title, .asi-title-sm) {
  color: var(--asi-panel-text) !important;
}
.asi-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.asi-header__title {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}
.asi-header__title h1 {
  font-size: 1.05rem !important;
  margin: 0 0 0.15rem;
  font-weight: 600;
}
.asi-header__title p {
  margin: 0;
  color: var(--asi-muted);
  font-size: 0.78rem;
  max-width: 36rem;
}
.asi-header__icon {
  font-size: 1.25rem;
  color: var(--asi-accent);
  flex-shrink: 0;
  margin-top: 0.1rem;
}
.asi-header__actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
}
.asi-select {
  border: 1px solid var(--asi-border);
  background: var(--asi-panel);
  color: var(--asi-text);
  border-radius: 8px;
  padding: 0.45rem 0.65rem;
  font-size: 0.85rem;
}
.asi-btn {
  border: none;
  border-radius: 8px;
  padding: 0.45rem 0.85rem;
  font-size: 0.85rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.asi-btn--primary {
  background: var(--asi-accent);
  color: #fff;
}
.asi-btn--ghost {
  background: var(--asi-panel);
  border: 1px solid var(--asi-border);
  color: var(--asi-text);
}
.asi-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.asi-alert, .asi-loading {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  background: #fde8ea;
  color: #9b1c2c;
  margin-bottom: 1rem;
}
.asi-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.asi-card {
  background: var(--asi-panel);
  border: 1px solid var(--asi-border);
  border-radius: 12px;
  padding: 0.85rem 1rem;
}
.asi-card__label { font-size: 0.72rem; color: var(--asi-muted); display: block; }
.asi-card__value { font-size: 1.15rem; display: block; line-height: 1.2; color: var(--asi-panel-text); font-weight: 700; }
.asi-card__hint { font-size: 0.68rem; color: var(--asi-muted); }
.asi-grid { display: grid; gap: 1rem; margin-bottom: 1rem; }
.asi-grid--2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
.asi-grid--3 { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.asi-panel {
  background: var(--asi-panel);
  border: 1px solid var(--asi-border);
  border-radius: 12px;
  padding: 1rem 1.1rem;
}
.asi-panel--highlight {
  border-color: var(--asi-accent);
  background: linear-gradient(135deg, rgba(115,62,135,0.06), transparent);
}
.asi-summary-text {
  margin: 0;
  line-height: 1.5;
  font-size: 0.82rem;
  color: var(--asi-panel-text);
}
.asi-table-wrap { overflow-x: auto; }
.asi-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.78rem;
}
.asi-table th {
  color: var(--asi-muted);
  font-weight: 600;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.asi-table th, .asi-table td {
  text-align: left;
  padding: 0.4rem 0.35rem;
  border-bottom: 1px solid var(--asi-border);
  color: var(--asi-panel-text);
}
.asi-table__row--active { background: rgba(115,62,135,0.08); }
.asi-table tbody tr { cursor: pointer; }
.asi-score { font-weight: 700; }
.asi-score--excellent { color: #1a8f4a; }
.asi-score--good { color: #2d7dd2; }
.asi-score--warn { color: #c47f00; }
.asi-score--critical { color: #c0392b; }
.asi-badge {
  font-size: 0.7rem;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  text-transform: capitalize;
  background: #eee;
}
.asi-badge--excellent { background: #d4f5e0; color: #1a6b38; }
.asi-badge--good { background: #dbeafe; color: #1e4a8a; }
.asi-badge--needs_attention { background: #fff3cd; color: #856404; }
.asi-badge--critical { background: #fde8ea; color: #9b1c2c; }
.asi-risk { text-transform: capitalize; font-size: 0.78rem; font-weight: 600; }
.asi-risk--low { color: #1a8f4a; }
.asi-risk--medium { color: #c47f00; }
.asi-risk--high { color: #c0392b; }
.asi-link {
  background: none;
  border: none;
  color: var(--asi-accent);
  cursor: pointer;
  font-size: 0.8rem;
}
.asi-ranking__row {
  display: flex;
  gap: 0.5rem;
  padding: 0.4rem 0;
  border-bottom: 1px solid var(--asi-border);
  font-size: 0.85rem;
}
.asi-ranking__pos { width: 2rem; color: var(--asi-muted); }
.asi-ranking__name { flex: 1; }
.asi-ranking__score { font-weight: 700; color: var(--asi-accent); }
.asi-alerts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 0.65rem;
}
.asi-alert-card {
  border-radius: 10px;
  padding: 0.75rem;
  border: 1px solid var(--asi-border);
  font-size: 0.82rem;
}
.asi-alert-card--high { border-left: 3px solid #c0392b; }
.asi-alert-card--medium { border-left: 3px solid #c47f00; }
.asi-alert-card p { margin: 0.35rem 0; color: var(--asi-muted); }
.asi-alert-card__meta { font-size: 0.72rem; color: var(--asi-muted); }
.asi-metrics-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.asi-metrics-list li {
  display: flex;
  justify-content: space-between;
  padding: 0.35rem 0;
  border-bottom: 1px dashed var(--asi-border);
  font-size: 0.8rem;
}
.asi-obs-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.asi-obs-list li {
  padding: 0.5rem 0.65rem;
  margin-bottom: 0.4rem;
  border-radius: 8px;
  font-size: 0.82rem;
  background: rgba(115,62,135,0.06);
}
.asi-obs--warning { background: #fff8e6; }
.asi-obs--critical { background: #fde8ea; }
.asi-coaching {
  display: grid;
  gap: 0.5rem;
}
.asi-coach-card {
  border: 1px solid var(--asi-border);
  border-radius: 8px;
  padding: 0.65rem;
  font-size: 0.82rem;
}
.asi-coach-card h4 { margin: 0 0 0.25rem; font-size: 0.85rem; }
.asi-coach-card p { margin: 0; color: var(--asi-muted); }
.asi-coach-card--critical { border-left: 3px solid #c0392b; }
.asi-coach-card--high { border-left: 3px solid #c47f00; }
.asi-tag {
  display: inline-block;
  font-size: 0.68rem;
  padding: 0.1rem 0.35rem;
  margin: 0.1rem;
  border-radius: 4px;
  background: rgba(115,62,135,0.12);
}
.asi-drilldown {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}
.asi-drilldown__group h4 { margin: 0 0 0.35rem; font-size: 0.85rem; }
.asi-drilldown__group ul {
  margin: 0;
  padding-left: 1rem;
  font-size: 0.78rem;
  color: var(--asi-muted);
}
.asi-empty {
  color: var(--asi-muted);
  font-size: 0.78rem;
  margin: 0;
}
.asi-success {
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  background: #e8f5ec;
  color: #1a6b38;
  margin-bottom: 0.75rem;
  font-size: 0.8rem;
}
.asi-scoring__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.asi-scoring__actions { display: flex; gap: 0.5rem; }
.asi-scoring__group { margin-bottom: 1.25rem; }
.asi-scoring__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.65rem;
}
.asi-rule-card {
  border: 1px solid var(--asi-border);
  border-radius: 8px;
  padding: 0.65rem 0.75rem;
  background: var(--asi-bg);
}
.asi-rule-card__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}
.asi-rule-card__name { font-size: 0.78rem; font-weight: 600; color: var(--asi-panel-text); }
.asi-rule-card__val { font-size: 0.75rem; color: var(--asi-accent); font-weight: 700; }
.asi-rule-card__desc { font-size: 0.68rem; color: var(--asi-muted); margin: 0 0 0.35rem; }
.asi-range { width: 100%; accent-color: var(--asi-accent); }
</style>
