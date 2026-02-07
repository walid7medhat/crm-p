<template>
    <b-modal 
        id="create-integration-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="lg"
        centered
        body-class="p-0"
        modal-class="create-integration-modal"
    >
        <div class="create-integration-modal-content">
            <!-- Header -->
            <div class="modal-header-section">
                <p class="modal-title">Add CRM Form</p>
                <button class="close-btn" @click="show = false">
                    <iconify-icon icon="lucide:x" class="close-icon"></iconify-icon>
                </button>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    class="tab-button"
                    :class="{ active: activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.name }}
                </button>
            </div>

            <!-- Content Area -->
            <div class="modal-body-section info-card bg-white m-3 p-3 radius-12 shadow-4 border">
                <!-- CRM Entities Tab Content -->
                <CrmEntitiesTab
                    v-if="activeTab === 'crm-entities'"
                    v-model:model-selected-entity="selectedEntity"
                    v-model:model-expert-mode="expertMode"
                    v-model:model-duplicate-handling="duplicateHandling"
                />

                <!-- Hidden Field Values Tab Content -->
                <HiddenFieldValuesTab
                    v-else-if="activeTab === 'hidden-fields'"
                />

                <!-- Facebook Lead Ads Tab Content -->
                <!-- <FacebookLeadAdsTab
                    v-else-if="activeTab === 'facebook-leads'"
                /> -->

                <!-- Other Tabs Content (Placeholder) -->
                <div v-else class="tab-content">
                    <h4 class="section-title">{{ tabs.find(t => t.id === activeTab)?.name }}</h4>
                    <p class="tab-placeholder">Content for {{ tabs.find(t => t.id === activeTab)?.name }} coming soon...</p>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="modal-footer-section">
                <button class="footer-btn cancel-btn" @click="show = false">Cancel</button>
                <button class="footer-btn apply-btn" @click="handleApply">Apply</button>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { BModal } from 'bootstrap-vue-3'
import CrmEntitiesTab from './CrmEntitiesTab.vue'
import HiddenFieldValuesTab from './HiddenFieldValuesTab.vue'
// import FacebookLeadAdsTab from './FacebookLeadAdsTab.vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'integration-created'])

const show = ref(props.modelValue)
const activeTab = ref('crm-entities')
const selectedEntity = ref(null)
const expertMode = ref(false)
const duplicateHandling = ref('merge')

const tabs = [
    { id: 'crm-entities', name: 'CRM Entities' },
    { id: 'hidden-fields', name: 'Hidden Field Values' },
    { id: 'facebook-leads', name: 'Facebook Lead Ads' },
    { id: 'other-settings', name: 'Other Settings' }
]

watch(() => props.modelValue, (newVal) => {
    show.value = newVal
})

watch(show, (newVal) => {
    emit('update:modelValue', newVal)
    if (!newVal) {
        // Reset form when modal closes
        activeTab.value = 'crm-entities'
        selectedEntity.value = null
        expertMode.value = false
        duplicateHandling.value = 'merge'
    }
})

const handleApply = () => {
    // Handle apply logic here
    emit('integration-created', {
        entity: selectedEntity.value,
        expertMode: expertMode.value
    })
    show.value = false
}
</script>

<style scoped>
:deep(.create-integration-modal .modal-dialog) {
    max-width: 600px;
    margin: 1.75rem auto;
}

:deep(.create-integration-modal .modal-content) {
    border-radius: 16px;
    border: none;
    box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

:deep(.create-integration-modal .modal-body) {
    padding: 0;
}

.create-integration-modal-content {
    background: #FFFFFF;
    display: flex;
    flex-direction: column;
    /* max-height: 50vh; */
    border-radius: 10px;
}

/* Header Section */
.modal-header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 32px;
    border-bottom: 1px solid #E2E8F0;
}

.modal-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    /* font-weight: 600; */
    color: #01062C;
    margin: 0;
}

.close-btn {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    border-radius: 4px;
    transition: all 0.2s;
}

.close-btn:hover {
    background: #F1F5F9;
    color: #01062C;
}

.close-icon {
    font-size: 20px;
}

/* Tabs Container */
.tabs-container {
    display: flex;
    gap: 0;
    justify-content: center;
    margin: 10px 0;
    background: #FFFFFF;
}

.tab-button {
    padding: 5px 10px;
    border: 1px solid #E5E7EB;
    background: #FFFFFF;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #666666;
    cursor: pointer;
    position: relative;
    border-radius: 25px;
    transition: all 0.2s;
    margin-right: 8px;
}

.tab-button:hover {
    background: #E2E8F0;
    color: #01062C;
}

.tab-button.active {
    background: #01062C;
    color: #FFFFFF;
    font-weight: 600;
    border-color: #01062C;
}

.tab-button.active:hover {
    background: #01062C;
    color: #FFFFFF;
}

.section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
    margin: 0 0 24px 0;
}

/* Input Field Wrapper */
.input-field-wrapper {
    position: relative;
    width: 100%;
}

.custom-input-field {
    width: 100%;
    height: 44px;
    padding: 0 40px 0 16px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #1E293B;
    background: #FFFFFF;
    transition: all 0.2s;
}

.custom-input-field:focus {
    outline: none;
    border-color: #01062C;
    box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1);
}

.custom-input-field::placeholder {
    color: #94A3B8;
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #94A3B8;
    pointer-events: none;
}

/* Tab Placeholder */
.tab-placeholder {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #64748B;
    margin: 0;
}

/* Footer Section */
.modal-footer-section {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 10px 20px;
    border-top: 1px solid #E2E8F0;
    background: #FFFFFF;
}

.footer-btn {
    padding: 10px 24px;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.cancel-btn {
    background: #FFFFFF;
    color: #64748B;
    border: 1px solid #E2E8F0;
}

.cancel-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.apply-btn {
    background: #01062C;
    color: #FFFFFF;
}

.apply-btn:hover {
    background: #020A3D;
}

/* Scrollbar Styling */
.modal-body-section::-webkit-scrollbar {
    width: 8px;
}

.modal-body-section::-webkit-scrollbar-track {
    background: #F8FAFC;
    border-radius: 4px;
}

.modal-body-section::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}

.modal-body-section::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}
</style>
