<template>
  <div class="dashboard-main-body sales-intelligence-page">
    <div class="si-shell">
    <SiTopBar
      :rules="rules"
      :active-tab="activeTab"
      :system-busy="systemBusy"
      :data-stale="dataStale"
      @distribute="goTab('distribution')"
      @ai-suggest="goTab('ai')"
      @pick-agent="onPickAgent"
      @pick-lead="onPickLead"
      @pick-rule="onPickRule"
      @palette-exec="onPaletteExec"
    />

    <SiTabsBar v-model="activeTab" />

    <p v-if="bootError" class="si-app__alert">{{ bootError }}</p>

    <div class="si-app__body">
      <main class="si-app__main">
        <div v-if="tabState.loadingTab" class="si-app__tabload">Loading {{ tabState.loadingTab }}…</div>

        <section v-show="activeTab === 'overview'" v-if="loaded.overview" class="si-pane">
          <OverviewCards
            :overview="overview"
            :distributions-today="distributionsToday"
            :loading="tabState.loadingTab === 'overview'"
          />
        </section>

        <section v-show="activeTab === 'agents'" v-if="loaded.agents" class="si-pane">
          <AgentTable
            v-model:filter="agentFilterQuery"
            :agents="pagedAgents"
            :loading="agentsLoading"
            :page="agentPage"
            :total-pages="agentTotalPages"
            :total="agentsSorted.length"
            :sort-key="agentSortKey"
            :sort-dir="agentSortDir"
            :recommended-id="recommendedAgentId"
            :selected-id="drawerAgent?.id ?? null"
            :pulse-ids="pulseAgentIds"
            :assist-line="agentTableAssistLine"
            @select="openDrawer"
            @page-change="agentPage = $event"
            @sort-change="onAgentSort"
          />
        </section>

        <section v-show="activeTab === 'rules'" v-if="loaded.rules" class="si-pane">
          <ScoringRules
            v-model:rules="rules"
            :loading="rulesLoading"
            :live-preview="preview.preview"
            :live-preview-loading="preview.loading"
            :impact-user-id="liveUserId"
            :impact-user-label="impactUserLabel"
            @rules-changed="onRulesChanged"
          />
        </section>

        <section v-show="activeTab === 'distribution'" v-if="loaded.distribution" class="si-pane">
          <DistributionPanel
            :server-max-leads="settings?.max_leads_per_agent_per_day ?? 15"
            :external-lead-id="prefillLeadId"
            :agents="agents"
            :recommended-user-id="recommendedAgentId"
            :recommended-label="recommendedAgentName"
            recommended-reason="Highest composite in loaded pool (client heuristic)."
          />
        </section>

        <section v-show="activeTab === 'ai'" v-if="loaded.ai" class="si-pane">
          <AIPanel :server-mode="settings?.ai_mode || 'hybrid'" />
        </section>

        <section v-show="activeTab === 'logs'" v-if="loaded.logs" class="si-pane">
          <LogsTimeline :logs="logs" :agents="agents" :loading="logsLoading" @reload="loadLogs" />
        </section>
      </main>

      <aside class="si-app__rail">
        <SiInsightsRail
          v-model:live-user-id="liveUserId"
          :agents="agents"
          :overview="overview"
          :distributions-today="distributionsToday"
          :preview="preview.preview"
          :preview-loading="preview.loading"
          @go-ai="goTab('ai')"
        />
      </aside>
    </div>

    <AgentDrawer
      :open="!!drawerAgent"
      :agent="drawerAgent"
      :preview="drawerPreview"
      :previous-preview="drawerPreviewPrevious"
      :loading="drawerLoading"
      @close="closeDrawer"
    />
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import SiTopBar from './components/SiTopBar.vue'
import SiTabsBar from './components/SiTabsBar.vue'
import SiInsightsRail from './components/SiInsightsRail.vue'
import OverviewCards from './components/OverviewCards.vue'
import AgentTable from './components/AgentTable.vue'
import AgentDrawer from './components/AgentDrawer.vue'
import ScoringRules from './components/ScoringRules.vue'
import DistributionPanel from './components/DistributionPanel.vue'
import AIPanel from './components/AIPanel.vue'
import LogsTimeline from './components/LogsTimeline.vue'
import { useAgents } from '@/composables/useAgents'
import { useScoring } from '@/composables/useScoring'
import { useDistribution } from '@/composables/useDistribution'
import { useSalesIntelligencePreview } from '@/composables/useSalesIntelligencePreview'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'

const { agents, loading: agentsLoading, fetchAgents } = useAgents()
const { rules, loading: rulesLoading, fetchRules } = useScoring()
const { logsRaw, loadingLogs: logsLoading, fetchLogs } = useDistribution()

const overview = ref({})
const settings = ref(null)
const bootError = ref('')

const activeTab = ref('overview')
const loaded = reactive({
  overview: false,
  agents: false,
  rules: false,
  distribution: false,
  ai: false,
  logs: false,
})
const tabState = reactive({ loadingTab: null })

const liveUserId = ref('')
const preview = useSalesIntelligencePreview(rules, 400)

const drawerAgent = ref(null)
const drawerPreview = ref(null)
const drawerPreviewPrevious = ref(null)
const drawerLoading = ref(false)

const pulseAgentIds = ref([])
let agentsScoreSig = ''

const recommendedAgentId = computed(() => {
  const list = (agents.value || []).filter((a) => a.score != null && !Number.isNaN(Number(a.score)))
  if (!list.length) return null
  const top = list.reduce((a, b) => (Number(a.score) >= Number(b.score) ? a : b))
  return top.id
})

const recommendedAgentName = computed(() => {
  const id = recommendedAgentId.value
  if (id == null) return ''
  const a = (agents.value || []).find((x) => x.id === id)
  return a?.name || ''
})

const agentTableAssistLine = computed(() => {
  const d = drawerAgent.value
  const rid = recommendedAgentId.value
  if (!d || rid == null || d.id === rid) return ''
  const rec = (agents.value || []).find((a) => a.id === rid)
  if (!rec) return ''
  const da = Number(d.score)
  const dr = Number(rec.score)
  if (Number.isNaN(da) || Number.isNaN(dr)) return ''
  const diff = Math.round(da - dr)
  if (diff === 0) return `Same composite as assist pick (${rec.name}).`
  return diff > 0
    ? `+${diff} pts vs assist pick (${rec.name}) on server snapshot.`
    : `${diff} pts vs assist pick (${rec.name}) on server snapshot.`
})

watch(drawerPreview, (_nv, ov) => {
  if (!ov) return
  try {
    drawerPreviewPrevious.value = JSON.parse(JSON.stringify(ov))
  } catch {
    drawerPreviewPrevious.value = ov
  }
})

watch(
  () => (agents.value || []).map((a) => `${a.id}:${a.score}`).join(','),
  (sig) => {
    if (!sig) return
    if (!agentsScoreSig) {
      agentsScoreSig = sig
      return
    }
    if (sig === agentsScoreSig) return
    const oldMap = {}
    for (const p of agentsScoreSig.split(',')) {
      const [id, sc] = p.split(':')
      if (id) oldMap[id] = sc
    }
    const ids = []
    for (const p of sig.split(',')) {
      const [id, sc] = p.split(':')
      if (id && oldMap[id] != null && oldMap[id] !== sc) ids.push(Number(id))
    }
    agentsScoreSig = sig
    pulseAgentIds.value = ids
    if (ids.length) {
      window.setTimeout(() => {
        pulseAgentIds.value = []
      }, 900)
    }
  }
)

const prefillLeadId = ref(null)

const agentPage = ref(1)
const agentPageSize = 12
const agentFilterQuery = ref('')
const agentSortKey = ref('score')
const agentSortDir = ref('desc')

const STALE_MS = 2 * 60 * 60 * 1000

const systemBusy = computed(
  () =>
    !!tabState.loadingTab ||
    agentsLoading.value ||
    rulesLoading.value ||
    logsLoading.value ||
    drawerLoading.value ||
    preview.loading.value
)

const dataStale = computed(() => {
  const t = overview.value?.last_calculated_at
  if (!t) return false
  const ts = new Date(t).getTime()
  if (Number.isNaN(ts)) return false
  return Date.now() - ts > STALE_MS
})

const impactUserLabel = computed(() => {
  const id = liveUserId.value
  if (!id) return ''
  const a = (agents.value || []).find((x) => String(x.id) === String(id))
  return a?.name || `User ${id}`
})

const logs = computed(() => logsRaw.value || [])

const distributionsToday = computed(() => {
  const start = new Date()
  start.setHours(0, 0, 0, 0)
  return logs.value.filter((l) => new Date(l.created_at) >= start).length
})

const agentsFiltered = computed(() => {
  const q = agentFilterQuery.value.trim().toLowerCase()
  const list = agents.value || []
  if (!q) return list
  return list.filter(
    (a) =>
      (a.name || '').toLowerCase().includes(q) ||
      String(a.email || '')
        .toLowerCase()
        .includes(q)
  )
})

function tierOrder(rank) {
  const x = String(rank || '').toLowerCase()
  if (x === 'hot') return 3
  if (x === 'warm') return 2
  return 1
}

const agentsSorted = computed(() => {
  const list = [...agentsFiltered.value]
  const k = agentSortKey.value
  const d = agentSortDir.value === 'asc' ? 1 : -1
  list.sort((a, b) => {
    if (k === 'name') return d * String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' })
    if (k === 'tier') return d * (tierOrder(a.rank) - tierOrder(b.rank))
    if (k === 'at') {
      const ta = a.calculated_at ? new Date(a.calculated_at).getTime() : 0
      const tb = b.calculated_at ? new Date(b.calculated_at).getTime() : 0
      return d * (ta - tb)
    }
    const sa = Number(a.score)
    const sb = Number(b.score)
    const na = Number.isNaN(sa) ? -1 : sa
    const nb = Number.isNaN(sb) ? -1 : sb
    return d * (na - nb)
  })
  return list
})

const pagedAgents = computed(() => {
  const start = (agentPage.value - 1) * agentPageSize
  return agentsSorted.value.slice(start, start + agentPageSize)
})

const agentTotalPages = computed(() => Math.max(1, Math.ceil(agentsSorted.value.length / agentPageSize)))

watch(
  () => agentsSorted.value.length,
  () => {
    if (agentPage.value > agentTotalPages.value) agentPage.value = agentTotalPages.value
  }
)

watch(agentFilterQuery, () => {
  agentPage.value = 1
})

function rulesPayload() {
  return rules.value.map((r) => ({
    factor_name: r.factor_name,
    weight: Number(r.weight),
    low_value: r.low_value,
    medium_value: r.medium_value,
    high_value: r.high_value,
    direction: r.direction || 'higher_better',
  }))
}

async function loadOverviewSettings() {
  const [o, s] = await Promise.all([salesIntelligenceApi.overview(), salesIntelligenceApi.settings()])
  overview.value = o || {}
  settings.value = s || {}
}

async function loadLogs() {
  await fetchLogs(50)
}

const loaders = {
  overview: async () => {
    await Promise.all([loadOverviewSettings(), loadLogs()])
  },
  agents: async () => {
    await fetchAgents('')
  },
  rules: async () => {
    await fetchRules()
  },
  distribution: async () => {
    if (!settings.value) await loadOverviewSettings()
  },
  ai: async () => {
    if (!settings.value) await loadOverviewSettings()
  },
  logs: async () => {
    await loadLogs()
  },
}

async function ensureTab(tabId) {
  if (!loaders[tabId] || loaded[tabId]) return
  tabState.loadingTab = tabId
  bootError.value = ''
  try {
    await loaders[tabId]()
    loaded[tabId] = true
  } catch (e) {
    bootError.value = e?.message || 'Failed to load section'
  } finally {
    tabState.loadingTab = null
  }
}

async function goTab(id) {
  activeTab.value = id
  await ensureTab(id)
}

watch(activeTab, (t) => ensureTab(t), { immediate: true })

async function loadDrawerPreview(agent) {
  if (!agent?.id) return
  drawerLoading.value = true
  try {
    drawerPreview.value = await salesIntelligenceApi.previewScore({
      user_id: agent.id,
      rules: rulesPayload(),
    })
  } catch {
    drawerPreview.value = null
  } finally {
    drawerLoading.value = false
  }
}

function openDrawer(agent) {
  drawerAgent.value = agent
  loadDrawerPreview(agent)
}

function closeDrawer() {
  drawerAgent.value = null
  drawerPreview.value = null
  drawerPreviewPrevious.value = null
}

function onRulesChanged() {
  if (liveUserId.value) preview.schedule(Number(liveUserId.value))
  if (drawerAgent.value) loadDrawerPreview(drawerAgent.value)
}

watch(liveUserId, (v) => {
  if (v) preview.schedule(Number(v))
  else preview.cancel()
})

watch(
  rules,
  () => {
    if (drawerAgent.value) loadDrawerPreview(drawerAgent.value)
  },
  { deep: true }
)

function onPickAgent(row) {
  const full = agents.value.find((a) => a.id === row.id)
  goTab('agents')
  openDrawer(
    full || {
      id: row.id,
      name: row.title,
      email: row.subtitle,
      avatar: null,
      rank: row.rank,
      score: row.score,
    }
  )
}

function onPickLead(row) {
  prefillLeadId.value = row.id
  goTab('distribution')
}

function onPickRule() {
  goTab('rules')
}

function onAgentSort({ key, dir }) {
  agentSortKey.value = key
  agentSortDir.value = dir
  agentPage.value = 1
}

async function onPaletteExec(e) {
  if (!e || !e.type) return
  if (e.type === 'chain' && Array.isArray(e.steps)) {
    for (const step of e.steps) {
      await onPaletteExec(step)
      await new Promise((r) => setTimeout(r, 140))
    }
    return
  }
  if (e.type === 'open-top-agent') {
    const list = (agents.value || []).filter((a) => a.score != null && !Number.isNaN(Number(a.score)))
    if (!list.length) return
    const top = list.reduce((a, b) => (Number(a.score) >= Number(b.score) ? a : b))
    await goTab('agents')
    openDrawer(top)
    return
  }
  if (e.type === 'nav') {
    await goTab(e.tab)
    return
  }
  if (e.type === 'distribute') {
    await goTab('distribution')
    return
  }
  if (e.type === 'ai') {
    await goTab('ai')
    return
  }
  if (e.type === 'reload-logs') {
    await goTab('logs')
    await loadLogs()
  }
}

</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* High-density SaaS shell: soft gray canvas, white surface */
.sales-intelligence-page {
  width: 100%;
  max-width: none;
  margin: 0;
  box-sizing: border-box;
  min-height: calc(100vh - var(--app-topbar-height, 3.25rem));
  padding: 8px;
  background: #f3f4f6;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  color: #111827;
}

.si-shell {
  --si-focus-ring: 0 0 0 2px #fff, 0 0 0 4px #111827;
  --si-ease: 0.16s ease;
  width: 100%;
  max-width: none;
  margin: 0;
  box-sizing: border-box;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  padding: 8px 10px 10px;
}

.si-app__alert {
  margin: 8px 0 0;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid #fecaca;
  background: #fff5f5;
  color: #991b1b;
  font-size: 12px;
}

.si-app__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(260px, 20vw);
  gap: 10px;
  align-items: start;
  margin-top: 2px;
  max-height: calc(100vh - 88px);
}

.si-app__main {
  min-height: 0;
  max-height: calc(100vh - 88px);
  overflow: auto;
  padding-bottom: 4px;
}

.si-app__rail {
  position: sticky;
  top: 80px;
  max-height: calc(100vh - 88px);
  overflow: auto;
  padding-bottom: 4px;
}

.si-pane {
  padding: 2px 0 4px;
  animation: si-pane-in 0.18s ease-out;
}

@keyframes si-pane-in {
  from {
    opacity: 0.55;
  }
  to {
    opacity: 1;
  }
}

.si-app__tabload {
  font-size: 12px;
  color: #6b7280;
  padding: 8px 0;
}

@media (max-width: 1024px) {
  .si-app__body {
    grid-template-columns: 1fr;
    max-height: none;
  }

  .si-app__main {
    max-height: none;
  }

  .si-app__rail {
    position: relative;
    top: 0;
    max-height: none;
    order: -1;
  }
}

@media (max-width: 768px) {
  .sales-intelligence-page {
    padding: 8px;
  }

  .si-shell {
    border-radius: 10px;
    padding: 10px 10px 12px;
  }
}
</style>
