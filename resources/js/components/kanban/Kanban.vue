<template>
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
                    <!-- Create New Button -->
                    <button class="btn-create-new d-flex align-items-center">
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
import leadsSettings from '@/assets/images/kanban/svg/leads-setting.svg'
import { BTabs, BTab } from 'bootstrap-vue-3'

const activeTab = ref('leads')

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
    opacity: 1;
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
</style>
