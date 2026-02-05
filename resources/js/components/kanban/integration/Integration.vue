<template>
    <div class="integration-container">
        <!-- Header -->
        <div class="integration-header">
            <p class="integration-title">CRM Forms</p>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <table class="integration-table">
                <thead>
                    <tr>
                        <th class="checkbox-column">
                            <div class="form-check">
                                <input 
                                    class="form-check-input-select" 
                                    type="checkbox" 
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                />
                            </div>
                        </th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Created On</th>
                        <th>Active</th>
                        <th>Conversation</th>
                        <th>Platform</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="form in paginatedForms" :key="form.id">
                        <td class="checkbox-column">
                            <div class="form-check">
                                <input 
                                    class="form-check-input-select" 
                                    type="checkbox" 
                                    v-model="selectedIds"
                                    :value="form.id"
                                />
                            </div>
                        </td>
                        <td>
                            <span class="form-id-link" @click="viewForm(form.id)">
                                {{ form.id }}
                            </span>
                        </td>
                        <td class="form-name">{{ form.name }}</td>
                        <td class="created-on">{{ formatDate(form.createdOn) }}</td>
                        <td class="active-column">
                            <div class="active-toggle-wrapper">
                                <div class="form-switch switch-warning">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        :id="`toggle-${form.id}`"
                                        v-model="form.active"
                                        @change="toggleActive(form)"
                                    />
                                </div>
                                <span class="active-date">{{ formatDateShort(form.activeDate) }}</span>
                            </div>
                        </td>
                        <td class="conversation-count">
                            {{ form.conversation }}
                        </td>
                        <td class="platform">{{ form.platform }}</td>
                        <td class="action-column">
                            <b-dropdown 
                                variant="link" 
                                no-caret 
                                toggle-class="action-dropdown-btn p-0 border-0"
                                menu-class="action-dropdown-menu"
                                right
                            >
                                <template #button-content>
                                    <button class="action-btn">
                                        <iconify-icon icon="lucide:more-vertical" class="action-icon"></iconify-icon>
                                    </button>
                                </template>
                                
                                <b-dropdown-item @click="editForm(form)" class="dropdown-item-custom">
                                    <iconify-icon icon="lucide:edit" class="dropdown-icon"></iconify-icon>
                                    <span>Edit</span>
                                </b-dropdown-item>
                                <b-dropdown-item @click="deleteForm(form)" class="dropdown-item-custom">
                                    <iconify-icon icon="lucide:trash-2" class="dropdown-icon"></iconify-icon>
                                    <span>Delete</span>
                                </b-dropdown-item>
                            </b-dropdown>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-info">
                <span>Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ totalEntries }} Entries</span>
                <iconify-icon icon="lucide:chevron-up" class="entries-icon"></iconify-icon>
            </div>
            <div class="pagination-controls">
                <button 
                    class="pagination-btn prev-btn"
                    :disabled="currentPage === 1"
                    @click="changePage(currentPage - 1)"
                >
                    <iconify-icon icon="lucide:chevron-left"></iconify-icon>
                    Previous
                </button>
                <div class="page-numbers">
                    <button
                        v-for="page in displayedPages"
                        :key="page"
                        class="page-number"
                        :class="{ active: page === currentPage, ellipsis: page === '...' }"
                        @click="page !== '...' && changePage(page)"
                        :disabled="page === '...'"
                    >
                        {{ page }}
                    </button>
                </div>
                <button 
                    class="pagination-btn next-btn"
                    :disabled="currentPage === totalPages"
                    @click="changePage(currentPage + 1)"
                >
                    Next
                    <iconify-icon icon="lucide:chevron-right"></iconify-icon>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { BDropdown, BDropdownItem } from 'bootstrap-vue-3'
import api from '@/plugins/axios'

const forms = ref([
    { 
        id: '024844', 
        name: 'Contact info of January 21', 
        createdOn: new Date(), 
        active: true, 
        activeDate: new Date('2026-01-12'),
        conversation: 2548, 
        platform: 'Meta Ads' 
    },
    { 
        id: '024845', 
        name: 'ORA Jan Ar', 
        createdOn: new Date(Date.now() - 86400000), 
        active: false, 
        activeDate: new Date('2026-01-12'),
        conversation: 45875, 
        platform: 'Self Leads' 
    },
    { 
        id: '024846', 
        name: 'Upcoming new Project - Yas Island', 
        createdOn: new Date('2025-02-14T19:15:00'), 
        active: true, 
        activeDate: new Date('2026-01-12'),
        conversation: 1234, 
        platform: 'WhatsApp from bayut' 
    },
    { 
        id: '024847', 
        name: 'Contact of January 15', 
        createdOn: new Date('2025-01-15T10:30:00'), 
        active: true, 
        activeDate: new Date('2026-01-12'),
        conversation: 5678, 
        platform: 'Land Line' 
    },
    { 
        id: '024848', 
        name: 'Contact info of November 10', 
        createdOn: new Date('2024-11-10T14:20:00'), 
        active: true, 
        activeDate: new Date('2026-01-12'),
        conversation: 9012, 
        platform: 'Meta Ads' 
    },
    { 
        id: '024849', 
        name: 'Contact info of September 11', 
        createdOn: new Date('2024-09-11T16:45:00'), 
        active: true, 
        activeDate: new Date('2026-01-12'),
        conversation: 3456, 
        platform: 'Self Leads' 
    },
    { 
        id: '024850', 
        name: 'Contact info of August 5', 
        createdOn: new Date('2024-08-05T09:00:00'), 
        active: false, 
        activeDate: new Date('2026-01-12'),
        conversation: 7890, 
        platform: 'WhatsApp from bayut' 
    },
    { 
        id: '024851', 
        name: 'Contact info of July 20', 
        createdOn: new Date('2024-07-20T11:30:00'), 
        active: false, 
        activeDate: new Date('2026-01-12'),
        conversation: 2345, 
        platform: 'Land Line' 
    },
    { 
        id: '024852', 
        name: 'Contact info of June 15', 
        createdOn: new Date('2024-06-15T13:15:00'), 
        active: true, 
        activeDate: new Date('2026-01-12'),
        conversation: 6789, 
        platform: 'Meta Ads' 
    },
    { 
        id: '024853', 
        name: 'Contact info of May 10', 
        createdOn: new Date('2024-05-10T15:45:00'), 
        active: false, 
        activeDate: new Date('2026-01-12'),
        conversation: 1234, 
        platform: 'Self Leads' 
    }
])

// Generate more sample data to reach 120 entries
const generateSampleData = () => {
    const platforms = ['Meta Ads', 'Self Leads', 'WhatsApp from bayut', 'Land Line']
    const sampleData = []
    
    for (let i = 0; i < 120; i++) {
        const daysAgo = Math.floor(Math.random() * 365)
        const createdDate = new Date()
        createdDate.setDate(createdDate.getDate() - daysAgo)
        
        sampleData.push({
            id: String(Math.floor(100000 + Math.random() * 900000)),
            name: `Contact info of ${createdDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })}`,
            createdOn: createdDate,
            active: Math.random() > 0.5,
            activeDate: new Date('2026-01-12'),
            conversation: Math.floor(Math.random() * 50000),
            platform: platforms[Math.floor(Math.random() * platforms.length)]
        })
    }
    
    return sampleData
}

onMounted(() => {
    // Uncomment to use generated data
    // forms.value = generateSampleData()
})

const selectAll = ref(false)
const selectedIds = ref([])
const currentPage = ref(1)
const entriesPerPage = ref(10)

const totalEntries = computed(() => forms.value.length)
const totalPages = computed(() => Math.ceil(totalEntries.value / entriesPerPage.value))
const startIndex = computed(() => (currentPage.value - 1) * entriesPerPage.value)
const endIndex = computed(() => Math.min(startIndex.value + entriesPerPage.value, totalEntries.value))

const paginatedForms = computed(() => {
    return forms.value.slice(startIndex.value, endIndex.value)
})

// Computed property to sync selectAll checkbox state
const isAllSelected = computed(() => {
    if (paginatedForms.value.length === 0) return false
    return paginatedForms.value.every(form => selectedIds.value.includes(form.id))
})

const displayedPages = computed(() => {
    const pages = []
    const total = totalPages.value
    const current = currentPage.value
    
    if (total <= 7) {
        for (let i = 1; i <= total; i++) {
            pages.push(i)
        }
    } else {
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) {
                pages.push(i)
            }
            pages.push('...')
            pages.push(total)
        } else if (current >= total - 3) {
            pages.push(1)
            pages.push('...')
            for (let i = total - 4; i <= total; i++) {
                pages.push(i)
            }
        } else {
            pages.push(1)
            pages.push('...')
            for (let i = current - 1; i <= current + 1; i++) {
                pages.push(i)
            }
            pages.push('...')
            pages.push(total)
        }
    }
    
    return pages
})

const toggleSelectAll = (event) => {
    const checked = event.target.checked
    if (checked) {
        // Add all paginated form IDs to selectedIds
        const paginatedIds = paginatedForms.value.map(form => form.id)
        paginatedIds.forEach(id => {
            if (!selectedIds.value.includes(id)) {
                selectedIds.value.push(id)
            }
        })
    } else {
        // Remove all paginated form IDs from selectedIds
        const paginatedIds = paginatedForms.value.map(form => form.id)
        selectedIds.value = selectedIds.value.filter(id => !paginatedIds.includes(id))
    }
}

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
        // Update selectAll state when page changes
        selectAll.value = isAllSelected.value
    }
}

const formatDate = (date) => {
    const now = new Date()
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)
    const dateToFormat = new Date(date)
    const dateOnly = new Date(dateToFormat.getFullYear(), dateToFormat.getMonth(), dateToFormat.getDate())
    
    if (dateOnly.getTime() === today.getTime()) {
        return `Today / ${dateToFormat.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}`
    } else if (dateOnly.getTime() === yesterday.getTime()) {
        return `Yesterday / ${dateToFormat.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}`
    } else {
        return `${dateToFormat.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })} / ${dateToFormat.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}`
    }
}

const formatDateShort = (date) => {
    const d = new Date(date)
    return d.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })
}

const toggleActive = (form) => {
    // Handle toggle action
    console.log('Toggle active for form:', form.id, form.active)
}

const viewForm = (id) => {
    // Handle view action
    console.log('View form:', id)
}

const editForm = (form) => {
    // Handle edit action
    console.log('Edit form:', form)
}

const deleteForm = (form) => {
    // Handle delete action
    console.log('Delete form:', form)
}
</script>

<style scoped>
.integration-container {
    padding: 24px;
    background: #ffffff;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.integration-header {
    margin-bottom: 24px;
}

.integration-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 18px;
    font-weight: 600;
    color: #343A40;
    margin: 0;
}

.table-container {
    background: #ffffff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    overflow-x: auto;
    flex: 1;
    min-height: 0;
}

.table-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #F8FAFC;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

.integration-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    table-layout: auto;
}

.integration-table thead {
    background-color: #F8FAFC;
    position: sticky;
    top: 0;
    z-index: 10;
}

.integration-table th {
    padding: 12px 16px;
    text-align: left;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #343A40;
    border-bottom: 1px solid #E2E8F0;
    background-color: #F8FAFC;
}

.integration-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #E2E8F0;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #1E293B;
    vertical-align: middle;
    background-color: #ffffff;
}

.integration-table tbody tr:last-child td {
    border-bottom: none;
}

.checkbox-column {
    width: 50px;
}

.form-check {
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-check-input {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    border-radius: 4px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: #0F172A;
    border-color: #0F172A;
}

.form-check-input-select {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    border-radius: 3px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    position: relative;
    background-color: #ffffff;
    transition: all 0.2s ease;
    margin: 0;
    flex-shrink: 0;
}

.form-check-input-select:checked {
    background-color: #FCA503;
    border-color: #FCA503;
    box-shadow: 0 0 0 3px rgba(252, 165, 3, 0.2), 
                0 0 0 6px rgba(252, 165, 3, 0.1);
}

.form-check-input-select:checked::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%) rotate(45deg);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    border-radius: 0;
}

.form-check-input-select:hover {
    border-color: #94A3B8;
}

.form-check-input-select:checked:hover {
    border-color: #FCA503;
    box-shadow: 0 0 0 3px rgba(252, 165, 3, 0.25), 
                0 0 0 6px rgba(252, 165, 3, 0.15);
}

.form-id-link {
    color: #4D7CFE;
    /* text-decoration: none; */
    font-weight: 400;
    /* cursor: pointer; */
}

.form-name {
    color: #1E293B;
    font-weight: 400;
}

.created-on {
    color: #64748B;
}

.active-column {
    min-width: 150px;
}

.active-toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-switch {
    padding-left: 0;
}

.form-switch .form-check-input {
    width: 44px;
    height: 24px;
    background-color: #CBD5E1;
    border: none;
    border-radius: 12px;
    position: relative;
    cursor: pointer;
    transition: background-color 0.2s;
    box-shadow: none !important;
}

.form-switch .form-check-input::before {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    background: #ffffff;
    border-radius: 50%;
    top: 50%;
    left: 3px;
    transform: translateY(-50%);
    transition: left 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.switch-warning .form-check-input:checked {
    background-color: #FCA503 !important;
    border-color: #FCA503 !important;
}

.switch-warning .form-check-input:checked::before {
    left: calc(100% - 21px);
    background-color: #ffffff;
}

.active-date {
    font-size: 14px;
    color: #64748B;
}

.conversation-count {
    /* font-weight: 600; */
    color: #1E293B;
}

.platform {
    color: #1E293B;
}

.action-column {
    width: 60px;
    text-align: center;
}

.action-btn {
    background: transparent;
    border: none;
    padding: 4px 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    transition: color 0.2s;
}

.action-btn:hover {
    color: #1E293B;
}

.action-icon {
    font-size: 20px;
}

:deep(.action-dropdown-btn) {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    box-shadow: none !important;
}

:deep(.action-dropdown-btn::after) {
    display: none !important;
}

:deep(.action-dropdown-btn::before) {
    display: none !important;
}

:deep(.action-dropdown-btn .dropdown-toggle::after) {
    display: none !important;
}

:deep(.action-dropdown-btn .dropdown-toggle::before) {
    display: none !important;
}

:deep(.action-dropdown-btn button::after),
:deep(.action-dropdown-btn button::before),
:deep(.action-dropdown-btn .btn::after),
:deep(.action-dropdown-btn .btn::before),
:deep(.action-dropdown-btn .btn-link::after),
:deep(.action-dropdown-btn .btn-link::before) {
    display: none !important;
    content: none !important;
}

:deep(.btn.dropdown-toggle::after),
:deep(.btn.dropdown-toggle::before),
:deep(.btn-link.dropdown-toggle::after),
:deep(.btn-link.dropdown-toggle::before) {
    display: none !important;
    content: none !important;
}

:deep(.action-dropdown-menu) {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.08);
    padding: 4px;
    min-width: 150px;
    margin-top: 8px !important;
}

:deep(.dropdown-item-custom) {
    display: flex !important;
    align-items: center;
    gap: 8px;
    padding: 8px 12px !important;
    border-radius: 6px;
    transition: all 0.2s;
    border: none !important;
    background: transparent !important;
    font-size: 14px;
    color: #1E293B;
}

:deep(.dropdown-item-custom:hover) {
    background: #F8FAFC !important;
}

:deep(.dropdown-item-custom .dropdown-icon) {
    font-size: 16px;
    color: #64748B;
}

:deep(.dropdown-item-custom::after) {
    display: none !important;
}

:deep(.dropdown-item-custom .dropdown-toggle::after) {
    display: none !important;
}

.pagination-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.pagination-info {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #64748B;
    font-family: 'Montserrat', sans-serif;
}

.entries-icon {
    font-size: 16px;
    color: #64748B;
    cursor: pointer;
    transition: color 0.2s;
}

.entries-icon:hover {
    color: #1E293B;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pagination-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #ffffff;
    color: #1E293B;
    font-size: 14px;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-btn iconify-icon {
    font-size: 16px;
}

.page-numbers {
    display: flex;
    align-items: center;
    gap: 4px;
}

.page-number {
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #ffffff;
    color: #1E293B;
    font-size: 14px;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.page-number:hover:not(.ellipsis):not(.active) {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.page-number.active {
    background: #F1F5F9;
    border-color: #E2E8F0;
    color: #1E293B;
    font-weight: 600;
}

.page-number.ellipsis {
    border: none;
    background: transparent;
    cursor: default;
    color: #64748B;
    min-width: auto;
    padding: 0 4px;
}

.page-number:disabled {
    cursor: default;
}
</style>
