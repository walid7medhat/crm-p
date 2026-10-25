<template>
  <Teleport to="body">
    <Transition name="oia-search-shell">
      <div
        v-if="open"
        class="oia-search"
        @keydown.esc.stop.prevent="close"
      >
        <button
          type="button"
          class="oia-search__backdrop"
          data-smart-search-backdrop
          aria-label="Close search"
          @pointerdown.stop="close"
          @click="close"
        />
        <div
          class="oia-search__panel"
          data-smart-search-panel
          role="listbox"
          aria-label="Search results"
          :style="panelStyle"
          @click.stop
          @mousedown.prevent
        >
          <div v-if="loading" class="oia-search__ai-status" role="status" aria-live="polite">
            <span class="oia-search__ai-dots" aria-hidden="true"><i /><i /><i /></span>
            <span>Searching…</span>
          </div>

          <div class="oia-search__tabs" role="tablist" aria-label="Filter results">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              type="button"
              role="tab"
              class="oia-search__tab"
              :class="{ 'is-on': activeTab === tab.id }"
              :aria-selected="activeTab === tab.id"
              @mousedown.prevent
              @click="toggleTab(tab.id)"
            >
              {{ tab.label }}
            </button>
          </div>

          <div v-if="chips.length" class="oia-search__chips">
            <button
              v-for="chip in chips"
              :key="chip.key"
              type="button"
              class="oia-search__chip"
              @mousedown.prevent
              @click="removeChip(chip)"
            >
              <span dir="auto">{{ chip.label }}</span>
              <iconify-icon icon="lucide:x" width="12" height="12" aria-hidden="true" />
              <span class="oia-search__sr">Remove {{ chip.label }}</span>
            </button>
          </div>

          <p v-if="summary && needsConfirmation" class="oia-search__preview" dir="auto">
            {{ summary }}
          </p>
          <p v-if="banner" class="oia-search__banner" role="status">{{ banner }}</p>

          <div v-if="needsConfirmation && !executed" class="oia-search__confirm">
            <button type="button" class="oia-search__confirm-btn" @mousedown.prevent @click="confirmSearch">
              Search with these filters
            </button>
          </div>

          <div
            id="oia-search-results"
            class="oia-search__results"
            :class="{ 'is-refreshing': loading && flat.length }"
          >
            <div v-if="loading && !resultRows.length" class="oia-search__skeletons" aria-hidden="true">
              <div v-for="n in 5" :key="n" class="oia-search__skeleton" :style="{ '--i': n }">
                <span class="oia-search__skeleton-icon" />
                <span class="oia-search__skeleton-copy">
                  <span class="oia-search__skeleton-line oia-search__skeleton-line--title" />
                  <span class="oia-search__skeleton-line oia-search__skeleton-line--meta" />
                </span>
              </div>
            </div>

            <div v-else-if="error" class="oia-search__state" role="alert">
              <span>{{ error }}</span>
              <button type="button" class="oia-search__text-btn" @mousedown.prevent @click="runNow">Try again</button>
            </div>

            <template v-else-if="resultRows.length || visibleGroups.length">
              <section
                v-for="(group, gIndex) in visibleGroups"
                :key="group.type"
                class="oia-search__group"
                :style="{ '--g': gIndex }"
              >
                <div class="oia-search__group-label">{{ groupTitle(group) }}</div>
                <p v-if="!group.results.length" class="oia-search__empty">
                  No {{ groupTitle(group).toLowerCase() }} found.
                </p>
                <button
                  v-for="(item, rIndex) in group.results"
                  :id="itemDomId(item)"
                  :key="item.type + '-' + item.id"
                  type="button"
                  class="oia-search__result"
                  :class="{ 'is-active': activeKey === itemKey(item) }"
                  :style="{ '--r': rIndex }"
                  role="option"
                  :aria-selected="activeKey === itemKey(item)"
                  @mouseenter="activeKey = itemKey(item)"
                  @mousedown.prevent
                  @click="activate(item)"
                >
                  <span class="oia-search__icon" aria-hidden="true">
                    <iconify-icon :icon="iconFor(item)" width="15" height="15" />
                  </span>
                  <span class="oia-search__copy">
                    <span class="oia-search__label" dir="auto">
                      <span v-html="highlight(item.label)" />
                      <span v-if="item.exact" class="oia-search__exact">Exact</span>
                    </span>
                    <span v-if="item.description" class="oia-search__meta" dir="auto">{{ item.description }}</span>
                  </span>
                  <span class="oia-search__type" aria-hidden="true">{{ item.type }}</span>
                </button>
                <div
                  v-for="item in group.results.filter((row) => row.meta?.choices?.length)"
                  :key="'choices-' + item.type + item.id"
                  class="oia-search__choices"
                >
                  <button
                    v-for="choice in item.meta?.choices || []"
                    :key="choice.id + '-' + item.id"
                    type="button"
                    class="oia-search__choice"
                    @mousedown.prevent
                    @click.stop="applyChoice(choice)"
                  >
                    {{ choice.label }}
                  </button>
                </div>
              </section>
            </template>

            <p v-else class="oia-search__state">
              {{ emptyText }}
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { openLeadView } from '@/composables/useLeadViewModal.js'
import { openDealView } from '@/composables/useDealViewModal.js'
import {
  bindSmartSearchShortcut,
  closeSmartSearch,
  registerSmartSearchNav,
  smartSearchAnchorEl,
  smartSearchLoading,
  smartSearchOpen,
  smartSearchQuery,
} from '@/composables/useSmartSearch.js'
import { useDashboardPermissions } from '@/composables/useDashboardPermissions.js'

const router = useRouter()
const route = useRoute()
const open = smartSearchOpen
const query = smartSearchQuery
const loading = smartSearchLoading
const activeTab = ref('all')
const error = ref('')
const payload = ref(null)
const activeKey = ref('')
const suppress = ref([])
const structured = ref({})
const confirmed = ref(false)
const panelStyle = ref({})

const { canViewModule } = useDashboardPermissions()

const tabs = computed(() => {
  const all = [
    { id: 'leads', label: 'Leads', module: 'crm' },
    { id: 'deals', label: 'Deals', module: 'deals' },
    { id: 'listings', label: 'Listings', module: 'listing' },
    { id: 'places', label: 'Location', module: 'listing' },
  ]
  return all.filter((tab) => {
    if (tab.id === 'places') {
      return canViewModule('crm') || canViewModule('listing')
    }
    return canViewModule(tab.module)
  })
})

let timer = null
let seq = 0
let abortController = null
let unbindShortcut = null
let unbindNav = null
let suppressWatch = false

const chips = computed(() => payload.value?.interpretation?.chips || [])
const summary = computed(() => payload.value?.interpretation?.summary || '')
const needsConfirmation = computed(() => !!payload.value?.interpretation?.needs_confirmation)
const executed = computed(() => payload.value?.executed !== false)
const banner = computed(() => payload.value?.message || '')
const visibleGroups = computed(() => {
  let groups = payload.value?.groups || []
  // When a module tab is selected, only show that module's results.
  if (activeTab.value && activeTab.value !== 'all') {
    groups = groups.filter((group) => group.type === activeTab.value)
  }
  if (!executed.value && needsConfirmation.value) {
    return groups.filter((group) => group.results?.length)
  }
  return groups
})
const resultRows = computed(() => visibleGroups.value.flatMap((group) => group.results || []))
const flat = computed(() => resultRows.value)
const emptyText = computed(() => {
  if (!query.value.trim() && !Object.keys(structured.value).length) {
    return 'Type a name, phone, reference, area, or phrase such as “2 bedroom Yas Island”.'
  }
  if (needsConfirmation.value) return 'Pick a choice below, or search with the recognized filters.'
  return 'Nothing matches yet.'
})

watch(open, async (isOpen) => {
  if (!isOpen) {
    window.removeEventListener('keydown', onEscape, true)
    window.removeEventListener('resize', updatePanelPosition)
    window.removeEventListener('scroll', updatePanelPosition, true)
    abortController?.abort()
    clearTimeout(timer)
    loading.value = false
    return
  }
  // Warm the lead modal chunk so a result click opens without waiting on download.
  import('@/components/kanban/viewLead/ViewLeadModal.vue').catch(() => {})
  suppressWatch = true
  payload.value = null
  structured.value = {}
  suppress.value = []
  confirmed.value = false
  activeTab.value = 'all'
  error.value = ''
  activeKey.value = ''
  loading.value = false
  window.addEventListener('keydown', onEscape, true)
  window.addEventListener('resize', updatePanelPosition)
  window.addEventListener('scroll', updatePanelPosition, true)
  await nextTick()
  updatePanelPosition()
  suppressWatch = false
})

watch(query, () => {
  if (suppressWatch) return
  structured.value = {}
  suppress.value = []
  confirmed.value = false
  schedule()
})

watch(activeTab, () => {
  if (suppressWatch) return
  schedule()
})

watch(smartSearchAnchorEl, () => {
  if (open.value) updatePanelPosition()
})

onMounted(() => {
  unbindShortcut = bindSmartSearchShortcut()
  unbindNav = registerSmartSearchNav({
    move,
    activate: () => activate(),
  })
})

onUnmounted(() => {
  unbindShortcut?.()
  unbindShortcut = null
  unbindNav?.()
  unbindNav = null
  abortController?.abort()
  clearTimeout(timer)
  window.removeEventListener('keydown', onEscape, true)
  window.removeEventListener('resize', updatePanelPosition)
  window.removeEventListener('scroll', updatePanelPosition, true)
  closeSmartSearch()
})

function updatePanelPosition() {
  const anchor = smartSearchAnchorEl.value
  const gap = 10
  const minWidth = 320
  const maxWidth = Math.min(520, window.innerWidth - 24)
  const vw = window.innerWidth
  const vh = window.innerHeight

  if (!anchor) {
    panelStyle.value = {
      top: '56px',
      right: '12px',
      left: 'auto',
      width: `${Math.min(420, vw - 24)}px`,
      maxHeight: `${Math.min(vh - 72, 520)}px`,
    }
    return
  }

  const rect = anchor.getBoundingClientRect()
  const width = Math.min(maxWidth, Math.max(rect.width, minWidth, Math.min(420, vw - 24)))
  let left = rect.right - width
  left = Math.max(12, Math.min(left, vw - width - 12))
  // Keep the panel fully below the expanded header input so tabs stay visible.
  const top = Math.min(Math.ceil(rect.bottom + gap), vh - 140)
  const maxHeight = Math.max(220, Math.min(520, vh - top - 16))

  panelStyle.value = {
    top: `${top}px`,
    left: `${left}px`,
    right: 'auto',
    width: `${width}px`,
    maxHeight: `${maxHeight}px`,
  }
}

function close() {
  closeSmartSearch()
}

function onEscape(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    close()
  }
}

function hasSearchInput() {
  if (query.value.trim().length > 0) return true
  return Object.keys(structured.value).some((key) => {
    const value = structured.value[key]
    return value !== undefined && value !== null && value !== ''
  })
}

function schedule() {
  clearTimeout(timer)
  if (!hasSearchInput()) {
    abortController?.abort()
    loading.value = false
    payload.value = null
    error.value = ''
    activeKey.value = ''
    return
  }
  timer = setTimeout(runNow, 400)
}

function runNow() {
  if (!hasSearchInput()) {
    loading.value = false
    payload.value = null
    return
  }
  const current = ++seq
  abortController?.abort()
  abortController = new AbortController()
  loading.value = true
  error.value = ''
  const params = {
    q: query.value,
    types: activeTab.value,
    limit: 5,
    confirmed: confirmed.value ? 1 : 0,
    ...structured.value,
  }
  if (suppress.value.length) params.suppress = suppress.value.join(',')

  api.get('/smart-search', { params, signal: abortController.signal })
    .then((response) => {
      if (current !== seq) return
      payload.value = response.data?.data || null
      if (!flat.value.some((row) => itemKey(row) === activeKey.value)) {
        activeKey.value = flat.value[0] ? itemKey(flat.value[0]) : ''
      }
    })
    .catch((err) => {
      if (current !== seq) return
      if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return
      if (err?.response?.status === 429) {
        error.value = 'Too many searches — wait a moment and try again.'
      } else {
        error.value = 'Search could not be completed.'
      }
      payload.value = null
    })
    .finally(() => {
      if (current === seq) loading.value = false
    })
}

function confirmSearch() {
  confirmed.value = true
  runNow()
}

function removeChip(chip) {
  const key = chip.key === 'requirement_area' ? 'requirement_area' : chip.key
  if (!suppress.value.includes(key)) suppress.value.push(key)
  const next = { ...structured.value }
  if (key === 'area') delete next.area_id
  if (key === 'bedrooms') delete next.bedrooms
  if (key === 'purpose') delete next.listing_status
  if (key === 'property_type') delete next.property_type_id
  if (key === 'project') delete next.project_id
  if (key === 'price') {
    delete next.min_price
    delete next.max_price
  }
  if (key === 'created') {
    delete next.created_from
    delete next.created_to
  }
  if (key === 'requirement_area') delete next.requirement_area_id
  structured.value = next
  confirmed.value = true
  runNow()
}

function applyChoice(choice) {
  const next = {}
  if (choice.area_id) next.area_id = choice.area_id
  if (choice.project_id) next.project_id = choice.project_id
  if (choice.requirement_area_id) next.requirement_area_id = choice.requirement_area_id
  structured.value = next
  confirmed.value = true
  if (choice.requirement_area_id) activeTab.value = 'leads'
  else activeTab.value = 'listings'
  runNow()
}

function move(step) {
  const rows = flat.value
  if (!rows.length) return
  const index = Math.max(0, rows.findIndex((row) => itemKey(row) === activeKey.value))
  const next = rows[(index + step + rows.length) % rows.length]
  activeKey.value = itemKey(next)
  document.getElementById(itemDomId(next))?.scrollIntoView({ block: 'nearest' })
}

function toggleTab(id) {
  // Default search is across everything (no "All" tab shown).
  // Click a module to filter; click it again to clear the filter.
  activeTab.value = activeTab.value === id ? 'all' : id
}

function activate(item) {
  const row = item || flat.value.find((entry) => itemKey(entry) === activeKey.value)
  if (!row || row.action === 'choose_place') return
  close()
  if (row.action === 'open_lead') {
    // Seed enough fields for an instant modal shell while GET /leads/{id} finishes.
    const meta = row.meta || {}
    openLeadView(row.id, {
      id: row.id,
      lead_name: row.label,
      lead_number: meta.lead_number || null,
      stage: meta.stage ? { name: meta.stage } : null,
      area: meta.area ? { name: meta.area } : null,
      responsible_person: meta.agent ? { name: meta.agent, display_name: meta.agent } : null,
    })
    return
  }
  if (row.action === 'open_deal') {
    if (route.path === '/kanban_deal') {
      openDealView(row.id, { autoEditSection: false })
      router.replace({ query: { ...route.query, deal: String(row.id) } }).catch(() => {})
    } else {
      router.push({ path: '/kanban_deal', query: { deal: String(row.id) } }).catch(() => {})
    }
    return
  }
  if (row.action === 'open_listing') {
    router.push({ name: 'property.show', params: { id: row.id } }).catch(() => {})
    return
  }
  if (row.action === 'open_project') {
    router.push({ name: 'show-project', params: { id: row.id } }).catch(() => {})
  }
}

function itemKey(item) {
  return `${item.type}:${item.id}`
}

function itemDomId(item) {
  return `oia-search-opt-${item.type}-${item.id}`
}

function iconFor(item) {
  if (item.type === 'lead') return 'lucide:user-round'
  if (item.type === 'deal') return 'lucide:handshake'
  if (item.type === 'listing') return 'lucide:building-2'
  return 'lucide:map-pin'
}

function groupTitle(group) {
  if (group?.type === 'places' || group?.label === 'Places') return 'Location'
  return group?.label || ''
}

function highlight(text) {
  const safe = escapeHtml(String(text || ''))
  const needle = query.value.trim()
  if (needle.length < 2) return safe
  const pattern = new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'ig')
  return safe.replace(pattern, (match) => `<mark>${match}</mark>`)
}

function escapeHtml(value) {
  return value.replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]))
}
</script>

<style scoped>
.oia-search {
  --oia-navy: #1b2438;
  --oia-navy-soft: #243049;
  --oia-line: rgba(27, 36, 56, 0.1);
  --oia-muted: #6b7289;
  --oia-ease: cubic-bezier(0.22, 1, 0.36, 1);
  position: fixed;
  inset: 0;
  z-index: 14000;
  pointer-events: none;
}

.oia-search > * {
  pointer-events: auto;
}

.oia-search__backdrop {
  position: absolute;
  inset: 0;
  border: 0;
  cursor: default;
  /* No backdrop-filter — it was blurring the header search text. */
  background: rgba(16, 22, 36, 0.16);
}

.oia-search__panel {
  position: fixed;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  padding-top: 10px;
  background:
    linear-gradient(165deg, rgba(255, 255, 255, 0.99) 0%, rgba(248, 249, 252, 0.97) 100%);
  color: var(--oia-navy);
  border: 1px solid rgba(var(--color-primary-rgb, 115, 62, 135), 0.14);
  transform-origin: top center;
  box-shadow:
    0 1px 0 rgba(255, 255, 255, 0.9) inset,
    0 24px 56px rgba(16, 22, 36, 0.2),
    0 8px 18px rgba(var(--color-primary-rgb, 115, 62, 135), 0.1),
    0 0 0 1px rgba(255, 255, 255, 0.4) inset;
}

.oia-search__ai-status {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: none;
  padding: 2px 12px 0;
  color: var(--color-primary, #733e87);
  font-size: 11px;
  font-weight: 600;
}

.oia-search__ai-dots {
  display: inline-flex;
  gap: 3px;
}

.oia-search__ai-dots i {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.35;
  animation: oia-search-dot 1s ease-in-out infinite;
}

.oia-search__ai-dots i:nth-child(2) { animation-delay: 0.15s; }
.oia-search__ai-dots i:nth-child(3) { animation-delay: 0.3s; }

.oia-search__tabs {
  display: flex;
  align-items: center;
  gap: 2px;
  flex: none;
  flex-shrink: 0;
  min-height: 36px;
  padding: 4px 10px 6px;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
}

.oia-search__tabs::-webkit-scrollbar { display: none; }

.oia-search__tab {
  border: 0;
  background: transparent;
  color: var(--oia-muted);
  border-radius: 8px;
  min-height: 32px;
  height: 32px;
  padding: 0 10px;
  cursor: pointer;
  font: inherit;
  font-size: 12px;
  font-weight: 550;
  line-height: 1;
  white-space: nowrap;
  flex: none;
}

.oia-search__tab:hover {
  color: var(--oia-navy);
  background: rgba(27, 36, 56, 0.04);
}

.oia-search__tab.is-on {
  color: var(--color-primary-ink, #3f2148);
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.1);
  box-shadow: inset 0 -2px 0 var(--color-primary, #733e87);
}

.oia-search__chips,
.oia-search__choices {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  flex: none;
  padding: 4px 10px 0;
}

.oia-search__chip,
.oia-search__choice,
.oia-search__confirm-btn,
.oia-search__text-btn {
  border: 1px solid var(--oia-line);
  background: #fff;
  color: var(--oia-navy-soft);
  border-radius: 999px;
  min-height: 26px;
  padding: 0 9px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font: inherit;
  font-size: 11px;
  font-weight: 550;
}

.oia-search__chip:hover,
.oia-search__choice:hover,
.oia-search__text-btn:hover {
  border-color: rgba(var(--color-primary-rgb, 115, 62, 135), 0.28);
  background: var(--color-primary-soft, #f7f2f8);
}

.oia-search__confirm {
  flex: none;
  padding: 8px 10px 0;
}

.oia-search__confirm-btn {
  background: var(--color-primary, #733e87);
  color: #fff;
  border-color: transparent;
  min-height: 30px;
  font-size: 12px;
}

.oia-search__preview,
.oia-search__banner,
.oia-search__state,
.oia-search__empty {
  margin: 0;
  padding: 8px 12px 0;
  color: var(--oia-muted);
  font-size: 12px;
  line-height: 1.4;
}

.oia-search__state {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-height: 72px;
  padding: 16px 12px;
}

.oia-search__results {
  overflow: auto;
  padding: 4px 6px 10px;
  flex: 1 1 auto;
  min-height: 0;
  overscroll-behavior: contain;
}

.oia-search__results.is-refreshing {
  opacity: 0.55;
}

.oia-search__group {
  animation: oia-search-rise 180ms var(--oia-ease) both;
  animation-delay: calc(var(--g, 0) * 28ms);
}

.oia-search__group-label {
  margin: 6px 8px 2px;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 650;
  color: var(--oia-muted);
}

.oia-search__result {
  width: 100%;
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  text-align: start;
  border: 0;
  background: transparent;
  border-radius: 10px;
  min-height: 48px;
  padding: 7px 8px;
  cursor: pointer;
  color: inherit;
  animation: oia-search-rise 170ms var(--oia-ease) both;
  animation-delay: calc((var(--g, 0) * 28ms) + (var(--r, 0) * 22ms));
  transition:
    background 140ms var(--oia-ease),
    transform 140ms var(--oia-ease),
    box-shadow 140ms var(--oia-ease);
}

.oia-search__result:hover,
.oia-search__result.is-active {
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.09);
  transform: translateZ(4px) translateY(-0.5px);
  box-shadow: 0 6px 14px rgba(16, 22, 36, 0.06);
}

.oia-search__result.is-active {
  box-shadow:
    inset 2px 0 0 var(--color-primary, #733e87),
    0 6px 14px rgba(16, 22, 36, 0.06);
}

.oia-search__icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  background: rgba(27, 36, 56, 0.05);
  color: var(--color-primary, #733e87);
  border: 1px solid rgba(27, 36, 56, 0.06);
}

.oia-search__copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.oia-search__label {
  font-weight: 600;
  font-size: 13px;
  line-height: 1.25;
  color: var(--oia-navy);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.oia-search__label :deep(mark) {
  background: rgba(212, 175, 55, 0.28);
  color: inherit;
  border-radius: 3px;
  padding: 0 1px;
}

.oia-search__exact {
  margin-inline-start: 6px;
  font-size: 9px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 700;
  color: var(--color-primary, #733e87);
}

.oia-search__meta {
  color: var(--oia-muted);
  font-size: 11px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.oia-search__type {
  font-size: 9px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #98a0b4;
  font-weight: 650;
}

.oia-search__skeleton {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr);
  gap: 8px;
  align-items: center;
  min-height: 48px;
  margin: 4px;
  padding: 7px 8px;
  border-radius: 10px;
  animation: oia-search-rise 160ms var(--oia-ease) both;
  animation-delay: calc(var(--i, 1) * 40ms);
}

.oia-search__skeleton-icon,
.oia-search__skeleton-line {
  display: block;
  border-radius: 8px;
  background: linear-gradient(
    90deg,
    rgba(27, 36, 56, 0.05),
    rgba(var(--color-primary-rgb, 115, 62, 135), 0.14),
    rgba(27, 36, 56, 0.05)
  );
  background-size: 220% 100%;
  animation: oia-search-shimmer 1.05s linear infinite;
}

.oia-search__skeleton-icon {
  width: 28px;
  height: 28px;
}

.oia-search__skeleton-copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.oia-search__skeleton-line--title {
  height: 10px;
  width: min(62%, 220px);
}

.oia-search__skeleton-line--meta {
  height: 8px;
  width: min(42%, 140px);
  opacity: 0.75;
}

.oia-search__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

.oia-search-shell-enter-active,
.oia-search-shell-leave-active {
  transition: opacity 160ms var(--oia-ease);
}

.oia-search-shell-enter-active .oia-search__panel,
.oia-search-shell-leave-active .oia-search__panel {
  transition:
    opacity 180ms var(--oia-ease),
    transform 180ms var(--oia-ease);
}

.oia-search-shell-enter-active .oia-search__backdrop,
.oia-search-shell-leave-active .oia-search__backdrop {
  transition: opacity 180ms var(--oia-ease);
}

.oia-search-shell-enter-from .oia-search__backdrop,
.oia-search-shell-leave-to .oia-search__backdrop {
  opacity: 0;
}

.oia-search-shell-enter-from .oia-search__panel,
.oia-search-shell-leave-to .oia-search__panel {
  opacity: 0;
  transform: translateY(-8px) scale(0.97);
}

@keyframes oia-search-rise {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: none; }
}

@keyframes oia-search-shimmer {
  from { background-position: 100% 0; }
  to { background-position: -100% 0; }
}

@keyframes oia-search-dot {
  0%, 80%, 100% { opacity: 0.28; transform: translateY(0); }
  40% { opacity: 1; transform: translateY(-1.5px); }
}

@media (max-width: 720px) {
  .oia-search__result,
  .oia-search__choice,
  .oia-search__chip {
    min-height: 44px;
  }

  .oia-search__type {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .oia-search__group,
  .oia-search__result,
  .oia-search__skeleton,
  .oia-search__skeleton-icon,
  .oia-search__skeleton-line,
  .oia-search__ai-dots i,
  .oia-search-shell-enter-active,
  .oia-search-shell-leave-active,
  .oia-search-shell-enter-active .oia-search__panel,
  .oia-search-shell-leave-active .oia-search__panel {
    animation: none !important;
    transition: none !important;
  }

  .oia-search__results.is-refreshing {
    opacity: 1;
  }

  .oia-search-shell-enter-from .oia-search__panel,
  .oia-search-shell-leave-to .oia-search__panel {
    transform: none;
  }
}
</style>
