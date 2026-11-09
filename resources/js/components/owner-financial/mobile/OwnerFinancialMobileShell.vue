<template>
  <div class="om-shell">
    <header class="om-header">
      <div class="om-header-row">
        <div class="om-brand">
          <span class="om-brand-ico"><iconify-icon icon="lucide:chart-column" width="12" height="12" /></span>
          <div>
            <h1 class="om-title">Financial Intelligence</h1>
            <p class="om-sub">
              <span class="om-chip">{{ scopeLabel }}</span>
              <span v-if="lastLoaded" class="om-updated">{{ lastLoaded }}</span>
            </p>
          </div>
        </div>
      </div>

      <OfrMobileSearch
        ref="searchRef"
        v-model="searchDraft"
        :agents="options.agents"
        @search="onSearch"
        @clear="onSearchClear"
        @select-agent="onSelectAgent"
      />

      <OfrMobileQuickBar
        :applied="applied"
        :active-count="activeCount"
        @open-dates="openFilterSheet('dates')"
        @open-sources="openFilterSheet('sources')"
        @open-filters="openFilterSheet('all')"
      />
    </header>

    <div class="om-scroll">
      <OfrMobileKpis
        :summary="summary"
        :data-status="applied.data_status"
        @toggle-missing="$emit('toggle-missing')"
      />

      <OfrMobileInsights
        ref="insightsRef"
        :insights="insights"
        :loading="insightsLoading"
        :error="insightsError"
        :preferred-tab="insightsTab"
        @open="$emit('load-insights')"
        @retry="$emit('load-insights')"
      />

      <OfrMobileDealCards
        :rows="rows"
        :pagination="pagination"
        :page="page"
        :loading="loading"
        :error="error"
        :selected-id="openDealId"
        :sort="sort"
        @open="(id) => $emit('open-deal-id', id)"
        @open-deal="(row) => $emit('open-deal-modal', row)"
        @page="(p) => $emit('page', p)"
        @sort="(s) => $emit('sort', s)"
        @retry="$emit('retry')"
      />
    </div>

    <nav class="om-dock" aria-label="Report actions">
      <button type="button" class="om-dock-btn" @click="focusSearch">
        <iconify-icon icon="lucide:search" width="18" height="18" />
        <span>Search</span>
      </button>
      <button type="button" class="om-dock-btn" @click="openFilterSheet('all')">
        <iconify-icon icon="lucide:sliders-horizontal" width="18" height="18" />
        <span>Filters</span>
        <em v-if="activeCount" class="om-badge">{{ activeCount }}</em>
      </button>
      <button type="button" class="om-dock-btn om-dock-btn--primary" @click="launcherOpen = true">
        <iconify-icon icon="lucide:sparkles" width="18" height="18" />
        <span>Analyze</span>
      </button>
      <button type="button" class="om-dock-btn" :disabled="!pagination.total" @click="exportOpen = true">
        <iconify-icon icon="lucide:download" width="18" height="18" />
        <span>Export</span>
      </button>
    </nav>

    <OfrMobileFilterSheet
      :open="filtersOpen"
      :focus="filterFocus"
      :draft="draft"
      :options="options"
      @close="filtersOpen = false"
      @apply="onFilterApply"
      @reset="$emit('clear-filters'); filtersOpen = false"
      @quick-apply="onQuickApply"
    />

    <OfrMobileReportLauncher
      :open="launcherOpen"
      @close="launcherOpen = false"
      @select="onReportSelect"
    />

    <OfrMobileExportSheet
      :open="exportOpen"
      :total="pagination.total"
      :filter-summary="filterSummary"
      :exporting="exporting"
      :status="exportStatus"
      :status-error="exportStatusError"
      @close="exportOpen = false"
      @excel="$emit('export-excel')"
      @pdf="$emit('export-pdf')"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import OfrMobileKpis from './OfrMobileKpis.vue'
import OfrMobileSearch from './OfrMobileSearch.vue'
import OfrMobileQuickBar from './OfrMobileQuickBar.vue'
import OfrMobileDealCards from './OfrMobileDealCards.vue'
import OfrMobileFilterSheet from './OfrMobileFilterSheet.vue'
import OfrMobileReportLauncher from './OfrMobileReportLauncher.vue'
import OfrMobileExportSheet from './OfrMobileExportSheet.vue'
import OfrMobileInsights from './OfrMobileInsights.vue'
import { datePresetRange } from './useOfrMobile.js'

const props = defineProps({
  summary: { type: Object, required: true },
  rows: { type: Array, default: () => [] },
  pagination: { type: Object, required: true },
  page: { type: Number, required: true },
  loading: Boolean,
  error: { type: String, default: '' },
  openDealId: { type: [Number, null], default: null },
  sort: { type: String, default: 'deal_date' },
  draft: { type: Object, required: true },
  applied: { type: Object, required: true },
  options: { type: Object, required: true },
  activeCount: { type: Number, default: 0 },
  lastLoaded: { type: String, default: '' },
  insights: { type: Object, default: null },
  insightsLoading: Boolean,
  insightsError: { type: String, default: '' },
  filterSummary: { type: Object, default: () => ({}) },
  exporting: { type: [Boolean, String], default: false },
  exportStatus: { type: String, default: '' },
  exportStatusError: Boolean,
})

const emit = defineEmits([
  'toggle-missing', 'open-deal-id', 'open-deal-modal', 'page', 'sort', 'retry',
  'apply-filters', 'clear-filters', 'quick-filters', 'search', 'select-agent',
  'clear-search', 'load-insights', 'export-excel', 'export-pdf', 'run-report',
])

const searchRef = ref(null)
const insightsRef = ref(null)
const searchDraft = ref(props.applied.search || '')
const filtersOpen = ref(false)
const filterFocus = ref('all')
const launcherOpen = ref(false)
const exportOpen = ref(false)
const insightsTab = ref('')

watch(() => props.applied.search, (v) => {
  if ((v || '') !== searchDraft.value) searchDraft.value = v || ''
})

const scopeLabel = computed(() => (
  props.applied.deal_status === 'all' ? 'All deals' : 'Closed deals'
))

function openFilterSheet(focus = 'all') {
  filterFocus.value = focus
  filtersOpen.value = true
}

function focusSearch() {
  searchRef.value?.focus?.()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function onSearch(q) {
  emit('search', q)
}

function onSearchClear() {
  emit('clear-search')
}

function onSelectAgent(a) {
  emit('select-agent', a)
}

function onFilterApply(payload) {
  emit('apply-filters', payload)
  filtersOpen.value = false
  if ((payload.source || []).length || filterFocus.value === 'sources') {
    insightsTab.value = 'by_source'
    insightsRef.value?.open('by_source')
    emit('load-insights')
  }
}

function onQuickApply(payload) {
  emit('quick-filters', payload)
  // Date presets from the date sheet apply instantly; keep sheet open for more tweaks.
}

function onReportSelect(id) {
  launcherOpen.value = false
  const patch = {}
  let preferredInsights = ''

  if (id === 'overview') {
    preferredInsights = 'by_source'
  } else if (id === 'deals') {
    emit('run-report', { sort: 'deal_date', direction: 'desc' })
    return
  } else if (id === 'commission') {
    emit('run-report', { sort: 'gross_commission', direction: 'desc' })
    return
  } else if (id === 'missing') {
    patch.data_status = 'missing'
  } else if (id === 'month' || id === 'quarter' || id === 'year') {
    Object.assign(patch, datePresetRange(id), { deal_status: 'closed' })
  } else if (id === 'sale') {
    patch.deal_type = 'sale'
  } else if (id === 'rental') {
    patch.deal_type = 'rental'
  } else if (id === 'agent') {
    preferredInsights = 'by_agent'
  } else if (id === 'branch') {
    preferredInsights = 'by_branch'
  }

  if (Object.keys(patch).length) {
    emit('quick-filters', { ...props.applied, ...patch, source: [...(props.applied.source || [])] })
  }

  if (preferredInsights) {
    insightsTab.value = preferredInsights
    insightsRef.value?.open(preferredInsights)
    emit('load-insights')
  } else if (id === 'overview') {
    insightsTab.value = 'by_source'
    insightsRef.value?.open('by_source')
    emit('load-insights')
  }
}
</script>

<style scoped>
.om-shell {
  --ofr-p: var(--color-primary, #733E87);
  --ofr-p-rgb: var(--color-primary-rgb, 115, 62, 135);
  --ofr-ink: var(--color-primary-ink, #3f2148);
  --ofr-soft: var(--color-primary-soft, #f7f2f8);
  --ofr-border: var(--color-primary-border, #e7dcec);
  --ofr-muted-line: var(--color-primary-muted, #d4c0de);
  --ofr-text: var(--color-text-primary, #1e1b2e);
  --ofr-text-2: var(--color-text-secondary, #6b6678);
  --ofr-text-3: var(--color-text-muted, #8b8798);
  --ofr-warn: #c2283a;
  --ofr-warn-soft: #fdf0f2;
  --ofr-warn-line: #f5c6cd;
  /* Unified mobile type scale — titles vs body (values + meta) */
  --om-fs-title: 11px;
  --om-fs-body: 12px;
  --om-fw-title: 650;
  --om-fw-value: 700;

  display: flex;
  flex-direction: column;
  min-height: 100%;
  background: #faf8fb;
  color: var(--ofr-text);
  font-size: var(--om-fs-body);
  padding-bottom: calc(72px + env(safe-area-inset-bottom));
}

.om-header {
  position: sticky;
  top: 0;
  z-index: 30;
  background: rgba(255, 255, 255, 0.94);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--ofr-border);
  padding: 10px 12px 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.om-header-row { display: flex; align-items: center; justify-content: space-between; }
.om-brand { display: flex; align-items: center; gap: 8px; min-width: 0; }
.om-brand-ico {
  width: 22px; height: 22px; border-radius: 7px; flex-shrink: 0;
  display: grid; place-items: center; background: var(--ofr-soft); color: var(--ofr-p);
  border: 1px solid var(--ofr-border);
}
.om-title {
  margin: 0 !important;
  font-size: var(--om-fs-title) !important;
  font-weight: var(--om-fw-title) !important;
  letter-spacing: 0 !important;
  text-transform: none !important;
  color: var(--ofr-ink) !important;
  line-height: 1.25 !important;
}
.om-sub {
  margin: 1px 0 0; display: flex; align-items: center; gap: 6px;
  font-size: var(--om-fs-body); color: var(--ofr-text-3);
}
.om-chip {
  display: inline-flex; align-items: center; height: 18px; padding: 0 7px;
  border-radius: 999px; border: 1px solid var(--ofr-border); background: var(--ofr-soft);
  color: var(--ofr-p); font-size: var(--om-fs-body); font-weight: 600;
}
.om-updated { font-size: var(--om-fs-body); }

.om-scroll {
  display: flex; flex-direction: column; gap: 10px;
  padding: 10px 12px 16px;
  min-width: 0;
}

.om-dock {
  position: fixed;
  left: 0; right: 0; bottom: 0;
  z-index: 40;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2px;
  padding: 8px 8px calc(8px + env(safe-area-inset-bottom));
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(12px);
  border-top: 1px solid var(--ofr-border);
  box-shadow: 0 -8px 24px rgba(115, 62, 135, 0.08);
}
.om-dock-btn {
  position: relative;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 2px; min-height: 48px; border: none; background: transparent;
  color: var(--ofr-text-2); font-size: 10px; font-weight: 600; cursor: pointer;
  border-radius: 12px; -webkit-tap-highlight-color: transparent;
}
.om-dock-btn:disabled { opacity: .4; cursor: not-allowed; }
.om-dock-btn--primary { color: var(--ofr-p); }
.om-dock-btn--primary iconify-icon {
  width: 34px; height: 34px; border-radius: 11px;
  display: grid; place-items: center;
  background: linear-gradient(135deg, #733E87, #9a5fb0); color: #fff;
  box-shadow: 0 4px 12px rgba(115, 62, 135, 0.28);
}
.om-badge {
  position: absolute; top: 4px; right: calc(50% - 22px);
  min-width: 16px; height: 16px; padding: 0 4px; border-radius: 999px;
  background: var(--ofr-p); color: #fff; font-size: 9px; font-weight: 800;
  font-style: normal; display: grid; place-items: center;
}
</style>
