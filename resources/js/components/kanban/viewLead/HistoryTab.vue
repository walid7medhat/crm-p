<template>
    <div class="info-card bg-white p-3 radius-12 shadow-sm history-content">
        <div class="history-table-wrapper">
            <div v-if="loading && historyEntries.length === 0" class="loading-state">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading history...</p>
                </div>
            </div>
            <template v-else>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Created By</th>
                            <th>Event Type</th>
                            <th>Client ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="paginatedEntries.length === 0 && !loading">
                            <td colspan="4" class="text-center p-4 text-muted">
                                No history entries found
                            </td>
                        </tr>
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
                            <td class="event-type-column">{{ entry.eventType }}</td>
                            <td class="client-id-column">{{ entry.clientId }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>
            
            <!-- Pagination Footer -->
            <div class="history-pagination">
                <div class="pagination-info">
                    <span v-if="totalEntries > 0">
                        Showing {{ startEntry }} to {{ endEntry }} of {{ totalEntries }} Entries
                    </span>
                    <span v-else-if="!loading">No entries</span>
                    <span v-else>Loading...</span>
                    <iconify-icon icon="lucide:chevron-up-down" class="entries-icon"></iconify-icon>
                </div>
                <div v-if=" !loading" class="pagination-controls">
                    <button 
                        class="pagination-btn" 
                        :disabled="currentPage === 1 || loading"
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
                            :disabled="loading"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                        <span v-if="showEllipsis" class="pagination-ellipsis">...</span>
                        <button 
                            v-if="totalPages > 3"
                            class="pagination-number"
                            :class="{ active: totalPages === currentPage }"
                            :disabled="loading"
                            @click="goToPage(totalPages)"
                        >
                            {{ totalPages }}
                        </button>
                    </div>
                    <button 
                        class="pagination-btn" 
                        :disabled="currentPage === totalPages || loading"
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
import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue'
import api from '@/plugins/axios'

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

// History data
const historyEntries = ref([])
const loading = ref(false)
const loadingOlder = ref(false)
const nextPageUrl = ref(null)

// Pagination state
const currentPage = ref(1)
const entriesPerPage = ref(7)
const totalEntries = ref(0)
const totalPages = ref(1)

// Computed property for showing "Show older" button
const hasNextPage = computed(() => !!nextPageUrl.value)

// Computed properties
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
    
    if (totalPages.value <= maxVisible) {
        // Show all pages if total is less than max visible
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i)
        }
    } else {
        // Always show first 3 pages
        pages.push(1, 2, 3)
    }
    
    return pages
})

const showEllipsis = computed(() => {
    return totalPages.value > 3
})

// Transform API response to component format
const transformHistoryEntry = (entry) => {
    // Format date and time
    let dateTime = '---'
    if (entry.date) {
        const date = new Date(entry.date)
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
    
    // Handle avatar URL - if it's already a full URL, use it; otherwise prepend /storage/
    if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/')) {
        avatar = `/storage/${avatar}`
    }
    
    // Get event type from action or changes
    let eventType = entry.action || '---'
    if (entry.changes && entry.changes.action) {
        eventType = entry.changes.action
    }
    
    // Format event type to be more readable
    if (eventType === 'view') {
        eventType = 'View'
    } else if (eventType === 'stage_changed') {
        eventType = 'Status Changed'
    } else if (eventType === 'assigned') {
        eventType = 'Responsible Person Changed'
    } else if (eventType === 'updated') {
        eventType = 'Lead Updated'
    } else if (eventType === 'created') {
        eventType = 'Lead Created'
    } else {
        // Capitalize first letter
        eventType = eventType.charAt(0).toUpperCase() + eventType.slice(1).replace(/_/g, ' ')
    }
    
    // Get client ID from changes
    let clientId = '---'
    if (entry.changes) {
        if (entry.changes.old_stage && entry.changes.new_stage) {
            clientId = `${entry.changes.old_stage} → ${entry.changes.new_stage}`
        } else if (entry.changes.old_person && entry.changes.new_person) {
            clientId = `${entry.changes.old_person} → ${entry.changes.new_person}`
        } else if (entry.changes.new_stage) {
            clientId = entry.changes.new_stage
        } else if (entry.changes.new_person) {
            clientId = entry.changes.new_person
        } else if (entry.changes.action === 'view') {
            clientId = '---'
        }
    }
    
    return {
        id: entry.id,
        dateTime: dateTime,
        createdBy: {
            name: user.name || 'Unknown',
            avatar: avatar
        },
        eventType: eventType,
        clientId: clientId
    }
}

// Fetch history from API
const fetchHistory = async (page = 1) => {
    if (!props.lead?.id) {
        return
    }
    
    try {
        loading.value = true
        const response = await api.get(`/leads/${props.lead.id}/history`, {
            params: {
                page: page,
                per_page: entriesPerPage.value
            }
        })
        
        // Handle paginated response
        const responseData = response.data
        console.log('📊 Full API Response:', responseData)
        
        // Handle new structure: { data: { items: [...], pagination: {...} } }
        let historyData = []
        let paginationData = null
        
        // Check for new structure first (data.items and data.pagination)
        if (responseData.data && typeof responseData.data === 'object') {
            if (Array.isArray(responseData.data.items)) {
                // New structure: data.items and data.pagination
                historyData = responseData.data.items
                paginationData = responseData.data.pagination
            } else if (Array.isArray(responseData.data.data)) {
                // ResourceCollection structure: { data: { data: [...] } }
                historyData = responseData.data.data
                paginationData = responseData.meta || responseData.data.pagination
            } else if (Array.isArray(responseData.data)) {
                // Direct array in data
                historyData = responseData.data
                paginationData = responseData.meta
            }
        } else if (Array.isArray(responseData.data)) {
            // Direct array
            historyData = responseData.data
            paginationData = responseData.meta
        }
        
        console.log('📝 Parsed History Data:', historyData)
        console.log('📄 Pagination Data:', paginationData)
        
        // Transform history entries
        historyEntries.value = historyData.map(transformHistoryEntry)
        
        // Update pagination info
        if (paginationData) {
            totalEntries.value = parseInt(paginationData.total) || 0
            totalPages.value = parseInt(paginationData.last_page) || 1
            currentPage.value = parseInt(paginationData.current_page) || page
            entriesPerPage.value = parseInt(paginationData.per_page) || 7
            
            // Set next page URL if available
            if (paginationData.next_page) {
                // If next_page is a number, construct the URL
                if (typeof paginationData.next_page === 'number') {
                    nextPageUrl.value = `/leads/${props.lead.id}/history?page=${paginationData.next_page}&per_page=${entriesPerPage.value}`
                } else {
                    // If it's already a URL, use it
                    nextPageUrl.value = paginationData.next_page
                }
            } else {
                nextPageUrl.value = null
            }
            
            console.log('✅ Pagination Set:', {
                total: totalEntries.value,
                lastPage: totalPages.value,
                currentPage: currentPage.value,
                perPage: entriesPerPage.value,
                entriesCount: historyEntries.value.length,
                nextPageUrl: nextPageUrl.value,
                shouldShowPagination: totalPages.value > 1
            })
        } else {
            // Fallback if no pagination data
            totalEntries.value = historyData.length
            totalPages.value = historyData.length > 0 ? Math.ceil(historyData.length / entriesPerPage.value) : 0
            currentPage.value = 1
            nextPageUrl.value = null
            
            console.warn('⚠️ No pagination data found. Using fallback:', {
                total: totalEntries.value,
                lastPage: totalPages.value
            })
        }
        
    } catch (error) {
        console.error('Error fetching history:', error)
        historyEntries.value = []
        totalEntries.value = 0
        totalPages.value = 1
        nextPageUrl.value = null
        
        // Show error notification
        $showNotification('Failed to load history', 'error')
    } finally {
        loading.value = false
    }
}

// Load older history entries (next page)
const loadOlderHistory = async () => {
    if (!nextPageUrl.value || loadingOlder.value) {
        return
    }
    
    try {
        loadingOlder.value = true
        
        // Handle both absolute and relative URLs
        let apiPath = nextPageUrl.value
        try {
            // If it's an absolute URL, extract the path after /api
            if (apiPath.startsWith('http')) {
                const url = new URL(apiPath)
                apiPath = url.pathname + url.search
            }
            
            // Remove /api prefix if present (since axios baseURL already includes it)
            if (apiPath.startsWith('/api')) {
                apiPath = apiPath.substring(4)
            }
        } catch (e) {
            // If URL parsing fails, use as-is
        }
        
        const response = await api.get(apiPath)
        const responseData = response.data
        
        // Handle new structure: { data: { items: [...], pagination: {...} } }
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
        
        // Transform and append new history entries
        const newEntries = historyData.map(transformHistoryEntry)
        historyEntries.value = [...historyEntries.value, ...newEntries]
        
        // Update pagination info
        if (paginationData) {
            totalEntries.value = parseInt(paginationData.total) || historyEntries.value.length
            totalPages.value = parseInt(paginationData.last_page) || 1
            currentPage.value = parseInt(paginationData.current_page) || currentPage.value
            
            // Update next page URL
            if (paginationData.next_page) {
                if (typeof paginationData.next_page === 'number') {
                    nextPageUrl.value = `/leads/${props.lead.id}/history?page=${paginationData.next_page}&per_page=${entriesPerPage.value}`
                } else {
                    nextPageUrl.value = paginationData.next_page
                }
            } else {
                nextPageUrl.value = null
            }
        } else {
            nextPageUrl.value = null
        }
    } catch (error) {
        console.error('Error loading older history:', error)
        $showNotification('Failed to load older history', 'error')
    } finally {
        loadingOlder.value = false
    }
}

// Methods
const goToPage = async (page) => {
    if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        await fetchHistory(page)
    }
}

// Watch for tab activation - fetch history only when tab is opened
watch(() => props.isActive, (isActive) => {
    if (isActive && props.lead?.id) {
        // Reset to first page when tab is opened
        currentPage.value = 1
        nextPageUrl.value = null
        historyEntries.value = []
        fetchHistory(1)
    }
})

// Watch for lead changes when tab is active
watch(() => props.lead?.id, (newId, oldId) => {
    // Only fetch if tab is active and lead ID actually changed
    if (props.isActive && newId && newId !== oldId) {
        currentPage.value = 1
        nextPageUrl.value = null
        historyEntries.value = []
        fetchHistory(1)
    }
})
</script>

<style scoped>
.history-content {
    animation: fadeIn 0.3s ease-in-out;
    border: 1px solid #F3F3F3
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

.history-table-wrapper {
    padding: 0;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
}

.history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background-color: transparent;
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
    color: #374151;
}

.client-id-column {
    color: #374151;
    max-width: 300px;
}

/* Pagination Styling */
.history-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid #E5E7EB;
    margin-top: 0;
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
    gap: 4px;
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
}

.pagination-number.active {
    background-color: #F3F4F6;
    color: #1F2937;
    font-weight: 500;
}

.pagination-ellipsis {
    padding: 0 4px;
    color: #6B7280;
    font-size: 14px;
}

.show-older-wrapper {
    padding: 16px 24px;
    border-top: 1px solid #E5E7EB;
    display: flex;
    justify-content: center;
}

.show-older-link {
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 400;
    color: #3B82F6;
    padding: 0;
    transition: all 0.2s;
}

.show-older-link:hover:not(:disabled) {
    color: #2563EB;
}

.show-older-link:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.show-older-icon {
    font-size: 14px;
    color: inherit;
}

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.timeline-date {
    padding-left: 44px;
}

.bg-info-soft {
    background-color: #E0F2FE;
}

.text-info {
    color: #0EA5E9;
}

.bg-success-soft {
    background-color: #D1FAE5;
}

.text-success {
    color: #10B981;
}

.bg-warning-soft {
    background-color: #FEF3C7;
}

.text-warning {
    color: #FAA300;
}

.bg-primary-soft {
    background-color: #DBEAFE;
}

.text-primary {
    color: #3B82F6;
}

.h-fit-content {
    height: fit-content;
}

.radius-12 { border-radius: 12px; }
.radius-100 { border-radius: 100px; }

.loading-state {
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 0.3em;
}
</style>
