<template>
  <div class="dashboard-main-body lead-export-page">
    <Breadcrumb title="Export Leads" :breadcrumbs="[{ name: 'Export Leads' }]" />

    <div class="lex-shell">
      <div class="lex-toolbar">
        <div ref="searchBoxRef" class="lex-search" @click="onSearchBoxClick">
          <iconify-icon
            :icon="loading ? 'lucide:loader-2' : 'lucide:search'"
            :class="['lex-search-icon', { spin: loading }]"
            width="16"
            height="16"
          />
          <button
            v-for="f in chipFilters"
            :key="f.id"
            type="button"
            class="lex-chip"
            :title="`Remove ${f.label}`"
            @click.stop="removeFilter(f)"
          >
            <span>{{ f.label }}: {{ f.value }}</span>
            <iconify-icon icon="lucide:x" width="12" height="12" />
          </button>
          <input
            v-model="term"
            type="text"
            class="lex-input"
            :placeholder="chipFilters.length ? 'Add search text...' : 'Search leads (name, phone, email...)'"
            autocomplete="off"
            @click.stop
            @keydown.enter.prevent="searchNow"
          />
          <button
            type="button"
            class="lex-icon-btn"
            title="Advanced search"
            aria-label="Advanced search"
            @click.stop="showFilters = true"
          >
            <iconify-icon icon="lucide:list-filter" width="16" height="16" />
          </button>
          <button v-if="hasCriteria" type="button" class="lex-icon-btn" aria-label="Clear search" @click.stop="clearAll">
            <iconify-icon icon="lucide:x" width="14" height="14" />
          </button>
        </div>
        <button
          type="button"
          class="lex-btn lex-btn--primary"
          :disabled="downloading || !searched || !total"
          @click="downloadExcel"
        >
          <iconify-icon :icon="downloading ? 'lucide:loader-2' : 'lucide:download'" :class="{ spin: downloading }" width="16" height="16" />
          {{ downloading ? 'Exporting...' : 'Export Excel' }}
        </button>
      </div>
      <p class="lex-hint">
        Same search as the Leads board: type text or open the filter button for every lead search filter.
        Only leads that have an email are listed. The Excel file has: Lead Name, Name, Email.
      </p>

      <div v-if="error" class="lex-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
      </div>

      <div v-if="loading && !searched" class="lex-empty">Searching...</div>

      <template v-else-if="searched">
        <div class="lex-summary">
          <strong>{{ formatNumber(total) }}</strong>
          lead{{ total === 1 ? '' : 's' }} with an email match this search
          <span v-if="total > rows.length" class="lex-muted">— showing the newest {{ rows.length }}; the Excel file has all {{ formatNumber(total) }}.</span>
        </div>

        <div v-if="!rows.length" class="lex-empty">No leads match this search.</div>

        <div v-else class="lex-table-wrap" :class="{ 'is-stale': loading }">
          <table class="lex-table">
            <thead>
              <tr>
                <th>Lead Name</th>
                <th>Name</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" class="lex-row" @click="openLeadView(row.id)">
                <td>{{ row.lead_name || '—' }}</td>
                <td>{{ row.name || '—' }}</td>
                <td>{{ row.email || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="lex-empty">Type at least 2 characters or add a filter to search.</div>
    </div>

    <!-- Same panel mode as the Leads board search (it has the fixed height + inner scroll). -->
    <Teleport to="body">
      <div v-if="filtersMounted" v-show="showFilters" class="lex-filters-backdrop" @mousedown.self="showFilters = false" />
      <div
        v-if="filtersMounted"
        v-show="showFilters"
        class="lead-search-dropdown-outer lex-filters-panel"
        :style="panelStyle"
      >
        <LeadSearchModal
          :model-value="showFilters"
          :as-dropdown="true"
          :current-query="query"
          :has-active-filters="activeFilters.length > 0"
          :searching="loading"
          @update:model-value="showFilters = $event"
          @search="onModalSearch"
        />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import { openLeadView } from '@/composables/useLeadViewModal.js'

// Lead search modal query → API params: drop empty values (same keys the modal uses).
function buildLeadSearchApiParams(q) {
  const params = {}
  for (const [key, value] of Object.entries(q || {})) {
    if (value === undefined || value === null || value === '' || value === false) continue
    if (Array.isArray(value) && !value.length) continue
    // UI-only objects (not search params).
    if (!Array.isArray(value) && typeof value === 'object') continue
    params[key] = value === true ? 1 : value
  }
  return params
}

const LeadSearchModal = defineAsyncComponent(() => import('@/components/kanban/leadList/LeadSearchModal.vue'))

// `query` is the lead search modal's query (same shape the Leads board uses);
// the text box edits its `search` key.
const query = ref(null)
const activeFilters = ref([])
const term = ref('')
const showFilters = ref(false)
const filtersMounted = ref(false)

const rows = ref([])
const total = ref(0)
const searched = ref(false)
const loading = ref(false)
const downloading = ref(false)
const error = ref('')

const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)

// Panel sits under the search box, as on the Leads board.
const searchBoxRef = ref(null)
const panelStyle = ref({})

function placePanel() {
  const rect = searchBoxRef.value?.getBoundingClientRect()
  if (!rect) return
  panelStyle.value = {
    top: `${Math.round(rect.bottom + 8)}px`,
    left: `${Math.round(Math.max(12, Math.min(rect.left, window.innerWidth - 1152)))}px`,
  }
}

watch(showFilters, (open) => {
  if (!open) return
  filtersMounted.value = true
  placePanel()
})

function onWindowChange() {
  if (showFilters.value) placePanel()
}
onMounted(() => {
  window.addEventListener('resize', onWindowChange)
  window.addEventListener('scroll', onWindowChange, true)
})
onBeforeUnmount(() => {
  window.removeEventListener('resize', onWindowChange)
  window.removeEventListener('scroll', onWindowChange, true)
})

// The text is shown in the input, so no chip for it.
const chipFilters = computed(() => activeFilters.value.filter((f) => f.queryKey !== 'search'))

const apiParams = computed(() => {
  const params = buildLeadSearchApiParams(query.value || {})
  // 1-character text is ignored by the board search too.
  if (params.search && String(params.search).trim().length < 2) delete params.search
  return params
})
const hasCriteria = computed(() => Object.keys(apiParams.value).length > 0)

function onSearchBoxClick() {
  if (chipFilters.value.length) showFilters.value = true
}

// ---- search requests (latest wins) ----
let seq = 0

function resetResults() {
  rows.value = []
  total.value = 0
  searched.value = false
  error.value = ''
}

async function runSearch() {
  const params = apiParams.value
  if (!Object.keys(params).length) {
    seq++
    loading.value = false
    resetResults()
    return
  }
  const mySeq = ++seq
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/leads/reports/lead-search', { params })
    if (mySeq !== seq) return
    const data = res?.data?.data || {}
    rows.value = data.rows || []
    total.value = Number(data.total) || 0
    searched.value = true
  } catch (err) {
    if (mySeq !== seq) return
    error.value = err?.response?.data?.message || 'Search failed'
    rows.value = []
    total.value = 0
    searched.value = false
  } finally {
    if (mySeq === seq) loading.value = false
  }
}

function setQuery(next) {
  query.value = next && Object.keys(next).length ? next : null
}

// Search as you type (400 ms after the last keystroke); Enter searches right away.
let debounceTimer = null

watch(term, (value) => {
  const text = value.trim()
  // Already in the query (set from the modal / cleared) — nothing typed.
  if (text === String(query.value?.search ?? '').trim()) return
  const next = { ...(query.value || {}) }
  if (text) next.search = text
  else delete next.search
  if (!text) activeFilters.value = activeFilters.value.filter((f) => f.queryKey !== 'search')
  setQuery(next)
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(runSearch, 400)
})

function searchNow() {
  clearTimeout(debounceTimer)
  runSearch()
}

// Call after `query` is updated, so the term watcher sees no change.
function setTermSilently(value) {
  term.value = value
}

function onModalSearch(payload) {
  if (!payload || payload.query === null) {
    clearAll()
    return
  }
  setQuery(payload.query ? { ...payload.query } : null)
  activeFilters.value = Array.isArray(payload.activeFilters) ? [...payload.activeFilters] : []
  setTermSilently(query.value?.search ? String(query.value.search) : '')
  // The modal debounces its own live typing, so search right away.
  searchNow()
}

// Same linked keys the board's chips drop (navbar dropLinkedQueryKeys).
const LINKED_KEYS = {
  created_at: ['created_from', 'created_to'],
  assigned_at: ['assigned_from', 'assigned_to'],
  modified_at: ['modified_from', 'modified_to'],
  office_branch: ['lead_branch_source'],
  source: ['source_website'],
  budget_from: ['budget_to'],
  budget_to: ['budget_from'],
}

function removeFilter(f) {
  const next = { ...(query.value || {}) }
  if (f.queryKey) {
    delete next[f.queryKey]
    for (const key of LINKED_KEYS[f.queryKey] || []) delete next[key]
  }
  activeFilters.value = activeFilters.value.filter((x) => x.id !== f.id)
  setQuery(next)
  searchNow()
}

function clearAll() {
  clearTimeout(debounceTimer)
  query.value = null
  activeFilters.value = []
  setTermSilently('')
  seq++
  loading.value = false
  resetResults()
}

onBeforeUnmount(() => clearTimeout(debounceTimer))

async function downloadExcel() {
  if (!searched.value || !hasCriteria.value) return
  downloading.value = true
  try {
    const res = await api.get('/leads/reports/lead-search/export', {
      params: apiParams.value,
      responseType: 'blob',
    })
    const blob = new Blob([res.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const text = String(apiParams.value.search || '')
    const slug = text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'search'
    link.download = `leads-${slug}.xlsx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    let message = err?.response?.data?.message
    // Blob responses carry the JSON error as a blob.
    if (!message && err?.response?.data instanceof Blob) {
      try { message = JSON.parse(await err.response.data.text())?.message } catch { /* not JSON */ }
    }
    Swal.fire({
      icon: 'error',
      title: 'Export failed',
      text: message || 'Could not export the leads',
    })
  } finally {
    downloading.value = false
  }
}
</script>

<style scoped>
.lead-export-page.lead-export-page {
  --lex-primary: var(--color-primary);
  --lex-primary-soft: #f4eef7;
  --lex-text: #1e1b2e;
  --lex-muted: #8b8798;
  --lex-line: #ece9f1;
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.lex-shell { border: 1px solid var(--lex-line); background: #fff; border-radius: 14px; padding: 16px; }

.lex-toolbar { display: flex; flex-direction: column; gap: 8px; }
.lex-search {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
  width: 100%; max-width: 640px; min-height: 38px; box-sizing: border-box;
  border: 1px solid var(--lex-line); border-radius: 8px; padding: 4px 6px 4px 10px; background: #fff;
}
.lex-search:focus-within { border-color: var(--lex-primary); }
.lex-search-icon { color: var(--lex-muted); flex-shrink: 0; }
.lex-input { flex: 1 1 140px; min-width: 120px; height: 28px; border: none; outline: none; padding: 0 4px; color: var(--lex-text); background: transparent; }
.lex-chip {
  display: inline-flex; align-items: center; gap: 4px; max-width: 260px;
  border: 1px solid #e3d6ea; background: var(--lex-primary-soft); color: var(--lex-primary);
  border-radius: 999px; padding: 2px 8px; font-size: 12px; cursor: pointer;
}
.lex-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lex-chip:hover { border-color: var(--lex-primary); }
.lex-icon-btn { display: inline-flex; align-items: center; justify-content: center; border: none; background: transparent; color: var(--lex-muted); cursor: pointer; padding: 5px; border-radius: 6px; }
.lex-icon-btn:hover { color: var(--lex-primary); background: var(--lex-primary-soft); }
.lex-hint { margin: 8px 0 16px; color: var(--lex-muted); font-size: 12px; }

.lex-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; }
.lex-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.lex-btn--primary { background: var(--lex-primary); color: #fff; }
.spin { animation: lex-spin 0.9s linear infinite; }
@keyframes lex-spin { to { transform: rotate(360deg); } }

.lex-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.lex-empty { padding: 24px; text-align: center; color: var(--lex-muted); }
.lex-summary { margin-bottom: 12px; font-size: 13px; color: var(--lex-text); }
.lex-summary strong { font-size: 16px; color: var(--lex-primary); }
.lex-muted { color: var(--lex-muted); }

.lex-table-wrap { overflow-x: auto; transition: opacity 0.15s; }
.lex-table-wrap.is-stale { opacity: 0.55; }
.lex-table { width: 100%; border-collapse: collapse; min-width: 520px; }
.lex-table th { text-align: left; font-size: 12px; color: var(--lex-muted); text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid var(--lex-line); }
.lex-table td { padding: 10px; border-bottom: 1px solid #f4f2f7; font-size: 13px; color: var(--lex-text); }
.lex-row { cursor: pointer; }
.lex-row:hover td { background: #faf8fc; }

/* Search Filter panel (teleported to body) — same size / layer as the Leads board's. */
.lex-filters-backdrop { position: fixed; inset: 0; z-index: 14999; background: rgba(11, 7, 54, 0.18); }
.lex-filters-panel {
  position: fixed; z-index: 15000;
  width: min(1140px, calc(100vw - 24px)); max-width: calc(100vw - 24px);
}
.lex-filters-panel :deep(.lead-search-dropdown-panel) { position: relative; box-shadow: 0 16px 48px rgba(11, 7, 54, 0.28); }

@media (max-width: 768px) {
  .lex-filters-panel { inset: 0 !important; width: 100%; max-width: 100%; height: 100dvh; }
}

@media (min-width: 768px) {
  .lead-export-page.lead-export-page { padding: 20px; }
  .lex-toolbar { flex-direction: row; align-items: flex-start; justify-content: space-between; }
}
</style>
