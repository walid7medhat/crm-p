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
                    v-model="selectedPageObject"
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
                    v-model="selectedFormObject"
                    :options="forms"
                    :reduce="form => form"
                    label="name"
                    placeholder="Choose a form..."
                    class="custom-select"
                    :loading="loadingForms"
                    :disabled="loadingForms"
                >
                    <template #option="{ name, leads_count, id }">
                        <div class="select-option">
                            <span class="option-name">{{ name }}</span>
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

                <!-- Load More Button (لو في forms أكتر) -->
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
        </template>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, getCurrentInstance, computed } from 'vue'
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
const selectedPage = ref(null)
const selectedPageId = ref(props.pageId)
const selectedPageAccessToken = ref(null)
const forms = ref([])
const selectedForm = ref(null)
const selectedFormId = ref(props.formId)
const nextCursor = ref(null)
const hasNextPage = ref(false)

// Computed for page selection
const selectedPageObject = computed({
    get: () => pages.value.find(p => p.id === selectedPageId.value) || null,
    set: (page) => {
        if (page) {
            handlePageSelect(page)
        }
    }
})

// Computed for form selection
const selectedFormObject = computed({
    get: () => forms.value.find(f => f.id === selectedFormId.value) || null,
    set: (form) => {
        if (form) {
            handleFormSelect(form)
        }
    }
})

// Load pages on mount
onMounted(() => {
    loadPages()
})

// Load Facebook Pages from server
const loadPages = async () => {
    loading.value = true
    loadingPages.value = true
    error.value = null
    
    try {
        const response = await api.get('/integrations/meta/pages')
        pages.value = response.data.data.pages || []
        
        // If we have a pre-selected page, find its token and load forms
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
    selectedPageId.value = page.id
    selectedPageAccessToken.value = page.access_token
    selectedFormId.value = null
    selectedForm.value = null
    forms.value = []
    nextCursor.value = null
    hasNextPage.value = false
    
    emit('update:pageId', page.id)
    
    // Load forms for selected page
    loadForms(page.id, page.access_token)
}

// Handle form selection
const handleFormSelect = (form) => {
    selectedFormId.value = form.id
    selectedForm.value = form
    
    emit('update:formName', form.name)
    emit('update:formId', form.id)
    
    emit('connected', {
        pageId: selectedPageId.value,
        pageAccessToken: selectedPageAccessToken.value,
        formId: form.id,
        formName: form.name,
        fieldMappings: []
    })
    
    proxy?.$showNotification?.(`Form "${form.name}" selected`, 'success')
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

        // Show success notification when forms loaded
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

// Watch for props changes (for edit mode)
watch(() => props.pageId, (newVal) => {
    if (newVal && pages.value.length > 0) {
        const page = pages.value.find(p => p.id === newVal)
        if (page) {
            selectedPageId.value = newVal
            selectedPageAccessToken.value = page.access_token
        }
    }
})

watch(() => props.formId, (newVal) => {
    if (newVal && forms.value.length > 0) {
        const form = forms.value.find(f => f.id === newVal)
        if (form) {
            selectedFormId.value = newVal
            selectedForm.value = form
        }
    }
})
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

/* Loading Spinner */
:deep(.spinner-border) {
    width: 3rem;
    height: 3rem;
}
</style>