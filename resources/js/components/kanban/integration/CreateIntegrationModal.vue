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
        @hidden="resetModal"
    >
        <div class="create-integration-modal-content">
            <!-- Header -->
            <div class="modal-header-section">
                <p class="modal-title">Add CRM Form Integration</p>
                <button class="close-btn" @click="show = false">
                    <iconify-icon icon="lucide:x" class="close-icon"></iconify-icon>
                </button>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <button 
                    v-for="(tab, index) in tabs" 
                    :key="tab.id"
                    class="tab-button"
                    :class="{ 
                        active: activeTab === tab.id,
                        completed: isTabCompleted(tab.id)
                    }"
                    @click="goToTab(tab.id)"
                >
                    <span class="tab-number">{{ index + 1 }}</span>
                    {{ tab.name }}
                    <iconify-icon 
                        v-if="isTabCompleted(tab.id) && activeTab !== tab.id" 
                        icon="lucide:check" 
                        class="tab-check-icon"
                    ></iconify-icon>
                </button>
            </div>

            <!-- Content Area -->
            <div class="modal-body-section info-card bg-white m-3 p-3 radius-12 shadow-4 border">
                <!-- CRM Entities Tab Content -->
                <CrmEntitiesTab
                    v-if="activeTab === 'crm-entities'"
                    v-model:selected-entity="formData.selectedEntity"
                    v-model:expert-mode="formData.expertMode"
                    v-model:duplicate-handling="formData.duplicateHandling"
                />

                <!-- Hidden Field Values Tab Content -->
                <HiddenFieldValuesTab
                    v-else-if="activeTab === 'hidden-fields'"
                    v-model:project="formData.project"
                    v-model:lead-source="formData.leadSource"
                />

                <!-- Facebook Lead Ads Tab Content -->
                <FacebookLeadAdsTab
                    v-else-if="activeTab === 'facebook-leads'"
                    v-model:page-id="formData.pageId"
                    v-model:form-id="formData.facebookFormId"
                    v-model:form-name="formData.facebookFormName"
                    v-model:field-mappings="formData.fieldMappings"
                    @connected="handleFacebookConnected"
                />

                <!-- Other Settings Tab Content -->
                <OtherSettingsTab
                    v-else-if="activeTab === 'other-settings'"
                    v-model:integration-name="formData.integrationName"
                    v-model:responsible-person-id="formData.responsiblePersonId"
                    v-model:responsible-person="formData.responsiblePerson"
                    v-model:dont-make-responsible-if-not-clocked-in="formData.dontMakeResponsibleIfNotClockedIn"
                />
            </div>

            <!-- Navigation Footer -->
            <div class="modal-footer-section">
                <div class="footer-left">
                    <button 
                        v-if="activeTab !== 'crm-entities'" 
                        class="footer-btn prev-btn" 
                        @click="previousTab"
                    >
                        <iconify-icon icon="lucide:arrow-left" class="btn-icon"></iconify-icon>
                        Previous
                    </button>
                </div>
                
                <div class="footer-right">
                    <button class="footer-btn cancel-btn" @click="show = false">Cancel</button>
                    
                    <button 
                        v-if="activeTab !== 'other-settings'" 
                        class="footer-btn next-btn" 
                        @click="nextTab"
                        :disabled="!isTabCompleted(activeTab)"
                    >
                        Next
                        <iconify-icon icon="lucide:arrow-right" class="btn-icon"></iconify-icon>
                    </button>
                    
                    <button 
                        v-else
                        class="footer-btn save-btn" 
                        @click="handleSave"
                        :disabled="!canSave"
                    >
                        <iconify-icon icon="lucide:save" class="btn-icon"></iconify-icon>
                        Save Integration
                    </button>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch, computed ,getCurrentInstance } from 'vue'
import { BModal } from 'bootstrap-vue-3'
import CrmEntitiesTab from './CrmEntitiesTab.vue'
import HiddenFieldValuesTab from './HiddenFieldValuesTab.vue'
import FacebookLeadAdsTab from './FacebookLeadAdsTab.vue'
import OtherSettingsTab from './OtherSettingsTab.vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    editId: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['update:modelValue', 'saved', 'updated'])
const { proxy } = getCurrentInstance()

const show = ref(props.modelValue)
const activeTab = ref('crm-entities')
const loading = ref(false)

// Tabs configuration
const tabs = [
    { id: 'crm-entities', name: 'CRM Entities' },
    { id: 'hidden-fields', name: 'Hidden Field Values' },
    { id: 'facebook-leads', name: 'Facebook Lead Ads' },
    { id: 'other-settings', name: 'Other Settings' }
]

// Form data model
const formData = ref({
    selectedEntity: null,
    expertMode: false,
    duplicateHandling: 'merge',
    project: null,
    leadSource: null,
    pageId: null,
    facebookFormId: null,
    facebookFormName: null,
    fieldMappings: [],
    integrationName: '',
    responsiblePersonId: null,
    responsiblePerson: null,
    dontMakeResponsibleIfNotClockedIn: true
})

// Check if tab is completed
const isTabCompleted = (tabId) => {
    switch (tabId) {
        case 'crm-entities':
            return !!formData.value.selectedEntity
        case 'hidden-fields':
            // !!formData.value.project &&
            return  !!formData.value.leadSource
        case 'facebook-leads':
            return !!formData.value.facebookFormId
        case 'other-settings':
            return !!formData.value.integrationName && !!formData.value.responsiblePersonId
        default:
            return false
    }
}

// Check if all required fields are filled
const canSave = computed(() => {
    return (
        formData.value.selectedEntity &&
        // formData.value.project &&
        formData.value.leadSource &&
        formData.value.facebookFormId &&
        formData.value.integrationName &&
        formData.value.responsiblePersonId
    )
})

// Navigation
const goToTab = (tabId) => {
    const currentIndex = tabs.findIndex(t => t.id === activeTab.value)
    const targetIndex = tabs.findIndex(t => t.id === tabId)
    
    // Allow going to completed tabs or previous tabs
    if (targetIndex <= currentIndex || isTabCompleted(tabId)) {
        activeTab.value = tabId
    }
}

const nextTab = () => {
    const currentIndex = tabs.findIndex(t => t.id === activeTab.value)
    if (currentIndex < tabs.length - 1) {
        activeTab.value = tabs[currentIndex + 1].id
    }
}

const previousTab = () => {
    const currentIndex = tabs.findIndex(t => t.id === activeTab.value)
    if (currentIndex > 0) {
        activeTab.value = tabs[currentIndex - 1].id
    }
}

// Watch for modal visibility
watch(() => props.modelValue, (newVal) => {
    show.value = newVal
    if (newVal && props.editId) {
        loadIntegrationData()
    }
})

watch(show, (newVal) => {
    emit('update:modelValue', newVal)
})

// Load integration data for editing
const loadIntegrationData = async () => {
    if (!props.editId) return
    
    loading.value = true
    try {
        const response = await api.get(`/integrations/${props.editId}`)
        const data = response.data.data
        
        formData.value = {
            selectedEntity: data.crm_entity,
            expertMode: data.expert_mode,
            duplicateHandling: data.duplicate_handling,
            project: data.project_id,
            leadSource: data.lead_source,
            pageId: data.page_id,
            facebookFormId: data.facebook_form_id,
            facebookFormName: data.facebook_form_name,
            fieldMappings: data.field_mappings || [],
            integrationName: data.name,
            responsiblePersonId: data.responsible_person_id,
            responsiblePerson: data.responsible_person,
            dontMakeResponsibleIfNotClockedIn: data.dont_make_responsible_if_not_clocked_in
        }

        proxy?.$showNotification?.('Integration data loaded successfully', 'success')
    } catch (error) {
        proxy?.$showNotification?.(error.response?.data?.message || 'Failed to load integration data', 'error')
    } finally {
        loading.value = false
    }
}

// Handle Facebook connection
const handleFacebookConnected = (data) => {
    formData.value.pageId = data.pageId
    formData.value.facebookFormId = data.formId
    formData.value.facebookFormName = data.formName
    formData.value.fieldMappings = data.fieldMappings
}

// Save integration
const handleSave = async () => {
    try {
        const payload = {
            name: formData.value.integrationName,
            crm_entity: formData.value.selectedEntity,
            expert_mode: formData.value.expertMode,
            duplicate_handling: formData.value.duplicateHandling,
            project_id: formData.value.project,
            lead_source: formData.value.leadSource,
            page_id: formData.value.pageId,
            facebook_form_id: formData.value.facebookFormId,
            facebook_form_name: formData.value.facebookFormName,
            field_mappings: formData.value.fieldMappings,
            responsible_person_id: formData.value.responsiblePersonId,
            dont_make_responsible_if_not_clocked_in: formData.value.dontMakeResponsibleIfNotClockedIn,
            status: 'active'
        }

        let response
        if (props.editId) {
            response = await api.put(`/integrations/${props.editId}`, payload)
            emit('updated', response.data.data)
            proxy?.$showNotification?.('Integration updated successfully', 'success')
        } else {
            response = await api.post('/integrations', payload)
            emit('saved', response.data.data)
            proxy?.$showNotification?.('Integration created successfully', 'success')
        }

        show.value = false
    } catch (error) {
        proxy?.$showNotification?.(error.response?.data?.message || 'Failed to save integration', 'error')
    }
}

// Reset modal
const resetModal = () => {
    activeTab.value = 'crm-entities'
    formData.value = {
        selectedEntity: null,
        expertMode: false,
        duplicateHandling: 'merge',
        project: null,
        leadSource: null,
        pageId: null,
        facebookFormId: null,
        facebookFormName: null,
        fieldMappings: [],
        integrationName: '',
        responsiblePersonId: null,
        responsiblePerson: null,
        dontMakeResponsibleIfNotClockedIn: true
    }
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

/* Tabs Container - نفس الاستايل القديم */
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

/* استايل جديد للتاب المكتمل */
.tab-button.completed {
    background: #01062C;
    color: #FFFFFF;
    border-color: #01062C;
}

.tab-button.completed:hover {
    background: #FAA300;
    border-color: #FAA300;
}

.tab-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    font-size: 11px;
    font-weight: 600;
    margin-right: 4px;
}

.tab-check-icon {
    font-size: 16px;
    margin-left: 4px;
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

/* Footer Section - معدل مع أزرار جديدة */
.modal-footer-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 32px;
    border-top: 1px solid #E2E8F0;
    background: #FFFFFF;
}

.footer-left {
    flex: 1;
}

.footer-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.footer-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-icon {
    font-size: 16px;
}

.prev-btn {
    background: #F1F5F9;
    color: #64748B;
    border: 1px solid #E2E8F0;
}

.prev-btn:hover {
    background: #E2E8F0;
    border-color: #CBD5E1;
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

.next-btn {
    background: #01062C;
    color: #FFFFFF;
}

.next-btn:hover:not(:disabled) {
    background: #020A3D;
}

.next-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* زر Save الجديد */
.save-btn {
    background: #faa300;
    color: #FFFFFF;
    font-weight: 600;
    padding: 10px 24px;
}

.save-btn:hover:not(:disabled) {
    background: #FAA300;
}

.save-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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