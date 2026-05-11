<template>
  <div class="so-page" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
    <transition name="fade">
      <div v-if="accessDenied" class="so-denied">
        <iconify-icon icon="lucide:shield-off" class="so-denied-icon" />
        <div>{{ t('page.accessDenied') }}</div>
        <router-link to="/" class="so-link-home">{{ t('page.backHome') }}</router-link>
      </div>
    </transition>

    <template v-if="!accessDenied && ready">
      <aside class="so-sidebar">
        <div class="so-brand">
          <span class="so-brand-mark">◇</span>
          <div>
            <div class="so-brand-title">
              {{ mapMode ? t('page.sidebarSystemMap') : t('page.sidebarProductMap') }}
            </div>
            <div class="so-brand-sub">
              {{ mapMode ? t('page.sidebarArchitectureView') : t('page.sidebarCommercialOs') }}
            </div>
          </div>
        </div>

        <nav v-if="!mapMode" class="so-nav" :aria-label="lang === 'en' ? 'Module sections' : 'أقسام الموديولات'">
          <a
            v-for="item in moduleNavI18n"
            :key="item.id"
            :href="'#' + item.id"
            class="so-nav-item"
            :class="{ active: activeSection === item.id }"
            @click.prevent="scrollTo(item.id)"
          >
            <iconify-icon :icon="item.icon" class="so-nav-ic" />
            <span>{{ item.label }}</span>
          </a>
        </nav>
        <div v-else class="so-map-hint">
          <iconify-icon icon="lucide:map" class="so-map-hint-ic" />
          <div class="so-map-hint-txt">{{ t('page.mapHint') }}</div>
        </div>

        <div class="so-side-controls">
          <div class="so-view-toggle-wrap">
            <span class="so-view-label">{{ t('page.viewLabel') }}</span>
            <div class="so-view-toggle" role="group" :aria-label="t('page.viewLabel')">
              <button
                type="button"
                class="so-view-btn"
                :class="{ active: !mapMode }"
                @click="mapMode = false"
              >
                {{ t('page.viewModules') }}
              </button>
              <button
                type="button"
                class="so-view-btn"
                :class="{ active: mapMode }"
                @click="mapMode = true"
              >
                {{ t('page.viewSystemMap') }}
              </button>
            </div>
          </div>

          <label class="so-toggle">
            <input v-model="demoMode" type="checkbox" />
            <span class="so-toggle-ui" />
            <span>{{ t('page.demoMode') }}</span>
          </label>
          <div class="so-hint">{{ t('page.demoHint') }}</div>
        </div>
      </aside>

      <div class="so-main" :class="{ 'so-main-wide': mapMode }">
        <header class="so-hero">
          <div>
            <div class="so-kicker">{{ t('page.kicker') }}</div>
            <div class="so-h1" role="heading" aria-level="1">
              {{ mapMode ? t('page.heroTitleMap') : t('page.heroTitleModules') }}
            </div>
            <div class="so-lead">
              {{ mapMode ? t('page.heroLeadMap') : t('page.heroLeadModules') }}
            </div>
          </div>
          <div class="so-hero-aside">
            <div class="so-view-toggle so-view-toggle-hero" role="group" :aria-label="t('page.viewLabel')">
              <button
                type="button"
                class="so-view-btn"
                :class="{ active: !mapMode }"
                @click="mapMode = false"
              >
                {{ t('page.viewModules') }}
              </button>
              <button
                type="button"
                class="so-view-btn"
                :class="{ active: mapMode }"
                @click="mapMode = true"
              >
                {{ t('page.viewSystemMap') }}
              </button>
            </div>
            <div v-if="demoMode" class="so-demo-banner">
              <iconify-icon icon="lucide:sparkles" />
              <span>{{ t('page.demoBanner') }}</span>
            </div>
            <div class="so-meta">
              <span class="so-meta-pill">{{ t('page.apiBase') }} <code>/api</code></span>
              <span v-if="backendOk" class="so-meta-pill ok">
                <iconify-icon icon="lucide:check-circle" /> {{ t('page.accessOk') }}
              </span>
              <span v-else class="so-meta-pill warn">
                <iconify-icon icon="lucide:wifi-off" /> {{ t('page.accessPending') }}
              </span>
            </div>
          </div>
        </header>

        <!-- Map mode: full architecture canvas -->
        <div v-if="mapMode" id="architecture-map" class="so-map-stage">
          <SystemArchitectureMap
            :ribbon-k="t('presentation.mapRibbonK')"
            :ribbon-h="t('presentation.mapRibbonH')"
            :ribbon-p="t('presentation.mapRibbonP')"
            :band-head="t('presentation.mapBand')"
            :matrix-title="t('presentation.mapMatrixTitle')"
            :foot-pill="t('presentation.mapFootPill')"
            :foot-note="t('presentation.mapFootNote')"
            :engines-display="mapEnginesBand"
            :stages-display="mapStagesDisplay"
            :matrix-rows="mapMatrixRows"
          />
        </div>

        <!-- Normal: presentation pipeline + modules -->
        <template v-else>
          <div class="so-strip">
            <PipelineFlowStrip
              :stages-override="pipelineStripStages"
              :foot-label="t('presentation.pipelineFoot')"
              :foot-path="t('presentation.pipelinePath')"
            />
          </div>
          <IntelligenceLayerPanel
            :title="t('presentation.intelligenceTitle')"
            :subtitle="t('presentation.intelligenceSub')"
            :pill="t('presentation.intelligencePill')"
            :engines-override="intelEnginesDisplay"
          />
          <CrossModuleGraph
            :title="t('presentation.crossTitle')"
            :subtitle="t('presentation.crossSub')"
            :hub-label="t('presentation.crossHub')"
            :connections-aria="t('presentation.crossConnectionsAria')"
            :edges-display="crossEdgesDisplay"
            :nodes-override="crossNodesDisplay"
          />

          <ModulePresentationCard
            v-for="mod in moduleKeys"
            :key="mod"
            :anchor-id="mod"
            :title="packedByMod[mod].title"
            :subtitle="packedByMod[mod].shortTitle"
            :icon="modulesContent[mod].icon"
            :badges="packedByMod[mod].badges"
            :tone="toneFor(mod)"
            :kpis="packedByMod[mod].kpis"
            :micro-flow="packedByMod[mod].microFlow"
            :actions="packedByMod[mod].actions"
            :dependencies="packedByMod[mod].dependencies"
          >
            <div v-if="demoMode" class="so-highlight">
              <strong>{{ t('page.demoSnapshot') }}</strong>
              <span>{{ demoLine(mod) }}</span>
            </div>

            <ModuleSection
              :label="t('sections.overview')"
              :default-open="false"
              bilingual
            >
              <template #en>
                <div class="so-prose">{{ modulesContent[mod].overview }}</div>
              </template>
              <template #ar>
                <div class="so-prose">{{ messages.ar.modules[mod].overview }}</div>
              </template>
            </ModuleSection>

            <ModuleSection
              :label="t('sections.features')"
              :badge="t('sections.badgeDetail')"
              bilingual
            >
              <template #en>
                <FeatureList :items="modulesContent[mod].features" />
              </template>
              <template #ar>
                <FeatureList :items="messages.ar.modules[mod].features" />
              </template>
            </ModuleSection>

            <ModuleSection
              :label="t('sections.workflows')"
              :badge="demoMode ? t('sections.badgeFlow') : ''"
              bilingual
            >
              <template #en>
                <WorkflowSteps
                  :steps="modulesContent[mod].workflows"
                  :demo-prefix="demoMode ? 'D' : ''"
                />
              </template>
              <template #ar>
                <WorkflowSteps
                  :steps="messages.ar.modules[mod].workflows"
                  :demo-prefix="demoMode ? 'D' : ''"
                />
              </template>
            </ModuleSection>

            <ModuleSection
              :label="t('sections.uiActions')"
              :badge="t('sections.badgeUx')"
              bilingual
            >
              <template #en>
                <FeatureList :items="modulesContent[mod].uiActions" />
              </template>
              <template #ar>
                <FeatureList :items="messages.ar.modules[mod].uiActions" />
              </template>
            </ModuleSection>

            <ModuleSection
              :label="t('sections.dataStructure')"
              :badge="t('sections.badgeSchema')"
              bilingual
            >
              <template #en>
                <DataTable :columns="dataColumns" :rows="dataRowsFor(mod, 'en')" />
              </template>
              <template #ar>
                <DataTable :columns="dataColumns" :rows="dataRowsFor(mod, 'ar')" />
              </template>
            </ModuleSection>

            <ModuleSection
              :label="t('sections.apiEndpoints')"
              :badge="t('sections.badgeRest')"
              bilingual
            >
              <template #en>
                <div class="so-endpoint-note">
                  {{ t('page.endpointNotePrefix') }}
                  <code class="inline-code">/api</code>
                  {{ t('page.endpointNoteSuffix') }}
                </div>
                <DataTable :columns="endpointColumns" :rows="endpointRows(mod, 'en')" />
              </template>
              <template #ar>
                <div class="so-endpoint-note">
                  {{ t('page.endpointNotePrefix') }}
                  <code class="inline-code">/api</code>
                  {{ t('page.endpointNoteSuffix') }}
                </div>
                <DataTable :columns="endpointColumns" :rows="endpointRows(mod, 'ar')" />
              </template>
            </ModuleSection>

            <ModuleSection
              :label="t('sections.specialLogic')"
              :badge="t('sections.badgeEngine')"
              bilingual
            >
              <template #en>
                <FeatureList :items="modulesContent[mod].specialLogic" />
                <div v-if="modulesContent[mod].highlights?.length" class="so-hl-box">
                  <div class="so-hl-title">{{ t('page.productHighlights') }}</div>
                  <ul>
                    <li v-for="h in modulesContent[mod].highlights" :key="h">{{ h }}</li>
                  </ul>
                </div>
              </template>
              <template #ar>
                <FeatureList :items="messages.ar.modules[mod].specialLogic" />
                <div v-if="messages.ar.modules[mod].highlights?.length" class="so-hl-box">
                  <div class="so-hl-title">{{ t('page.productHighlights') }}</div>
                  <ul>
                    <li v-for="h in messages.ar.modules[mod].highlights" :key="h">{{ h }}</li>
                  </ul>
                </div>
              </template>
            </ModuleSection>
          </ModulePresentationCard>
        </template>

        <footer class="so-foot">
          <span>{{ t('page.footer') }}</span>
        </footer>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, computed, provide } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'
import { useSystemOverviewLang } from '@/composables/useSystemOverviewLang'
import { messages } from '@/i18n/systemOverview.js'
import ModuleSection from '@/components/system-overview/ModuleSection.vue'
import FeatureList from '@/components/system-overview/FeatureList.vue'
import WorkflowSteps from '@/components/system-overview/WorkflowSteps.vue'
import DataTable from '@/components/system-overview/DataTable.vue'
import PipelineFlowStrip from '@/components/system-overview/PipelineFlowStrip.vue'
import IntelligenceLayerPanel from '@/components/system-overview/IntelligenceLayerPanel.vue'
import CrossModuleGraph from '@/components/system-overview/CrossModuleGraph.vue'
import ModulePresentationCard from '@/components/system-overview/ModulePresentationCard.vue'
import SystemArchitectureMap from '@/components/system-overview/SystemArchitectureMap.vue'
import { moduleNav, modulesContent, demoSamples } from '@/data/systemOverviewModules.js'
import {
  moduleKpis,
  moduleMicroFlow,
  actionChips,
  moduleDependencies,
  intelligenceEngines,
  pipelineStages,
  crossModuleEdges,
} from '@/data/systemOverviewPresentation.js'

const route = useRoute()
const { lang, setLang, t } = useSystemOverviewLang()

provide('soI18n', { lang, setLang, t, messages })

const moduleKeys = ['listings', 'leads', 'deals']

const NAV_TKEY = { listings: 'navListings', leads: 'navLeads', deals: 'navDeals' }

const moduleNavI18n = computed(() =>
  moduleNav.map((item) => ({
    ...item,
    label: lang.value === 'en' ? item.label : t(`page.${NAV_TKEY[item.id]}`),
  }))
)

const dataColumns = computed(() => [
  { key: 'field', label: t('table.field') },
  { key: 'type', label: t('table.type') },
  { key: 'desc', label: t('table.desc') },
])

const endpointColumns = computed(() => [
  { key: 'method', label: t('table.method'), mono: true },
  { key: 'path', label: t('table.path'), mono: true },
  { key: 'note', label: t('table.notes') },
])

function packMod(mod) {
  if (lang.value === 'en') {
    return {
      title: modulesContent[mod].title,
      shortTitle: modulesContent[mod].shortTitle,
      badges: modulesContent[mod].badges,
      kpis: moduleKpis[mod],
      microFlow: moduleMicroFlow[mod],
      actions: actionChips[mod],
      dependencies: moduleDependencies[mod],
    }
  }
  const ar = messages.ar.modules[mod]
  return {
    title: ar.title,
    shortTitle: ar.shortTitle,
    badges: ar.badges,
    kpis: ar.kpis,
    microFlow: ar.microFlow,
    actions: ar.actions,
    dependencies: ar.dependencies,
  }
}

const packedByMod = computed(() => {
  const o = {}
  for (const m of moduleKeys) {
    o[m] = packMod(m)
  }
  return o
})

function arNodeLabel(id) {
  if (id === 'intelligence') return t('mpc.depTargets.intelligence')
  return messages.ar.presentation.pipeline[id].label
}

const pipelineStripStages = computed(() => {
  if (lang.value === 'en') return null
  return pipelineStages.map((s) => ({
    ...s,
    label: messages.ar.presentation.pipeline[s.id].label,
    tagline: messages.ar.presentation.pipeline[s.id].tagline,
  }))
})

const intelEnginesDisplay = computed(() => {
  if (lang.value === 'en') return null
  return intelligenceEngines.map((e, i) => ({
    ...e,
    title: messages.ar.presentation.intelligenceEngines[i].title,
    subtitle: messages.ar.presentation.intelligenceEngines[i].subtitle,
    summary: messages.ar.presentation.intelligenceEngines[i].summary,
    feeds: e.feeds.map((f) => messages.ar.presentation.intelligenceFeeds[f]),
  }))
})

const crossEdgesDisplay = computed(() => {
  if (lang.value === 'en') return null
  const caps = messages.ar.presentation.crossEdges
  return crossModuleEdges.map((e, i) => ({
    fromLabel: arNodeLabel(e.from),
    toLabel: arNodeLabel(e.to),
    cap: caps[i],
  }))
})

const crossNodesDisplay = computed(() => {
  if (lang.value === 'en') return null
  return pipelineStages.map((s) => ({
    ...s,
    label: messages.ar.presentation.pipeline[s.id].label,
  }))
})

const mapMatrixRows = computed(() => {
  if (lang.value === 'en') return null
  const caps = messages.ar.presentation.crossEdges
  return crossModuleEdges.map((e, i) => ({
    from: arNodeLabel(e.from),
    arrow: '→',
    to: arNodeLabel(e.to),
    cap: caps[i],
  }))
})

const mapEnginesBand = computed(() => {
  if (lang.value === 'en') return null
  return intelligenceEngines.map((e, i) => ({
    ...e,
    title: messages.ar.presentation.mapEngineTitles[i],
  }))
})

const mapStagesDisplay = computed(() => {
  if (lang.value === 'en') return null
  return pipelineStages.map((s) => ({
    ...s,
    label: messages.ar.presentation.pipeline[s.id].label,
    tagline: messages.ar.presentation.pipeline[s.id].tagline,
  }))
})

const ready = ref(false)
const accessDenied = ref(false)
const backendOk = ref(false)
const activeSection = ref('listings')
const demoMode = ref(false)
const mapMode = ref(false)

function toneFor(id) {
  if (id === 'listings') return 'amber'
  if (id === 'leads') return 'violet'
  return 'emerald'
}

function dataRowsFor(mod, locale) {
  const base =
    locale === 'en' ? modulesContent[mod].dataFields : messages.ar.modules[mod].dataFields
  if (!demoMode.value) return base
  return base.map((r) => ({
    ...r,
    field: r.field + (mod === 'listings' ? ' · e.g. ' + demoSamples.listing.ref : ''),
  }))
}

function endpointRows(mod, locale) {
  const base = modulesContent[mod].endpoints
  if (locale === 'en') return base
  const notes = messages.ar.modules[mod].endpointNotes
  return base.map((row, i) => ({ ...row, note: notes[i] }))
}

function demoLine(mod) {
  if (mod === 'listings') {
    return `${demoSamples.listing.ref} · ${demoSamples.listing.unit} · ${demoSamples.listing.area} · ${demoSamples.listing.price}`
  }
  if (mod === 'leads') {
    return `${demoSamples.lead.number} · ${demoSamples.lead.name} · ${demoSamples.lead.stage}`
  }
  return `${demoSamples.deal.number} · ${demoSamples.deal.type} · ${demoSamples.deal.stage}`
}

function scrollTo(id) {
  const el = document.getElementById(id)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  activeSection.value = id
  try {
    history.replaceState(null, '', '#' + id)
  } catch {
    /* ignore */
  }
}

function onScroll() {
  if (mapMode.value) return
  const y = window.scrollY + 140
  for (const id of moduleKeys) {
    const el = document.getElementById(id)
    if (!el) continue
    const top = el.offsetTop
    const h = el.offsetHeight
    if (y >= top && y < top + h) {
      activeSection.value = id
      return
    }
  }
}

onMounted(async () => {
  try {
    const raw = localStorage.getItem('user')
    const u = raw ? JSON.parse(raw) : null
    const canAccess =
      u?.roles?.includes('super_admin') || u?.roles?.includes('admin')
    if (!canAccess) {
      accessDenied.value = true
      ready.value = true
      return
    }

    try {
      await api.get('/system-overview/access')
      backendOk.value = true
    } catch {
      backendOk.value = false
    }
  } finally {
    ready.value = true
  }

  const storedDemo = localStorage.getItem('systemOverviewDemoMode')
  if (storedDemo === '1') demoMode.value = true

  const storedMap = localStorage.getItem('systemOverviewMapMode')
  if (storedMap === '1') mapMode.value = true

  const hash = (route.hash || '').replace('#', '')
  if (hash && moduleKeys.includes(hash)) {
    if (hash) mapMode.value = false
    setTimeout(() => scrollTo(hash), 100)
  }

  window.addEventListener('scroll', onScroll, { passive: true })
})

function applySystemOverviewPageDir() {
  const rtl = lang.value === 'ar'
  document.documentElement.setAttribute('dir', rtl ? 'rtl' : 'ltr')
  document.documentElement.setAttribute('lang', rtl ? 'ar' : 'en')
}

watch(lang, applySystemOverviewPageDir, { immediate: true })

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  document.documentElement.setAttribute('dir', 'ltr')
  document.documentElement.setAttribute('lang', 'en')
})

watch(demoMode, (v) => {
  localStorage.setItem('systemOverviewDemoMode', v ? '1' : '0')
})

watch(mapMode, (v) => {
  localStorage.setItem('systemOverviewMapMode', v ? '1' : '0')
  if (v) {
    try {
      history.replaceState(null, '', route.path + '#architecture-map')
    } catch {
      /* ignore */
    }
  }
})
</script>

<style scoped>
.so-page {
  --so-text: #0f172a;
  --so-muted: #64748b;
  --so-border: rgba(15, 23, 42, 0.08);
  --so-font-max: 15px;
  font-size: 11px;
  min-height: calc(100vh - 80px);
  display: flex;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 40%, #eef2ff 100%);
}

.so-denied {
  margin: auto;
  text-align: center;
  padding: 48px 24px;
  color: #64748b;
  font-size: 12px;
}
.so-denied-icon {
  font-size: 15px;
  color: #94a3b8;
  display: block;
  margin: 0 auto 12px;
}
.so-link-home {
  color: #4f46e5;
  font-weight: 600;
}

/* Sidebar */
.so-sidebar {
  width: 240px;
  flex-shrink: 0;
  padding: 16px 12px;
  border-right: 1px solid var(--so-border);
  position: sticky;
  top: 0;
  align-self: flex-start;
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(12px);
}
.so-brand {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 24px;
  padding: 0 8px;
}
.so-brand-mark {
  font-size: 15px;
  color: #4f46e5;
}
.so-brand-title {
  font-weight: 700;
  font-size: 13px;
  color: #0f172a;
}
.so-brand-sub {
  font-size: 10px;
  color: #94a3b8;
}
.so-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}
.so-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  color: #475569;
  text-decoration: none;
  font-size: 12px;
  font-weight: 500;
  transition:
    background 0.15s,
    color 0.15s;
}
.so-nav-item:hover {
  background: rgba(99, 102, 241, 0.08);
  color: #1e293b;
}
.so-nav-item.active {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
  color: #4f46e5;
  font-weight: 600;
}
.so-nav-ic {
  font-size: 14px;
  opacity: 0.9;
}
.so-map-hint {
  flex: 1;
  padding: 12px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(30, 27, 75, 0.06));
  border: 1px solid rgba(99, 102, 241, 0.15);
}
.so-map-hint-ic {
  font-size: 15px;
  color: #4f46e5;
  display: block;
  margin-bottom: 8px;
}
.so-map-hint-txt {
  margin: 0;
  font-size: 11px;
  line-height: 1.45;
  color: #64748b;
}
.so-side-controls {
  margin-top: auto;
  padding-top: 18px;
  border-top: 1px solid var(--so-border);
}
.so-view-toggle-wrap {
  margin-bottom: 16px;
}
.so-view-label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
  margin-bottom: 8px;
}
.so-view-toggle {
  display: inline-flex;
  padding: 4px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.06);
  border: 1px solid rgba(15, 23, 42, 0.08);
  gap: 2px;
}
.so-view-toggle-hero {
  width: 100%;
  max-width: 280px;
  justify-content: stretch;
}
.so-view-btn {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 700;
  padding: 8px 12px;
  border-radius: 9px;
  color: #64748b;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s;
}
.so-view-btn:hover {
  color: #334155;
}
.so-view-btn.active {
  background: #fff;
  color: #4f46e5;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
}
.so-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  color: #334155;
  user-select: none;
}
.so-toggle input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
.so-toggle-ui {
  width: 40px;
  height: 22px;
  border-radius: 999px;
  background: #e2e8f0;
  position: relative;
  transition: background 0.2s;
  flex-shrink: 0;
}
.so-toggle-ui::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
  transition: transform 0.2s;
}
.so-toggle input:checked + .so-toggle-ui {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
}
.so-toggle input:checked + .so-toggle-ui::after {
  transform: translateX(18px);
}
.so-hint {
  margin: 10px 0 0;
  font-size: 10px;
  color: #94a3b8;
  line-height: 1.4;
}

/* Main */
.so-main {
  flex: 1;
  min-width: 0;
  padding: 20px 24px 32px;
  max-width: 1080px;
}
.so-main-wide {
  max-width: 1200px;
}
.so-hero {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 28px;
}
.so-kicker {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #6366f1;
  margin: 0 0 6px;
}
.so-h1 {
  margin: 0 0 8px;
  font-size: 15px;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.3;
  color: #0f172a;
}
.so-lead {
  margin: 0;
  max-width: 640px;
  font-size: 12px;
  line-height: 1.5;
  color: #64748b;
}
.so-hero-aside {
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: flex-end;
  min-width: 200px;
}
.so-demo-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 600;
  padding: 10px 14px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.12));
  color: #b45309;
  border: 1px solid rgba(245, 158, 11, 0.25);
  animation: pulse-soft 2.5s ease-in-out infinite;
}
@keyframes pulse-soft {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.15);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(245, 158, 11, 0);
  }
}
.so-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.so-meta-pill {
  font-size: 11px;
  padding: 5px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.06);
  color: #475569;
}
.so-meta-pill.ok {
  background: rgba(16, 185, 129, 0.12);
  color: #047857;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.so-meta-pill.warn {
  background: rgba(245, 158, 11, 0.12);
  color: #b45309;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.inline-code {
  font-size: 10px;
  padding: 2px 5px;
  border-radius: 6px;
  background: rgba(15, 23, 42, 0.06);
}

.so-strip {
  margin-bottom: 8px;
}
.so-map-stage {
  margin-bottom: 8px;
}

.so-highlight {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 12px;
  margin-bottom: 8px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.06));
  border: 1px solid rgba(99, 102, 241, 0.2);
  font-size: 11px;
  color: #4338ca;
}
.so-highlight strong {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #4f46e5;
}

.so-prose {
  margin: 0;
  font-size: 12px;
  line-height: 1.55;
  color: #475569;
}
.so-endpoint-note {
  margin: 0 0 10px;
  font-size: 11px;
  color: #64748b;
}

.so-hl-box {
  margin-top: 16px;
  padding: 14px 16px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.2);
}
.so-hl-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #047857;
  margin-bottom: 8px;
}
.so-hl-box ul {
  margin: 0;
  padding-left: 18px;
  color: #334155;
  font-size: 11px;
  line-height: 1.5;
}

.so-foot {
  margin-top: 28px;
  padding-top: 14px;
  border-top: 1px solid var(--so-border);
  font-size: 10px;
  color: #94a3b8;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 1024px) {
  .so-page {
    flex-direction: column;
  }
  .so-sidebar {
    width: 100%;
    height: auto;
    position: relative;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    border-right: none;
    border-bottom: 1px solid var(--so-border);
  }
  .so-nav {
    flex-direction: row;
    flex-wrap: wrap;
    flex: 1;
  }
  .so-map-hint {
    flex: 1 1 200px;
  }
  .so-side-controls {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
    width: 100%;
  }
  .so-main {
    padding: 24px 20px 40px;
  }
  .so-hero-aside {
    align-items: stretch;
  }
  .so-view-toggle-hero {
    max-width: none;
  }
}
</style>
