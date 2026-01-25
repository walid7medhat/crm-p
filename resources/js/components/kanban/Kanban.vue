<template>
    <LeadSearchModal v-model="showSearchModal" />
    <!-- <CreateLeadModal v-model="showCreateModal" /> -->
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
                <Leads v-if="tab.id === 'leads'" />
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
                    <button class="btn-create-new d-flex align-items-center" @click="showCreateModal = true">
                        <span class="btn-create-new-text">Create New</span>
                        <iconify-icon icon="lucide:chevrons-up-down" class="text-warning-600 text-md"></iconify-icon>
                    </button>

                    <!-- More Options -->
                    <div class="more-options-wrapper d-flex align-items-center gap-12">
                        <button class="action-icon-btn d-flex align-items-center justify-content-center radius-circle border">
                            <iconify-icon icon="lucide:more-vertical" class="text-lg font-weight-bold"></iconify-icon>
                        </button>
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
import { ref, computed } from 'vue'
import Leads from './leads.vue'
import LeadSearchModal from './LeadSearchModal.vue'
import CreateLeadModal from './CreateLeadModal.vue'
import leadsSettings from '@/assets/images/kanban/svg/leads-setting.svg'
import { BTabs, BTab, BFormInput } from 'bootstrap-vue-3'

const activeTab = ref('leads')
const showSearchModal = ref(false)
const showCreateModal = ref(false)

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
}

.action-icon-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1 !important;
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
</style>
