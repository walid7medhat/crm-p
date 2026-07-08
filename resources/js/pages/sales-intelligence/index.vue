<template>
  <div class="dashboard-main-body asi">
    <!-- Hero header -->
    <header class="asi-hero">
      <div class="asi-hero__bg" aria-hidden="true" />
      <div class="asi-hero__content">
        <div class="asi-hero__left">
          <div class="asi-hero__icon-wrap">
            <Icon icon="lucide:brain-circuit" />
          </div>
          <div>
            <h1>AI Sales Intelligence</h1>
            <p>Pipeline discipline · follow-up quality · lead neglect detection</p>
          </div>
        </div>
        <div class="asi-hero__actions">
          <div class="asi-hero__search">
            <Icon icon="lucide:search" />
            <input
              v-model="agentSearch"
              type="search"
              placeholder="Search agents…"
              aria-label="Search agents"
            />
          </div>
          <button type="button" class="asi-hero__btn" :disabled="recalculating" @click="runRecalculate">
            <Icon icon="lucide:refresh-cw" :class="{ spin: recalculating }" />
            {{ recalculating ? 'Running…' : 'Recalculate all' }}
          </button>
        </div>
      </div>

      <!-- Tabs -->
      <nav class="asi-tabs" aria-label="Sections">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="asi-tabs__btn"
          :class="{ 'asi-tabs__btn--active': activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          <Icon :icon="tab.icon" />
          {{ tab.label }}
          <span v-if="tab.badge" class="asi-tabs__badge">{{ tab.badge }}</span>
        </button>
      </nav>
    </header>

    <!-- Toasts -->
    <div v-if="error" class="asi-toast asi-toast--error">
      <Icon icon="lucide:alert-circle" />
      {{ error }}
      <button type="button" @click="error = ''"><Icon icon="lucide:x" /></button>
    </div>
    <div v-if="saveMsg" class="asi-toast asi-toast--success">
      <Icon icon="lucide:check-circle" />
      {{ saveMsg }}
      <button type="button" @click="saveMsg = ''"><Icon icon="lucide:x" /></button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="asi-loading">
      <div class="asi-loading__spinner" />
      <p>Loading intelligence data…</p>
    </div>

    <main v-else class="asi-main">
      <!-- OVERVIEW TAB -->
      <template v-if="activeTab === 'overview'">
        <section class="asi-kpis">
          <article v-for="kpi in kpiCards" :key="kpi.key" class="asi-kpi" :style="{ '--kpi-color': kpi.color }">
            <div class="asi-kpi__icon"><Icon :icon="kpi.icon" /></div>
            <div class="asi-kpi__body">
              <span class="asi-kpi__label">{{ kpi.label }}</span>
              <strong class="asi-kpi__value">{{ kpi.value }}</strong>
              <span class="asi-kpi__hint">{{ kpi.hint }}</span>
            </div>
          </article>
        </section>

        <div class="asi-overview-grid">
          <!-- Agent list -->
          <section class="asi-card">
            <header class="asi-card__head">
              <h2><Icon icon="lucide:users" /> Agent health</h2>
              <div class="asi-filters">
                <button
                  v-for="f in statusFilters"
                  :key="f.id"
                  type="button"
                  class="asi-filter"
                  :class="{ 'asi-filter--active': statusFilter === f.id }"
                  @click="statusFilter = f.id"
                >
                  {{ f.label }}
                </button>
              </div>
            </header>
            <div class="asi-agent-list">
              <button
                v-for="agent in filteredAgents"
                :key="agent.user_id"
                type="button"
                class="asi-agent-row"
                :class="{ 'asi-agent-row--selected': selectedAgentId == agent.user_id }"
                @click="openAgent(agent.user_id)"
              >
                <div class="asi-agent-row__avatar">{{ initials(agent.user?.name) }}</div>
                <div class="asi-agent-row__info">
                  <span class="asi-agent-row__name">{{ agent.user?.name || `Agent #${agent.user_id}` }}</span>
                  <span class="asi-agent-row__meta">{{ formatStatus(agent.status) }} · {{ agent.risk_level }} risk</span>
                </div>
                <div class="asi-agent-row__score" :class="scoreClass(agent.overall_ai_score)">
                  {{ Math.round(agent.overall_ai_score ?? 0) }}
                </div>
                <span class="asi-agent-row__badge" :class="`asi-agent-row__badge--${agent.status}`">
                  {{ formatStatus(agent.status) }}
                </span>
                <Icon icon="lucide:chevron-right" class="asi-agent-row__chevron" />
              </button>
              <p v-if="!filteredAgents.length" class="asi-empty">
                <Icon icon="lucide:inbox" />
                No agents match. Run recalculate to populate scores.
              </p>
            </div>
          </section>

          <!-- Rankings + Alerts sidebar -->
          <aside class="asi-sidebar">
            <section class="asi-card">
              <header class="asi-card__head">
                <h2><Icon icon="lucide:trophy" /> Team ranking</h2>
              </header>
              <div v-if="topRankings.length" class="asi-podium">
                <div v-for="(r, idx) in topRankings.slice(0, 5)" :key="r.user_id" class="asi-podium__row">
                  <span class="asi-podium__rank" :class="{ 'asi-podium__rank--top': idx < 3 }">#{{ r.overall_rank || idx + 1 }}</span>
                  <span class="asi-podium__name">{{ rankingName(r.user_id) }}</span>
                  <span class="asi-podium__score">{{ agentScoreById(r.user_id) }}</span>
                </div>
              </div>
              <p v-else class="asi-empty-sm">
                <Icon icon="lucide:info" />
                Run recalculate to populate rankings
              </p>
            </section>

            <section class="asi-card">
              <header class="asi-card__head">
                <h2><Icon icon="lucide:bell" /> Risk alerts</h2>
                <button v-if="alertsList.length" type="button" class="asi-link" @click="activeTab = 'alerts'">View all</button>
              </header>
              <div class="asi-alerts-mini">
                <article
                  v-for="alert in alertsList.slice(0, 4)"
                  :key="alert.id"
                  class="asi-alert-mini"
                  :class="`asi-alert-mini--${alert.severity}`"
                >
                  <strong>{{ alert.title }}</strong>
                  <p>{{ alert.message }}</p>
                  <span>{{ alert.user?.name }}</span>
                </article>
                <p v-if="!alertsList.length" class="asi-empty-sm">
                  <Icon icon="lucide:check-circle" />
                  No active alerts
                </p>
              </div>
            </section>

            <section class="asi-card asi-card--cta">
              <Icon icon="lucide:settings-2" class="asi-card--cta-icon" />
              <h3>Customize scoring model</h3>
              <p>View and edit the default auto-structure — weights, thresholds, and formula.</p>
              <button type="button" class="asi-cta-btn" @click="activeTab = 'scoring'">
                Open scoring model
                <Icon icon="lucide:arrow-right" />
              </button>
            </section>
          </aside>
        </div>
      </template>

      <!-- AGENTS TAB -->
      <template v-else-if="activeTab === 'agents'">
        <section class="asi-card">
          <header class="asi-card__head">
            <h2><Icon icon="lucide:users" /> All agents ({{ filteredAgents.length }})</h2>
            <div class="asi-filters">
              <button
                v-for="f in statusFilters"
                :key="f.id"
                type="button"
                class="asi-filter"
                :class="{ 'asi-filter--active': statusFilter === f.id }"
                @click="statusFilter = f.id"
              >
                {{ f.label }}
              </button>
            </div>
          </header>
          <div class="asi-agent-grid">
            <button
              v-for="agent in filteredAgents"
              :key="agent.user_id"
              type="button"
              class="asi-agent-card"
              @click="openAgent(agent.user_id)"
            >
              <div class="asi-agent-card__top">
                <div class="asi-agent-card__avatar">{{ initials(agent.user?.name) }}</div>
                <span class="asi-agent-card__score" :class="scoreClass(agent.overall_ai_score)">
                  {{ Math.round(agent.overall_ai_score ?? 0) }}
                </span>
              </div>
              <h3>{{ agent.user?.name || `Agent #${agent.user_id}` }}</h3>
              <span class="asi-agent-card__badge" :class="`asi-agent-card__badge--${agent.status}`">
                {{ formatStatus(agent.status) }}
              </span>
              <div class="asi-agent-card__bars">
                <div v-for="s in getSubscores(agent)" :key="s.key" class="asi-agent-card__bar-row">
                  <span>{{ s.label }}</span>
                  <div class="asi-agent-card__bar"><div :style="{ width: `${s.value}%`, background: s.color }" /></div>
                </div>
              </div>
            </button>
          </div>
        </section>
      </template>

      <!-- SCORING TAB -->
      <template v-else-if="activeTab === 'scoring'">
        <AsiScoringPanel
          :rules="scoringRules"
          :saving="rulesSaving"
          @save="saveRules"
          @reset="resetRules"
        />
      </template>

      <!-- ALERTS TAB -->
      <template v-else-if="activeTab === 'alerts'">
        <section class="asi-card">
          <header class="asi-card__head">
            <h2><Icon icon="lucide:bell-ring" /> Risk alerts ({{ alertsList.length }})</h2>
          </header>
          <div class="asi-alerts-full">
            <article
              v-for="alert in alertsList"
              :key="alert.id"
              class="asi-alert-full"
              :class="`asi-alert-full--${alert.severity}`"
            >
              <div class="asi-alert-full__icon">
                <Icon :icon="alert.severity === 'high' ? 'lucide:alert-triangle' : 'lucide:info'" />
              </div>
              <div>
                <strong>{{ alert.title }}</strong>
                <p>{{ alert.message }}</p>
                <span>{{ alert.user?.name }} · {{ formatDate(alert.created_at) }}</span>
              </div>
            </article>
            <p v-if="!alertsList.length" class="asi-empty">
              <Icon icon="lucide:check-circle" />
              No active risk alerts — team is on track.
            </p>
          </div>
        </section>
      </template>
    </main>

    <!-- Agent drawer -->
    <AsiAgentDrawer
      :open="drawerOpen"
      :agent="drawerAgent"
      :observations="drawerObservations"
      :loading="drawerLoading"
      :recalculating="recalculating"
      @close="closeDrawer"
      @recalculate="recalculateAgent"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { aiSalesIntelligenceApi } from '@/services/aiSalesIntelligenceApi'
import AsiScoringPanel from './components/AsiScoringPanel.vue'
import AsiAgentDrawer from './components/AsiAgentDrawer.vue'

const loading = ref(true)
const error = ref('')
const saveMsg = ref('')
const recalculating = ref(false)
const rulesSaving = ref(false)
const activeTab = ref('overview')
const dashboard = ref(null)
const scoringRules = ref([])
const agentSearch = ref('')
const statusFilter = ref('all')

const selectedAgentId = ref('')
const drawerOpen = ref(false)
const drawerAgent = ref(null)
const drawerObservations = ref([])
const drawerLoading = ref(false)

const tabs = computed(() => [
  { id: 'overview', label: 'Overview', icon: 'lucide:layout-dashboard' },
  { id: 'agents', label: 'Agents', icon: 'lucide:users', badge: agentsList.value.length || null },
  { id: 'scoring', label: 'Scoring model', icon: 'lucide:sliders-horizontal' },
  { id: 'alerts', label: 'Alerts', icon: 'lucide:bell', badge: alertsList.value.length || null },
])

const statusFilters = [
  { id: 'all', label: 'All' },
  { id: 'excellent', label: 'Excellent' },
  { id: 'good', label: 'Good' },
  { id: 'needs_attention', label: 'Attention' },
  { id: 'critical', label: 'Critical' },
]

const summary = computed(() => dashboard.value?.summary || {})
const agentsList = computed(() => {
  const agents = dashboard.value?.agents || []
  return Array.isArray(agents) ? agents : agents.data || []
})
const rankings = computed(() => dashboard.value?.rankings || [])
const alertsList = computed(() => dashboard.value?.alerts || [])
const topRankings = computed(() => rankings.value.slice(0, 8))

const filteredAgents = computed(() => {
  let list = [...agentsList.value]
  const q = agentSearch.value.trim().toLowerCase()
  if (q) {
    list = list.filter((a) => (a.user?.name || '').toLowerCase().includes(q))
  }
  if (statusFilter.value !== 'all') {
    list = list.filter((a) => a.status === statusFilter.value)
  }
  return list.sort((a, b) => (b.overall_ai_score ?? 0) - (a.overall_ai_score ?? 0))
})

const kpiCards = computed(() => {
  const s = summary.value
  return [
    { key: 'agents', label: 'Agents tracked', value: s.agents_tracked ?? 0, hint: 'Active sales team', icon: 'lucide:users', color: '#6366f1' },
    { key: 'avg', label: 'Avg AI score', value: s.avg_ai_score ?? '—', hint: 'Behavior-weighted', icon: 'lucide:gauge', color: '#8b5cf6' },
    { key: 'excellent', label: 'Excellent', value: s.excellent ?? 0, hint: 'Score ≥ 85', icon: 'lucide:star', color: '#10b981' },
    { key: 'risk', label: 'High risk', value: s.high_risk ?? 0, hint: 'Needs review', icon: 'lucide:alert-triangle', color: '#ef4444' },
  ]
})

const SUBSCORE_COLORS = { pipeline: '#6366f1', response: '#8b5cf6', followup: '#a855f7', behavior: '#f59e0b' }

function getSubscores(agent) {
  const scores = agent.scores || {}
  return [
    { key: 'behavior', label: 'Behavior', value: Math.round(scores.behavior || 0), color: SUBSCORE_COLORS.behavior },
    { key: 'pipeline', label: 'Pipeline', value: Math.round(scores.pipeline || 0), color: SUBSCORE_COLORS.pipeline },
    { key: 'response', label: 'Response', value: Math.round(scores.response || 0), color: SUBSCORE_COLORS.response },
  ]
}

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()
}

function formatStatus(s) { return (s || '').replace(/_/g, ' ') }
function formatDate(d) { return d ? new Date(d).toLocaleString() : '—' }
function scoreClass(score) {
  if (score >= 85) return 'score--excellent'
  if (score >= 70) return 'score--good'
  if (score >= 50) return 'score--warn'
  return 'score--critical'
}
function rankingName(userId) {
  const a = agentsList.value.find((x) => x.user_id === userId)
  return a?.user?.name || `User #${userId}`
}
function agentScoreById(userId) {
  const a = agentsList.value.find((x) => x.user_id === userId)
  return a ? Math.round(a.overall_ai_score ?? 0) : '—'
}

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    dashboard.value = await aiSalesIntelligenceApi.dashboard()
  } catch (e) {
    error.value = e.message || 'Failed to load dashboard'
  } finally {
    loading.value = false
  }
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
    saveMsg.value = 'Scoring rules saved. Click Recalculate to apply.'
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
    saveMsg.value = 'Defaults restored. Click Recalculate to refresh scores.'
    await loadScoringRules()
  } catch (e) {
    error.value = e.message || 'Failed to reset rules'
  } finally {
    rulesSaving.value = false
  }
}

async function runRecalculate() {
  recalculating.value = true
  error.value = ''
  try {
    await aiSalesIntelligenceApi.recalculate({})
    await loadDashboard()
    if (drawerOpen.value && selectedAgentId.value) {
      await loadDrawerAgent(selectedAgentId.value)
    }
    saveMsg.value = 'Recalculation complete.'
  } catch (e) {
    error.value = e.message || 'Recalculation failed'
  } finally {
    recalculating.value = false
  }
}

async function recalculateAgent(userId) {
  recalculating.value = true
  try {
    await aiSalesIntelligenceApi.recalculate({ user_id: Number(userId) })
    await loadDashboard()
    await loadDrawerAgent(userId)
    saveMsg.value = 'Agent recalculated.'
  } catch (e) {
    error.value = e.message || 'Recalculation failed'
  } finally {
    recalculating.value = false
  }
}

async function loadDrawerAgent(userId) {
  drawerLoading.value = true
  try {
    const data = await aiSalesIntelligenceApi.showAgent(userId)
    drawerAgent.value = data.agent
    drawerObservations.value = data.observations || []
  } catch (e) {
    error.value = e.message || 'Failed to load agent'
    drawerAgent.value = null
  } finally {
    drawerLoading.value = false
  }
}

function openAgent(userId) {
  selectedAgentId.value = String(userId)
  drawerOpen.value = true
  loadDrawerAgent(userId)
}

function closeDrawer() {
  drawerOpen.value = false
  selectedAgentId.value = ''
}

onMounted(async () => {
  await Promise.all([loadDashboard(), loadScoringRules()])
})
</script>

<style scoped>
/* Override global CRM heading scales (style.css uses clamp + !important) */
.asi :is(h1, h2, h3, h4, h5, h6) {
  font-weight: 600 !important;
  line-height: 1.3 !important;
  letter-spacing: normal !important;
  text-transform: none !important;
}

.asi {
  min-height: 100%;
  background: #f1f5f9;
  padding-bottom: 2rem;
}

/* Hero */
.asi-hero {
  position: relative;
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4f46e5 100%);
  color: #fff;
  padding: 1.5rem 1.5rem 0;
  margin: -1rem -1.25rem 0;
  overflow: hidden;
}

.asi-hero__bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 50% at 50% -20%, rgba(139, 92, 246, 0.4), transparent),
    radial-gradient(ellipse 60% 40% at 100% 0%, rgba(99, 102, 241, 0.3), transparent);
  pointer-events: none;
}

.asi-hero__content {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1.25rem;
  margin-bottom: 1.25rem;
}

.asi-hero__left {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.asi-hero__icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.15);
  display: grid;
  place-items: center;
  font-size: 1.25rem;
  color: #ffffff;
  flex-shrink: 0;
}

.asi-hero h1 {
  margin: 0 !important;
  font-size: 1.1rem !important;
  font-weight: 700 !important;
  letter-spacing: -0.01em;
  color: #ffffff !important;
}

.asi-hero p {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  color: rgba(255, 255, 255, 0.88) !important;
  max-width: 28rem;
  line-height: 1.4;
}

.asi-hero__actions {
  display: flex;
  gap: 0.65rem;
  align-items: center;
  flex-wrap: wrap;
}

.asi-hero__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  padding: 0 0.85rem;
  min-width: 200px;
  color: #ffffff;
}

.asi-hero__search input {
  flex: 1;
  border: none;
  background: transparent;
  color: #fff;
  padding: 0.55rem 0;
  font-size: 0.85rem;
  outline: none;
}

.asi-hero__search input::placeholder { color: rgba(255, 255, 255, 0.5); }

.asi-hero__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 1.1rem;
  border-radius: 12px;
  border: none;
  background: #fff;
  color: #4f46e5;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.asi-hero__btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.asi-hero__btn:disabled { opacity: 0.7; cursor: not-allowed; }

/* Tabs */
.asi-tabs {
  position: relative;
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.asi-tabs__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.65rem 1rem;
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  border-radius: 10px 10px 0 0;
  transition: color 0.15s ease, background 0.15s ease;
}

.asi-tabs__btn:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }

.asi-tabs__btn--active {
  color: #312e81;
  background: #f1f5f9;
  font-weight: 600;
}

.asi-tabs__badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
}

.asi-tabs__btn--active .asi-tabs__badge {
  background: #e0e7ff;
  color: #4f46e5;
}

/* Main */
.asi-main {
  padding: 1.25rem 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Toasts */
.asi-toast {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 1rem 1.5rem 0;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-size: 0.85rem;
}

.asi-toast button {
  margin-left: auto;
  border: none;
  background: transparent;
  cursor: pointer;
  opacity: 0.7;
}

.asi-toast--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.asi-toast--success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

/* Loading */
.asi-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 4rem;
  color: #6b7280;
}

.asi-loading__spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* KPIs */
.asi-kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}

@media (max-width: 900px) { .asi-kpis { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .asi-kpis { grid-template-columns: 1fr; } }

.asi-kpi {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  background: #fff;
  border-radius: 16px;
  padding: 1.15rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.asi-kpi:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  transform: translateY(-2px);
}

.asi-kpi__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--kpi-color) 15%, transparent);
  color: var(--kpi-color);
  display: grid;
  place-items: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.asi-kpi__label { font-size: 0.75rem; color: #64748b; display: block; }
.asi-kpi__value { font-size: 1.25rem; font-weight: 700; color: #0f172a; display: block; line-height: 1.2; font-variant-numeric: tabular-nums; }
.asi-kpi__hint { font-size: 0.68rem; color: #94a3b8; }

/* Grid */
.asi-overview-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  align-items: start;
}

@media (max-width: 1024px) { .asi-overview-grid { grid-template-columns: 1fr; } }

.asi-sidebar { display: flex; flex-direction: column; gap: 1rem; }

/* Cards */
.asi-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 1.15rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.asi-card__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.asi-card__head h2 {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0 !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  color: #0f172a !important;
}

.asi-card__head h2 :deep(svg),
.asi-card__head h2 svg {
  font-size: 0.95rem;
  color: #6366f1;
  flex-shrink: 0;
}

/* Filters */
.asi-filters { display: flex; gap: 0.35rem; flex-wrap: wrap; }

.asi-filter {
  padding: 0.3rem 0.65rem;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 0.72rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.asi-filter:hover { border-color: #cbd5e1; color: #334155; }

.asi-filter--active {
  background: #eef2ff;
  border-color: #c7d2fe;
  color: #4f46e5;
  font-weight: 600;
}

/* Agent list */
.asi-agent-list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-height: 520px;
  overflow-y: auto;
}

.asi-agent-row {
  display: grid;
  grid-template-columns: 40px 1fr auto auto 20px;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid transparent;
  border-radius: 12px;
  background: #f8fafc;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: all 0.15s ease;
}

.asi-agent-row:hover {
  background: #f1f5f9;
  border-color: #e2e8f0;
}

.asi-agent-row--selected {
  background: #eef2ff;
  border-color: #c7d2fe;
}

.asi-agent-row__avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: grid;
  place-items: center;
  font-size: 0.75rem;
  font-weight: 700;
}

.asi-agent-row__name { font-size: 0.85rem; font-weight: 600; color: #0f172a; display: block; }
.asi-agent-row__meta { font-size: 0.72rem; color: #94a3b8; }

.asi-agent-row__score {
  font-size: 1.1rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.score--excellent { color: #059669; }
.score--good { color: #2563eb; }
.score--warn { color: #d97706; }
.score--critical { color: #dc2626; }

.asi-agent-row__badge {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  text-transform: capitalize;
}

.asi-agent-row__badge--excellent { background: #d1fae5; color: #047857; }
.asi-agent-row__badge--good { background: #dbeafe; color: #1d4ed8; }
.asi-agent-row__badge--needs_attention { background: #fef3c7; color: #b45309; }
.asi-agent-row__badge--critical { background: #fee2e2; color: #b91c1c; }

.asi-agent-row__chevron { color: #cbd5e1; font-size: 1rem; }

/* Podium */
.asi-podium__row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.82rem;
}

.asi-podium__rank {
  width: 28px;
  font-weight: 700;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
}

.asi-podium__rank--top { color: #6366f1; }
.asi-podium__name { flex: 1; font-weight: 500; color: #0f172a; }
.asi-podium__score { font-weight: 700; color: #6366f1; }

/* Alerts mini */
.asi-alerts-mini { display: flex; flex-direction: column; gap: 0.5rem; }

.asi-alert-mini {
  padding: 0.65rem 0.75rem;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  font-size: 0.78rem;
}

.asi-alert-mini strong { display: block; margin-bottom: 0.2rem; color: #0f172a; }
.asi-alert-mini p { margin: 0; color: #64748b; line-height: 1.35; }
.asi-alert-mini span { font-size: 0.68rem; color: #94a3b8; }
.asi-alert-mini--high { border-left: 3px solid #ef4444; }
.asi-alert-mini--medium { border-left: 3px solid #f59e0b; }

/* CTA card */
.asi-card--cta {
  background: linear-gradient(135deg, #eef2ff, #f5f3ff);
  border-color: #c7d2fe;
  text-align: center;
}

.asi-card--cta-icon { font-size: 2rem; color: #6366f1; margin-bottom: 0.5rem; }
.asi-card--cta h3 {
  margin: 0 0 0.35rem !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  color: #312e81 !important;
}
.asi-card--cta p { margin: 0 0 1rem; font-size: 0.78rem; color: #6366f1; line-height: 1.4; }

.asi-cta-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  border-radius: 10px;
  border: none;
  background: #6366f1;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
}

.asi-cta-btn:hover { background: #4f46e5; }

.asi-link {
  border: none;
  background: transparent;
  color: #6366f1;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

/* Agent grid */
.asi-agent-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}

.asi-agent-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1rem;
  text-align: left;
  cursor: pointer;
  transition: all 0.2s ease;
}

.asi-agent-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.12);
  transform: translateY(-2px);
}

.asi-agent-card__top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.65rem;
}

.asi-agent-card__avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: grid;
  place-items: center;
  font-size: 0.8rem;
  font-weight: 700;
}

.asi-agent-card__score { font-size: 1.35rem; font-weight: 800; }

.asi-agent-card h3 {
  margin: 0 0 0.35rem !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  color: #0f172a !important;
}

.asi-agent-card__badge {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  text-transform: capitalize;
  display: inline-block;
  margin-bottom: 0.65rem;
}

.asi-agent-card__badge--excellent { background: #d1fae5; color: #047857; }
.asi-agent-card__badge--good { background: #dbeafe; color: #1d4ed8; }
.asi-agent-card__badge--needs_attention { background: #fef3c7; color: #b45309; }
.asi-agent-card__badge--critical { background: #fee2e2; color: #b91c1c; }

.asi-agent-card__bars { display: flex; flex-direction: column; gap: 0.3rem; }

.asi-agent-card__bar-row {
  display: grid;
  grid-template-columns: 60px 1fr;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.68rem;
  color: #64748b;
}

.asi-agent-card__bar {
  height: 4px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}

.asi-agent-card__bar div {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}

/* Alerts full */
.asi-alerts-full { display: flex; flex-direction: column; gap: 0.75rem; }

.asi-alert-full {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}

.asi-alert-full__icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.asi-alert-full--high .asi-alert-full__icon { background: #fee2e2; color: #dc2626; }
.asi-alert-full--medium .asi-alert-full__icon { background: #fef3c7; color: #d97706; }

.asi-alert-full strong { display: block; margin-bottom: 0.25rem; color: #0f172a; }
.asi-alert-full p { margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.4; }
.asi-alert-full span { font-size: 0.72rem; color: #94a3b8; }

/* Empty states */
.asi-empty,
.asi-empty-sm {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2rem 1rem;
  text-align: center;
  color: #94a3b8;
  font-size: 0.85rem;
}

.asi-empty-sm { padding: 1rem; font-size: 0.78rem; flex-direction: row; justify-content: center; }
</style>
