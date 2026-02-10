<template>
    <LeadSearchModal v-model="showSearchModal" @search="onLeadSearch" />
    <CreateLeadModal v-model="showCreateModal" @lead-created="handleLeadCreated" />
    <CreateIntegrationModal v-model="showCreateIntegrationModal" @integration-created="handleIntegrationCreated" />
    <AddStageModal v-model="showAddStageModal" @stage-created="handleStageCreated" />
    <div class="kanban-main-wrapper">
        <b-tabs 
            v-model="activeTabIndex"
            class="kanban-tabs-container"
            content-class="kanban-content-area"
            no-fade
        >
            <!-- Tabs -->
            <b-tab 
                v-for="tab in tabs" 
                :key="tab.id"
                title-link-class="nav-tab-item"
            >
                <template #title>
                    <span class="d-flex align-items-center gap-2 h-100 nav-tab-item">
                        {{ tab.name }}
                        <iconify-icon v-if="tab.hasChevron" icon="lucide:chevrons-up-down" class="text-md text-secondary-light"></iconify-icon>
                    </span>
                    <div class="active-indicator"></div>
                </template>

                <!-- Tab Content -->
                <Leads v-if="tab.id === 'leads'" ref="leadsRef" />
                <Integration v-else-if="tab.id === 'integration'" />
                <div v-else class="p-40 text-center text-secondary-light h-100 d-flex align-items-center justify-content-center">
                    <div class="card p-40 radius-12 border shadow-sm">
                        <h4 class="mb-0">{{ tab.name }} Content coming soon...</h4>
                    </div>
                </div>
            </b-tab>

            <!-- Header Actions at the end of the tabs row -->
            <template #tabs-end>
                <div class="header-actions ms-auto d-flex align-items-center gap-11">

                    <!-- Search Input Wrapper -->
                    <div class="search-wrapper d-flex align-items-center">
                        <div class="search-tag d-flex align-items-center gap-2">
                            <span>Deals in progress</span>
                            <iconify-icon icon="lucide:x" class="close-tag-icon"></iconify-icon>
                        </div>
                        <div class="search-input-container d-flex align-items-center">
                            <iconify-icon icon="lucide:plus" class="search-plus-icon" @click="showSearchModal = true" style="cursor: pointer;"></iconify-icon>
                            <b-form-input placeholder="Search" class="search-input" />
                        </div>
                        <iconify-icon icon="lucide:x" class="clear-search-icon"></iconify-icon>
                    </div>
                    
                    <!-- Create New Button -->
                    <button class="btn-create-new d-flex align-items-center" @click="handleCreateNew">
                        <span class="btn-create-new-text">Create New</span>
                        <iconify-icon icon="lucide:chevrons-up-down" class="text-warning-600 text-md"></iconify-icon>
                    </button>

                    <!-- More Options -->
                    <div class="more-options-wrapper d-flex align-items-center gap-12">
                        <b-dropdown 
                            variant="link" 
                            no-caret 
                            toggle-class="action-icon-btn-dropdown p-0 border-0"
                            menu-class="stage-dropdown-menu"
                            right
                        >
                            <template #button-content>
                                <button class="action-icon-btn d-flex align-items-center justify-content-center radius-circle border">
                                    <iconify-icon icon="lucide:more-vertical" class="text-lg font-weight-bold"></iconify-icon>
                                </button>
                            </template>
                            
                            <b-dropdown-item @click="showAddStageModal = true" class="dropdown-item-custom">
                                <img :src="addStage" alt="Add Stage" class="dropdown-icon" />
                                <span class="dropdown-text">Add New Stage</span>
                            </b-dropdown-item>
                        </b-dropdown>
                        
                        <button class="action-icon-btn d-flex align-items-center justify-content-center radius-circle border">
                            <img :src="leadsSettings" alt="Settings" />
                        </button>
                    </div>
                </div>
            </template>
        </b-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import Leads from './leadList/leads.vue'
import Integration from './integration/Integration.vue'
import LeadSearchModal from './leadList/LeadSearchModal.vue'
import CreateLeadModal from './createLead/CreateLeadModal.vue'
import CreateIntegrationModal from './integration/CreateIntegrationModal.vue'
import AddStageModal from './stage/AddStageModal.vue'
import leadsSettings from '@/assets/images/kanban/leads-setting.svg'
import addStage from '@/assets/images/kanban/add-stage.svg'
import { BTabs, BTab, BFormInput, BDropdown, BDropdownItem } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const activeTab = ref('leads')
const showSearchModal = ref(false)
const showCreateModal = ref(false)
const showCreateIntegrationModal = ref(false)
const showAddStageModal = ref(false)
const leadsRef = ref(null)

const echoListeners = ref([])
const pollingInterval = ref(null)

const tabs = ref([
    { id: 'deals', name: 'Deals', hasChevron: false },
    { id: 'leads', name: 'Leads', hasChevron: false },
    { id: 'inventory', name: 'Inventory', hasChevron: true },
    { id: 'costumers', name: 'Costumers', hasChevron: true },
    { id: 'integration', name: 'Integration', hasChevron: false },
    { id: 'analytics', name: 'Analytics', hasChevron: false }
])

const activeTabIndex = computed({
    get: () => tabs.value.findIndex(t => t.id === activeTab.value),
    set: (index) => {
        if (index >= 0) {
            activeTab.value = tabs.value[index].id
        }
    }
})

const activeTabName = computed(() => {
    return tabs.value.find(t => t.id === activeTab.value)?.name || ''
})

onMounted(() => {
    setTimeout(() => {
        initializeStageUpdates()
    }, 1000)
})

onUnmounted(() => {
    cleanup()
})

// Initialize real-time updates for stages
const initializeStageUpdates = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        console.log('❌ Real-time stage updates not available, using polling...')
        startPolling()
        return
    }

    console.log('🔔 Kanban: Initializing real-time stage updates for user:', user.id)

    try {
        const listener = window.Echo.private(`user.${user.id}`)
            .listen('.stage.updated', (event) => {
                console.log('🎉 Kanban: Stage update received:', event)
                handleStageUpdate(event)
            })
            .error((error) => {
                console.error('❌ Echo error for stages:', error)
                startPolling()
            })

        echoListeners.value.push(listener)
    } catch (error) {
        console.error('❌ Failed to initialize Echo for stages:', error)
        startPolling()
    }
}

const handleStageUpdate = (event) => {
    console.log('📊 Handling stage update:', event.action_type)
    
    // Only refresh if it's a structural change (created/deleted/reordered)
    // For updates, the real-time lead updates will handle individual lead changes
    if (event.action_type === 'created' || event.action_type === 'deleted' || event.action_type === 'reordered') {
        if (leadsRef.value && typeof leadsRef.value.fetchLeads === 'function') {
            const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
            if (leadsComponent) {
                // Immediate execution for real-time events
                leadsComponent.fetchLeads(true)
            }
        }
    }
    
    showStageNotification(event)
}

const showStageNotification = (event) => {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })

    const stageData = event.stage?.data || event.stage
    const stageName = stageData?.name || 'Unknown Stage'
    const userName = event.user_name || 'Someone'

    let title = ''
    let icon = 'info'

    switch (event.action_type) {
        case 'created':
            title = `📝 New Stage: ${stageName}`
            icon = 'success'
            break
        case 'updated':
            title = `✏️ ${userName} updated stage: ${stageName}`
            icon = 'info'
            break
        case 'deleted':
            title = `🗑️ ${userName} deleted stage: ${stageName}`
            icon = 'error'
            break
        case 'reordered':
            title = `🔄 ${userName} reordered stages`
            icon = 'info'
            break
        default:
            title = `📊 Stage updated: ${stageName}`
    }

    Toast.fire({
        icon: icon,
        title: title,
        text: event.message || 'Stage has been updated'
    })
}

const startPolling = () => {
    // Only start polling if not already polling
    if (pollingInterval.value) {
        return
    }
    
    console.log('🔄 Kanban: Starting polling for stages every 30 seconds')
    pollingInterval.value = setInterval(() => {
        if (leadsRef.value && typeof leadsRef.value.fetchLeads === 'function') {
            const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
            if (leadsComponent) {
                leadsComponent.fetchLeads()
            }
        }
    }, 30000)
}

const cleanup = () => {
    echoListeners.value.forEach(listener => {
        if (listener && typeof listener.stopListening === 'function') {
            listener.stopListening('.stage.updated')
        }
    })
    echoListeners.value = []

    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

const onLeadSearch = (query) => {
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, query || null)
        }
    }
}

const handleCreateNew = () => {
    // Check if we're on the integration tab
    if (activeTab.value === 'integration') {
        showCreateIntegrationModal.value = true
    } else {
        // Default to showing create lead modal
        showCreateModal.value = true
    }
}

const handleLeadCreated = async () => {
    console.log('🎯 handleLeadCreated triggered')
    
    // Wait for DOM to update
    await nextTick()
    
    // Don't refetch - real-time updates will handle the new lead via Pusher events
    // Only refetch if real-time updates are not available (fallback)
    if (leadsRef.value && (!window.Echo || !JSON.parse(localStorage.getItem('user')))) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            console.log('✅ Calling fetchLeads after lead creation (no real-time updates)')
            await leadsComponent.fetchLeads(true) // Immediate execution
        }
    }
}

const handleIntegrationCreated = (data) => {
    console.log('🎯 handleIntegrationCreated triggered', data)
    // Handle integration creation logic here
    // You can refresh the integration list or show a notification
    $showNotification('Integration created successfully!', 'success')
}

const handleStageCreated = async (stageData) => {
    console.log('🎯 handleStageCreated triggered')
    console.log('New stage created:', stageData)
    
    // Refresh the leads view to show the new stage
    // This is necessary because we need to fetch the new stage structure
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            console.log('✅ Calling fetchLeads after stage creation')
            // Pass true for immediate execution (no debounce) since this is a user action
            await leadsComponent.fetchLeads(true)
        }
    }
    
    $showNotification('Stage created successfully!', 'success')
}

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
        // Fallback notification using Swal
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        })
        
        const iconMap = {
            'success': 'success',
            'error': 'error',
            'warning': 'warning',
            'info': 'info'
        }
        
        Toast.fire({
            icon: iconMap[type] || 'info',
            title: message
        })
    }
}
</script>

<style scoped>
.kanban-main-wrapper {
    min-height: calc(100vh - 72px);
    display: flex;
    flex-direction: column;
    background-color: #ffffff !important;
    margin-top: 11px;
    border-radius: 20px;
}

/* Bootstrap Tabs Customization */
:deep(.kanban-tabs-container > .nav-tabs) {
    background: transparent;
    height: 72px;
    flex-shrink: 0;
    border-bottom: 1px solid #E2E8F0 !important;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 32px;
    flex-wrap: nowrap;
}

:deep(.kanban-tabs-container .nav-item) {
    height: 100%;
    display: flex;
    align-items: center;
    margin-bottom: 0; /* Override bootstrap default */
}

:deep(.kanban-tabs-container .nav-link) {
    border: none !important;
    background: transparent !important;
    padding: 20px !important;
    height: 100%;
    display: flex;
    align-items: center;
    border-radius: 0;
    position: relative;
    margin-bottom: 0 !important;
    text-decoration: none;
    color: #64748B;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px; /* text-md */
    transition: all 0.2s ease;
}

:deep(.kanban-tabs-container .nav-link:hover) {
    color: #01062C;
}

:deep(.kanban-tabs-container .nav-link.active) {
    color: #01062C !important;
    font-weight: 600;
}

:deep(.kanban-tabs-container .nav-link .active-indicator) {
    display: none;
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: #F2994A;
    border-radius: 4px 4px 4px 4px;
}

:deep(.kanban-tabs-container .nav-link.active .active-indicator) {
    display: block;
}

.kanban-content-area {
    flex-grow: 1;
    padding: 0;
    overflow: auto;
    background: transparent;
}

.btn-create-new {
    opacity: 1;
    border-radius: 50px;
    padding: 2px 18px;
    background-color: #0a0f3d;
}

.btn-create-new:hover {
    background-color: #0a0f3d;
}

.action-icon-btn {
    width: 40px;
    height: 40px;
    background: white;
    border-color: #E2E8F0 !important;
    transition: all 0.2s;
    color: inherit;
}

.action-icon-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1 !important;
}

.action-icon-btn:focus {
    outline: none !important;
    box-shadow: none !important;
}

:deep(.action-icon-btn-dropdown .action-icon-btn) {
    color: #1E293B !important;
}

.radius-circle {
    border-radius: 50%;
}

.btn-create-new-text {
    font-style: Medium;
    font-size: 14px;
    color: #FFFF;
    margin: 5px;
}

/* Utility Classes */
.text-warning-600 {
    color: #D97706 !important;
}

.text-s {
    font-size: 12px;
}
.more-options-wrapper {
    margin-right: 28px;
}

/* Search Input Styles */
.search-wrapper {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 100px;
    max-width: 438px;
    gap: 5px;
    height: 38px;
    display: flex;
    align-items: center;
}

.search-tag {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 100px;
    margin: 5px;
    padding: 5px 10px;
    font-size: 12px;
    color: #475569;
    white-space: nowrap;
}

.close-tag-icon {
    font-size: 12px;
    cursor: pointer;
    color: #000000;
    background: #E2E8F0;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-input-container {
    color: #94A3B8;
    height: 100%;
    display: flex;
    align-items: center;
    flex-grow: 1;
}

.search-plus-icon {
    font-size: 18px;
    color: #94A3B8;
    margin-right: 5px;
    margin-bottom: 2px;
}

.search-input {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    width: 100%;
    font-size: 11px;
    color: #1E293B;
    background: transparent !important;
    padding: 0 !important;
    height: 100% !important;
    display: flex;
    align-items: center;
}

.search-input::placeholder {
    color: #94A3B8;
    font-size: 11px;
}

.clear-search-icon {
    color: #F2994A;
    font-size: 20px;
    cursor: pointer;
    margin-right: 8px;
}

/* Dropdown Styles */
:deep(.action-icon-btn-dropdown) {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    box-shadow: none !important;
    color: inherit !important;
}

:deep(.action-icon-btn-dropdown:hover),
:deep(.action-icon-btn-dropdown:focus),
:deep(.action-icon-btn-dropdown:active),
:deep(.action-icon-btn-dropdown.show) {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    color: inherit !important;
}

:deep(.action-icon-btn-dropdown::after) {
    display: none !important;
}

:deep(.stage-dropdown-menu) {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.08);
    padding: 8px;
    min-width: 220px;
    margin-top: 8px !important;
}

:deep(.dropdown-item-custom) {
    display: flex !important;
    align-items: center;
    gap: 12px;
    padding: 8px 12px !important;
    border-radius: 10px;
    transition: all 0.2s;
    border: none !important;
    background: transparent !important;
}

:deep(.dropdown-item-custom:hover) {
    background: #F8FAFC !important;
}

:deep(.dropdown-item-custom .dropdown-icon) {
    font-size: 18px;
    color: #666666;
    vertical-align: middle !important;
    margin-right: 10px;
}

:deep(.dropdown-item-custom .dropdown-text) {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #666666;
}

:deep(.dropdown-item-custom:hover .dropdown-icon),
:deep(.dropdown-item-custom:hover .dropdown-text) {
    color: #0F172A;
}
</style>
