<template>
  <div class="se-page" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
    <div v-if="!allowed" class="se-denied">
      <iconify-icon icon="lucide:shield-off" width="22" />
      <p>{{ ui.accessDenied }}</p>
      <router-link to="/" class="se-btn">{{ ui.backHome }}</router-link>
    </div>

    <template v-else>
      <aside class="se-side">
        <div class="se-brand">
          <span class="se-logo">◈</span>
          <div>
            <div class="se-brand-title">{{ ui.brand }}</div>
            <div class="se-brand-sub">OIA CRM</div>
          </div>
        </div>

        <div class="se-lang" role="group" aria-label="Language">
          <button type="button" :class="{ active: lang === 'en' }" @click="setLang('en')">EN</button>
          <button type="button" :class="{ active: lang === 'ar' }" @click="setLang('ar')">AR</button>
        </div>

        <nav class="se-nav" :aria-label="ui.brand">
          <button
            v-for="s in sectionList"
            :key="s.id"
            type="button"
            class="se-nav-btn"
            :class="{ active: section === s.id }"
            @click="goSection(s.id)"
          >
            <iconify-icon :icon="s.icon" width="13" height="13" />
            {{ s.label }}
          </button>
        </nav>

        <div class="se-side-meta">
          <div>{{ ui.catalogMeta }} v{{ catalog.version }}</div>
          <div>{{ catalog.generatedAt }}</div>
        </div>
      </aside>

      <div class="se-main">
        <header class="se-top">
          <div class="se-top-text">
            <h1 class="se-h1">{{ currentSectionLabel }}</h1>
          </div>
          <div class="se-search-wrap">
            <iconify-icon icon="lucide:search" width="14" height="14" class="se-search-ico" />
            <input
              v-model="query"
              type="search"
              class="se-search"
              :placeholder="ui.searchPlaceholder"
              autocomplete="off"
              @keydown.enter.prevent="openFirstResult"
            />
            <div v-if="query.trim() && searchHits.length" class="se-search-drop">
              <button
                v-for="hit in searchHits.slice(0, 12)"
                :key="hit.id"
                type="button"
                class="se-search-item"
                @click="openHit(hit)"
              >
                <span class="se-search-type">{{ hit.type }}</span>
                <span>{{ hit.title }}</span>
              </button>
            </div>
            <div v-else-if="query.trim()" class="se-search-drop se-empty">{{ ui.noResults }}</div>
          </div>
        </header>

        <main class="se-body">
          <!-- Overview -->
          <section v-show="section === 'overview'" class="se-panel">
            <h2>{{ ui.overviewTitle }}</h2>
            <p>{{ ui.overviewBody }}</p>

            <div class="se-cards">
              <div class="se-card">
                <h3>{{ ui.layersTitle }}</h3>
                <ul class="se-list">
                  <li><strong>Frontend</strong> — {{ catalog.stack.frontend.name }} · <SeFilePath :path="catalog.stack.spaEntry" /></li>
                  <li><strong>Backend</strong> — {{ catalog.stack.backend.name }} · <SeFilePath :path="catalog.stack.apiRoutes" /></li>
                  <li><strong>Auth</strong> — {{ catalog.stack.auth.name }}</li>
                  <li><strong>API</strong> — <code>{{ catalog.stack.apiPrefix }}</code></li>
                </ul>
              </div>
              <div class="se-card">
                <h3>{{ ui.coverageTitle }}</h3>
                <ul class="se-stats">
                  <li><strong>{{ stats.modules }}</strong> modules</li>
                  <li><strong>{{ stats.features }}</strong> features</li>
                  <li><SeStatusBadge status="verified" /> {{ stats.verified }}</li>
                  <li><SeStatusBadge status="partial" /> {{ stats.partial }}</li>
                </ul>
                <p class="se-hint">{{ catalog.source }}</p>
              </div>
            </div>

            <h3>{{ ui.statusLegend }}</h3>
            <div class="se-legend">
              <SeStatusBadge status="verified" />
              <SeStatusBadge status="partial" />
              <SeStatusBadge status="needs_verification" />
            </div>

            <h3>Architecture</h3>
            <SeFlow :mermaid="catalog.architectureMermaid" :steps="archSteps" />

            <h3>{{ ui.risksTitle }}</h3>
            <ul class="se-list">
              <li v-for="(r, i) in ui.risks" :key="i">{{ r }}</li>
            </ul>
            <p class="se-hint">{{ ui.refreshHint }}</p>
            <div class="se-actions">
              <router-link class="se-btn ghost" to="/system-overview">{{ ui.openSystemOverview }}</router-link>
              <router-link class="se-btn ghost" to="/project-map">{{ ui.openProjectMap }}</router-link>
            </div>
          </section>

          <!-- Modules -->
          <section v-show="section === 'modules'" class="se-panel">
            <article
              v-for="m in catalog.modules"
              :id="'mod-' + m.id"
              :key="m.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === m.id }"
            >
              <header class="se-article-h">
                <iconify-icon :icon="m.icon" width="14" height="14" />
                <h2>{{ L(m.name) }}</h2>
                <SeStatusBadge :status="m.status" />
              </header>
              <p>{{ L(m.purpose) }}</p>
              <div class="se-grid2">
                <div>
                  <h4>Frontend</h4>
                  <SeFilePath v-for="f in m.frontend || []" :key="f.path" :path="f.path" />
                  <div v-if="m.vueRoutes?.length" class="se-chips">
                    <code v-for="r in m.vueRoutes" :key="r">{{ r }}</code>
                  </div>
                </div>
                <div>
                  <h4>API / Backend</h4>
                  <div v-for="(a, i) in m.api || []" :key="i" class="se-api-row">
                    <code>{{ a.method }}</code> {{ a.path }}
                    <div class="se-muted">{{ a.controller }}</div>
                  </div>
                  <SeFilePath v-for="s in m.services || []" :key="s" :path="s" />
                  <SeFilePath v-for="mo in m.models || []" :key="mo" :path="mo" />
                </div>
              </div>
              <div v-if="m.tables?.length" class="se-chips">
                <span v-for="tb in m.tables" :key="tb" class="se-chip">{{ tb }}</span>
              </div>
              <div v-if="m.permissions?.length" class="se-muted">Permissions: {{ m.permissions.join(' · ') }}</div>
              <div v-if="m.related?.length" class="se-muted">{{ ui.related }}: {{ m.related.join(', ') }}</div>
            </article>
          </section>

          <!-- Frontend -->
          <section v-show="section === 'frontend'" class="se-panel">
            <article class="se-article">
              <h2>Bootstrap</h2>
              <p>{{ L(catalog.frontendLayer.bootstrap) }}</p>
              <SeFilePath :path="catalog.frontendLayer.bootstrap.path" />
            </article>
            <article class="se-article">
              <h2>Router</h2>
              <p>{{ L(catalog.frontendLayer.router) }}</p>
              <SeFilePath :path="catalog.frontendLayer.router.path" />
            </article>
            <article class="se-article">
              <h2>Layout</h2>
              <p>{{ L(catalog.frontendLayer.layout) }}</p>
              <SeFilePath :path="catalog.frontendLayer.layout.path" />
            </article>
            <article class="se-article">
              <h2>API client</h2>
              <p>{{ L(catalog.frontendLayer.apiClient) }}</p>
              <SeFilePath :path="catalog.frontendLayer.apiClient.path" />
            </article>
            <article class="se-article">
              <h2>State</h2>
              <p>{{ L(catalog.frontendLayer.state) }}</p>
              <SeFilePath :path="catalog.frontendLayer.state.path" />
              <SeStatusBadge :status="catalog.frontendLayer.state.status || 'partial'" />
            </article>
            <h3>Example Vue routes (from router.js)</h3>
            <ul class="se-list">
              <li v-for="m in catalog.modules" :key="m.id">
                <strong>{{ L(m.name) }}</strong>:
                <code v-for="r in m.vueRoutes || []" :key="r" class="se-inline-code">{{ r }}</code>
              </li>
            </ul>
          </section>

          <!-- Backend -->
          <section v-show="section === 'backend'" class="se-panel">
            <article v-for="(block, key) in backendBlocks" :key="key" class="se-article">
              <h2>{{ block.title }}</h2>
              <p>{{ block.text }}</p>
              <SeFilePath :path="block.path" />
            </article>
          </section>

          <!-- Database -->
          <section v-show="section === 'database'" class="se-panel">
            <SeFlow :mermaid="catalog.erMermaid" :steps="erSteps" />
            <article
              v-for="tb in catalog.tables"
              :id="'tbl-' + tb.id"
              :key="tb.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === tb.id }"
            >
              <header class="se-article-h">
                <h2><code>{{ tb.id }}</code></h2>
                <SeStatusBadge :status="tb.status" />
              </header>
              <p>{{ L(tb.purpose) }}</p>
              <SeFilePath :path="tb.model" />
              <h4>Relations</h4>
              <ul class="se-list">
                <li v-for="(rel, i) in tb.relations || []" :key="i">
                  <code>{{ rel.type }}</code> → <strong>{{ rel.to }}</strong>
                  <span v-if="rel.via"> via <code>{{ rel.via }}</code></span>
                  <span class="se-muted"> — {{ rel.evidence }}</span>
                </li>
              </ul>
              <p v-if="tb.notes" class="se-hint">{{ L(tb.notes) }}</p>
            </article>
          </section>

          <!-- Business / Features -->
          <section v-show="section === 'business'" class="se-panel">
            <div class="se-mode">
              <button type="button" :class="{ active: mode === 'simple' }" @click="mode = 'simple'">{{ ui.simpleMode }}</button>
              <button type="button" :class="{ active: mode === 'deep' }" @click="mode = 'deep'">{{ ui.deepMode }}</button>
            </div>
            <article
              v-for="f in catalog.features"
              :id="'feat-' + f.id"
              :key="f.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === f.id }"
            >
              <header class="se-article-h">
                <h2>{{ L(f.name) }}</h2>
                <SeStatusBadge :status="f.status" />
              </header>
              <p class="se-muted">{{ L(f.who) }}</p>
              <p>{{ L(f.simple) }}</p>
              <h3>{{ ui.howItWorks }}</h3>
              <ol v-if="mode === 'simple'" class="se-flow-steps se-compact">
                <li v-for="(step, i) in f.howItWorks" :key="i">
                  <span class="se-flow-num">{{ i + 1 }}</span>
                  <div>
                    <strong>{{ step.layer }}</strong> — {{ L(step) }}
                  </div>
                </li>
              </ol>
              <ol v-else class="se-flow-steps">
                <li v-for="(step, i) in f.howItWorks" :key="i">
                  <span class="se-flow-num">{{ i + 1 }}</span>
                  <div>
                    <strong>{{ step.layer }}</strong> — {{ L(step) }}
                    <SeFilePath v-if="step.path" :path="step.path" />
                    <SeStatusBadge v-if="step.statusNote === 'partial'" status="partial" />
                  </div>
                </li>
              </ol>
              <h4>{{ ui.filesFirst }}</h4>
              <SeFilePath v-for="p in f.filesFirst || []" :key="p" :path="p" />
              <h4>{{ ui.debug }}</h4>
              <p>{{ L(f.debug) }}</p>
            </article>
          </section>

          <!-- Integrations -->
          <section v-show="section === 'integrations'" class="se-panel">
            <article
              v-for="it in catalog.integrations"
              :id="'int-' + it.id"
              :key="it.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === it.id }"
            >
              <header class="se-article-h">
                <h2>{{ L(it.name) }}</h2>
                <SeStatusBadge :status="it.status" />
              </header>
              <p>{{ L(it.purpose) }}</p>
              <p><strong>Trigger:</strong> {{ L(it.trigger) }}</p>
              <p><strong>Mapping:</strong> {{ L(it.mapping) }}</p>
              <div class="se-chips">
                <code v-for="r in it.routes || []" :key="r">{{ r }}</code>
              </div>
              <SeFilePath v-for="f in it.files || []" :key="f" :path="f" />
              <p v-if="it.configKeysOnly" class="se-hint">Config keys (names only): {{ Array.isArray(it.configKeysOnly) ? it.configKeysOnly.join(', ') : it.configKeysOnly }}</p>
              <ul v-if="it.unknowns?.length" class="se-list">
                <li v-for="(u, i) in it.unknowns" :key="i"><SeStatusBadge status="needs_verification" /> {{ u }}</li>
              </ul>
            </article>
          </section>

          <!-- Workflows -->
          <section v-show="section === 'workflows'" class="se-panel">
            <article
              v-for="w in catalog.workflows"
              :id="'wf-' + w.id"
              :key="w.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === w.id }"
            >
              <header class="se-article-h">
                <h2>{{ L(w.name) }}</h2>
                <SeStatusBadge :status="w.status" />
              </header>
              <p>{{ L(w.summary) }}</p>
              <SeFlow :steps="w.steps" :mermaid="w.mermaid" />
            </article>
          </section>

          <!-- File explorer -->
          <section v-show="section === 'files'" class="se-panel">
            <p class="se-hint">{{ ui.refreshHint }}</p>
            <input v-model="fileFilter" type="search" class="se-search se-search-inline" :placeholder="ui.searchPlaceholder" />
            <ul class="se-file-list">
              <li v-for="row in filteredFiles" :key="row.path">
                <SeFilePath :path="row.path" />
                <span class="se-muted">{{ row.from }}</span>
              </li>
            </ul>
          </section>

          <!-- Troubleshooting -->
          <section v-show="section === 'troubleshoot'" class="se-panel">
            <article
              v-for="ts in catalog.troubleshooting"
              :id="'ts-' + ts.id"
              :key="ts.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === ts.id }"
            >
              <header class="se-article-h">
                <h2>{{ L(ts.title) }}</h2>
                <SeStatusBadge :status="ts.status" />
              </header>
              <ol class="se-flow-steps">
                <li v-for="(step, i) in ts.order" :key="i">
                  <span class="se-flow-num">{{ i + 1 }}</span>
                  <div>{{ L(step) }}</div>
                </li>
              </ol>
              <SeFilePath v-for="f in ts.files || []" :key="f" :path="f" />
            </article>
          </section>

          <!-- Glossary -->
          <section v-show="section === 'glossary'" class="se-panel">
            <article
              v-for="g in catalog.glossary"
              :id="'gl-' + g.id"
              :key="g.id"
              class="se-article"
              :class="{ 'se-flash': highlightId === g.id }"
            >
              <h2>{{ L(g.term) }}</h2>
              <p>{{ L(g.def) }}</p>
            </article>
          </section>
        </main>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { catalog, buildSearchIndex, coverageStats } from './data/catalog.js'
import { useSystemExplorerLang } from './composables/useSystemExplorerLang.js'
import SeStatusBadge from './components/SeStatusBadge.vue'
import SeFilePath from './components/SeFilePath.vue'
import SeFlow from './components/SeFlow.vue'

const { lang, setLang, t, L } = useSystemExplorerLang()
const ui = computed(() => t.value)

const allowed = ref(false)
const section = ref('overview')
const query = ref('')
const fileFilter = ref('')
const mode = ref('deep')
const highlightId = ref('')

onMounted(() => {
  try {
    const roles = JSON.parse(localStorage.getItem('user') || '{}')?.roles || []
    allowed.value = Array.isArray(roles) && roles.includes('super_admin')
  } catch {
    allowed.value = false
  }
})

const stats = computed(() => coverageStats())

const sectionList = computed(() => {
  const s = ui.value.sections
  return [
    { id: 'overview', label: s.overview, icon: 'lucide:layout-dashboard' },
    { id: 'modules', label: s.modules, icon: 'lucide:boxes' },
    { id: 'frontend', label: s.frontend, icon: 'lucide:panel-left' },
    { id: 'backend', label: s.backend, icon: 'lucide:server' },
    { id: 'database', label: s.database, icon: 'lucide:database' },
    { id: 'business', label: s.business, icon: 'lucide:git-branch' },
    { id: 'integrations', label: s.integrations, icon: 'lucide:plug' },
    { id: 'workflows', label: s.workflows, icon: 'lucide:workflow' },
    { id: 'files', label: s.files, icon: 'lucide:folder-tree' },
    { id: 'troubleshoot', label: s.troubleshoot, icon: 'lucide:life-buoy' },
    { id: 'glossary', label: s.glossary, icon: 'lucide:book-open' },
  ]
})

const currentSectionLabel = computed(() => sectionList.value.find((x) => x.id === section.value)?.label || '')

const searchIndex = computed(() => buildSearchIndex(lang.value))
const searchHits = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return []
  return searchIndex.value.filter((r) => r.haystack.includes(q))
})

const archSteps = computed(() => [
  { id: '1', en: 'Browser Vue SPA (main.js + router)', ar: 'واجهة Vue (main.js + router)', paths: ['resources/js/main.js'] },
  { id: '2', en: 'axios → /api', ar: 'axios → /api', paths: ['resources/js/plugins/axios.js'] },
  { id: '3', en: 'jwt.auth + Spatie', ar: 'jwt.auth + Spatie', paths: ['JwtAuthMiddleware'] },
  { id: '4', en: 'Api Controllers → Services/Jobs', ar: 'Controllers → Services/Jobs', paths: ['app/Http/Controllers/Api/'] },
  { id: '5', en: 'Eloquent → Database', ar: 'Eloquent → قاعدة البيانات', paths: ['app/Models/'] },
])

const erSteps = computed(() =>
  catalog.tables.slice(0, 6).map((tb) => ({
    id: tb.id,
    en: `${tb.id}: ${tb.purpose.en}`,
    ar: `${tb.id}: ${tb.purpose.ar}`,
    paths: [tb.model],
  })),
)

const backendBlocks = computed(() => {
  const b = catalog.backendLayer
  return [
    { title: 'Routing', text: L(b.routing), path: b.routing.path },
    { title: 'Auth', text: L(b.auth), path: b.auth.path },
    { title: 'Controllers', text: L(b.controllers), path: b.controllers.path },
    { title: 'Services', text: L(b.services), path: b.services.path },
    { title: 'Models', text: L(b.models), path: b.models.path },
    { title: 'Jobs / Events', text: L(b.jobsEvents), path: b.jobsEvents.path },
    { title: 'Schedule', text: L(b.schedule), path: b.schedule.path },
  ]
})

const fileRows = computed(() => {
  const rows = []
  for (const m of catalog.modules) {
    for (const f of m.frontend || []) rows.push({ path: f.path, from: L(m.name) })
    for (const s of m.services || []) rows.push({ path: s, from: L(m.name) })
    for (const mo of m.models || []) rows.push({ path: mo, from: L(m.name) })
  }
  for (const f of catalog.features) {
    for (const p of f.filesFirst || []) rows.push({ path: p, from: L(f.name) })
  }
  const seen = new Set()
  return rows.filter((r) => {
    if (!r.path || seen.has(r.path)) return false
    seen.add(r.path)
    return true
  })
})

const filteredFiles = computed(() => {
  const q = fileFilter.value.trim().toLowerCase()
  if (!q) return fileRows.value
  return fileRows.value.filter((r) => r.path.toLowerCase().includes(q) || r.from.toLowerCase().includes(q))
})

function goSection(id) {
  section.value = id
  query.value = ''
}

function openHit(hit) {
  section.value = hit.section
  query.value = ''
  highlightId.value = hit.targetId
  nextTick(() => {
    const prefix =
      hit.type === 'module' ? 'mod' :
      hit.type === 'feature' ? 'feat' :
      hit.type === 'table' ? 'tbl' :
      hit.type === 'integration' ? 'int' :
      hit.type === 'workflow' ? 'wf' :
      hit.type === 'glossary' ? 'gl' :
      hit.type === 'troubleshoot' ? 'ts' : null
    if (prefix) {
      document.getElementById(`${prefix}-${hit.targetId}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
    setTimeout(() => { highlightId.value = '' }, 1800)
  })
}

function openFirstResult() {
  if (searchHits.value[0]) openHit(searchHits.value[0])
}

watch(lang, () => {
  /* search index recomputes via computed */
})
</script>

<style src="./system-explorer.css"></style>
