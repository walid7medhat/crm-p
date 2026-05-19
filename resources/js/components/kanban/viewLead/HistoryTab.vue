<template>
    <div class="info-card bg-white p-3 radius-12 shadow-sm history-content">
        <!-- Search area: bar + dropdown (sidebar pills INSIDE popup) -->
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
                        <iconify-icon icon="lucide:x" @click="removeFilter('search')"></iconify-icon>
                    </span>
                    <span v-if="searchFilters.action" class="filter-badge">
                        Event: {{ getActionLabel(searchFilters.action) }}
                        <iconify-icon icon="lucide:x" @click="removeFilter('action')"></iconify-icon>
                    </span>
                    <span v-if="searchFilters.user" class="filter-badge">
                        User: {{ getUserName(searchFilters.user) }}
                        <iconify-icon icon="lucide:x" @click="removeFilter('user')"></iconify-icon>
                    </span>
                    <span v-if="searchFilters.dateFrom || searchFilters.dateTo" class="filter-badge">
                        Date: {{ searchFilters.dateFrom || 'Any' }} – {{ searchFilters.dateTo || 'Any' }}
                        <iconify-icon icon="lucide:x" @click="removeFilter('date')"></iconify-icon>
                    </span>
                </div>
                <div class="search-input-container d-flex align-items-center">
                    <input
                        v-model="searchFilters.search"
                        type="text"
                        class="search-input-field"
                        placeholder="Search..."
                        @input="onSearchInput"
                        @keydown.enter="applySearch()"
                        @focus="showSearchModal = true"
                    />
                    <button
                        type="button"
                        class="search-filter-btn"
                        aria-label="Open filters"
                        @click.stop="showSearchModal = true"
                    >
                        <iconify-icon icon="lucide:sliders-horizontal"></iconify-icon>
                    </button>
                </div>
                <iconify-icon
                    v-if="hasActiveFilters"
                    icon="lucide:x"
                    class="clear-search-icon"
                    @click="clearAllFilters"
                />
            </div>

            <!-- Dropdown panel: under search input so you can write + choose (teleport so not clipped) -->
            <Teleport to="body">
                <div
                    v-if="showSearchModal"
                    class="history-search-dropdown-outer"
                    :style="dropdownPositionStyle"
                    ref="dropdownRef"
                    @click.stop
                >
                    <div class="history-search-dropdown-panel d-flex">
                    <!-- Sidebar pills INSIDE popup -->
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
                    <!-- Form content -->
                    <div class="history-search-form-column position-relative flex-grow-1">
                        <button class="history-modal-close" aria-label="Close" @click="showSearchModal = false">
                            <iconify-icon icon="lucide:x" width="18" height="18"></iconify-icon>
                        </button>
                        <div class="history-search-modal-body">
                            <HistorySearchForm
                                :initial-search="searchFilters.search"
                                :initial-action="searchFilters.action"
                                :initial-user="searchFilters.user"
                                :initial-date-from="searchFilters.dateFrom"
                                :initial-date-to="searchFilters.dateTo"
                                :users="users"
                                @search="onSearch"
                                @close="showSearchModal = false"
                            />
                        </div>
                    </div>
                    </div>
                </div>
            </Teleport>
        </div>

        <!-- History Table -->
        <div class="history-table-wrapper">
            <!-- Loading State -->
            <div v-if="loading && historyEntries.length === 0" class="loading-state">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading history...</p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="historyEntries.length === 0 && !loading" class="search-empty-state">
                <iconify-icon icon="lucide:history"></iconify-icon>
                <h4>No history entries found</h4>
                <p v-if="hasActiveFilters">No results match your search criteria.</p>
                <p v-else>No history entries available for this lead.</p>
                <button v-if="hasActiveFilters" class="btn-clear-search" @click="clearAllFilters">
                    Clear all filters
                </button>
            </div>

            <!-- History Table -->
            <template v-else>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Created By</th>
                            <th>Event Type</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(entry, index) in paginatedEntries" :key="entry.id || index">
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
                                        <iconify-icon icon="lucide:user" class="text-neutral-500"></iconify-icon>
                                    </div>
                                    <span class="created-by-name"  v-html="entry.createdBy.name"></span>
                                </div>
                            </td>
                            <td class="event-type-column">
                                <span class="event-type-badge">{{ entry.eventType }}</span>
                            </td>
                            <td class="changes-column">
                                <div class="changes-content" v-html="entry.changes"></div>
                                <span v-if="entry.count" class="changes-count">({{ entry.count }})</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
            
            <!-- Pagination Footer -->
            <div class="history-pagination" v-if="totalEntries > 0 && !loading">
                <div class="pagination-info">
                    Showing {{ startEntry }} to {{ endEntry }} of {{ totalEntries }} Entries
                    <iconify-icon icon="lucide:chevron-up-down" class="entries-icon"></iconify-icon>
                </div>
                <div class="pagination-controls">
                    <button 
                        class="pagination-btn" 
                        :disabled="currentPage === 1"
                        @click="goToPage(currentPage - 1)"
                    >
                        <iconify-icon icon="lucide:chevron-left"></iconify-icon>
                        Previous
                    </button>
                    <div class="pagination-numbers">
                        <button 
                            v-for="page in visiblePages" 
                            :key="page"
                            class="pagination-number"
                            :class="{ active: page === currentPage }"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                    </div>
                    <button 
                        class="pagination-btn" 
                        :disabled="currentPage === totalPages"
                        @click="goToPage(currentPage + 1)"
                    >
                        Next
                        <iconify-icon icon="lucide:chevron-right"></iconify-icon>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, getCurrentInstance, nextTick } from 'vue'
import api from '@/plugins/axios'
import HistorySearchForm from './HistorySearchModal.vue'

const instance = getCurrentInstance()

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (instance?.appContext?.config?.globalProperties?.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(message, type)
    } else if (window.$showNotification) {
        window.$showNotification(message, type)
    }
}

const props = defineProps({
    lead: {
        type: Object,
        default: null
    },
    isActive: {
        type: Boolean,
        default: false
    }
})

// Dropdown state (search opens dropdown, not modal)
const showSearchModal = ref(false)
const searchDropdownAnchorRef = ref(null)
const searchWrapperRef = ref(null)
const dropdownRef = ref(null)

// Position dropdown under search bar, aligned to same left as search input (same line)
const dropdownPositionStyle = ref({})
function updateDropdownPosition() {
    const wrapper = searchWrapperRef.value
    if (!wrapper) return
    const rect = wrapper.getBoundingClientRect()
    dropdownPositionStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 6}px`,
        left: `${rect.left}px`,
        zIndex: 10500
    }
}

// Quick filter pill: 'today' | 'yesterday' | 'by_me' | null
const quickPill = ref(null)
let currentUserId = null
try {
    const u = JSON.parse(localStorage.getItem('user') || '{}')
    currentUserId = u?.id || null
} catch (_) {}

// Users list for filter dropdown
const users = ref([])

// Search filters state
const searchFilters = ref({
    search: '',
    action: '',
    user: '',
    dateFrom: '',
    dateTo: ''
})

// Debounce typing in search input
let searchInputDebounce = null
const onSearchInput = () => {
    if (searchInputDebounce) clearTimeout(searchInputDebounce)
    searchInputDebounce = setTimeout(() => {
        applySearch()
    }, 350)
}

// History data
const historyEntries = ref([])
const loading = ref(false)
const nextPageUrl = ref(null)

// Pagination state
const currentPage = ref(1)
const entriesPerPage = ref(7)
const totalEntries = ref(0)
const totalPages = ref(1)

// Computed property for active filters
const hasActiveFilters = computed(() => {
    return searchFilters.value.search || 
           searchFilters.value.action || 
           searchFilters.value.user || 
           searchFilters.value.dateFrom || 
           searchFilters.value.dateTo
})

// Computed properties for pagination
const startEntry = computed(() => {
    if (totalEntries.value === 0) return 0
    return (currentPage.value - 1) * entriesPerPage.value + 1
})

const endEntry = computed(() => {
    return Math.min(currentPage.value * entriesPerPage.value, totalEntries.value)
})

const paginatedEntries = computed(() => {
    return historyEntries.value
})

const visiblePages = computed(() => {
    const pages = []
    const maxVisible = 3
    const total = totalPages.value
    
    if (total <= maxVisible) {
        for (let i = 1; i <= total; i++) {
            pages.push(i)
        }
    } else {
        pages.push(1, 2, 3)
    }
    
    return pages
})

// Helper functions
const getActionLabel = (action) => {
    const labels = {
        'view': 'View',
        'stage_changed': 'Status Changed',
        'assigned': 'Responsible Person Changed',
        'updated': 'Lead Updated',
        'created': 'Lead Created'
    }
    return labels[action] || action
}

const getUserName = (userId) => {
    const user = users.value.find(u => u.id === parseInt(userId))
    return user ? user.name : 'Unknown User'
}

// Filter methods
const removeFilter = (filterType) => {
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

const clearAllFilters = () => {
    quickPill.value = null
    searchFilters.value = {
        search: '',
        action: '',
        user: '',
        dateFrom: '',
        dateTo: ''
    }
    applySearch()
    showSearchModal.value = false
}

// Quick pills: Created Today, Created Yesterday, Created By Me
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
    applySearch()
}

// Handle search from form
const onSearch = (filters) => {
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

// Apply search and fetch data
const applySearch = () => {
    currentPage.value = 1
    fetchHistory(1)
}

// Click outside to close dropdown; don't close when interacting with "Created By" v-select
function handleClickOutside(e) {
    if (!showSearchModal.value) return
    const anchor = searchDropdownAnchorRef.value
    const dropdown = dropdownRef.value
    const inAnchor = anchor && anchor.contains(e.target)
    const inDropdown = dropdown && dropdown.contains(e.target)
    const inVSelectMenu = e.target.closest && e.target.closest('.vs__dropdown-menu')
    const inVSelect = e.target.closest && e.target.closest('.v-select')
    const inVSelectAny = e.target.closest && e.target.closest('[class*="vs__"]')
    if (!inAnchor && !inDropdown && !inVSelectMenu && !inVSelect && !inVSelectAny) {
        showSearchModal.value = false
    }
}

// Keep dropdown position under search bar when scroll/resize
function onScrollOrResize() {
    if (showSearchModal.value) updateDropdownPosition()
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    window.addEventListener('scroll', onScrollOrResize, true)
    window.addEventListener('resize', onScrollOrResize)
})
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('scroll', onScrollOrResize, true)
    window.removeEventListener('resize', onScrollOrResize)
})

// Transform API response
const transformHistoryEntry = (entry) => {
    // Format date and time
    let dateTime = '---'
    if (entry.created_at || entry.date) {
        const date = new Date(entry.created_at || entry.date)
        const now = new Date()
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
        const entryDate = new Date(date.getFullYear(), date.getMonth(), date.getDate())
        
        const diffTime = today - entryDate
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))
        
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
    
    // Get created by user info
    const user = entry.user || {}
    let avatar = user.avatar || ''
    
    if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/')) {
        avatar = `/storage/${avatar}`
    }
    
    // Get event type
    const changes = entry.changes || {}
    let eventType = changes.action || entry.action || '---'
    
    const eventTypeMap = {
        'view': 'View',
        'revert': 'Revert',
        'stage_changed': 'Status Changed',
        'assigned': 'Responsible Person Changed',
        'updated': 'Lead Updated',
        'created': 'Lead Created'
    }
    const fieldLabels = {
        work_phone: 'Primary Phone',
        work_phone_2: 'Secondary Phone',
        email: 'Primary Email',
        secondary_email: 'Secondary Email',
    }
    eventType = eventTypeMap[eventType] || eventType.charAt(0).toUpperCase() + eventType.slice(1).replace(/_/g, ' ')
    
    // Format changes
    let changesHtml = '---'
    let count = null
    
    if (changes) {
        if (changes.old_stage && changes.new_stage) {
            changesHtml = `<span class="change-old">${changes.old_stage}</span> <span class="change-arrow">→</span> <span class="change-new">${changes.new_stage}</span>`
            count = 3
        } else if (changes.old_person && changes.new_person) {
            changesHtml = `<span class="change-old">${changes.old_person}</span> <span class="change-arrow">→</span> <span class="change-new">${changes.new_person}</span>`
        } else if (changes.new_stage) {
            changesHtml = `<span class="change-new">${changes.new_stage}</span>`
        } else if (changes.new_person) {
            changesHtml = `<span class="change-new">${changes.new_person}</span>`
        }
        else if (changes.fields && typeof changes.fields === 'object') {
        
            const entries = Object.entries(changes.fields)
                .filter(([key]) => key !== 'updated_at');
        
            changesHtml = entries.map(([key, val]) => {
        
                const oldVal = val?.old !== null && val?.old !== undefined ? val.old : '-'
                const newVal = val?.new !== null && val?.new !== undefined ? val.new : '-'
        
                const label = fieldLabels[key] || key
                        .replace(/_/g, ' ')
                        .replace(/\b\w/g, l => l.toUpperCase())
        
                return `
                    <div class="history-change" style="margin-bottom:4px;">
                        <strong>${label}:</strong>
                        <span class="change-old">${oldVal}</span>
                        <span class="change-arrow"> → </span>
                        <span class="change-new">${newVal}</span>
                    </div>
                `
            }).join('')
        }
    }
    let name = ''

        if (user.name) {
            name = user.name
        } else {
            const responsePerson = entry.response_person || ''
            const source = entry.source || ''
        
            if (responsePerson && source) {
              name = `${responsePerson}<br><small class="text-muted">${source}</small>`
            } else if (responsePerson) {
                name = responsePerson
            } else if (source) {
                name = source
            } else {
                name = '---'
            }
        }
    return {
        id: entry.id,
        dateTime: dateTime,
        createdBy: {
            name: name,
            avatar: user.avatar || avatar,
        },
        eventType: eventType,
        changes: changesHtml,
        count: count
    }
}

// Fetch users
const fetchUsers = async () => {
    try {
        const response = await api.get('/users', {
            params: { per_page: 100 }
        })
        
        if (response.data?.data) {
            if (Array.isArray(response.data.data)) {
                users.value = response.data.data
            } else if (response.data.data?.data) {
                users.value = response.data.data.data
            }
        } else if (Array.isArray(response.data)) {
            users.value = response.data
        }
    } catch (error) {
        console.error('Error fetching users:', error)
    }
}

// Fetch history
const fetchHistory = async (page = 1) => {
    if (!props.lead?.id) {
        return
    }
    
    try {
        loading.value = true
        
        const params = {
            page: page,
            per_page: entriesPerPage.value
        }
        
        // Add filters
        if (searchFilters.value.search) {
            params.search = searchFilters.value.search
        }
        
        if (searchFilters.value.action) {
            params.action = searchFilters.value.action
        }
        
        if (searchFilters.value.user) {
            params.user_id = searchFilters.value.user
        }
        
        if (searchFilters.value.dateFrom) {
            params.from_date = searchFilters.value.dateFrom
        }
        
        if (searchFilters.value.dateTo) {
            params.to_date = searchFilters.value.dateTo
        }
        
        const response = await api.get(`/leads/${props.lead.id}/history`, { params })
        
        // Handle response
        const responseData = response.data
        
        let historyData = []
        let paginationData = null
        
        if (responseData.data && typeof responseData.data === 'object') {
            if (Array.isArray(responseData.data.items)) {
                historyData = responseData.data.items
                paginationData = responseData.data.pagination
            } else if (Array.isArray(responseData.data.data)) {
                historyData = responseData.data.data
                paginationData = responseData.meta || responseData.data.pagination
            } else if (Array.isArray(responseData.data)) {
                historyData = responseData.data
                paginationData = responseData.meta
            }
        } else if (Array.isArray(responseData.data)) {
            historyData = responseData.data
            paginationData = responseData.meta
        }
        
        historyEntries.value = historyData.map(transformHistoryEntry)
        
        if (paginationData) {
            totalEntries.value = parseInt(paginationData.total) || 0
            totalPages.value = parseInt(paginationData.last_page) || 1
            currentPage.value = parseInt(paginationData.current_page) || page
        } else {
            totalEntries.value = historyData.length
            totalPages.value = historyData.length > 0 ? Math.ceil(historyData.length / entriesPerPage.value) : 0
            currentPage.value = 1
        }
        
    } catch (error) {
        console.error('Error fetching history:', error)
        historyEntries.value = []
        totalEntries.value = 0
        totalPages.value = 1
        $showNotification('Failed to load history', 'error')
    } finally {
        loading.value = false
    }
}

// Go to page
const goToPage = async (page) => {
    if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        await fetchHistory(page)
        document.querySelector('.history-content')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}

// When modal opens, position dropdown under search bar
watch(showSearchModal, async (isOpen) => {
    if (isOpen) {
        await nextTick()
        updateDropdownPosition()
    }
})

// Watch for tab activation
watch(() => props.isActive, (isActive) => {
    if (isActive && props.lead?.id) {
        clearAllFilters()
        fetchHistory(1)
    }
})

// Watch for lead changes
watch(() => props.lead?.id, (newId, oldId) => {
    if (props.isActive && newId && newId !== oldId) {
        clearAllFilters()
        fetchHistory(1)
    }
})

// Initial fetch
onMounted(() => {
    fetchUsers()
    if (props.isActive && props.lead?.id) {
        fetchHistory(1)
    }
})
</script>

<style scoped>
.history-content {
    animation: fadeIn 0.3s ease-in-out;
    border: 1px solid #F3F3F3;
    background: white;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Search Area – compact bar like reference image (not full width) */
.search-area {
    padding: 12px 24px 0;
}

.search-wrapper {
    width: auto;
    max-width: 420px;
    border: 1px solid #E0E0E0;
    border-radius: 100px;
    background: #F7F7F7;
    min-height: 34px;
    padding: 4px 8px 4px 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: max-width 0.35s ease, border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.search-wrapper:hover {
    border-color: #D8D8D8;
    background: #F0F0F0;
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
    color: #999999;
}

.search-filter-btn {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    border-radius: 50%;
    color: #999999;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s, background 0.2s;
}

.search-filter-btn:hover {
    color: #374151;
    background: #EBEBEB;
}

.search-filter-btn iconify-icon {
    font-size: 16px;
}

.clear-search-icon {
    color: #733E87;
    font-size: 16px;
    cursor: pointer;
    padding: 2px;
}

.clear-search-icon:hover {
    color: #D97706;
}

/* Dropdown panel – position set by JS (Teleport); fixed size */
.history-search-dropdown-outer {
    width: 660px;
    flex-shrink: 0;
}

.history-search-dropdown-panel {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(99, 102, 241, 0.08);
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

/* Sidebar pills – more space for shortcuts in popup */
.history-sidebar-pills {
    flex-shrink: 0;
    width: 200px;
    min-width: 200px;
    padding: 18px 16px;
    gap: 12px;
    border-right: 1px solid #E5E7EB;
}

.history-pill {
    padding: 10px 16px;
    min-height: 38px;
    border-radius: 100px;
    border: 1px solid #E5E7EB;
    background: #F9FAFB;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
    white-space: nowrap;
    text-align: left;
    display: flex;
    align-items: center;
}

.history-pill:hover {
    background: #F3F4F6;
    border-color: #D1D5DB;
}

.history-pill.active {
    background: #FEF3C7;
    border-color: #F59E0B;
    color: #92400E;
}

.history-search-form-column {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: visible;
}

/* overflow: visible so v-select dropdown (append-to-body false) is not clipped */
.history-search-form-column .history-search-modal-body {
    flex: 1;
    padding: 16px 28px 28px;
    overflow: visible;
    min-height: 0;
}

/* Filter badges in bar – match image: light grey, capsule, small x */
.filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #EBEBEB;
    border: 1px solid #D8D8D8;
    border-radius: 100px;
    font-size: 13px;
    color: #555555;
}

.filter-badge iconify-icon {
    font-size: 13px;
    color: #999999;
    cursor: pointer;
}

.filter-badge iconify-icon:hover {
    color: #555555;
}

.btn-clear-all {
    background: transparent;
    border: 1px solid #E5E7EB;
    padding: 4px 10px;
    border-radius: 100px;
    font-size: 12px;
    color: #6B7280;
    cursor: pointer;
}

.btn-clear-all:hover {
    background: #F3F4F6;
    color: #374151;
}

/* Close X */
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

/* Table Styles */
.history-table-wrapper {
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    margin: 0 20px 20px;
    overflow: hidden;
}

.history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background-color: white;
}

.history-table thead th {
    background-color: #F9FAFB;
    color: #1F2937;
    font-weight: 600;
    font-size: 14px;
    padding: 13px 24px;
    text-align: left;
    border-bottom: 1px solid #E5E7EB;
}

.history-table thead th:first-child {
    border-top-left-radius: 12px;
}

.history-table thead th:last-child {
    border-top-right-radius: 12px;
}

.history-table tbody tr {
    border-bottom: 1px solid #E5E7EB;
    transition: background-color 0.2s ease;
}

.history-table tbody tr:hover {
    background-color: #F9FAFB;
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

.date-time-column {
    color: #374151;
    font-weight: 400;
    white-space: nowrap;
}

.created-by-column {
    min-width: 200px;
}

.created-by-name {
    color: #374151;
    font-size: 14px;
}

.history-avatar {
    width: 32px;
    height: 32px;
    object-fit: cover;
    flex-shrink: 0;
}

.event-type-column {
    min-width: 120px;
}

.event-type-badge {
    color: #374151;
    font-weight: 500;
}

.changes-column {
    min-width: 250px;
}

.changes-content {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.change-old {
    color: #6B7280;
    text-decoration: line-through;
    font-size: 13px;
}

.change-new {
    color: #059669;
    font-weight: 500;
    font-size: 13px;
}

.change-arrow {
    color: #9CA3AF;
    font-size: 14px;
    margin: 0 2px;
}

.changes-count {
    color: #6B7280;
    font-size: 13px;
    margin-left: 4px;
}

/* Pagination */
.history-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid #E5E7EB;
    background-color: white;
}

.pagination-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6B7280;
    font-size: 14px;
}

.entries-icon {
    font-size: 16px;
    color: #9CA3AF;
    cursor: pointer;
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
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    background-color: white;
    color: #374151;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
    background-color: #F9FAFB;
    border-color: #D1D5DB;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    color: #9CA3AF;
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
    border-radius: 6px;
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
    background-color: #F9FAFB;
    border-color: #E5E7EB;
}

.pagination-number.active {
    background-color: #F3F4F6;
    color: #1F2937;
    font-weight: 500;
    border-color: #E5E7EB;
}

/* Loading State */
.loading-state {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 0.3em;
    color: #733E87;
}

/* Empty State */
.search-empty-state {
    text-align: center;
    padding: 60px 24px;
    color: #6B7280;
}

.search-empty-state iconify-icon {
    font-size: 48px;
    color: #9CA3AF;
    margin-bottom: 16px;
}

.search-empty-state h4 {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 8px;
    color: #374151;
}

.search-empty-state p {
    font-size: 14px;
    color: #6B7280;
}

.btn-clear-search {
    background: none;
    border: 1px solid #E5E7EB;
    padding: 8px 16px;
    border-radius: 8px;
    color: #374151;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-clear-search:hover {
    background-color: #F3F4F6;
    border-color: #D1D5DB;
}

/* Utilities */
.radius-12 {
    border-radius: 12px;
}

.bg-neutral-200 {
    background-color: #F3F4F6;
}

.text-neutral-500 {
    color: #6B7280;
}

/* Responsive */
@media (max-width: 768px) {
    .search-wrapper {
        width: 100%;
    }
    
    .history-table-wrapper {
        margin: 0 16px 16px 16px;
        overflow-x: auto;
    }
    
    .history-table {
        min-width: 900px;
    }
    
    .history-pagination {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .pagination-controls {
        width: 100%;
        justify-content: space-between;
    }
}
</style>