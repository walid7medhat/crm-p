<template>
    <div class="info-card bg-white p-3 radius-12 shadow-sm history-content">
        <div class="history-table-wrapper">
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
                    <tr v-for="(entry, index) in paginatedEntries" :key="index">
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
            
            <!-- Pagination Footer -->
            <div class="history-pagination">
                <div class="pagination-info">
                    <span>Showing {{ startEntry }} to {{ endEntry }} of {{ totalEntries }} Entries</span>
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
                        <span v-if="showEllipsis" class="pagination-ellipsis">...</span>
                        <button 
                            class="pagination-number"
                            :class="{ active: 10 === currentPage }"
                            @click="goToPage(10)"
                        >
                            10
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
import { ref, computed } from 'vue'

const props = defineProps({
    lead: {
        type: Object,
        default: null
    }
})

// Dummy data matching the image
const historyEntries = ref([
    {
        id: 1,
        dateTime: 'Today / 9:45 PM',
        createdBy: {
            name: 'Mohammed Shibli',
            avatar: ''
        },
        eventType: 'View',
        clientId: '---'
    },
    {
        id: 2,
        dateTime: 'Yesterday / 12:25 PM',
        createdBy: {
            name: 'Ahmad Mahfouz',
            avatar: ''
        },
        eventType: 'Status Changed',
        clientId: 'Unqualified → Assigned'
    },
    {
        id: 3,
        dateTime: '14 Feb 2025 / 10:25 PM',
        createdBy: {
            name: 'Basil Faiz',
            avatar: ''
        },
        eventType: 'Field "Name" changed',
        clientId: 'Suhail Elarabi → Mohammed Shibli'
    },
    {
        id: 4,
        dateTime: '14 Feb 2025 / 10:25 PM',
        createdBy: {
            name: 'Suhail Elarabi',
            avatar: ''
        },
        eventType: 'Field "Responsible person" Changed',
        clientId: 'Oia Agent-Vida Residences Saadiyat Island | Oia Properties → test lead'
    },
    {
        id: 5,
        dateTime: '14 Feb 2025 / 10:25 PM',
        createdBy: {
            name: 'Kim Duero',
            avatar: ''
        },
        eventType: 'Activity Created',
        clientId: 'Vida Residences Saadiyat Island | Oia Properties'
    },
    {
        id: 6,
        dateTime: '13 Feb 2025 / 3:15 PM',
        createdBy: {
            name: 'Mohammed Shibli',
            avatar: ''
        },
        eventType: 'View',
        clientId: '---'
    },
    {
        id: 7,
        dateTime: '13 Feb 2025 / 2:30 PM',
        createdBy: {
            name: 'Ahmad Mahfouz',
            avatar: ''
        },
        eventType: 'Status Changed',
        clientId: 'Assigned → Qualified'
    }
])

// Add more entries to reach 120 total
for (let i = 8; i <= 120; i++) {
    const names = ['Mohammed Shibli', 'Ahmad Mahfouz', 'Basil Faiz', 'Suhail Elarabi', 'Kim Duero']
    const eventTypes = ['View', 'Status Changed', 'Field "Name" changed', 'Activity Created', 'Comment Added']
    const clientIds = ['---', 'Unqualified → Assigned', 'Assigned → Qualified', 'Test Lead', 'Vida Residences Saadiyat Island | Oia Properties']
    
    const randomDate = new Date(2025, 1, Math.floor(Math.random() * 14) + 1)
    const dateStr = randomDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    const timeStr = `${Math.floor(Math.random() * 12) + 1}:${String(Math.floor(Math.random() * 60)).padStart(2, '0')} ${Math.random() > 0.5 ? 'AM' : 'PM'}`
    
    historyEntries.value.push({
        id: i,
        dateTime: `${dateStr} / ${timeStr}`,
        createdBy: {
            name: names[Math.floor(Math.random() * names.length)],
            avatar: ''
        },
        eventType: eventTypes[Math.floor(Math.random() * eventTypes.length)],
        clientId: clientIds[Math.floor(Math.random() * clientIds.length)]
    })
}

// Pagination state
const currentPage = ref(1)
const entriesPerPage = ref(7) // Matching "Showing 1 to 7" in the image

// Computed properties
const totalEntries = computed(() => historyEntries.value.length)
const totalPages = computed(() => Math.ceil(totalEntries.value / entriesPerPage.value))
const startEntry = computed(() => (currentPage.value - 1) * entriesPerPage.value + 1)
const endEntry = computed(() => Math.min(currentPage.value * entriesPerPage.value, totalEntries.value))

const paginatedEntries = computed(() => {
    const start = (currentPage.value - 1) * entriesPerPage.value
    const end = start + entriesPerPage.value
    return historyEntries.value.slice(start, end)
})

const visiblePages = computed(() => {
    const pages = []
    // Matching the image: show 1, 2, 3, ..., 10
    // Always show first 3 pages
    pages.push(1, 2, 3)
    return pages
})

const showEllipsis = computed(() => {
    // Always show ellipsis before the last page (10)
    return true
})

// Methods
const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
    }
}
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
</style>
