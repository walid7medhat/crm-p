<template>
    <b-modal
        v-model="showSelectedFiltersModal"
        title="Selected filters"
        hide-footer
        body-class="p-4"
        header-class="border-0 pb-0"
    >
        <div class="selected-filters-list d-flex flex-column gap-2">
            <div
                v-for="f in activeFilters"
                :key="f.id"
                class="selected-filter-row d-flex align-items-center justify-content-between py-2 px-3 rounded"
            >
                <span class="text-dark">{{ f.label }}: {{ f.value }}</span>
                <iconify-icon icon="lucide:x" class="filter-remove-icon" @click="removeFilter(f)" />
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <b-button variant="outline-secondary" @click="showSelectedFiltersModal = false">Close</b-button>
            <b-button variant="primary" @click="showSelectedFiltersModal = false; showSearchModal = true">Edit search</b-button>
        </div>
    </b-modal>
    <CreateLeadModal v-model="showCreateModal" @lead-created="handleLeadCreated" />
    <CreateDealModal v-model="showCreateDealModal" @deal-created="handleDealCreated" />
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
                <Deals v-if="tab.id === 'deals'" />
                <Leads v-else-if="tab.id === 'leads'" ref="leadsRef" />
                <Integration v-else-if="tab.id === 'integration'" ref="integrationRef" />
            </b-tab>

            <!-- Header Actions at the end of the tabs row -->
            <template #tabs-end>
                <div class="header-actions ms-auto d-flex align-items-center gap-11">   

                    <!-- Search: dropdown under input -->
                    <div class="search-area-column d-flex flex-column align-items-end position-relative" ref="searchDropdownAnchorRef">
                        <div
                            class="search-wrapper d-flex align-items-center"
                            :class="{
                                'search-wrapper-expanded': activeFilters && activeFilters.length,
                                'search-wrapper-tall': searchInputFocused
                            }"
                        >
                            <div v-if="activeFilters.length" class="search-filters-pills d-flex align-items-center">
                                <div
                                    v-for="f in visibleFilterPills"
                                    :key="f.id"
                                    class="search-tag d-flex align-items-center gap-2"
                                >
                                    <span>{{ f.label }}: {{ f.value }}</span>
                                    <iconify-icon icon="lucide:x" class="close-tag-icon" @click.stop="removeFilter(f)" style="cursor: pointer;"></iconify-icon>
                                </div>
                                <div
                                    v-if="moreFiltersCount > 0"
                                    class="search-tag search-tag-more d-flex align-items-center gap-2"
                                >
                                    <span class="search-tag-more-text" @click="showSearchModal = true">+{{ moreFiltersCount }} more</span>
                                    <iconify-icon icon="lucide:x" class="close-tag-icon" @click.stop="clearMoreFilters" style="cursor: pointer;"></iconify-icon>
                                </div>
                            </div>
                            <div
                                class="search-input-container d-flex align-items-center"
                                :class="{ 'search-input-container-tall': searchInputFocused }"
                                @click="showSearchModal = true"
                            >
                                <iconify-icon icon="lucide:plus" class="search-plus-icon" style="cursor: pointer;"></iconify-icon>
                                <b-form-input
                                    placeholder="Search"
                                    v-model="search"
                                    class="search-input"
                                    @focus="onSearchFocus"
                                    @blur="onSearchBlur"
                                    @input="showSearchModal = false"
                                />
                            </div>
                            <iconify-icon v-if="activeFilter || (activeFilters && activeFilters.length || search)" icon="lucide:x" class="clear-search-icon" @click="clearSearchFilter" style="cursor: pointer;"></iconify-icon>
                        </div>
                        <div v-if="showSearchModal" class="lead-search-dropdown-outer">
                            <LeadSearchModal
                                v-model="showSearchModal"
                                :as-dropdown="true"
                                :initial-active-pill="activeFilter?.id"
                                :has-active-filters="(activeFilters && activeFilters.length) > 0"
                                :current-query="lastQuery"
                                @search="onLeadSearch"
                            />
                        </div>
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import Deals from './deals/Deals.vue'
import Leads from './leadList/leads.vue'
import Integration from './integration/Integration.vue'
import LeadSearchModal from './leadList/LeadSearchModal.vue'
import CreateLeadModal from './createLead/CreateLeadModal.vue'
import CreateDealModal from './deals/CreateDealModal.vue'
import CreateIntegrationModal from './integration/CreateIntegrationModal.vue'
import AddStageModal from './stage/AddStageModal.vue'
const leadsSettings = '/assets/images/kanban/leads-setting.svg'
const addStage = '/assets/images/kanban/add-stage.svg'
import { BTabs, BTab, BFormInput, BDropdown, BDropdownItem, BModal, BButton } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const activeTab = ref('leads')
const showSearchModal = ref(false)
const showSelectedFiltersModal = ref(false)
const showCreateModal = ref(false)
const showCreateDealModal = ref(false)
const showCreateIntegrationModal = ref(false)
const showAddStageModal = ref(false)
const searchInputFocused = ref(false)
const leadsRef = ref(null)
const integrationRef = ref(null)
const searchDropdownAnchorRef = ref(null)
const search = ref('')
const searchDebounceTimer = ref(null)
const SEARCH_DEBOUNCE_MS = 400

const echoListeners = ref([])
const pollingInterval = ref(null)

const tabs = ref([
    // { id: 'deals', name: 'Deals', hasChevron: false },
    { id: 'leads', name: 'Leads', hasChevron: false },
    // { id: 'inventory', name: 'Inventory', hasChevron: true },
    // { id: 'costumers', name: 'Costumers', hasChevron: true },
    { id: 'integration', name: 'Integration', hasChevron: false },
    // { id: 'analytics', name: 'Analytics', hasChevron: false }
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

function applySearchToApi() {
    const base = lastQuery.value && Object.keys(lastQuery.value).length ? { ...lastQuery.value } : {}
    const term = (search.value || '').trim()
    const query = term ? { ...base, search: term } : base
    if (!leadsRef.value) return
    const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
    if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
        leadsComponent.fetchLeads(true, Object.keys(query).length ? query : null)
    }
}

watch(search, () => {
    if (searchDebounceTimer.value) {
        clearTimeout(searchDebounceTimer.value)
        searchDebounceTimer.value = null
    }
    searchDebounceTimer.value = setTimeout(() => {
        searchDebounceTimer.value = null
        applySearchToApi()
    }, SEARCH_DEBOUNCE_MS)
})

watch(showSearchModal, (isOpen) => {
    if (!isOpen) {
        if (searchBlurTimeout) clearTimeout(searchBlurTimeout)
        searchInputFocused.value = false
    }
})

function onDocumentClick(e) {
    if (!showSearchModal.value) return
    if (e.target.closest && e.target.closest('.modal')) return
    const el = searchDropdownAnchorRef.value
    if (el && !el.contains(e.target)) {
        showSearchModal.value = false
    }
}

onMounted(() => {
    setTimeout(() => {
        initializeStageUpdates()
    }, 1000)
    document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick)
    if (searchDebounceTimer.value) {
        clearTimeout(searchDebounceTimer.value)
        searchDebounceTimer.value = null
    }
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

    const text = event.message || 'Stage has been updated'
    const message = text ? `${title}\n${text}` : title
    // Defer so SweetAlert2 never runs in same turn as Bootstrap modal focus (avoids stack overflow)
    setTimeout(() => {
        if (window.$showNotification) {
            window.$showNotification(message, icon)
        }
    }, 200)
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

const defaultFilter = { id: 'leads-in-progress', label: 'Leads In Progress' }
const activeFilter = ref({ ...defaultFilter })
const activeFilters = ref([])
const lastQuery = ref(null)

const onLeadSearch = (payload) => {
    if (payload === null || payload?.query === null) {
        activeFilter.value = null
        activeFilters.value = []
        lastQuery.value = null
        if (leadsRef.value) {
            const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
            if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
                leadsComponent.fetchLeads(true, null)
            }
        }
        return
    }
    const query = payload?.query !== undefined ? payload.query : payload
    const pill = payload?.activePill
    console.log("pill"+pill.id);
    if (pill) {
        activeFilter.value = { id: pill.id, label: pill.label }
    } else if (!activeFilter.value) {
        activeFilter.value = { ...defaultFilter }
    }
    activeFilters.value = Array.isArray(payload?.activeFilters) ? payload.activeFilters : []
    lastQuery.value = query && Object.keys(query).length ? { ...query } : null
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, query || null)
        }
    }
}

const visibleFilterPills = computed(() => {
    const list = activeFilters.value || []
    return list.slice(0, 2)
})

const moreFiltersCount = computed(() => {
    const n = (activeFilters.value || []).length - 2
    return n > 0 ? n : 0
})

const removeFilter = (f) => {
    if (!lastQuery.value) return
    const nextQuery = { ...lastQuery.value }
    delete nextQuery[f.queryKey]
    
    if (f.queryKey === 'created_at') {
        delete nextQuery.created_from
        delete nextQuery.created_to
    }
    
    activeFilters.value = activeFilters.value.filter(x => x.id !== f.id)
    lastQuery.value = Object.keys(nextQuery).length ? nextQuery : null
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, Object.keys(nextQuery).length ? nextQuery : null)
        }
    }
}

const clearMoreFilters = () => {
    const list = activeFilters.value || []
    if (list.length <= 2) return
    const keep = list.slice(0, 2)
    const remove = list.slice(2)
    const nextQuery = lastQuery.value ? { ...lastQuery.value } : {}
    
    remove.forEach(f => { 
        delete nextQuery[f.queryKey]
        if (f.queryKey === 'created_at') {
            delete nextQuery.created_from
            delete nextQuery.created_to
        }
    })
    
    activeFilters.value = keep
    lastQuery.value = Object.keys(nextQuery).length ? nextQuery : null
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, Object.keys(nextQuery).length ? nextQuery : null)
        }
    }
}

const clearSearchFilter = () => {
    activeFilter.value = null
    activeFilters.value = []
    lastQuery.value = null
    search.value = ''
        showSearchModal.value = false

    onLeadSearch(null)
}

let searchBlurTimeout = null
function onSearchFocus() {
    if (searchBlurTimeout) {
        clearTimeout(searchBlurTimeout)
        searchBlurTimeout = null
    }
    searchInputFocused.value = true
    showSearchModal.value = true
}
function onSearchBlur() {
    searchBlurTimeout = setTimeout(() => {
        searchInputFocused.value = false
        searchBlurTimeout = null
    }, 200)
}

const handleCreateNew = () => {
    if (activeTab.value === 'deals') {
        showCreateDealModal.value = true
    } else if (activeTab.value === 'integration') {
        showCreateIntegrationModal.value = true
    } else {
        showCreateModal.value = true
    }
}

const handleDealCreated = (payload) => {
    if (window.$showNotification) {
        setTimeout(() => window.$showNotification('Deal created successfully!', 'success'), 200)
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
    $showNotification('Integration created successfully!', 'success')
    const comp = integrationRef.value && (Array.isArray(integrationRef.value) ? integrationRef.value[0] : integrationRef.value)
    if (comp && typeof comp.loadIntegrations === 'function') comp.loadIntegrations()
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

// Notification helper – always use global deferred to avoid SweetAlert2 stack overflow
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
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
.search-area-column {
    align-items: flex-end;
}

.lead-search-dropdown-outer {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 6px;
    z-index: 1050;
    width: 1140px;
    max-width: calc(100vw - 32px);
}

.search-wrapper {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 100px;
    min-height: 38px;
    gap: 8px;
    padding: 5px 8px 5px 5px;
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    width: max-content;
    max-width: 220px;
    transition: max-width 0.35s ease, min-height 0.3s ease, padding 0.3s ease, border-radius 0.3s ease;
}

.search-wrapper-expanded {
    max-width: 1020px;
}

.search-wrapper-tall {
    /*min-height: 72px;*/
    /*padding: 12px 12px 12px 14px;*/
    /*border-radius: 16px;*/
}

.search-filters-pills {
    flex: 1 1 0;
    min-width: 0;
    gap: 6px 8px;
    flex-wrap: nowrap;
    overflow: hidden;
}

.search-tag-more {
    flex-shrink: 0;
}

.search-tag-more-text {
    cursor: pointer;
    user-select: none;
}

.selected-filter-row {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
}

.filter-remove-icon {
    font-size: 16px;
    color: #64748B;
    cursor: pointer;
}

.filter-remove-icon:hover {
    color: #1E293B;
}

.search-tag {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 100px;
    padding: 4px 10px;
    font-size: 12px;
    color: #475569;
    white-space: nowrap;
    width: fit-content;
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
    height: 32px;
    min-height: 32px;
    display: flex;
    align-items: center;
    flex-shrink: 0;
    width: 180px;
    min-width: 180px;
    transition: height 0.3s ease, min-height 0.3s ease;
}

/*.search-input-container-tall {*/
/*    height: 48px;*/
/*    min-height: 48px;*/
/*}*/

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
    font-size: 14px;
    color: #1E293B;
    background: transparent !important;
    padding: 0 !important;
    height: 100% !important;
    min-height: 32px;
    display: flex;
    align-items: center;
    transition: min-height 0.3s ease;
}

/*.search-input-container-tall .search-input {*/
/*    min-height: 48px;*/
/*    font-size: 15px;*/
/*}*/

.search-input::placeholder {
    color: #94A3B8;
    font-size: 14px;
}

.clear-search-icon {
    color: #F2994A;
    font-size: 20px;
    cursor: pointer;
    margin-right: 8px;
    flex-shrink: 0;
    min-width: 20px;
    min-height: 20px;
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
}<template>
    <b-modal
        v-model="showSelectedFiltersModal"
        title="Selected filters"
        hide-footer
        body-class="p-4"
        header-class="border-0 pb-0"
    >
        <div class="selected-filters-list d-flex flex-column gap-2">
            <div
                v-for="f in activeFilters"
                :key="f.id"
                class="selected-filter-row d-flex align-items-center justify-content-between py-2 px-3 rounded"
            >
                <span class="text-dark">{{ f.label }}: {{ f.value }}</span>
                <iconify-icon icon="lucide:x" class="filter-remove-icon" @click="removeFilter(f)" />
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <b-button variant="outline-secondary" @click="showSelectedFiltersModal = false">Close</b-button>
            <b-button variant="primary" @click="showSelectedFiltersModal = false; showSearchModal = true">Edit search</b-button>
        </div>
    </b-modal>
    <CreateLeadModal v-model="showCreateModal" @lead-created="handleLeadCreated" />
    <CreateDealModal v-model="showCreateDealModal" @deal-created="handleDealCreated" />
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
                <Deals v-if="tab.id === 'deals'" />
                <Leads v-else-if="tab.id === 'leads'" ref="leadsRef" />
                <Integration v-else-if="tab.id === 'integration'" ref="integrationRef" />
            </b-tab>

            <!-- Header Actions at the end of the tabs row -->
            <template #tabs-end>
                <div class="header-actions ms-auto d-flex align-items-center gap-11">   

                    <!-- Search: dropdown under input -->
                    <div class="search-area-column d-flex flex-column align-items-end position-relative" ref="searchDropdownAnchorRef">
                        <div
                            class="search-wrapper d-flex align-items-center"
                            :class="{
                                'search-wrapper-expanded': activeFilters && activeFilters.length,
                                'search-wrapper-tall': searchInputFocused
                            }"
                        >
                            <div v-if="activeFilters.length" class="search-filters-pills d-flex align-items-center">
                                <div
                                    v-for="f in visibleFilterPills"
                                    :key="f.id"
                                    class="search-tag d-flex align-items-center gap-2"
                                >
                                    <span>{{ f.label }}: {{ f.value }}</span>
                                    <iconify-icon icon="lucide:x" class="close-tag-icon" @click.stop="removeFilter(f)" style="cursor: pointer;"></iconify-icon>
                                </div>
                                <div
                                    v-if="moreFiltersCount > 0"
                                    class="search-tag search-tag-more d-flex align-items-center gap-2"
                                >
                                    <span class="search-tag-more-text" @click="showSearchModal = true">+{{ moreFiltersCount }} more</span>
                                    <iconify-icon icon="lucide:x" class="close-tag-icon" @click.stop="clearMoreFilters" style="cursor: pointer;"></iconify-icon>
                                </div>
                            </div>
                            <div
                                class="search-input-container d-flex align-items-center"
                                :class="{ 'search-input-container-tall': searchInputFocused }"
                                @click="showSearchModal = true"
                            >
                                <iconify-icon icon="lucide:plus" class="search-plus-icon" style="cursor: pointer;"></iconify-icon>
                                <b-form-input
                                    placeholder="Search"
                                    v-model="search"
                                    class="search-input"
                                    @focus="onSearchFocus"
                                    @blur="onSearchBlur"
                                    @input="showSearchModal = false"
                                />
                            </div>
                            <iconify-icon v-if="activeFilter || (activeFilters && activeFilters.length || search)" icon="lucide:x" class="clear-search-icon" @click="clearSearchFilter" style="cursor: pointer;"></iconify-icon>
                        </div>
                        <div v-if="showSearchModal" class="lead-search-dropdown-outer">
                            <LeadSearchModal
                                v-model="showSearchModal"
                                :as-dropdown="true"
                                :initial-active-pill="activeFilter?.id"
                                :has-active-filters="(activeFilters && activeFilters.length) > 0"
                                :current-query="lastQuery"
                                @search="onLeadSearch"
                            />
                        </div>
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import Deals from './deals/Deals.vue'
import Leads from './leadList/leads.vue'
import Integration from './integration/Integration.vue'
import LeadSearchModal from './leadList/LeadSearchModal.vue'
import CreateLeadModal from './createLead/CreateLeadModal.vue'
import CreateDealModal from './deals/CreateDealModal.vue'
import CreateIntegrationModal from './integration/CreateIntegrationModal.vue'
import AddStageModal from './stage/AddStageModal.vue'
const leadsSettings = '/assets/images/kanban/leads-setting.svg'
const addStage = '/assets/images/kanban/add-stage.svg'
import { BTabs, BTab, BFormInput, BDropdown, BDropdownItem, BModal, BButton } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const activeTab = ref('leads')
const showSearchModal = ref(false)
const showSelectedFiltersModal = ref(false)
const showCreateModal = ref(false)
const showCreateDealModal = ref(false)
const showCreateIntegrationModal = ref(false)
const showAddStageModal = ref(false)
const searchInputFocused = ref(false)
const leadsRef = ref(null)
const integrationRef = ref(null)
const searchDropdownAnchorRef = ref(null)
const search = ref('')
const searchDebounceTimer = ref(null)
const SEARCH_DEBOUNCE_MS = 400

const echoListeners = ref([])
const pollingInterval = ref(null)

const tabs = ref([
    // { id: 'deals', name: 'Deals', hasChevron: false },
    { id: 'leads', name: 'Leads', hasChevron: false },
    // { id: 'inventory', name: 'Inventory', hasChevron: true },
    // { id: 'costumers', name: 'Costumers', hasChevron: true },
    { id: 'integration', name: 'Integration', hasChevron: false },
    // { id: 'analytics', name: 'Analytics', hasChevron: false }
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

function applySearchToApi() {
    const base = lastQuery.value && Object.keys(lastQuery.value).length ? { ...lastQuery.value } : {}
    const term = (search.value || '').trim()
    const query = term ? { ...base, search: term } : base
    if (!leadsRef.value) return
    const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
    if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
        leadsComponent.fetchLeads(true, Object.keys(query).length ? query : null)
    }
}

watch(search, () => {
    if (searchDebounceTimer.value) {
        clearTimeout(searchDebounceTimer.value)
        searchDebounceTimer.value = null
    }
    searchDebounceTimer.value = setTimeout(() => {
        searchDebounceTimer.value = null
        applySearchToApi()
    }, SEARCH_DEBOUNCE_MS)
})

watch(showSearchModal, (isOpen) => {
    if (!isOpen) {
        if (searchBlurTimeout) clearTimeout(searchBlurTimeout)
        searchInputFocused.value = false
    }
})

function onDocumentClick(e) {
    if (!showSearchModal.value) return
    if (e.target.closest && e.target.closest('.modal')) return
    const el = searchDropdownAnchorRef.value
    if (el && !el.contains(e.target)) {
        showSearchModal.value = false
    }
}

onMounted(() => {
    setTimeout(() => {
        initializeStageUpdates()
    }, 1000)
    document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick)
    if (searchDebounceTimer.value) {
        clearTimeout(searchDebounceTimer.value)
        searchDebounceTimer.value = null
    }
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

    const text = event.message || 'Stage has been updated'
    const message = text ? `${title}\n${text}` : title
    // Defer so SweetAlert2 never runs in same turn as Bootstrap modal focus (avoids stack overflow)
    setTimeout(() => {
        if (window.$showNotification) {
            window.$showNotification(message, icon)
        }
    }, 200)
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

const defaultFilter = { id: 'leads-in-progress', label: 'Leads In Progress' }
const activeFilter = ref({ ...defaultFilter })
const activeFilters = ref([])
const lastQuery = ref(null)

const onLeadSearch = (payload) => {
    if (payload === null || payload?.query === null) {
        activeFilter.value = null
        activeFilters.value = []
        lastQuery.value = null
        if (leadsRef.value) {
            const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
            if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
                leadsComponent.fetchLeads(true, null)
            }
        }
        return
    }
    const query = payload?.query !== undefined ? payload.query : payload
    const pill = payload?.activePill
    console.log("pill"+pill.id);
    if (pill) {
        activeFilter.value = { id: pill.id, label: pill.label }
    } else if (!activeFilter.value) {
        activeFilter.value = { ...defaultFilter }
    }
    activeFilters.value = Array.isArray(payload?.activeFilters) ? payload.activeFilters : []
    lastQuery.value = query && Object.keys(query).length ? { ...query } : null
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, query || null)
        }
    }
}

const visibleFilterPills = computed(() => {
    const list = activeFilters.value || []
    return list.slice(0, 2)
})

const moreFiltersCount = computed(() => {
    const n = (activeFilters.value || []).length - 2
    return n > 0 ? n : 0
})

const removeFilter = (f) => {
    if (!lastQuery.value) return
    const nextQuery = { ...lastQuery.value }
    delete nextQuery[f.queryKey]
    
    if (f.queryKey === 'created_at') {
        delete nextQuery.created_from
        delete nextQuery.created_to
    }
    
    activeFilters.value = activeFilters.value.filter(x => x.id !== f.id)
    lastQuery.value = Object.keys(nextQuery).length ? nextQuery : null
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, Object.keys(nextQuery).length ? nextQuery : null)
        }
    }
}

const clearMoreFilters = () => {
    const list = activeFilters.value || []
    if (list.length <= 2) return
    const keep = list.slice(0, 2)
    const remove = list.slice(2)
    const nextQuery = lastQuery.value ? { ...lastQuery.value } : {}
    
    remove.forEach(f => { 
        delete nextQuery[f.queryKey]
        if (f.queryKey === 'created_at') {
            delete nextQuery.created_from
            delete nextQuery.created_to
        }
    })
    
    activeFilters.value = keep
    lastQuery.value = Object.keys(nextQuery).length ? nextQuery : null
    if (leadsRef.value) {
        const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
        if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
            leadsComponent.fetchLeads(true, Object.keys(nextQuery).length ? nextQuery : null)
        }
    }
}

const clearSearchFilter = () => {
    activeFilter.value = null
    activeFilters.value = []
    lastQuery.value = null
    search.value = ''
        showSearchModal.value = false

    onLeadSearch(null)
}

let searchBlurTimeout = null
function onSearchFocus() {
    if (searchBlurTimeout) {
        clearTimeout(searchBlurTimeout)
        searchBlurTimeout = null
    }
    searchInputFocused.value = true
    showSearchModal.value = true
}
function onSearchBlur() {
    searchBlurTimeout = setTimeout(() => {
        searchInputFocused.value = false
        searchBlurTimeout = null
    }, 200)
}

const handleCreateNew = () => {
    if (activeTab.value === 'deals') {
        showCreateDealModal.value = true
    } else if (activeTab.value === 'integration') {
        showCreateIntegrationModal.value = true
    } else {
        showCreateModal.value = true
    }
}

const handleDealCreated = (payload) => {
    if (window.$showNotification) {
        setTimeout(() => window.$showNotification('Deal created successfully!', 'success'), 200)
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
    $showNotification('Integration created successfully!', 'success')
    const comp = integrationRef.value && (Array.isArray(integrationRef.value) ? integrationRef.value[0] : integrationRef.value)
    if (comp && typeof comp.loadIntegrations === 'function') comp.loadIntegrations()
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

// Notification helper – always use global deferred to avoid SweetAlert2 stack overflow
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
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
.search-area-column {
    align-items: flex-end;
}

.lead-search-dropdown-outer {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 6px;
    z-index: 1050;
    width: 1140px;
    max-width: calc(100vw - 32px);
}

.search-wrapper {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 100px;
    min-height: 38px;
    gap: 8px;
    padding: 5px 8px 5px 5px;
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    width: max-content;
    max-width: 220px;
    transition: max-width 0.35s ease, min-height 0.3s ease, padding 0.3s ease, border-radius 0.3s ease;
}

.search-wrapper-expanded {
    max-width: 1020px;
}

.search-wrapper-tall {
    /*min-height: 72px;*/
    /*padding: 12px 12px 12px 14px;*/
    /*border-radius: 16px;*/
}

.search-filters-pills {
    flex: 1 1 0;
    min-width: 0;
    gap: 6px 8px;
    flex-wrap: nowrap;
    overflow: hidden;
}

.search-tag-more {
    flex-shrink: 0;
}

.search-tag-more-text {
    cursor: pointer;
    user-select: none;
}

.selected-filter-row {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
}

.filter-remove-icon {
    font-size: 16px;
    color: #64748B;
    cursor: pointer;
}

.filter-remove-icon:hover {
    color: #1E293B;
}

.search-tag {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 100px;
    padding: 4px 10px;
    font-size: 12px;
    color: #475569;
    white-space: nowrap;
    width: fit-content;
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
    height: 32px;
    min-height: 32px;
    display: flex;
    align-items: center;
    flex-shrink: 0;
    width: 180px;
    min-width: 180px;
    transition: height 0.3s ease, min-height 0.3s ease;
}

/*.search-input-container-tall {*/
/*    height: 48px;*/
/*    min-height: 48px;*/
/*}*/

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
    font-size: 14px;
    color: #1E293B;
    background: transparent !important;
    padding: 0 !important;
    height: 100% !important;
    min-height: 32px;
    display: flex;
    align-items: center;
    transition: min-height 0.3s ease;
}

/*.search-input-container-tall .search-input {*/
/*    min-height: 48px;*/
/*    font-size: 15px;*/
/*}*/

.search-input::placeholder {
    color: #94A3B8;
    font-size: 14px;
}

.clear-search-icon {
    color: #F2994A;
    font-size: 20px;
    cursor: pointer;
    margin-right: 8px;
    flex-shrink: 0;
    min-width: 20px;
    min-height: 20px;
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
