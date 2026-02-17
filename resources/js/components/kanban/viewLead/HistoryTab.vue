<template>
    <div class="info-card bg-white p-3 radius-12 shadow-sm history-content">
        <!-- Search Bar that opens modal -->
        <div class="search-area d-flex justify-content-start position-relative mb-3">
            <div
                class="search-wrapper d-flex align-items-center"
                @click="showSearchModal = true"
            >
                <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
                <span class="search-placeholder">Search history...</span>
            </div>
        </div>

        <!-- Active Filters Display -->
        <div v-if="hasActiveFilters" class="active-filters px-4 mb-3">
            <div class="d-flex flex-wrap gap-2">
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
                    Date: {{ searchFilters.dateFrom || 'Any' }} to {{ searchFilters.dateTo || 'Any' }}
                    <iconify-icon icon="lucide:x" @click="removeFilter('date')"></iconify-icon>
                </span>
                <button v-if="hasActiveFilters" class="btn-clear-all" @click="clearAllFilters">
                    Clear All
                </button>
            </div>
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
                                    <span class="created-by-name">{{ entry.createdBy.name }}</span>
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

    <!-- Search Modal -->
    <b-modal
        id="history-search-modal"
        v-model="showSearchModal"
        centered
        size="md"
        hide-footer
        hide-header
        body-class="p-0"
        @hidden="onModalHidden"
    >
        <div class="history-search-modal">
            <!-- Modal Header -->
            <div class="modal-header-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="created-date">Filter History</span>
                    <button class="close-btn" @click="showSearchModal = false">
                        <iconify-icon icon="lucide:x" width="20" height="20"></iconify-icon>
                    </button>
                </div>
            </div>

            <!-- History Search Form -->
            <div class="p-4 pt-0">
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
    </b-modal>
</template>

<script setup>
import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue'
import { BModal } from 'bootstrap-vue-3'
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

// Modal state
const showSearchModal = ref(false)

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
    searchFilters.value = {
        search: '',
        action: '',
        user: '',
        dateFrom: '',
        dateTo: ''
    }
    applySearch()
}

// Handle search from form
const onSearch = (filters) => {
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

// Modal hidden handler
const onModalHidden = () => {
    // Optional: reset filters when modal is closed without searching
    // You can keep this empty or add logic if needed
}

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
    }
    
    return {
        id: entry.id,
        dateTime: dateTime,
        createdBy: {
            name: user.name || 'System',
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

/* Search Area */
.search-area {
    padding: 16px 24px 0 24px;
}

.search-wrapper {
    border: 1px solid #E5E7EB;
    border-radius: 100px;
    background: white;
    height: 42px;
    padding: 0 16px;
    width: 300px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-wrapper:hover {
    border-color: #FAA300;
    box-shadow: 0 0 0 3px rgba(250, 163, 0, 0.1);
}

.search-icon {
    color: #9CA3AF;
    font-size: 16px;
}

.search-placeholder {
    color: #9CA3AF;
    font-size: 14px;
}

/* Active Filters */
.active-filters {
    padding: 0 24px;
}

.filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background-color: #F3F4F6;
    border-radius: 100px;
    font-size: 13px;
    color: #374151;
}

.filter-badge iconify-icon {
    font-size: 14px;
    color: #9CA3AF;
    cursor: pointer;
    transition: color 0.2s ease;
}

.filter-badge iconify-icon:hover {
    color: #374151;
}

.btn-clear-all {
    background: transparent;
    border: 1px solid #E5E7EB;
    padding: 6px 12px;
    border-radius: 100px;
    font-size: 13px;
    color: #6B7280;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-clear-all:hover {
    background: #F3F4F6;
    color: #374151;
}

/* Modal Styles */
.history-search-modal {
    background: white;
    border-radius: 16px;
    overflow: hidden;
}

.modal-header-custom {
    background: #F9FAFB;
    border-bottom: 1px solid #E5E7EB;
}

.created-date {
    font-size: 14px;
    font-weight: 500;
    color: #1F2937;
}

.close-btn {
    background: transparent;
    border: none;
    color: #6B7280;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #F3F4F6;
    color: #374151;
}

/* Table Styles */
.history-table-wrapper {
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    margin: 0 24px 24px 24px;
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
    color: #FAA300;
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

/* Modal Customization */
:deep(.modal-content) {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

:deep(.modal-body) {
    padding: 0;
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