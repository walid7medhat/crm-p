<template>
  <div class="dm-dash">
    <aside class="dm-side" aria-label="Dev dashboard navigation">
      <div class="dm-brand">
        <span class="dm-logo">⟨⟩</span>
        <div>
          <div class="dm-title">Project map</div>
          <div class="dm-meta">Vue · dev only</div>
        </div>
      </div>

      <nav class="dm-nav">
        <button
          v-for="item in navItems"
          :key="item.id"
          type="button"
          class="dm-nav-btn"
          :class="{ active: activeTab === item.id }"
          @click="activeTab = item.id"
        >
          <span class="dm-ico" aria-hidden="true">{{ item.icon }}</span>
          {{ item.label }}
        </button>
      </nav>

      <div class="dm-side-foot">
        <p v-if="!hasVite" class="dm-hint">
          Live scan needs the Vite dev server. Use <strong>Refresh</strong> or run <code>npm run map:build</code> for static JSON.
        </p>
        <template v-else>
          <p v-if="liveSync && streamConnected" class="dm-live">Live sync on (SSE)</p>
          <p v-else-if="liveSync" class="dm-live dim">Watching files…</p>
          <p class="dm-hint sm">
            Renamed or moved a file under <code>resources/js</code>? Click <strong>Refresh</strong> if the tree lags (or wait ~15s). Only
            <code>.vue</code> / <code>.js</code> / <code>.ts</code> are listed.
          </p>
        </template>
      </div>
    </aside>

    <div class="dm-main">
      <header class="dm-top">
        <div>
          <h1 class="dm-h1">{{ currentNav?.label || 'Dashboard' }}</h1>
          <p class="dm-sub">
            {{ envLabel }}
            · last scan {{ data?.generatedAt || '—' }}
            <span v-if="data?.stats" class="dm-stat">
              · {{ data.stats.routesCount }} routes · {{ data.stats.jsVueFilesApprox }} files
              <template v-if="data?.architecture?.domains?.length">
                · {{ data.architecture.domains.length }} domains
              </template>
            </span>
          </p>
        </div>
        <div class="dm-toolbar">
          <label class="dm-check">
            <input v-model="liveSync" type="checkbox" />
            Live updates
          </label>
          <button type="button" class="dm-btn" :disabled="loading" @click="refresh">
            {{ loading ? 'Scanning…' : 'Refresh' }}
          </button>
          <input
            v-model="query"
            type="search"
            class="dm-search"
            placeholder="Filter current tab…"
            autocomplete="off"
          />
        </div>
      </header>

      <div v-if="error" class="dm-err">Scan: {{ error }}</div>

      <main class="dm-body">
        <Suspense>
          <component :is="activeComponent" />
          <template #fallback>
            <div class="dm-fallback">Loading tab…</div>
          </template>
        </Suspense>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent, provide, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectMapState } from './composables/useProjectMapState.js'

const router = useRouter()

const pm = useProjectMapState()
provide('projectMap', pm)

const {
  data,
  loading,
  query,
  liveSync,
  streamConnected,
  error,
  refresh,
  getViteDevOrigin,
} = pm

const envLabel = import.meta.env.PROD ? 'production' : import.meta.env.MODE || 'development'
const hasVite = computed(() => !!getViteDevOrigin())

const activeTab = ref('architecture')

const navItems = [
  { id: 'architecture', label: 'Architecture', icon: '🧭' },
  { id: 'roadmap', label: 'Roadmap graph', icon: '◫' },
  { id: 'routeflow', label: 'Route flow', icon: '⟿' },
  { id: 'routes', label: 'Routes (list)', icon: '⎈' },
  { id: 'dataflow', label: 'Data flow', icon: '⬡' },
  { id: 'apis', label: 'APIs', icon: '⇄' },
  { id: 'logic', label: 'Logic', icon: '⚙' },
  { id: 'raw', label: 'Raw files', icon: '🗂' },
]

const currentNav = computed(() => navItems.find((n) => n.id === activeTab.value))

function isSuperAdminUser() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    if (u.role === 'super_admin') return true
    return Array.isArray(u.roles) && u.roles.includes('super_admin')
  } catch {
    return false
  }
}

onMounted(() => {
  if (import.meta.env.PROD || !isSuperAdminUser()) {
    router.replace('/')
  }
})

const ArchitectureTreeTab = defineAsyncComponent(() =>
  import('./project-map/tabs/ArchitectureTreeTab.vue'),
)
const RoadmapGraphTab = defineAsyncComponent(() => import('./project-map/tabs/RoadmapGraphTab.vue'))
const RouteFlowTab = defineAsyncComponent(() => import('./project-map/tabs/RouteFlowTab.vue'))
const RoutesTab = defineAsyncComponent(() => import('./project-map/tabs/RoutesTab.vue'))
const DataFlowTab = defineAsyncComponent(() => import('./project-map/tabs/DataFlowTab.vue'))
const ApisTab = defineAsyncComponent(() => import('./project-map/tabs/ApisTab.vue'))
const LogicTab = defineAsyncComponent(() => import('./project-map/tabs/LogicTab.vue'))
const FileStructureTab = defineAsyncComponent(() => import('./project-map/tabs/FileStructureTab.vue'))

const tabComponents = {
  architecture: ArchitectureTreeTab,
  roadmap: RoadmapGraphTab,
  routeflow: RouteFlowTab,
  routes: RoutesTab,
  dataflow: DataFlowTab,
  apis: ApisTab,
  logic: LogicTab,
  raw: FileStructureTab,
}

const activeComponent = computed(() => tabComponents[activeTab.value] || ArchitectureTreeTab)
</script>

<style scoped>
.dm-dash {
  --dm-bg: #0d1117;
  --dm-panel: #161b22;
  --dm-panel2: #0d1117;
  --dm-border: #30363d;
  --dm-fg: #e6edf3;
  --dm-muted: #8b949e;
  --dm-accent: #58a6ff;
  --dm-accent2: #79c0ff;

  min-height: 100vh;
  display: grid;
  grid-template-columns: 240px 1fr;
  background: var(--dm-bg);
  color: var(--dm-fg);
  font-family:
    ui-sans-serif,
    system-ui,
    -apple-system,
    'Segoe UI',
    Roboto,
    sans-serif;
  font-size: 14px;
  line-height: 1.45;
}

.dm-side {
  position: sticky;
  top: 0;
  align-self: start;
  height: 100vh;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--dm-border);
  background: var(--dm-panel);
  padding: 1rem 0.65rem;
}

.dm-brand {
  display: flex;
  gap: 0.65rem;
  align-items: center;
  padding: 0 0.5rem 1rem;
  border-bottom: 1px solid var(--dm-border);
  margin-bottom: 0.75rem;
}
.dm-logo {
  font-size: 1.25rem;
  color: var(--dm-accent);
  font-weight: 700;
}
.dm-title {
  font-weight: 600;
  font-size: 15px;
  letter-spacing: -0.02em;
}
.dm-meta {
  font-size: 11px;
  color: var(--dm-muted);
  margin-top: 0.15rem;
}

.dm-nav {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  flex: 1;
  overflow: auto;
}
.dm-nav-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  text-align: left;
  padding: 0.5rem 0.6rem;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--dm-fg);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
}
.dm-nav-btn:hover {
  background: rgba(56, 139, 253, 0.08);
}
.dm-nav-btn.active {
  background: rgba(56, 139, 253, 0.15);
  color: var(--dm-accent2);
  font-weight: 600;
}
.dm-ico {
  width: 1.25rem;
  text-align: center;
  opacity: 0.85;
}

.dm-side-foot {
  margin-top: auto;
  padding-top: 1rem;
  font-size: 11px;
  color: var(--dm-muted);
  line-height: 1.4;
}
.dm-hint code {
  color: var(--dm-accent2);
  font-size: 10px;
}
.dm-hint.sm {
  margin-top: 0.5rem;
  font-size: 10px;
  line-height: 1.35;
}
.dm-live {
  color: #3fb950;
  font-weight: 500;
}
.dm-live.dim {
  color: var(--dm-muted);
  font-weight: 400;
}

.dm-main {
  min-width: 0;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
.dm-top {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.15rem 1.5rem;
  border-bottom: 1px solid var(--dm-border);
  background: rgba(13, 17, 23, 0.92);
  backdrop-filter: blur(8px);
}
.dm-h1 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  letter-spacing: -0.02em;
}
.dm-sub {
  margin: 0.35rem 0 0;
  font-size: 12px;
  color: var(--dm-muted);
}
.dm-stat {
  color: #6e7681;
}
.dm-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.dm-check {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 12px;
  color: var(--dm-muted);
  cursor: pointer;
  user-select: none;
}
.dm-btn {
  padding: 0.45rem 0.85rem;
  border-radius: 6px;
  border: 1px solid var(--dm-border);
  background: #21262d;
  color: var(--dm-fg);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
}
.dm-btn:hover:not(:disabled) {
  background: #30363d;
}
.dm-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.dm-search {
  width: min(280px, 100%);
  padding: 0.45rem 0.65rem;
  border-radius: 6px;
  border: 1px solid var(--dm-border);
  background: #0d1117;
  color: var(--dm-fg);
  font-size: 13px;
}
.dm-search::placeholder {
  color: #6e7681;
}

.dm-err {
  margin: 0.5rem 1.5rem 0;
  padding: 0.5rem 0.75rem;
  background: #3d1111;
  border: 1px solid #8b2e2e;
  border-radius: 6px;
  color: #ffb4b4;
  font-size: 12px;
}

.dm-body {
  flex: 1;
  padding: 1rem 1.5rem 2rem;
}

.dm-fallback {
  padding: 2rem;
  color: var(--dm-muted);
  font-size: 13px;
}

@media (max-width: 900px) {
  .dm-dash {
    grid-template-columns: 1fr;
  }
  .dm-side {
    position: relative;
    height: auto;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }
  .dm-nav {
    flex-direction: row;
    flex-wrap: wrap;
    flex: 1;
  }
}
</style>
