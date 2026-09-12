<template>
  <div class="dashboard-main-body ali-page">
    <header class="ali-header">
      <div class="ali-header__left">
        <h1 class="ali-header__title">AI Lead Intelligence</h1>
        <p class="ali-header__sub">Deterministic CRM pipeline signals for super admins</p>
      </div>
      <div class="ali-header__right">
        <div class="ali-header__meta">
          <span class="ali-header__meta-label">Last updated</span>
          <strong>{{ lastUpdatedLabel }}</strong>
          <span v-if="statusHint" class="ali-header__status">{{ statusHint }}</span>
        </div>
        <button
          type="button"
          class="ali-btn ali-btn--accent"
          :disabled="pageState === 'loading' || isRefreshing || pageState === 'forbidden'"
          @click="refreshAnalysis"
        >
          <Icon icon="lucide:refresh-cw" :class="{ 'ali-spin': isRefreshing }" />
          Refresh Analysis
        </button>
      </div>
    </header>

    <div v-if="toast" class="ali-toast" :class="`ali-toast--${toast.type}`" role="status">
      <Icon :icon="toast.type === 'error' ? 'lucide:alert-circle' : 'lucide:info'" />
      <span>{{ toast.message }}</span>
      <button type="button" class="ali-toast__close" aria-label="Dismiss" @click="toast = null"><Icon icon="lucide:x" /></button>
    </div>

    <AliSectionState
      v-if="pageState === 'forbidden'"
      variant="forbidden"
      title="No permission"
      message="AI Lead Intelligence is available to super_admin users only."
    />

    <template v-else>
      <AliOverviewBrief
        :summary="overview?.brief?.summary"
        :summary-status="overview?.brief?.summary_status || 'not_available'"
        :cards="overview?.brief?.cards || defaultBriefCards"
      />
      <div class="ali-stack">
        <AliTodayActions
          class="ali-section--emphasis"
          :page-state="sectionPageState"
          :status="overview?.actions_today?.status || 'not_available'"
          :items="overview?.actions_today?.items || []"
          :error-message="errorMessage"
          @retry="loadOverview"
          @open-lead="openLead"
        />
        <AliPriorityLeads
          :page-state="sectionPageState"
          :status="overview?.priority_leads?.status || 'not_available'"
          :items="overview?.priority_leads?.items || []"
          :error-message="errorMessage"
          @retry="loadOverview"
          @open-lead="openLead"
          @view-matches="viewMatches"
        />
        <AliAtRisk
          :page-state="sectionPageState"
          :status="overview?.at_risk?.status || 'not_available'"
          :items="overview?.at_risk?.items || []"
          :error-message="errorMessage"
          @retry="loadOverview"
          @open-lead="openLead"
          @view-matches="viewMatches"
        />
        <AliPropertyOpportunities
          :page-state="sectionPageState"
          :status="overview?.property_opportunities?.status || 'not_available'"
          :items="overview?.property_opportunities?.items || []"
          :gaps="overview?.property_opportunities?.gaps || []"
          :error-message="errorMessage"
          @retry="loadOverview"
          @open-lead="openLead"
          @view-matches="viewMatches"
        />
        <AliNeglectedLeads
          :page-state="sectionPageState"
          :status="overview?.neglected?.status || 'not_available'"
          :groups="overview?.neglected?.groups || defaultNeglectGroups"
          :error-message="errorMessage"
          @retry="loadOverview"
          @open-lead="openLead"
          @view-matches="viewMatches"
        />
        <AliInsightPreview
          :status="overview?.insight_preview?.status || 'preview'"
          :text="overview?.insight_preview?.text"
          :disclaimer="overview?.insight_preview?.disclaimer || ''"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { aiLeadIntelligenceApi } from '@/services/aiLeadIntelligenceApi'
import { openLeadView } from '@/composables/useLeadViewModal'
import AliSectionState from './components/AliSectionState.vue'
import AliOverviewBrief from './components/AliOverviewBrief.vue'
import AliPriorityLeads from './components/AliPriorityLeads.vue'
import AliAtRisk from './components/AliAtRisk.vue'
import AliPropertyOpportunities from './components/AliPropertyOpportunities.vue'
import AliNeglectedLeads from './components/AliNeglectedLeads.vue'
import AliTodayActions from './components/AliTodayActions.vue'
import AliInsightPreview from './components/AliInsightPreview.vue'

const overview = ref(null)
const pageState = ref('loading')
const errorMessage = ref('')
const toast = ref(null)
let pollTimer = null

const defaultBriefCards = [
  { key: 'high_priority', label: 'High Priority', icon: 'priority', count: null, status: 'not_available' },
  { key: 'at_risk', label: 'At Risk', icon: 'risk', count: null, status: 'not_available' },
  { key: 'property_opportunities', label: 'Property Opportunities', icon: 'property', count: null, status: 'not_available' },
  { key: 'neglected', label: 'Neglected', icon: 'neglected', count: null, status: 'not_available' },
]
const defaultNeglectGroups = {
  critical: { status: 'not_available', count: null, items: [] },
  needs_attention: { status: 'not_available', count: null, items: [] },
  monitor: { status: 'not_available', count: null, items: [] },
}

const analysisStatus = computed(() => overview.value?.analysis_status || 'not_started')

const isRefreshing = computed(() =>
  pageState.value === 'refreshing'
  || analysisStatus.value === 'queued'
  || analysisStatus.value === 'running'
)

const sectionPageState = computed(() => {
  if (pageState.value === 'loading') return 'loading'
  if (isRefreshing.value) return 'refreshing'
  if (pageState.value === 'forbidden') return 'forbidden'
  if (pageState.value === 'error') return 'error'
  return ''
})

const lastUpdatedLabel = computed(() => {
  const ts = overview.value?.last_updated_at
  if (!ts) return 'Not available yet'
  try { return new Date(ts).toLocaleString() } catch { return 'Not available yet' }
})

const statusHint = computed(() => {
  const s = analysisStatus.value
  if (s === 'queued') return 'Queued'
  if (s === 'running') {
    const p = overview.value?.progress
    if (p?.total_eligible != null) return `Analyzing ${p.processed || 0}/${p.total_eligible}`
    return 'Analyzing'
  }
  if (s === 'partial') return 'Partial'
  if (s === 'failed') return 'Failed'
  if (s === 'ready' || s === 'completed') return 'Complete'
  if (s === 'empty') return 'No eligible leads'
  if (s === 'not_started') return 'Not started'
  return ''
})

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const data = await aiLeadIntelligenceApi.overview()
      overview.value = data
      const s = data?.analysis_status
      if (s !== 'queued' && s !== 'running') {
        stopPolling()
        pageState.value = 'ready'
      }
    } catch {
      /* keep polling until user retries */
    }
  }, 3000)
}

async function loadOverview({ silent = false } = {}) {
  if (!silent) {
    pageState.value = 'loading'
    errorMessage.value = ''
  }
  try {
    overview.value = await aiLeadIntelligenceApi.overview()
    pageState.value = 'ready'
    const s = overview.value?.analysis_status
    if (s === 'queued' || s === 'running') {
      startPolling()
    } else {
      stopPolling()
    }
  } catch (err) {
    if (err?.status === 403) {
      pageState.value = 'forbidden'
      errorMessage.value = err.message || 'No permission'
      return
    }
    pageState.value = 'error'
    errorMessage.value = err?.message || 'Failed to load AI Lead Intelligence'
    toast.value = { type: 'error', message: errorMessage.value }
  }
}

async function refreshAnalysis() {
  pageState.value = 'refreshing'
  try {
    const result = await aiLeadIntelligenceApi.refresh()
    toast.value = {
      type: 'info',
      message: result?.message || 'CRM intelligence aggregation queued.',
    }
    await loadOverview({ silent: true })
    if (overview.value?.analysis_status === 'queued' || overview.value?.analysis_status === 'running') {
      startPolling()
    }
  } catch (err) {
    if (err?.status === 403) { pageState.value = 'forbidden'; return }
    pageState.value = 'error'
    errorMessage.value = err?.message || 'Refresh failed'
    toast.value = { type: 'error', message: errorMessage.value }
  }
}

function openLead(leadId) {
  if (!leadId) return
  openLeadView(leadId)
}
function viewMatches(leadId) { openLead(leadId) }

onMounted(() => loadOverview())
onUnmounted(stopPolling)
</script>
