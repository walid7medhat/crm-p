<template>
    <div class="tab-content">
        <p class="section-title">Facebook Lead Ads</p>
        
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-card">
            <iconify-icon icon="lucide:alert-circle" class="error-icon"></iconify-icon>
            <p class="error-message">{{ error }}</p>
            <button class="retry-btn" @click="loadPages">
                <iconify-icon icon="lucide:refresh-cw" class="retry-icon"></iconify-icon>
                Retry
            </button>
        </div>

        <template v-else>
            <!-- Pages Select -->
            <div class="select-card">
                <label class="field-label">Select a Facebook Page</label>
                <v-select
                    :model-value="selectedPageObject"
                    @update:model-value="handlePageSelect"
                    :options="pages"
                    :reduce="page => page"
                    label="name"
                    placeholder="Choose a page..."
                    class="custom-select"
                    :loading="loadingPages"
                >
                    <template #option="{ name, id, access_token }">
                        <div class="select-option">
                            <span class="option-name">{{ name }}</span>
                            <span class="option-id">ID: {{ id }}</span>
                        </div>
                    </template>
                    <template #no-options>
                        <div class="no-options">No pages found</div>
                    </template>
                </v-select>
            </div>

            <!-- Forms Select (ظهر بس لو اختار page) -->
            <div v-if="selectedPageId" class="select-card">
                <label class="field-label">Select a Form</label>
                <v-select
                    :model-value="selectedFormObject"
                    @update:model-value="handleFormSelect"
                    :options="forms"
                    :reduce="form => form"
                    label="name"
                    placeholder="Choose a form..."
                    class="custom-select"
                    :loading="loadingForms"
                    :disabled="loadingForms"
                >
                    <template #option="{ name, status, leads_count, id }">
                      <div class="select-option">
                        <span class="option-name">
                          {{ name }}
                          <span 
                            class="status-indicator" 
                            :class="status.toLowerCase()"
                            :title="status"
                          ></span>
                        </span>
                        <span class="option-meta">
                          <iconify-icon icon="lucide:users" class="meta-icon"></iconify-icon>
                          {{ leads_count || 0 }} leads
                        </span>
                      </div>
                    </template>
                    <template #no-options>
                        <div v-if="loadingForms" class="no-options">Loading forms...</div>
                        <div v-else class="no-options">No forms found for this page</div>
                    </template>
                </v-select>

                <!-- Load More Button -->
                <button 
                    v-if="hasNextPage && forms.length > 0" 
                    class="load-more-btn"
                    :disabled="loadingMore"
                    @click="loadMoreForms"
                >
                    <span v-if="loadingMore">Loading...</span>
                    <span v-else>Load More Forms</span>
                </button>
            </div>

            <!-- Field Mappings Section -->
            <div v-if="selectedFormId" class="mappings-card">
                <div class="mappings-header">
                    <!--<h6 class="ui-h-mini mappings-title">Field Mapping</h6>-->
                    <label class="field-label">Field Mapping</label>
                    <p class="mappings-subtitle">Map Facebook form fields to CRM fields</p>
                </div>

                <div class="mappings-list">
                    <div 
                        v-for="(mapping, index) in localMappings" 
                        :key="index"
                        class="mapping-row"
                    >
                        <div class="mapping-field">
                           <v-select
                                v-model="mapping.meta_field"
                                :options="metaFields"
                                label="name"                    
                                :reduce="field => field"        
                                placeholder="Select Meta Field"
                                class="meta-field-select"
                            >
                                <template #option="{ name, label }">
                                    <div class="meta-option">
                                        <span class="meta-name">{{ name }}</span>
                                        <span v-if="label && label !== name" class="meta-label">{{ label }}</span>
                                    </div>
                                </template>
                            </v-select>
                            <iconify-icon icon="lucide:arrow-right" class="arrow-icon"></iconify-icon>
                            <v-select
                                v-model="mapping.crm_field"
                                :options="crmFields"
                                :reduce="field => field.value"
                                label="label"
                                placeholder="Select CRM field"
                                class="crm-field-select"
                            />
                        </div>
                        <button 
                            class="remove-mapping-btn"
                            @click="removeMapping(index)"
                            v-if="localMappings.length > 1"
                        >
                            <iconify-icon icon="lucide:x"></iconify-icon>
                        </button>
                    </div>
                </div>

                <button class="add-mapping-btn" @click="addMapping">
                    <iconify-icon icon="lucide:plus"></iconify-icon>
                    Add Field Mapping
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, getCurrentInstance, computed, nextTick } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

const props = defineProps({
    pageId: {
        type: String,
        default: null
    },
    formId: {
        type: String,
        default: null
    },
    formName: {
        type: String,
        default: null
    },
    fieldMappings: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:pageId', 'update:formId', 'update:formName', 'update:fieldMappings', 'connected'])
const { proxy } = getCurrentInstance()

// State
const loading = ref(true)
const loadingPages = ref(false)
const loadingForms = ref(false)
const loadingMore = ref(false)
const error = ref(null)

const pages = ref([])
const selectedPageId = ref(props.pageId)
const selectedPageAccessToken = ref(null)
const forms = ref([])
const selectedFormId = ref(props.formId)
const nextCursor = ref(null)
const hasNextPage = ref(false)

// Field mappings
const localMappings = ref([])
const crmFields = [
    { value: 'first_name', label: 'First Name' },
    { value: 'last_name', label: 'Last Name' },
    { value: 'email', label: 'Primary Email' },
    { value: 'secondary_email', label: 'Secondary Email' },
    { value: 'work_phone', label: 'Primary Phone' },
    { value: 'work_phone_2', label: 'Secondary Phone' },
    { value: 'date_of_birth', label: 'Date Of Birth' },
    { value: 'whatsapp_number', label: 'Whatsapp Number' },
    { value: 'lead_name', label: 'Lead Name' },
    { value: 'source_information', label: 'Notes' }
]

// Meta fields (مؤقتة)
// const metaFields = ref([
//     'full_name',
//     'first_name',
//     'last_name',
//     'email',
//     'phone_number',
//     'mobile',
//     'address',
//     'city',
//     'country',
//     'company',
//     'position',
//     'website'
// ])
const metaFields = ref([])
const loadMetaFields = async (formId) => {
    const res = await api.get(`/integrations/meta/form-fields/${formId}`)
    metaFields.value = res.data.fields || []
}

// Computed for page selection (readonly)
const selectedPageObject = computed(() => 
    pages.value.find(p => p.id === selectedPageId.value) || null
)

// Computed for form selection (readonly)
const selectedFormObject = computed(() => 
    forms.value.find(f => f.id === selectedFormId.value) || null
    
)

// Flag to prevent update loops
const isInternalUpdate = ref(false)


onMounted(async () => {
    await loadPages()
    
    // إذا كان في تعديل (formId موجود) حمل الأسئلة أولاً
    if (props.formId) {
        console.log('Edit mode: loading meta fields for form', props.formId)
        await loadMetaFields(props.formId)
        
        // بعد تحميل metaFields، قم بتعيين localMappings
        if (props.fieldMappings && props.fieldMappings.length > 0) {
            localMappings.value = props.fieldMappings.map(m => {
                // ابحث عن الـ object الكامل في metaFields
                const metaFieldObj = metaFields.value.find(f => f.name === m.meta_field)
                return {
                    meta_field: m.meta_field || null, // استخدم null إذا لم يوجد
                    crm_field: m.crm_field
                }
            })
            console.log('Local mappings set:', localMappings.value) // للتصحيح
        } else {
            localMappings.value = [
                { meta_field: metaFields.value.length > 0 ? metaFields.value[0] : null, crm_field: '' }
            ]
        }
    } else {
        // إذا كان في وضع الإضافة (formId غير موجود)
        localMappings.value = [
            { meta_field: null, crm_field: '' }
        ]
    }
})

// Load Facebook Pages from server
const loadPages = async () => {
    loading.value = true
    loadingPages.value = true
    error.value = null
    
    try {
        const response = await api.get('/integrations/meta/pages')
        pages.value = response.data.data.pages || []
        
        if (selectedPageId.value) {
            const selectedPage = pages.value.find(p => p.id === selectedPageId.value)
            if (selectedPage) {
                selectedPageAccessToken.value = selectedPage.access_token
                await loadForms(selectedPageId.value, selectedPage.access_token)
            }
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load Facebook pages'
        proxy?.$showNotification?.(error.value, 'error')
    } finally {
        loading.value = false
        loadingPages.value = false
    }
}

// Handle page selection
const handlePageSelect = (page) => {
    if (!page) return
    
    isInternalUpdate.value = true
    
    selectedPageId.value = page.id
    selectedPageAccessToken.value = page.access_token
    selectedFormId.value = null
    forms.value = []
    nextCursor.value = null
    hasNextPage.value = false
    if (!props.formId) {
        localMappings.value = []
    }
    
    emit('update:pageId', page.id)
    emit('update:fieldMappings', [])
    
    loadForms(page.id, page.access_token)
    
    nextTick(() => {
        isInternalUpdate.value = false
    })
}

// Handle form selection
const handleFormSelect = async (form) => {
    if (!form) return
    
    isInternalUpdate.value = true
    
    selectedFormId.value = form.id
    emit('update:formName', form.name)
    emit('update:formId', form.id)
    
        // Load meta fields
    await loadMetaFields(form.id)

    // Assign localMappings ensuring meta_field exists in metaFields
   localMappings.value = props.fieldMappings.length > 0
    ? props.fieldMappings.map(m => {
        const field = metaFields.value.find(f => f.name === m.meta_field) || metaFields.value[0];
        return {
            meta_field: field, // object كامل
            crm_field: m.crm_field
        }
    })
    : [
        { meta_field: metaFields.value[0] || null, crm_field: '' }
    ]
    
    emit('update:fieldMappings', localMappings.value)
    
    emit('connected', {
        pageId: selectedPageId.value,
        pageAccessToken: selectedPageAccessToken.value,
        formId: form.id,
        formName: form.name,
        fieldMappings: localMappings.value
    })
    
    proxy?.$showNotification?.(`Form "${form.name}" selected`, 'success')
    
    nextTick(() => {
        isInternalUpdate.value = false
    })
}

// Load forms for selected page
const loadForms = async (pageId, pageAccessToken, cursor = null) => {
    if (!pageId || !pageAccessToken) return
    
    loadingForms.value = true
    error.value = null
    
    try {
        const params = { 
            page_id: pageId,
            page_access_token: pageAccessToken
        }
        if (cursor) {
            params.cursor = cursor
        }
        
        const response = await api.get('/integrations/meta/forms', { params })
        const data = response.data.data
        
        if (cursor) {
            forms.value = [...forms.value, ...(data.forms || [])]
        } else {
            forms.value = data.forms || []
        }
        
        nextCursor.value = data.next_cursor || null
        hasNextPage.value = data.has_next || false

        if (!cursor && data.forms?.length > 0) {
            proxy?.$showNotification?.(`Found ${data.forms.length} forms`, 'success')
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load forms'
        proxy?.$showNotification?.(error.value, 'error')
    } finally {
        loadingForms.value = false
    }
}

// Load more forms
const loadMoreForms = async () => {
    if (!selectedPageId.value || !selectedPageAccessToken.value || !nextCursor.value) return
    
    loadingMore.value = true
    await loadForms(selectedPageId.value, selectedPageAccessToken.value, nextCursor.value)
    loadingMore.value = false
}

// Field mapping methods
const addMapping = () => {
    localMappings.value.push({
        meta_field: metaFields.value.length > 0 ? metaFields.value[0] : null,
        crm_field: ''
    })
    emit('update:fieldMappings', localMappings.value)
}

const removeMapping = (index) => {
    localMappings.value.splice(index, 1)
    emit('update:fieldMappings', localMappings.value)
}

// Watch for props changes (for edit mode)
watch(() => props.pageId, (newVal) => {
    if (isInternalUpdate.value) return
    if (newVal && pages.value.length > 0) {
        const page = pages.value.find(p => p.id === newVal)
        if (page) {
            selectedPageId.value = newVal
            selectedPageAccessToken.value = page.access_token
        }
    }
})

watch(() => props.formId, async (newVal) => {
    if (isInternalUpdate.value) return
    if (newVal && forms.value.length > 0) {
        const form = forms.value.find(f => f.id === newVal)
        if (form) {
            selectedFormId.value = newVal
        }
            await loadMetaFields(form.id)
             await nextTick()
        if (props.fieldMappings.length > 0) {
                localMappings.value = props.fieldMappings.map(m => ({
                    meta_field: m.meta_field,
                    crm_field: m.crm_field
                }))
            }
    }
})

watch(localMappings, (newVal) => {
    if (isInternalUpdate.value) return
    emit('update:fieldMappings', newVal)
}, { deep: true })
</script>

<style scoped>
.tab-content {
    padding: 0;
}

.section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
    margin: 0 0 24px 0;
}

/* Select Cards */
.select-card {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
}

.field-label {
    display: block;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 12px;
}

/* Custom Select Styles */
:deep(.custom-select) {
    font-family: 'Montserrat', sans-serif;
}

:deep(.custom-select .vs__dropdown-toggle) {
    height: 44px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 12px;
}

:deep(.custom-select .vs__selected) {
    font-size: 14px;
    color: #1E293B;
    margin: 0;
    padding: 0;
    line-height: 42px;
}

:deep(.custom-select .vs__search) {
    font-size: 14px;
    color: #94A3B8;
    margin: 0;
    padding: 0;
}

:deep(.custom-select .vs__dropdown-menu) {
    border: none;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    border-radius: 8px;
    padding: 8px;
    margin-top: 4px;
    max-height: 300px;
}

:deep(.custom-select .vs__dropdown-option) {
    padding: 10px 12px;
    font-size: 13px;
    color: #1E293B;
    border-radius: 6px;
    transition: all 0.2s;
}

:deep(.custom-select .vs__dropdown-option--highlight) {
    background: #F8FAFC !important;
    color: #1E293B !important;
}

:deep(.custom-select .vs__open-indicator) {
    fill: #94A3B8;
}

:deep(.custom-select .vs__clear) {
    fill: #94A3B8;
}

/* Option Styles */
.select-option {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
}

.option-name {
    font-weight: 600;
    color: #000;
}

.option-id,
.option-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #64748B;
}

.meta-icon {
    font-size: 12px;
}

/* No Options */
.no-options {
    padding: 20px;
    text-align: center;
    color: #94A3B8;
    font-size: 13px;
}

/* Error Card */
.error-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 32px;
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error-icon {
    font-size: 48px;
    color: #EF4444;
}

.error-message {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #64748B;
    text-align: center;
    margin: 0;
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

/* Load More Button */
.load-more-btn {
    width: 100%;
    padding: 10px;
    margin-top: 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.load-more-btn:hover:not(:disabled) {
    background: #F1F5F9;
    border-color: #CBD5E1;
}

.load-more-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Mappings Card */
.mappings-card {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
}

.mappings-header {
    margin-bottom: 20px;
}

.mappings-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
    margin: 0 0 4px 0;
}

.mappings-subtitle {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #64748B;
    margin: 0;
}

.mappings-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
    max-height: 300px;
    overflow-y: auto;
    padding-right: 8px;
}

.mapping-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #F8FAFC;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
}

.mapping-field {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
}

.meta-field {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #1E293B;
    min-width: 150px;
    padding: 8px 12px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
}

.arrow-icon {
    font-size: 18px;
    color: #94A3B8;
}

:deep(.crm-field-select) {
    flex: 1;
    min-width: 200px;
}

:deep(.crm-field-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
    background: #fff;
}

.remove-mapping-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #FEE2E2;
    border: none;
    border-radius: 6px;
    color: #EF4444;
    cursor: pointer;
    transition: all 0.2s;
}

.remove-mapping-btn:hover {
    background: #FECACA;
    color: #DC2626;
}

.remove-mapping-btn iconify-icon {
    font-size: 16px;
}

.add-mapping-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    background: #F8FAFC;
    border: 1px dashed #CBD5E1;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.add-mapping-btn:hover {
    background: #F1F5F9;
    border-color: #94A3B8;
    color: #475569;
}

.add-mapping-btn iconify-icon {
    font-size: 18px;
}

/* Scrollbar for mappings list */
.mappings-list::-webkit-scrollbar {
    width: 6px;
}

.mappings-list::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 3px;
}

.mappings-list::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 3px;
}

.mappings-list::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

/* Loading Spinner */
:deep(.spinner-border) {
    width: 3rem;
    height: 3rem;
}
:deep(.vs__dropdown-toggle),
:deep(.vs__selected),
:deep(.vs__search),
:deep(.vs__dropdown-option),
:deep(.vs__placeholder) {
    font-size: 14px !important;
    font-weight: 500;
}
.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-left: 6px;
    vertical-align: middle;
}

.status-indicator.active {
    background-color: #16A34A;
}

.status-indicator.inactive {
    background-color: #EF4444; 
}

.status-indicator.closed {
    background-color: #F59E0B; 
}

/* Custom v-select styles matching CreateLeadModal.vue */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.custom-v-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select .vs__selected) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px; 
}

:deep(.custom-v-select .vs__search) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
}

:deep(.custom-v-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.meta-ads-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.custom-v-select .vs__actions) {
    padding: 0 8px;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
}

:deep(svg) {
    vertical-align: middle !important;
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
    
    /* Add these properties for scrolling */
    max-height: 300px !important; /* Fixed max height */
    overflow-y: auto !important; /* Enable vertical scrolling */
    overflow-x: hidden; /* Hide horizontal scroll */
}

:deep(.meta-ads-select .vs__dropdown-menu) {
    border: none;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    padding: 4px 0;
    margin-top: 4px;
    z-index: 1100;
    border-radius: 8px;
    overflow: hidden;
    background: #FFFFFF;
    
    /* Add these properties for scrolling */
    max-height: 250px !important; /* Fixed max height */
    overflow-y: auto !important; /* Enable vertical scrolling */
    overflow-x: hidden; /* Hide horizontal scroll */
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

</style>