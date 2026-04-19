<template>
  <div class="deal-history-root history-content deal-figma-ui">
    <div class="search-area d-flex justify-content-start mb-3 position-relative" ref="searchDropdownAnchorRef">
      <div
        ref="searchWrapperRef"
        class="search-wrapper d-flex align-items-center flex-wrap"
        :class="{
          'search-wrapper-expanded': hasActiveFilters,
          'search-wrapper-focused': showSearchModal
        }"
      >
        <div v-if="hasActiveFilters" class="search-filters-pills d-flex align-items-center flex-wrap gap-2">
          <span v-if="searchFilters.search" class="filter-badge">
            Search: "{{ searchFilters.search }}"
            <iconify-icon icon="lucide:x" @click="removeFilter('search')" />
          </span>
          <span v-if="searchFilters.action" class="filter-badge">
            Event: {{ eventLabel(searchFilters.action) }}
            <iconify-icon icon="lucide:x" @click="removeFilter('action')" />
          </span>
          <span v-if="searchFilters.user && quickPill !== 'by_me'" class="filter-badge">
            User: {{ getUserName(searchFilters.user) }}
            <iconify-icon icon="lucide:x" @click="removeFilter('user')" />
          </span>
          <span
            v-if="quickPillLabel"
            class="filter-badge"
          >
            {{ quickPillLabel }}
            <iconify-icon icon="lucide:x" @click="removeQuickPill" />
          </span>
          <span
            v-else-if="searchFilters.dateFrom || searchFilters.dateTo"
            class="filter-badge"
          >
            Date: {{ searchFilters.dateFrom || 'Any' }} – {{ searchFilters.dateTo || 'Any' }}
            <iconify-icon icon="lucide:x" @click="removeFilter('date')" />
          </span>
        </div>
        <div class="search-input-container d-flex align-items-center">
          <iconify-icon icon="lucide:plus" class="search-plus-inline" aria-hidden="true" />
          <input
            v-model="searchFilters.search"
            type="text"
            class="search-input-field"
            placeholder="+ Search"
            @input="onSearchInput"
            @keydown.enter="applySearch()"
            @focus="openFilterDropdown"
          />
          <button
            type="button"
            class="search-filter-btn"
            aria-label="Open filters"
            @click.stop="toggleFilterDropdown"
          >
            <iconify-icon icon="lucide:sliders-horizontal" />
          </button>
        </div>
        <iconify-icon
          v-if="hasActiveFilters"
          icon="lucide:x"
          class="clear-search-icon"
          @click="clearAllFilters"
        />
      </div>

      <Teleport to="body">
        <div
          v-if="showSearchModal"
          class="history-search-dropdown-outer deal-history-dropdown-outer"
          :style="dropdownPositionStyle"
          ref="dropdownRef"
          @click.stop
        >
          <div class="history-search-dropdown-panel d-flex">
            <div class="history-sidebar-pills d-flex flex-column">
              <button
                type="button"
                class="history-pill"
                :class="{ active: quickPill === 'today' }"
                @click="applyQuickPill('today')"
              >
                Created Today
              </button>
              <button
                type="button"
                class="history-pill"
                :class="{ active: quickPill === 'yesterday' }"
                @click="applyQuickPill('yesterday')"
              >
                Created Yesterday
              </button>
              <button
                type="button"
                class="history-pill"
                :class="{ active: quickPill === 'by_me' }"
                @click="applyQuickPill('by_me')"
              >
                Created By Me
              </button>
            </div>
            <div class="history-search-form-column position-relative flex-grow-1">
              <button class="history-modal-close" type="button" aria-label="Close" @click="showSearchModal = false">
                <iconify-icon icon="lucide:x" width="18" height="18" />
              </button>
              <div class="history-search-modal-body">
                <DealHistorySearchModal
                  :initial-search="searchFilters.search"
                  :initial-action="searchFilters.action"
                  :initial-user="searchFilters.user"
                  :initial-date-from="searchFilters.dateFrom"
                  :initial-date-to="searchFilters.dateTo"
                  :users="users"
                  :event-type-options="dealEventTypeOptions"
                  @search="onSearch"
                  @close="showSearchModal = false"
                />
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>

    <div class="history-table-wrapper">
      <div v-if="loading && historyEntries.length === 0" class="loading-state">
        <div class="text-center p-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 text-muted">Loading history...</p>
        </div>
      </div>

      <div v-else-if="historyEntries.length === 0 && !loading" class="search-empty-state">
        <iconify-icon icon="lucide:history" />
        <h6 class="ui-h-mini">No history entries found</h6>
        <p v-if="hasActiveFilters">No results match your search criteria.</p>
        <p v-else>No history entries available for this deal.</p>
        <button v-if="hasActiveFilters" type="button" class="btn-clear-search" @click="clearAllFilters">
          Clear all filters
        </button>
      </div>

      <template v-else>
        <table class="history-table">
          <thead>
            <tr>
              <th class="th-checkbox" />
              <th>Date &amp; Time</th>
              <th>Created By</th>
              <th>Event Type</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(entry, index) in paginatedEntries"
              :key="entry.id || index"
              :class="{ 'row-selected': isRowSelected(entry.id) }"
            >
              <td class="td-checkbox" @click.stop>
                <input
                  type="checkbox"
                  class="history-row-checkbox"
                  :checked="isRowSelected(entry.id)"
                  @change.stop="toggleRowSelect(entry.id)"
                />
              </td>
              <td class="date-time-column">{{ entry.dateTime }}</td>
              <td class="created-by-column">
                <div class="d-flex align-items-center gap-2">
                  <img
                    v-if="entry.createdBy.avatar"
                    :src="entry.createdBy.avatar"
                    :alt="entry.createdBy.name"
                    class="history-avatar rounded-circle"
                  />
                  <div
                    v-else
                    class="history-avatar rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center"
                  >
                    <iconify-icon icon="lucide:user" class="text-neutral-500" />
                  </div>
                  <span class="created-by-name">{{ entry.createdBy.name }}</span>
                </div>
              </td>
              <td class="event-type-column">
                <span class="event-type-badge">{{ entry.eventType }}</span>
              </td>
              <td class="changes-column">
                <div class="changes-content-plain">{{ entry.description }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <div v-if="totalEntries > 0 && !loading" class="history-pagination">
        <div class="pagination-info position-relative">
          Showing {{ startEntry }} to {{ endEntry }} of {{ totalEntries }} Entries
          <button
            type="button"
            class="entries-per-page-btn p-0 border-0 bg-transparent d-inline-flex align-items-center"
            aria-label="Rows per page"
            @click="showPerPageMenu = !showPerPageMenu"
          >
            <iconify-icon icon="lucide:chevron-up-down" class="entries-icon" />
          </button>
          <div v-if="showPerPageMenu" class="per-page-menu shadow-sm border rounded" @click.stop>
            <button
              v-for="n in perPageOptions"
              :key="n"
              type="button"
              class="per-page-item d-flex align-items-center justify-content-between w-100"
              :class="{ active: entriesPerPage === n }"
              @click="setPerPage(n)"
            >
              {{ n }}
              <iconify-icon v-if="entriesPerPage === n" icon="lucide:check" class="text-primary" />
            </button>
          </div>
        </div>
        <div class="pagination-controls">
          <button type="button" class="pagination-btn" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
            <iconify-icon icon="lucide:chevron-left" />
            Previous
          </button>
          <div class="pagination-numbers">
            <template v-for="(page, idx) in visiblePageItems" :key="'p-' + idx + '-' + page">
              <span v-if="page === 'ellipsis'" class="pagination-ellipsis">…</span>
              <button
                v-else
                type="button"
                class="pagination-number"
                :class="{ active: page === currentPage }"
                @click="goToPage(page)"
              >
                {{ page }}
              </button>
            </template>
          </div>
          <button
            type="button"
            class="pagination-btn"
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            Next
            <iconify-icon icon="lucide:chevron-right" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, getCurrentInstance, nextTick } from 'vue'
import api from '@/plugins/axios'
import DealHistorySearchModal from './DealHistorySearchModal.vue'

const instance = getCurrentInstance()
const $showNotification = (message, type = 'info') => {
  if (instance?.appContext?.config?.globalProperties?.$showNotification) {
    instance.appContext.config.globalProperties.$showNotification(message, type)
  } else if (window.$showNotification) {
    window.$showNotification(message, type)
  }
}

const props = defineProps({
  dealId: {
    type: [Number, String],
    default: null
  },
  isActive: {
    type: Boolean,
    default: false
  }
})

const dealEventTypeOptions = [
  { label: 'View', value: 'view' },
  { label: 'Pipeline changed', value: 'stage_changed' },
  { label: 'Deal updated', value: 'updated' },
  { label: 'Deal created', value: 'created' },
  { label: 'Activity created', value: 'activity_created' },
  { label: 'Activity updated', value: 'activity_updated' },
  { label: 'Activity deleted', value: 'activity_deleted' },
  { label: 'Comment added', value: 'comment_added' },
  { label: 'Comment updated', value: 'comment_updated' },
  { label: 'Comment deleted', value: 'comment_deleted' }
]

const showSearchModal = ref(false)
const searchDropdownAnchorRef = ref(null)
const searchWrapperRef = ref(null)
const dropdownRef = ref(null)
const dropdownPositionStyle = ref({})

const quickPill = ref(null)
let currentUserId = null
try {
  const u = JSON.parse(localStorage.getItem('user') || '{}')
  currentUserId = u?.id || null
} catch (_) {}

const users = ref([])
const searchFilters = ref({
  search: '',
  action: '',
  user: '',
  dateFrom: '',
  dateTo: ''
})

let searchInputDebounce = null
const onSearchInput = () => {
  if (searchInputDebounce) clearTimeout(searchInputDebounce)
  searchInputDebounce = setTimeout(() => {
    applySearch()
  }, 350)
}

const historyEntries = ref([])
const loading = ref(false)
const currentPage = ref(1)
const entriesPerPage = ref(10)
const totalEntries = ref(0)
const totalPages = ref(1)
const showPerPageMenu = ref(false)
const perPageOptions = [10, 20, 30, 40, 50]

const selectedIds = ref(new Set())

const quickPillLabel = computed(() => {
  if (quickPill.value === 'today') return 'Created Today'
  if (quickPill.value === 'yesterday') return 'Created Yesterday'
  if (quickPill.value === 'by_me') return 'Created By Me'
  return ''
})

const hasActiveFilters = computed(() => {
  return !!(
    searchFilters.value.search ||
    searchFilters.value.action ||
    searchFilters.value.user ||
    searchFilters.value.dateFrom ||
    searchFilters.value.dateTo ||
    quickPill.value
  )
})

const startEntry = computed(() => {
  if (totalEntries.value === 0) return 0
  return (currentPage.value - 1) * entriesPerPage.value + 1
})

const endEntry = computed(() => {
  return Math.min(currentPage.value * entriesPerPage.value, totalEntries.value)
})

const paginatedEntries = computed(() => historyEntries.value)

function getPaginationPages(current, last) {
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1)
  }
  const pages = []
  pages.push(1)
  if (current > 3) pages.push('ellipsis')
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  for (let i = start; i <= end; i++) pages.push(i)
  if (current < last - 2) pages.push('ellipsis')
  pages.push(last)
  return pages
}

const visiblePageItems = computed(() => getPaginationPages(currentPage.value, totalPages.value))

function isRowSelected(id) {
  return selectedIds.value.has(id)
}

function toggleRowSelect(id) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function eventLabel(action) {
  const o = dealEventTypeOptions.find((x) => x.value === action)
  return o ? o.label : action
}

function getUserName(userId) {
  const user = users.value.find((u) => u.id === parseInt(userId, 10))
  return user ? user.name : 'Unknown User'
}

function updateDropdownPosition() {
  const wrapper = searchWrapperRef.value
  if (!wrapper) return
  const rect = wrapper.getBoundingClientRect()
  dropdownPositionStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 6}px`,
    left: `${rect.left}px`,
    zIndex: 10600
  }
}

function openFilterDropdown() {
  showSearchModal.value = true
}

function toggleFilterDropdown() {
  showSearchModal.value = !showSearchModal.value
}

function removeFilter(filterType) {
  quickPill.value = null
  if (filterType === 'search') searchFilters.value.search = ''
  if (filterType === 'action') searchFilters.value.action = ''
  if (filterType === 'user') searchFilters.value.user = ''
  if (filterType === 'date') {
    searchFilters.value.dateFrom = ''
    searchFilters.value.dateTo = ''
  }
  applySearch()
}

function removeQuickPill() {
  const was = quickPill.value
  quickPill.value = null
  if (was === 'today' || was === 'yesterday') {
    searchFilters.value.dateFrom = ''
    searchFilters.value.dateTo = ''
  }
  if (was === 'by_me') {
    searchFilters.value.user = ''
  }
  applySearch()
}

function clearAllFilters() {
  quickPill.value = null
  searchFilters.value = {
    search: '',
    action: '',
    user: '',
    dateFrom: '',
    dateTo: ''
  }
  selectedIds.value = new Set()
  applySearch()
  showSearchModal.value = false
}

function getTodayISO() {
  const d = new Date()
  return d.toISOString().slice(0, 10)
}
function getYesterdayISO() {
  const d = new Date()
  d.setDate(d.getDate() - 1)
  return d.toISOString().slice(0, 10)
}

function applyQuickPill(pill) {
  quickPill.value = pill
  if (pill === 'today') {
    const t = getTodayISO()
    searchFilters.value.dateFrom = t
    searchFilters.value.dateTo = t
    searchFilters.value.user = ''
  } else if (pill === 'yesterday') {
    const y = getYesterdayISO()
    searchFilters.value.dateFrom = y
    searchFilters.value.dateTo = y
    searchFilters.value.user = ''
  } else if (pill === 'by_me' && currentUserId) {
    searchFilters.value.user = String(currentUserId)
    searchFilters.value.dateFrom = ''
    searchFilters.value.dateTo = ''
  }
  showSearchModal.value = false
  applySearch()
}

function onSearch(filters) {
  quickPill.value = null
  searchFilters.value = {
    search: filters.search || '',
    action: filters.action || '',
    user: filters.user || '',
    dateFrom: filters.dateFrom || '',
    dateTo: filters.dateTo || ''
  }
  applySearch()
  showSearchModal.value = false
}

function applySearch() {
  currentPage.value = 1
  fetchHistory(1)
}

function mapDealActionLabel(action) {
  if (!action) return '—'
  const normalized = String(action).toLowerCase()
  const dictionary = {
    view: 'View',
    updated: 'Changes',
    stage_changed: 'Pipeline Changed',
    status_changed: 'Status Changed',
    comment_added: 'Comment Added',
    comment_updated: 'Comment Updated',
    comment_deleted: 'Comment Deleted',
    activity_created: 'Activity Created',
    activity_updated: 'Activity Updated',
    activity_deleted: 'Activity Deleted',
    created: 'Deal Created'
  }
  return dictionary[normalized] || normalized.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function transformDealHistoryEntry(entry) {
  let dateTime = '—'
  const rawDate = entry.created_at || entry.date
  if (rawDate) {
    const date = new Date(typeof rawDate === 'string' && rawDate.length === 16 ? rawDate.replace(' ', 'T') : rawDate)
    if (!isNaN(date.getTime())) {
      const now = new Date()
      const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      const entryDate = new Date(date.getFullYear(), date.getMonth(), date.getDate())
      const diffDays = Math.floor((today - entryDate) / (1000 * 60 * 60 * 24))
      const timeStr = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      })
      if (diffDays === 0) {
        dateTime = `Today / ${timeStr}`
      } else if (diffDays === 1) {
        dateTime = `Yesterday / ${timeStr}`
      } else {
        const dateStr = date.toLocaleDateString('en-GB', {
          day: 'numeric',
          month: 'short',
          year: 'numeric'
        })
        dateTime = `${dateStr} / ${timeStr}`
      }
    }
  }

  const user = entry.user || {}
  let avatar = user.avatar || ''
  if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/')) {
    avatar = `/storage/${avatar}`
  }

  const action = entry.action ?? entry.changes?.action
  const description = entry.description && entry.description !== '—' ? entry.description : '—'

  return {
    id: entry.id,
    dateTime,
    createdBy: {
      name: user.name || 'System',
      avatar
    },
    eventType: mapDealActionLabel(action),
    description
  }
}

async function fetchUsers() {
  try {
    const response = await api.get('/users', { params: { per_page: 100 } })
    if (response.data?.data) {
      if (Array.isArray(response.data.data)) {
        users.value = response.data.data
      } else if (response.data.data?.data) {
        users.value = response.data.data.data
      }
    } else if (Array.isArray(response.data)) {
      users.value = response.data
    }
  } catch (e) {
    console.error('Error fetching users', e)
  }
}

async function fetchHistory(page = 1) {
  if (!props.dealId) return
  loading.value = true
  try {
    const params = {
      page,
      per_page: entriesPerPage.value
    }
    if (searchFilters.value.search) params.search = searchFilters.value.search
    if (searchFilters.value.action) params.action = searchFilters.value.action
    if (searchFilters.value.user) params.user_id = searchFilters.value.user
    if (searchFilters.value.dateFrom) params.from_date = searchFilters.value.dateFrom
    if (searchFilters.value.dateTo) params.to_date = searchFilters.value.dateTo

    const { data: body } = await api.get(`/deals/${props.dealId}/history`, { params })
    const payload = body?.data || {}
    const items = payload.items || []
    const rawList = Array.isArray(items) ? items : []

    historyEntries.value = rawList.map((row) => transformDealHistoryEntry(row))

    const p = payload.pagination || {}
    totalEntries.value = parseInt(p.total, 10) || 0
    totalPages.value = parseInt(p.last_page, 10) || 1
    currentPage.value = parseInt(p.current_page, 10) || page
  } catch (e) {
    console.error('Deal history fetch failed', e)
    historyEntries.value = []
    totalEntries.value = 0
    totalPages.value = 1
    $showNotification('Failed to load deal history', 'error')
  } finally {
    loading.value = false
  }
}

async function goToPage(page) {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  await fetchHistory(page)
}

async function setPerPage(n) {
  showPerPageMenu.value = false
  if (entriesPerPage.value === n) return
  entriesPerPage.value = n
  currentPage.value = 1
  await fetchHistory(1)
}

function handleClickOutside(e) {
  if (!showSearchModal.value && !showPerPageMenu.value) return
  const anchor = searchDropdownAnchorRef.value
  const dropdown = dropdownRef.value
  const inAnchor = anchor && anchor.contains(e.target)
  const inDropdown = dropdown && dropdown.contains(e.target)
  const inVSelectAny = e.target.closest && e.target.closest('[class*="vs__"]')
  const inPerPage = e.target.closest && e.target.closest('.pagination-info')
  if (showSearchModal.value && !inAnchor && !inDropdown && !inVSelectAny) {
    showSearchModal.value = false
  }
  if (showPerPageMenu.value && !inPerPage) {
    showPerPageMenu.value = false
  }
}

function onScrollOrResize() {
  if (showSearchModal.value) updateDropdownPosition()
}

watch(showSearchModal, async (open) => {
  if (open) {
    await nextTick()
    updateDropdownPosition()
  }
})

watch(
  () => props.isActive,
  (active) => {
    if (active && props.dealId) {
      clearAllFilters()
      fetchHistory(1)
    }
  }
)

watch(
  () => props.dealId,
  (id, prev) => {
    if (props.isActive && id && id !== prev) {
      clearAllFilters()
      fetchHistory(1)
    }
  }
)

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.addEventListener('scroll', onScrollOrResize, true)
  window.addEventListener('resize', onScrollOrResize)
  fetchUsers()
  if (props.isActive && props.dealId) {
    fetchHistory(1)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  window.removeEventListener('scroll', onScrollOrResize, true)
  window.removeEventListener('resize', onScrollOrResize)
})
</script>

<style scoped>
.deal-history-root.history-content {
  animation: fadeIn 0.3s ease-in-out;
  border: 1px solid #f3f3f3;
  background: #fff;
  border-radius: 12px;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.search-area {
  padding: 12px 24px 0;
}

.search-wrapper {
  width: auto;
  max-width: 420px;
  border: 1px solid #e0e0e0;
  border-radius: 100px;
  background: #f7f7f7;
  min-height: 34px;
  padding: 4px 8px 4px 4px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: default;
  transition:
    max-width 0.35s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.search-wrapper:hover {
  border-color: #d8d8d8;
  background: #f0f0f0;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.search-wrapper-expanded {
  max-width: 560px;
}

.search-wrapper-focused {
  max-width: 640px;
}

.search-wrapper-expanded.search-wrapper-focused {
  max-width: 680px;
}

.search-filters-pills {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.search-input-container {
  flex: 1;
  min-width: 140px;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 2px 4px 2px 12px;
}

.search-plus-inline {
  font-size: 14px;
  color: #999;
  flex-shrink: 0;
}

.search-input-field {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  font-size: 13px;
  line-height: 1.3;
  color: #374151;
  outline: none;
  font-family: inherit;
}

.search-input-field::placeholder {
  color: #999;
}

.search-filter-btn {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  border-radius: 50%;
  color: #999;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition:
    color 0.2s,
    background 0.2s;
}

.search-filter-btn:hover {
  color: #374151;
  background: #ebebeb;
}

.clear-search-icon {
  color: #faa300;
  font-size: 16px;
  cursor: pointer;
  padding: 2px;
}

.clear-search-icon:hover {
  color: #d97706;
}

.filter-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #ebebeb;
  border: 1px solid #d8d8d8;
  border-radius: 100px;
  font-size: 13px;
  color: #555;
}

.filter-badge iconify-icon {
  font-size: 13px;
  color: #999;
  cursor: pointer;
}

.history-search-dropdown-outer.deal-history-dropdown-outer {
  width: 660px;
  flex-shrink: 0;
}

.history-search-dropdown-panel {
  font-family: var(--deal-font, 'Montserrat', ui-sans-serif, sans-serif);
  background: #fff;
  border-radius: 10px;
  box-shadow:
    0 20px 40px -12px rgba(0, 0, 0, 0.1),
    0 0 0 1px rgba(0, 0, 0, 0.04);
  border: 1px solid #f1f5f9;
  overflow: visible;
  width: 660px;
  height: 500px;
  min-width: 660px;
  min-height: 500px;
  max-width: 660px;
  max-height: 500px;
  box-sizing: border-box;
  display: flex;
  flex-shrink: 0;
}

.history-sidebar-pills {
  flex-shrink: 0;
  width: 200px;
  min-width: 200px;
  padding: 18px 16px;
  gap: 12px;
  border-right: 1px solid #e5e7eb;
}

.history-pill {
  padding: 10px 16px;
  min-height: 38px;
  border-radius: 100px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  color: #374151;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition:
    background 0.2s,
    border-color 0.2s,
    color 0.2s;
  white-space: nowrap;
  text-align: left;
  display: flex;
  align-items: center;
}

.history-pill:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.history-pill.active {
  background: #fef3c7;
  border-color: #f59e0b;
  color: #92400e;
}

.history-search-form-column {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: visible;
}

.history-search-form-column .history-search-modal-body {
  flex: 1;
  padding: 16px 28px 28px;
  overflow: visible;
  min-height: 0;
}

.history-modal-close {
  position: absolute;
  top: 12px;
  right: 12px;
  background: transparent;
  border: none;
  color: #1f2937;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: background 0.15s ease;
  z-index: 2;
}

.history-modal-close:hover {
  background: #f3f4f6;
}

.history-search-modal-body {
  padding: 40px 20px 20px;
}

.history-table-wrapper {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  margin: 0 20px 20px;
  overflow: hidden;
}

.history-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background-color: #fff;
}

.history-table thead th {
  background-color: #f9fafb;
  color: #1f2937;
  font-weight: 600;
  font-size: 14px;
  padding: 13px 24px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.th-checkbox,
.td-checkbox {
  width: 48px;
  padding-left: 16px !important;
  padding-right: 8px !important;
}

.history-row-checkbox {
  width: 16px;
  height: 16px;
  accent-color: #faa300;
  cursor: pointer;
}

.history-table tbody tr {
  border-bottom: 1px solid #e5e7eb;
  transition: background-color 0.2s ease;
  cursor: pointer;
}

.history-table tbody tr:hover {
  background-color: #f9fafb;
}

.history-table tbody tr.row-selected {
  background-color: #fffbeb;
}

.history-table tbody tr:last-child {
  border-bottom: none;
}

.history-table tbody td {
  padding: 13px 24px;
  color: #374151;
  font-size: 14px;
  vertical-align: middle;
}

.changes-content-plain {
  white-space: pre-wrap;
  word-break: break-word;
}

.date-time-column {
  white-space: nowrap;
}

.history-avatar {
  width: 32px;
  height: 32px;
  object-fit: cover;
  flex-shrink: 0;
}

.event-type-badge {
  font-weight: 500;
}

.loading-state {
  min-height: 280px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner-border {
  color: #faa300 !important;
}

.search-empty-state {
  text-align: center;
  padding: 48px 24px;
  color: #6b7280;
}

.search-empty-state iconify-icon {
  font-size: 48px;
  color: #9ca3af;
  margin-bottom: 16px;
}

.btn-clear-search {
  background: none;
  border: 1px solid #e5e7eb;
  padding: 8px 16px;
  border-radius: 8px;
  color: #374151;
  font-size: 14px;
  cursor: pointer;
  margin-top: 8px;
}

.history-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background-color: #fff;
  flex-wrap: wrap;
  gap: 12px;
}

.pagination-info {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #6b7280;
  font-size: 14px;
}

.entries-icon {
  font-size: 16px;
  color: #9ca3af;
  cursor: pointer;
}

.entries-per-page-btn {
  cursor: pointer;
  color: #9ca3af;
}

.per-page-menu {
  position: absolute;
  left: 0;
  bottom: 100%;
  margin-bottom: 4px;
  min-width: 120px;
  background: #fff;
  z-index: 20;
  padding: 4px 0;
}

.per-page-item {
  border: none;
  background: #fff;
  padding: 8px 14px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
  gap: 8px;
}

.per-page-item:hover {
  background: #f9fafb;
}

.per-page-item.active {
  background: #eff6ff;
  color: #1d4ed8;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pagination-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background-color: #fff;
  color: #374151;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
  background-color: #f9fafb;
  border-color: #d1d5db;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-numbers {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-number {
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  border: 1px solid transparent;
  border-radius: 999px;
  background-color: transparent;
  color: #374151;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pagination-number:hover {
  background-color: #f9fafb;
  border-color: #e5e7eb;
}

.pagination-number.active {
  background-color: #f3f4f6;
  color: #1f2937;
  font-weight: 600;
  border-color: #e5e7eb;
}

.pagination-ellipsis {
  padding: 0 6px;
  color: #9ca3af;
  user-select: none;
}

.bg-neutral-200 {
  background-color: #f3f4f6;
}

.text-neutral-500 {
  color: #6b7280;
}

.radius-12 {
  border-radius: 12px;
}
</style>
