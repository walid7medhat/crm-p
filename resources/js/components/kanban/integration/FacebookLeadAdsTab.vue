<template>
    <div class="tab-content">
        <p class="main-section-title">Facebook Lead Ads</p>
        
        <!-- Account Section -->
        <div class="account-card">
            <div class="account-info">
                <div class="profile-picture-wrapper">
                    <div class="profile-picture-placeholder">
                        <iconify-icon icon="lucide:user" class="profile-icon"></iconify-icon>
                    </div>
                </div>
                <div class="account-details">
                    <span class="account-label">Account</span>
                    <span class="account-name">Ahmad Mahfoz</span>
                </div>
            </div>
            <button class="disconnect-btn">
                <iconify-icon icon="lucide:link-off" class="disconnect-icon"></iconify-icon>
                <span>Disconnect</span>
            </button>
        </div>

        <!-- Page and From Section -->
        <div class="page-from-card">
            <!-- Page Selection -->
            <div class="field-group">
                <label class="field-label">Page</label>
                <v-select 
                    v-model="selectedPage" 
                    :options="pageOptions" 
                    :reduce="option => option.value"
                    label="text"
                    placeholder="Select Pages"
                    class="custom-v-select from-select"
                >
                    <template #open-indicator="{ attributes }">
                        <span v-bind="attributes" class="from-indicator-wrapper">
                            <iconify-icon icon="lucide:chevron-up" class="vs__open-indicator-icon up-icon"></iconify-icon>
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon down-icon"></iconify-icon>
                        </span>
                    </template>
                </v-select>
            </div>

            <!-- From Selection -->
            <div class="field-group">
                <label class="field-label">From</label>
                <v-select 
                    v-model="selectedFrom" 
                    :options="fromOptions" 
                    :reduce="option => option.value"
                    label="text"
                    placeholder="Select From"
                    class="custom-v-select from-select"
                >
                    <template #open-indicator="{ attributes }">
                        <span v-bind="attributes" class="from-indicator-wrapper">
                            <iconify-icon icon="lucide:chevron-up" class="vs__open-indicator-icon up-icon"></iconify-icon>
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon down-icon"></iconify-icon>
                        </span>
                    </template>
                </v-select>
            </div>
        </div>

        <!-- Field Mapping Sections -->
        <div 
            v-for="field in fieldMappings" 
            :key="field.id"
            class="field-mapping-card"
        >
            <div class="field-mapping-info">
                <span class="field-name">{{ field.name }}</span>
                <span 
                    class="mapping-status"
                    :class="{ 'not-selected': !field.mappedTo }"
                >
                    {{ field.mappedTo || 'CRM Form Not Selected' }}
                </span>
            </div>
            <button class="select-field-btn" @click="openFieldSelector(field)">
                Select Field
            </button>
        </div>

        <!-- Select Fields Modal -->
        <b-modal 
            id="select-fields-modal" 
            v-model="showSelectFieldsModal"
            hide-header
            hide-footer
            size="lg"
            centered
            body-class="p-0"
            modal-class="select-fields-modal"
            backdrop="true"
        >
            <div class="select-fields-modal-content">
                <!-- Header -->
                <div class="modal-header-section">
                    <p class="modal-title">Select Fields</p>
                    <button class="close-btn" @click="closeSelectFieldsModal">
                        <iconify-icon icon="lucide:x" class="close-icon"></iconify-icon>
                    </button>
                </div>

                <!-- Content Area - Fields Grid -->
                <div class="modal-body-section">
                    <div class="fields-grid">
                        <div 
                            v-for="fieldOption in availableFields" 
                            :key="fieldOption.id"
                            class="field-option"
                            @click="toggleFieldSelection(fieldOption.id)"
                        >
                            <input 
                                type="checkbox" 
                                :id="`field-${fieldOption.id}`"
                                :checked="selectedFieldIds.includes(fieldOption.id)"
                                class="field-checkbox"
                                @click.stop="toggleFieldSelection(fieldOption.id)"
                            />
                            <label 
                                :for="`field-${fieldOption.id}`"
                                class="field-label-text"
                                @click.stop="toggleFieldSelection(fieldOption.id)"
                            >
                                {{ fieldOption.name }}
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer-section">
                    <a href="#" class="create-field-link" @click.prevent="handleCreateField">Create Field</a>
                    <div class="footer-buttons">
                        <button class="footer-btn cancel-btn" @click="closeSelectFieldsModal">Cancel</button>
                        <button class="footer-btn apply-btn" @click="handleApplyFields">Apply</button>
                    </div>
                </div>
            </div>
        </b-modal>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { BModal } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const selectedPage = ref([])
const selectedFrom = ref('al-reem-island')
const showSelectFieldsModal = ref(false)
const currentFieldMapping = ref(null)
const selectedFieldIds = ref([])

const pageOptions = ref([
    { value: 'page1', text: 'Page 1' },
    { value: 'page2', text: 'Page 2' }
])

const fromOptions = ref([
    { value: 'al-reem-island', text: 'Al Reem island appartment' },
    { value: 'other', text: 'Other' }
])

const fieldMappings = ref([
    {
        id: 1,
        name: 'Work Phone Number - Facebook',
        mappedTo: null
    },
    {
        id: 2,
        name: 'Full Name - Facebook',
        mappedTo: null
    },
    {
        id: 3,
        name: 'Phone Number - Facebook',
        mappedTo: 'Phone - Contacted'
    },
    {
        id: 4,
        name: 'Work Phone Number - Facebook',
        mappedTo: 'Email - Lead'
    }
])

// Available fields for selection (matching the image)
const availableFields = ref([
    { id: 1, name: 'Lead Name' },
    { id: 2, name: 'Name' },
    { id: 3, name: 'Second Name' },
    { id: 4, name: 'Last Name' },
    { id: 5, name: 'Company Name' },
    { id: 6, name: 'Source Information' },
    { id: 7, name: 'More About This Stage' },
    { id: 8, name: 'Position' },
    { id: 9, name: 'Address' },
    { id: 10, name: 'Comment' },
    { id: 11, name: 'Work Phone' }
])

// Initialize selected fields based on current mapping or default selections
const initializeSelectedFields = (field) => {
    if (field && field.mappedTo) {
        // Parse the mapped field names and find their IDs
        const mappedNames = field.mappedTo.split(', ').map(name => name.trim())
        selectedFieldIds.value = availableFields.value
            .filter(f => mappedNames.includes(f.name))
            .map(f => f.id)
    } else {
        // Default selections as shown in the image
        // Lead Name, Second Name, Source Information, Address are checked
        selectedFieldIds.value = [1, 3, 6, 9]
    }
}

const openFieldSelector = (field) => {
    currentFieldMapping.value = field
    // Initialize with previously selected fields or default selections
    initializeSelectedFields(field)
    showSelectFieldsModal.value = true
}

const closeSelectFieldsModal = () => {
    showSelectFieldsModal.value = false
    currentFieldMapping.value = null
}

const toggleFieldSelection = (fieldId) => {
    const index = selectedFieldIds.value.indexOf(fieldId)
    if (index > -1) {
        selectedFieldIds.value.splice(index, 1)
    } else {
        selectedFieldIds.value.push(fieldId)
    }
}

const handleApplyFields = () => {
    // Get selected field names
    const selectedFields = availableFields.value
        .filter(field => selectedFieldIds.value.includes(field.id))
        .map(field => field.name)
    
    // Update the current field mapping
    if (currentFieldMapping.value) {
        const mapping = fieldMappings.value.find(f => f.id === currentFieldMapping.value.id)
        if (mapping) {
            // If multiple fields selected, join them; otherwise use single field name
            mapping.mappedTo = selectedFields.length > 0 
                ? selectedFields.join(', ') 
                : null
        }
    }
    
    closeSelectFieldsModal()
}

const handleCreateField = () => {
    // Handle create field logic
    console.log('Create field clicked')
    // You can open another modal or navigate to create field page
}
</script>

<style scoped>
.tab-content {
    padding: 0;
}

.main-section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #000000;
    margin: 0 0 24px 0;
    padding: 0;
}

/* Account Card */
.account-card {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.account-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-picture-wrapper {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background: #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.profile-picture-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #E2E8F0;
}

.profile-icon {
    font-size: 20px;
    color: #94A3B8;
}

.profile-picture {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.account-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.account-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #AAAAAA;
}

.account-name {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #000000;
}

.disconnect-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #EF4444;
    cursor: pointer;
    transition: all 0.2s;
}

.disconnect-btn:hover {
    background: #FEF2F2;
    border-color: #FECACA;
}

.disconnect-icon {
    font-size: 16px;
    color: #EF4444;
}

/* Page and From Card */
.page-from-card {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.field-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
}

/* Page Select - Matching From Select Style */
.page-select-wrapper {
    position: relative;
}

:deep(.page-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.page-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.page-select .vs__selected) {
    font-size: 13px;
    color: #000000;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px;
}

:deep(.page-select .vs__search) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
}

:deep(.page-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.page-select .vs__actions) {
    padding: 0 8px;
}

:deep(.page-select .vs__open-indicator-icon) {
    font-size: 16px;
    color: #94A3B8;
}

/* From Select with Solid Gray Border */
:deep(.from-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.from-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.from-select .vs__selected) {
    font-size: 13px;
    color: #000000;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px;
}

:deep(.from-select .vs__search) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
}

:deep(.from-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.from-select .vs__actions) {
    padding: 0 8px;
}

:deep(.from-select .vs__open-indicator) {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    height: 100%;
}

:deep(.from-select .from-indicator-wrapper) {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    line-height: 1;
}

:deep(.from-select .vs__open-indicator-icon) {
    font-size: 14px;
    color: #94A3B8;
    line-height: 1;
}

:deep(.from-select .up-icon) {
    margin-bottom: -4px;
}

:deep(.from-select .down-icon) {
    margin-top: -4px;
}

/* Common v-select dropdown styles */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-menu) {
    border: none;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    padding: 8px 0;
    margin-top: 4px;
    z-index: 1100;
    border-radius: 8px;
    overflow: hidden;
    background: #FFFFFF;
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 8px 12px;
    font-size: 13px;
    color: #000000;
    transition: all 0.2s;
    font-family: 'Montserrat', sans-serif;
    font-weight: 400;
    background: transparent;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #F8FAFC !important;
    color: #000000 !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: transparent;
    color: #000000;
}

/* Field Mapping Cards */
.field-mapping-card {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.field-mapping-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.field-name {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #000000;
    line-height: 1.4;
}

.mapping-status {
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    font-weight: 400;
    color: #94A3B8;
    padding-left: 4px;
    line-height: 1.4;
}

.mapping-status.not-selected {
    color: #EF4444;
}

.select-field-btn {
    padding: 8px 16px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.select-field-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

:deep(svg) {
    vertical-align: middle !important;
}

/* Select Fields Modal Styles */
:deep(.select-fields-modal) {
    z-index: 1055 !important;
}

:deep(.select-fields-modal.show .modal-backdrop) {
    background-color: rgba(0, 0, 0, 0.5) !important;
    z-index: 1050 !important;
    opacity: 1 !important;
}

:deep(.select-fields-modal .modal-backdrop.show) {
    background-color: rgba(0, 0, 0, 0.5) !important;
    opacity: 1 !important;
}

:deep(.select-fields-modal .modal-dialog) {
    max-width: 600px;
    margin: 1.75rem auto;
    z-index: 1055 !important;
    position: relative;
}

:deep(.select-fields-modal .modal-content) {
    border-radius: 16px;
    border: none;
    box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    position: relative;
    z-index: 1055 !important;
}

:deep(.select-fields-modal .modal-body) {
    padding: 0;
}

:deep(.select-fields-modal.show) {
    display: block !important;
}

:deep(.select-fields-modal.show .modal-dialog) {
    transform: none !important;
}

.select-fields-modal-content {
    background: #FFFFFF;
    display: flex;
    flex-direction: column;
    border-radius: 10px;
}

.select-fields-modal-content .modal-header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 32px;
    border-bottom: 1px solid #E2E8F0;
}

.select-fields-modal-content .modal-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
    margin: 0;
}

.select-fields-modal-content .close-btn {
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

.select-fields-modal-content .close-btn:hover {
    background: #F1F5F9;
    color: #01062C;
}

.select-fields-modal-content .close-icon {
    font-size: 20px;
}

.select-fields-modal-content .modal-body-section {
    padding: 24px 32px;
    max-height: 400px;
    overflow-y: auto;
}

/* Fields Grid - 3 columns */
.fields-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px 24px;
}

.field-option {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
    padding: 4px 0;
}

.field-option:hover {
    opacity: 0.8;
}

/* Checkbox styling matching CrmEntitiesTab.vue */
.field-checkbox {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    border-radius: 4px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    position: relative;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
    margin: 0;
    flex-shrink: 0;
}

.field-checkbox:checked {
    background-color: #FAA300;
    border-color: #FAA300;
}

.field-checkbox:checked::after {
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

.field-checkbox:hover {
    border-color: #FAA300;
}

.field-label-text {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 400;
    color: #1E293B;
    cursor: pointer;
    user-select: none;
    margin: 0;
    line-height: 1.5;
}

/* Modal Footer */
.select-fields-modal-content .modal-footer-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 32px;
    border-top: 1px solid #E2E8F0;
    background: #FFFFFF;
}

.create-field-link {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 400;
    color: #3B82F6;
    text-decoration: underline;
    cursor: pointer;
    transition: all 0.2s;
}

.create-field-link:hover {
    color: #2563EB;
}

.footer-buttons {
    display: flex;
    align-items: center;
    gap: 12px;
}

.select-fields-modal-content .footer-btn {
    padding: 10px 24px;
    border-radius: 25px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.select-fields-modal-content .footer-btn.cancel-btn {
    background: #F1F5F9;
    color: #64748B;
}

.select-fields-modal-content .footer-btn.cancel-btn:hover {
    background: #E2E8F0;
}

.select-fields-modal-content .footer-btn.apply-btn {
    background: #01062C;
    color: #FFFFFF;
}

.select-fields-modal-content .footer-btn.apply-btn:hover {
    background: #020A3D;
}

/* Scrollbar Styling */
.select-fields-modal-content .modal-body-section::-webkit-scrollbar {
    width: 8px;
}

.select-fields-modal-content .modal-body-section::-webkit-scrollbar-track {
    background: #F8FAFC;
    border-radius: 4px;
}

.select-fields-modal-content .modal-body-section::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}

.select-fields-modal-content .modal-body-section::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}
</style>
