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
    <CreateDealModal v-model="showCreateDealModal" @deal-created="handleDealCreated" :deal-type="currentDealType" />
    <CreateIntegrationModal v-model="showCreateIntegrationModal" @integration-created="handleIntegrationCreated" />
    <AddStageModal v-model="showAddStageModal"   :stage-type="currentStageType"
        :deal-type="currentDealType"
        @stage-created="handleStageCreated" />
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
                <Deals v-if="tab.id === 'deals'" ref="dealsRef" />
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
                    <div class="more-options-wrapper d-flex align-items-center gap-12" v-if="hasCreateStagePermission || (isSuperAdmin  && activeTab=='leads')">
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
                            
                            <b-dropdown-item @click="showAddStageModal = true" v-if="hasCreateStagePermission"  class="dropdown-item-custom">
                                <img :src="addStage" alt="Add Stage" class="dropdown-icon" />
                                <span class="dropdown-text">Add New Stage</span>
                            </b-dropdown-item>
                             <b-dropdown-item v-if="isSuperAdmin && activeTab=='leads'" @click="goToStageVisibility" class="dropdown-item-custom">
                                <iconify-icon icon="lucide:eye" class="dropdown-icon" style="font-size: 18px; color: #666;"></iconify-icon>
                                <span class="dropdown-text">Stage Visibility Settings</span>
                            </b-dropdown-item>
                            <b-dropdown-item v-if="isSuperAdmin && activeTab=='leads'" @click="goToLeadScoringSettings" class="dropdown-item-custom">
                                <iconify-icon icon="lucide:brain-circuit" class="dropdown-icon" style="font-size: 18px; color: #666;"></iconify-icon>
                                <span class="dropdown-text">Lead Scoring Engine</span>
                            </b-dropdown-item>
                    
                        </b-dropdown>
                        
                        <button   v-if="activeTab === 'leads' && isSuperAdmin" 
                                    @click="goToKanbanSettings" 
                                    class="action-icon-btn d-flex align-items-center justify-content-center radius-circle border">
                            <img :src="leadsSettings" id="settingsIcon" alt="Settings" />
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
import { useRouter } from 'vue-router'

const router = useRouter()
const activeTab = ref('leads')
const showSearchModal = ref(false)
const showSelectedFiltersModal = ref(false)
const showCreateModal = ref(false)
const showCreateDealModal = ref(false)
const showCreateIntegrationModal = ref(false)
const showAddStageModal = ref(false)
const searchInputFocused = ref(false)
const leadsRef = ref(null)
const dealsRef = ref(null)

const integrationRef = ref(null)
const searchDropdownAnchorRef = ref(null)
const search = ref('')
const searchDebounceTimer = ref(null)
const SEARCH_DEBOUNCE_MS = 400

const echoListeners = ref([])
const pollingInterval = ref(null)

const tabs = computed(() => {
    const baseTabs = [
        { id: 'deals', name: 'Deals', hasChevron: false },
        { id: 'leads', name: 'Leads', hasChevron: false },
        // { id: 'inventory', name: 'Inventory', hasChevron: true },
        // { id: 'costumers', name: 'Costumers', hasChevron: true },
        // { id: 'analytics', name: 'Analytics', hasChevron: false }
    ]
    
    if (isSuperAdmin.value) {
        baseTabs.push({ id: 'integration', name: 'Integration', hasChevron: false })
    }
    
    return baseTabs
})

const goToStageVisibility = () => {
    router.push('/settings/stage-visibility')
}
const goToKanbanSettings = () => {
    router.push('/settings/kanban')
}
const goToLeadScoringSettings = () => {
    router.push('/settings/lead-scoring')
}
const currentStageType = computed(() => {
    return activeTab.value === 'deals' ? 'deal' : 'lead'
})

const currentDealType = computed(() => {
    if (activeTab.value === 'deals') {
      
        if (dealsRef.value) {
            const dealsComponent = Array.isArray(dealsRef.value) ? dealsRef.value[0] : dealsRef.value
            return dealsComponent?.currentDealType || 'primary'
        }
    }
    return null
})



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
// Get user from storage (same pattern as header/index.vue)
const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())

// Applied search params (from search modal, not from URL)
const appliedSearchParams = ref(null)

// Check if user is admin or super_admin (same pattern as header/index.vue)
const isSuperAdmin = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('super_admin') || user.value.roles?.includes('admin') 
    
    return isAdminUser
})
const hasCreateStagePermission = computed(() => {
    if (!user.value) return false
    
    return user.value.permissions?.includes('stages-create')
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
    
    // التحقق من نوع المرحلة
    const stageType = stageData?.stage_type || currentStageType.value
    
    console.log('Stage type:', stageType)
    
    if (stageType === 'deal') {
        // تحديث Deals
        if (dealsRef.value) {
            const dealsComponent = Array.isArray(dealsRef.value) ? dealsRef.value[0] : dealsRef.value
            
            if (dealsComponent && typeof dealsComponent.fetchDeals === 'function') {
                console.log('✅ Calling fetchDeals after stage creation')
                await dealsComponent.fetchDeals(true)
            } else {
                console.log('⚠️ fetchDeals method not found on deals component')
            }
        } else {
            console.log('⚠️ dealsRef is not available')
        }
    } else {
        // تحديث Leads (السلوك الافتراضي)
        if (leadsRef.value) {
            const leadsComponent = Array.isArray(leadsRef.value) ? leadsRef.value[0] : leadsRef.value
            
            if (leadsComponent && typeof leadsComponent.fetchLeads === 'function') {
                console.log('✅ Calling fetchLeads after stage creation')
                await leadsComponent.fetchLeads(true)
            }
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
    background-color: transparent !important;
    /*margin: 8px 12px 0;*/
    border-radius: 16px;
}

:deep(.kanban-tabs-container),
:deep(.kanban-tabs-container .nav),
:deep(.kanban-tabs-container > .nav-tabs) {
    border-bottom: none !important;
    box-shadow: none !important;
}
/* Bootstrap Tabs Customization – tighter section spacing and left/right */
:deep(.kanban-tabs-container > .nav-tabs) {
    background: transparent;
    height: 56px;
    flex-shrink: 0;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 16px;
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
    border-bottom: none !important;
    background: transparent !important;
    padding: 10px 14px !important;
    height: 100%;
    display: flex;
    align-items: center;
    border-radius: 0;
    position: relative;
    margin-bottom: 0 !important;
    text-decoration: none;
    color: #fff;
    font-family: 'Montserrat', sans-serif;
    font-size: 15px;
    font-weight: 600;
    transition: all 0.2s ease;
}

:deep(.kanban-tabs-container .nav-link:hover) {
    color: #fff;
}

:deep(.kanban-tabs-container .nav-link.active) {
    color: #fff !important;
    font-weight: 700;
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
     border-radius: 999px;
    padding: 4px 14px;
    min-height: 30px;
    background: linear-gradient(90deg, #12b981, #22c55e);
    border: 1px solid #16a34a;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.45);
    transition: all 0.2s ease;
}

.btn-create-new:hover {
   background: linear-gradient(90deg, #16a34a, #22c55e);
    border-color: #15803d;
}

.action-icon-btn {
 background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.55) !important;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    color: rgba(255, 255, 255, 0.95);
}

.action-icon-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.7) !important;
    color: #fff;
}

.action-icon-btn:focus {
    outline: none !important;
    box-shadow: none !important;
}

:deep(.action-icon-btn-dropdown .action-icon-btn) {
        color: rgba(255, 255, 255, 0.95) !important;
}

.radius-circle {
    border-radius: 50%;
}

.btn-create-new-text {
    font-style: Medium;
    font-size: 14px;
    font-weight: 600;
    color: #ffffff;
    margin: 2px;
}

/* Utility Classes */
.text-warning-600 {
    color: rgba(255, 255, 255, 0.9) !important;
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
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 999px;
    height: 36px;
    min-height: 36px;
    gap: 8px;
    padding: 4px 12px 4px 10px;
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    width: max-content;
    max-width: 320px;
    min-width: 160px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: max-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1), min-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.search-wrapper-focused,
.search-wrapper-tall {
    max-width: 560px;
    min-width: 280px;
    height: 36px;
    min-height: 36px;
    padding: 4px 12px 4px 10px;
    border-radius: 999px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-wrapper-expanded {
    max-width: 560px;
    min-width: 280px;
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
    background: rgba(59, 130, 246, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #fff;
    white-space: nowrap;
    width: fit-content;
}

.close-tag-icon {
    font-size: 12px;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.9);
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 18px;
    height: 18px;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
}


.search-input-container {
    color: #1e293b;
    height: 26px;
    min-height: 26px;
    display: flex;
    align-items: center;
    flex: 1 1 auto;
    min-width: 80px;
    width: 100%;
    max-width: 160px;
    transition: min-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1), max-width 0.35s cubic-bezier(0.25, 0.1, 0.25, 1);
}

.search-wrapper-focused,
.search-wrapper-tall {
    max-width: 560px;
    min-width: 280px;
    height: 36px;
    min-height: 36px;
    padding: 4px 12px 4px 10px;
    border-radius: 999px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-wrapper-focused .search-input-container-tall,
.search-wrapper-tall .search-input-container-tall {
    min-width: 440px;
    max-width: 100%;
}

.search-magnify-icon {
    color: #64748b;
    font-size: 18px;
    flex-shrink: 0;
    margin-right: 4px;
}
.search-plus-icon {
    font-size: 18px;
    color: #64748b;
    margin-right: 6px;
    margin-bottom: 0;
    flex-shrink: 0;
}

.search-input,
.search-input:focus,
:deep(.search-input-container input),
:deep(.search-input-container input:focus) {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    background: transparent !important;
    color: #1e293b !important;
}
.search-input {
    width: 100%;
    font-size: 14px;
    font-weight: 500;
    color: #1e293b !important;
    padding: 0 4px !important;
    height: 100% !important;
    min-height: 26px;
    display: flex;
    align-items: center;
    caret-color: #1e293b;
}


.search-input-container-tall .search-input {
    min-height: 26px;
    font-size: 14px;
}

.search-input::placeholder {
    color: #94A3B8;
    font-size: 14px;
}

.clear-search-icon {
    color: #64748b;
    font-size: 18px;
    cursor: pointer;
    margin-right: 4px;
    flex-shrink: 0;
    min-width: 18px;
    min-height: 18px;
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
    background: rgba(255, 255, 255, 0.95);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
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
#settingsIcon{
    filter:invert(1);
    width: 20px;
    padding: 2px;
}
</style>
