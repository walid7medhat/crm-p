<template>
    <div class="integration-container">
        <div class="integration-header">
            <p class="integration-title">CRM Forms</p>
            <button type="button" class="create-btn" @click="openCreateModal">
                <iconify-icon icon="lucide:plus" class="create-icon"></iconify-icon>
                Create Integration
            </button>
        </div>

        <!-- Loading / Error -->
        <div v-if="loading" class="loading-state">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Loading integrations…</p>
        </div>
        <div v-else-if="loadError" class="error-state">
            <iconify-icon icon="lucide:alert-circle" class="error-icon"></iconify-icon>
            <p>{{ loadError }}</p>
            <button class="retry-btn" @click="loadIntegrations">
                <iconify-icon icon="lucide:refresh-cw" class="retry-icon"></iconify-icon>
                Retry
            </button>
        </div>

        <!-- Table Container -->
        <div v-else class="table-container">
            <table class="integration-table">
                <thead>
                    <tr>
                        <!--<th class="checkbox-column">-->
                        <!--    <div class="form-check">-->
                        <!--        <input -->
                        <!--            class="form-check-input-select" -->
                        <!--            type="checkbox" -->
                        <!--            :checked="isAllSelected"-->
                        <!--            @change="toggleSelectAll"-->
                        <!--        />-->
                        <!--    </div>-->
                        <!--</th>-->
                        <th>ID</th>
                        <th>Name</th>
                        <th>Created On</th>
                        <th>Active</th>
                        <th>Track</th>
                        <th>Count Of Leads</th>
                        <th>Platform</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="form in paginatedForms" :key="form.id">
                        <!--<td class="checkbox-column">-->
                        <!--    <div class="form-check">-->
                        <!--        <input -->
                        <!--            class="form-check-input-select" -->
                        <!--            type="checkbox" -->
                        <!--            v-model="selectedIds"-->
                        <!--            :value="form.id"-->
                        <!--        />-->
                        <!--    </div>-->
                        <!--</td>-->
                        <td>
                            <span class="form-id-link" @click="viewForm(form.id)">
                                {{ form.facebook_form_id || form.id }}
                            </span>
                        </td>
                        <td class="form-name">{{ form.name }}</td>
                        <td class="created-on">{{ formatDate(form.created_at) }}</td>
                        <td class="active-column">
                            <div class="active-toggle-wrapper">
                                <div class="form-switch switch-warning">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        :id="`toggle-${form.id}`"
                                        :checked="form.status === 'active'"
                                        @change="toggleActive(form)"
                                    />
                                </div>
                                <!--<span class="active-date">{{ formatDateShort(form.updated_at) }}</span>-->
                            </div>
                        </td>
                        <td class="form-name">{{ form.track_keyword }}</td>
                        <td class="conversation-count">
                            {{ form.leads_count || 0 }}
                        </td>
                        <td class="platform">
                            <span class="platform-badge">{{ form.platform }}</span>
                        </td>
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

        <!-- Empty state -->
        <div v-if="!loading && !loadError && forms.length === 0" class="empty-state">
            <iconify-icon icon="lucide:inbox" class="empty-icon"></iconify-icon>
            <p>No integrations yet. Click "Create Integration" to add your first Meta form.</p>
        </div>

        <!-- Pagination -->
        <div v-if="!loading && forms.length > 0" class="pagination-container">
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

    <!-- Create/Edit Modal -->
    <CreateIntegrationModal
        v-model="showModal"
        :edit-id="editingId"
        @saved="handleIntegrationSaved"
        @updated="handleIntegrationUpdated"
    />
</template>

<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue'
import { BDropdown, BDropdownItem } from 'bootstrap-vue-3'
import CreateIntegrationModal from './CreateIntegrationModal.vue'
import api from '@/plugins/axios'

const { proxy } = getCurrentInstance()
const forms = ref([])
const loading = ref(true)
const loadError = ref('')
const showModal = ref(false)
const editingId = ref(null)

// Pagination
const currentPage = ref(1)
const entriesPerPage = ref(10)
const selectedIds = ref([])



// Handle integration saved/updated
const handleIntegrationSaved = (data) => {
    console.log('🟢 handleIntegrationSaved called with:', data)
    console.log('📊 Before load - forms count:', forms.value.length)
    
    loadIntegrations().then(() => {
        console.log('✅ After load - forms count:', forms.value.length)
    })
    
    proxy?.$showNotification?.('Integration created successfully', 'success')
}

const handleIntegrationUpdated = (data) => {
    console.log('🔵 handleIntegrationUpdated called with:', data)
    console.log('📊 Before load - forms count:', forms.value.length)
    
    loadIntegrations().then(() => {
        console.log('✅ After load - forms count:', forms.value.length)
    })
    
    proxy?.$showNotification?.('Integration updated successfully', 'success')
}

// Load integrations from API
async function loadIntegrations() {
    console.log('📥 Loading integrations...')
    loading.value = true
    loadError.value = ''
    try {
        const response = await api.get('/integrations')
        console.log('📦 API response:', response.data)
        
        const data = response.data.data || []
        
        forms.value = data.map(integration => ({
            id: integration.id,
            name: integration.name,
            crm_entity: integration.crm_entity,
            expert_mode: integration.expert_mode,
            duplicate_handling: integration.duplicate_handling,
            project_id: integration.project_id,
            project: integration.project,
            lead_source: integration.lead_source,
            page_id: integration.page_id,
            facebook_form_id: integration.facebook_form_id,
            facebook_form_name: integration.facebook_form_name,
            field_mappings: integration.field_mappings,
            responsible_person: integration.responsible_person,
            status: integration.status || 'active',
            platform: integration.platform || 'Meta Ads',
            leads_count: integration.leads_count || 0,
            created_at: integration.created_at,
            updated_at: integration.updated_at,
            track_enabled: integration.track_enabled ?? false,
            track_keyword: integration.track_keyword ?? ''
        }))
        
        console.log('✅ Forms updated:', forms.value.length, 'records')
    } catch (err) {
        console.error('❌ Failed to load integrations:', err)
        loadError.value = err.response?.data?.message || 'Failed to load integrations'
        proxy?.$showNotification?.(loadError.value, 'error')
    } finally {
        loading.value = false
    }
}

// Modal functions
const openCreateModal = () => {
    editingId.value = null
    showModal.value = true
}

const editForm = (form) => {
    editingId.value = form.id
    showModal.value = true
}

// Delete function with confirmation
const deleteForm = async (form) => {
    const result = await proxy?.$showConfirmation?.({
        title: 'Delete Integration',
        text: `Are you sure you want to delete "${form.name}"?`,
        icon: 'warning',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    })
    
    if (!result) return
    
    try {
        await api.delete(`/integrations/${form.id}`)
        await loadIntegrations()
        proxy?.$showNotification?.('Integration deleted successfully', 'success')
    } catch (err) {
        console.error('Delete failed', err)
        proxy?.$showNotification?.(err.response?.data?.message || 'Failed to delete integration', 'error')
    }
}

// Toggle active status
const toggleActive = async (form) => {
    const newStatus = form.status === 'active' ? 'inactive' : 'active'
    const previousStatus = form.status
    
    // Optimistic update
    form.status = newStatus
    
    try {
        await api.patch(`/integrations/${form.id}`, {
            status: newStatus
        })
        proxy?.$showNotification?.(`Integration ${newStatus === 'active' ? 'activated' : 'deactivated'}`, 'success')
    } catch (err) {
        // Revert on error
        form.status = previousStatus
        console.error('Toggle active failed', err)
        proxy?.$showNotification?.(err.response?.data?.message || 'Failed to update status', 'error')
    }
}

// View form details
const viewForm = (id) => {
    console.log('View form details:', id)
}

// Load on mount
onMounted(() => {
    loadIntegrations()
})

// Export for parent components
defineExpose({ loadIntegrations })

// Computed properties for pagination
const totalEntries = computed(() => forms.value.length)
const totalPages = computed(() => Math.ceil(totalEntries.value / entriesPerPage.value))
const startIndex = computed(() => (currentPage.value - 1) * entriesPerPage.value)
const endIndex = computed(() => Math.min(startIndex.value + entriesPerPage.value, totalEntries.value))

const paginatedForms = computed(() => {
    return forms.value.slice(startIndex.value, endIndex.value)
})

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

// Methods
const toggleSelectAll = (event) => {
    const checked = event.target.checked
    if (checked) {
        const paginatedIds = paginatedForms.value.map(form => form.id)
        paginatedIds.forEach(id => {
            if (!selectedIds.value.includes(id)) {
                selectedIds.value.push(id)
            }
        })
    } else {
        const paginatedIds = paginatedForms.value.map(form => form.id)
        selectedIds.value = selectedIds.value.filter(id => !paginatedIds.includes(id))
    }
}

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
        // Clear selections when changing page
        selectedIds.value = []
    }
}

// Date formatting
const formatDate = (dateString) => {
    if (!dateString) return 'N/A'
    
    const date = new Date(dateString)
    const now = new Date()
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)
    
    const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate())
    
    const timeStr = date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: true 
    })
    
    if (dateOnly.getTime() === today.getTime()) {
        return `Today / ${timeStr}`
    } else if (dateOnly.getTime() === yesterday.getTime()) {
        return `Yesterday / ${timeStr}`
    } else {
        const dateStr = date.toLocaleDateString('en-GB', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric' 
        })
        return `${dateStr} / ${timeStr}`
    }
}

const formatDateShort = (dateString) => {
    if (!dateString) return 'N/A'
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { 
        month: '2-digit', 
        day: '2-digit', 
        year: 'numeric' 
    })
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
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.integration-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 18px;
    font-weight: 600;
    color: #343A40;
    margin: 0;
}

.create-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #0B0736;
    color: #FFFFFF;
    border: none;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.create-btn:hover {
    background: #020A3D;
}

.create-icon {
    font-size: 18px;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 48px 24px;
    color: #64748B;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
}

.error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 48px 24px;
    color: #EF4444;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
}

.error-icon {
    font-size: 48px;
    color: #EF4444;
}

.retry-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #F1F5F9;
    border: none;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.retry-btn:hover {
    background: #E2E8F0;
}

.retry-icon {
    font-size: 16px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 48px 24px;
    color: #64748B;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
}

.empty-icon {
    font-size: 48px;
    color: #94A3B8;
}

.table-container {
    background: #ffffff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    max-height: calc(100vh - 250px);
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
    z-index: 2;
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
}

.form-id-link {
    color: #4D7CFE;
    cursor: pointer;
    font-weight: 500;
}

.form-id-link:hover {
    text-decoration: underline;
}

.form-name {
    font-weight: 500;
    color: #1E293B;
}

.created-on {
    color: #64748B;
    font-size: 13px;
}

.platform-badge {
    display: inline-block;
    padding: 4px 8px;
    background: #F1F5F9;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
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
    margin: 0;
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
    margin: 0;
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
}

.switch-warning .form-check-input:checked::before {
    left: calc(100% - 21px);
}

.active-date {
    font-size: 13px;
    color: #64748B;
    white-space: nowrap;
}

.conversation-count {
    font-weight: 500;
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

:deep(.action-dropdown-menu) {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.08);
    padding: 4px;
    min-width: 120px;
}

:deep(.dropdown-item-custom) {
    display: flex !important;
    align-items: center;
    gap: 8px;
    padding: 8px 12px !important;
    border-radius: 6px;
    transition: all 0.2s;
    font-size: 13px;
    color: #1E293B;
}

:deep(.dropdown-item-custom:hover) {
    background: #F8FAFC !important;
}

:deep(.dropdown-item-custom .dropdown-icon) {
    font-size: 16px;
    color: #64748B;
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