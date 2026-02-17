<template>
    <div class="tab-content">
        <p class="main-section-title">Facebook Lead Ads</p>
        
        <!-- Connect with token: credentials + fetch forms -->
        <div class="page-from-card">
            <div class="field-group">
                <label class="field-label">Meta App ID</label>
                <input v-model="metaAppId" type="text" class="form-input" placeholder="e.g. 89238010" />
            </div>
            <div class="field-group">
                <label class="field-label">Meta App Secret</label>
                <input v-model="metaAppSecret" type="password" class="form-input" placeholder="Optional for connect" />
            </div>
            <div class="field-group">
                <label class="field-label">Access Token</label>
                <input v-model="accessToken" type="password" class="form-input" placeholder="Paste your Meta access token" />
            </div>
            <div class="field-group">
                <label class="field-label">Meta Account / Page ID</label>
                <input v-model="metaAccountId" type="text" class="form-input" placeholder="Pick a page below or paste Page ID (must be a Page, not your User ID)" />
                <p class="field-hint">Lead forms exist only on <strong>Facebook Pages</strong>, not on personal profiles. Use "Load my Pages" to pick the correct Page.</p>
            </div>
            <div class="field-group">
                <button
                    type="button"
                    class="fetch-forms-btn secondary"
                    :disabled="fetchingPages || !accessToken"
                    @click="() => fetchPages()"
                >
                    <span v-if="fetchingPages">Loading…</span>
                    <span v-else>Load my Pages</span>
                </button>
                <p v-if="fetchPagesError" class="error-text">{{ fetchPagesError }}</p>
            </div>
            <div v-if="metaPages.length > 0" class="field-group">
                <label class="field-label">Select a Page (then click Fetch forms)</label>
                <div class="pages-list">
                    <label
                        v-for="page in metaPages"
                        :key="page.id"
                        class="form-option"
                        :class="{ selected: metaAccountId === page.id }"
                    >
                        <input v-model="metaAccountId" type="radio" :value="page.id" class="form-radio" />
                        <span class="form-option-name">{{ page.name }}</span>
                        <span class="form-option-meta">ID: {{ page.id }}</span>
                    </label>
                </div>
            </div>
            <div class="field-group">
                <button
                    class="fetch-forms-btn"
                    :disabled="fetchingForms || !accessToken || !metaAccountId"
                    @click="() => fetchForms()"
                >
                    <span v-if="fetchingForms">Loading…</span>
                    <span v-else>Fetch forms</span>
                </button>
                <p v-if="fetchFormsError" class="error-text">{{ fetchFormsError }}</p>
            </div>
        </div>

        <!-- Forms list: select one then Connect -->
        <div v-if="metaForms.length > 0" class="page-from-card">
            <label class="field-label">Select a form to connect</label>
            <div class="forms-list">
                <label
                    v-for="form in metaForms"
                    :key="form.id"
                    class="form-option"
                    :class="{ selected: selectedFormId === form.id }"
                >
                    <input v-model="selectedFormId" type="radio" :value="form.id" class="form-radio" />
                    <span class="form-option-name">{{ form.name }}</span>
                    <span class="form-option-meta">ID: {{ form.id }} · Leads: {{ form.leads_count ?? 0 }}</span>
                </label>
            </div>
            <div class="field-group connect-row">
                <button
                    class="connect-btn"
                    :disabled="connecting || !selectedFormId"
                    @click="connectForm"
                >
                    <span v-if="connecting">Connecting…</span>
                    <span v-else>Connect</span>
                </button>
                <p v-if="connectError" class="error-text">{{ connectError }}</p>
                <p v-if="connectSuccess" class="success-text">{{ connectSuccess }}</p>
            </div>
            <!-- Pagination: load more forms -->
            <div v-if="nextCursor" class="field-group">
                <button class="fetch-forms-btn secondary" :disabled="fetchingForms" @click="fetchForms(nextCursor)">
                    Load more forms
                </button>
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
import api from '@/plugins/axios'

const emit = defineEmits(['connected'])

const metaAppId = ref('')
const metaAppSecret = ref('')
const accessToken = ref('')
const metaAccountId = ref('')
const metaPages = ref([])
const fetchingPages = ref(false)
const fetchPagesError = ref('')
const metaForms = ref([])
const selectedFormId = ref(null)
const nextCursor = ref(null)
const fetchingForms = ref(false)
const fetchFormsError = ref('')
const connecting = ref(false)
const connectError = ref('')
const connectSuccess = ref('')

async function fetchPages() {
    fetchPagesError.value = ''
    if (!accessToken.value) return
    fetchingPages.value = true
    try {
        const { data } = await api.post('/integrations/meta/pages', {
            access_token: accessToken.value,
        })
        if (data?.data?.pages) {
            metaPages.value = data.data.pages
        } else {
            metaPages.value = []
        }
    } catch (err) {
        const d = err.response?.data
        fetchPagesError.value = d?.message || err.message || 'Failed to load pages'
    } finally {
        fetchingPages.value = false
    }
}

async function fetchForms(cursor = null) {
    fetchFormsError.value = ''
    if (!accessToken.value || !metaAccountId.value) return
    fetchingForms.value = true
    try {
        const body = {
            access_token: accessToken.value,
            meta_account_id: metaAccountId.value,
        }
        if (cursor && typeof cursor === 'string') {
            body.cursor = cursor
        }
        const { data } = await api.post('/integrations/meta/forms', body)
        if (data?.data) {
            const forms = data.data.forms || []
            const newCursor = data.data.next_cursor
            if (cursor) {
                metaForms.value = [...metaForms.value, ...forms]
            } else {
                metaForms.value = forms
            }
            nextCursor.value = newCursor || null
        }
    } catch (err) {
        const d = err.response?.data
        if (d?.message) {
            fetchFormsError.value = d.message
        } else if (d?.errors && typeof d.errors === 'object') {
            const first = Object.values(d.errors).flat()
            fetchFormsError.value = first.length ? first[0] : 'Validation failed'
        } else {
            fetchFormsError.value = err.message || 'Failed to fetch forms'
        }
    } finally {
        fetchingForms.value = false
    }
}

async function connectForm() {
    if (!selectedFormId.value || !accessToken.value || !metaAccountId.value) return
    const form = metaForms.value.find(f => f.id === selectedFormId.value)
    if (!form) return
    connectError.value = ''
    connectSuccess.value = ''
    connecting.value = true
    try {
        await api.post('/integrations', {
            form_id: form.id,
            form_name: form.name,
            meta_account_id: metaAccountId.value,
            access_token: accessToken.value,
            meta_app_id: metaAppId.value || undefined,
        })
        connectSuccess.value = 'Form connected successfully.'
        emit('connected')
    } catch (err) {
        connectError.value = err.response?.data?.message || err.message || 'Failed to connect'
    } finally {
        connecting.value = false
    }
}

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

.form-input {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #000;
}

.form-input::placeholder {
    color: #94A3B8;
}

.field-hint {
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    color: #64748B;
    margin: 6px 0 0;
}

.fetch-forms-btn {
    padding: 10px 24px;
    background: #01062C;
    color: #fff;
    border: none;
    border-radius: 25px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.fetch-forms-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.fetch-forms-btn.secondary {
    background: #F1F5F9;
    color: #64748B;
}

.connect-btn {
    padding: 10px 24px;
    background: #FAA300;
    color: #01062C;
    border: none;
    border-radius: 25px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.connect-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.connect-row {
    margin-top: 16px;
}

.error-text {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #EF4444;
    margin: 8px 0 0;
}

.success-text {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #22C55E;
    margin: 8px 0 0;
}

.pages-list,
.forms-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 280px;
    overflow-y: auto;
    margin-top: 8px;
}

.form-option {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
}

.form-option:hover,
.form-option.selected {
    border-color: #FAA300;
    background: #FFFBF5;
}

.form-radio {
    margin: 0;
    accent-color: #FAA300;
}

.form-option-name {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #000;
}

.form-option-meta {
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    color: #64748B;
    width: 100%;
    margin-left: 28px;
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
