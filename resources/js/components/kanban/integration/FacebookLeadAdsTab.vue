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
            <!-- Pages List -->
            <div v-if="pages.length > 0" class="pages-card">
                <label class="field-label">Select a Facebook Page</label>
                <div class="pages-list">
                    <label
                        v-for="page in pages"
                        :key="page.id"
                        class="page-option"
                        :class="{ selected: selectedPageId === page.id }"
                    >
                        <input 
                            v-model="selectedPageId" 
                            type="radio" 
                            :value="page.id" 
                            class="page-radio"
                            @change="handlePageSelect(page)"
                        />
                        <div class="page-info">
                            <span class="page-name">{{ page.name }}</span>
                            <span class="page-id">ID: {{ page.id }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Forms List -->
            <div v-if="forms.length > 0 && selectedPageId" class="forms-card">
                <label class="field-label">Select a Form</label>
                <div class="forms-list">
                    <label
                        v-for="form in forms"
                        :key="form.id"
                        class="form-option"
                        :class="{ selected: selectedFormId === form.id }"
                    >
                        <input v-model="selectedFormId" type="radio" :value="form.id" class="form-radio" />
                        <div class="form-info">
                            <span class="form-name">{{ form.name }}</span>
                            <span class="form-meta">
                                <iconify-icon icon="lucide:users" class="meta-icon"></iconify-icon>
                                {{ form.leads_count || 0 }} leads
                            </span>
                        </div>
                    </label>
                </div>

                <!-- Load More Button -->
                <button 
                    v-if="hasNextPage" 
                    class="load-more-btn"
                    :disabled="loadingMore"
                    @click="loadMoreForms"
                >
                    <span v-if="loadingMore">Loading...</span>
                    <span v-else>Load More Forms</span>
                </button>
            </div>

            <!-- No Forms Message -->
            <div v-if="selectedPageId && forms.length === 0 && !loadingForms" class="empty-card">
                <iconify-icon icon="lucide:inbox" class="empty-icon"></iconify-icon>
                <p>No forms found for this page</p>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, getCurrentInstance } from 'vue'
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

// Get global properties for notifications
const { proxy } = getCurrentInstance()

// State
const loading = ref(true)
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

// Flag to prevent multiple emits
const isInternalUpdate = ref(false)

// Load pages on mount
onMounted(() => {
    loadPages()
})

// Load Facebook Pages from server
const loadPages = async () => {
    loading.value = true
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
    }
}

// Handle page selection
const handlePageSelect = (page) => {
    isInternalUpdate.value = true
    selectedPageId.value = page.id
    selectedPageAccessToken.value = page.access_token
    selectedFormId.value = null
    forms.value = []
    loadForms(page.id, page.access_token)
    isInternalUpdate.value = false
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

// Watch for form selection
watch(selectedFormId, (newVal) => {
    if (isInternalUpdate.value) return
    
    const form = forms.value.find(f => f.id === newVal)
    if (form) {
        emit('update:formName', form.name)
        emit('update:formId', newVal)
        
        // Emit connected event when form is selected
        emit('connected', {
            pageId: selectedPageId.value,
            pageAccessToken: selectedPageAccessToken.value,
            formId: newVal,
            formName: form.name,
            fieldMappings: []
        })
        
        proxy?.$showNotification?.(`Form "${form.name}" selected`, 'success')
    }
})

// Watch for page changes
watch(selectedPageId, (newVal) => {
    if (isInternalUpdate.value) return
    
    if (newVal) {
        const selectedPage = pages.value.find(p => p.id === newVal)
        if (selectedPage) {
            selectedPageAccessToken.value = selectedPage.access_token
        }
    }
    emit('update:pageId', newVal)
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

/* Cards */
.pages-card,
.forms-card,
.error-card,
.empty-card {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
}

/* Error Card */
.error-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 32px;
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

/* Empty Card */
.empty-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 40px;
}

.empty-icon {
    font-size: 48px;
    color: #94A3B8;
}

.empty-card p {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #64748B;
    margin: 0;
}

/* Field Label */
.field-label {
    display: block;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 15px;
}

/* Pages List */
.pages-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 250px;
    overflow-y: auto;
    margin-bottom: 15px;
}

.page-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.page-option:hover,
.page-option.selected {
    border-color: #FAA300;
    background: #FFFBF5;
}

.page-radio {
    accent-color: #FAA300;
    margin: 0;
}

.page-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.page-name {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #000;
}

.page-id {
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    color: #64748B;
}

/* Forms List */
.forms-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 250px;
    overflow-y: auto;
    margin-bottom: 15px;
}

.form-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.form-option:hover,
.form-option.selected {
    border-color: #FAA300;
    background: #FFFBF5;
}

.form-radio {
    accent-color: #FAA300;
    margin: 0;
}

.form-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.form-name {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #000;
}

.form-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    color: #64748B;
}

.meta-icon {
    font-size: 14px;
}

/* Load More Button */
.load-more-btn {
    width: 100%;
    padding: 10px;
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