<template>
    <div class="kanban-settings-container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-1">Kanban Card Settings</h4>
                <p class="text-secondary-light mb-0">Control which fields appear in lead cards</p>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2">Loading settings...</p>
        </div>

        <div v-else-if="error" class="text-center py-5">
            <iconify-icon icon="lucide:alert-circle" class="text-danger mb-2" style="font-size: 48px;" />
            <p class="text-danger">{{ error }}</p>
            <button class="btn btn-outline-primary mt-2" @click="fetchSettings">Try Again</button>
        </div>

        <div v-else class="settings-content">
            <!-- Revert Hours Section -->
            <div class="settings-section mb-4">
                <h5 class="section-title">Revert Settings</h5>
                <p class="section-desc">Control after how many hours a lead reverts to stage 1</p>
                
                <div class="revert-settings-card">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Revert Hours</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input 
                                        type="number" 
                                        v-model="revertHours" 
                                        class="form-control revert-input"
                                        min="1"
                                        max="720"
                                    />
                                    <span class="text-muted">hours</span>
                                </div>
                                <small class="text-muted">Lead will revert after {{ revertHours }} hours</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Fields Section -->
            <div class="settings-section">
                <h5 class="section-title">Card Fields</h5>
                <p class="section-desc">Select which fields appear in the lead card and arrange their order</p>
                
                <div class="fields-container">
                    <!-- Show all fields in one list for drag & drop -->
                    <div class="fields-sortable">
                        <div class="fields-sortable-header">
                            <span class="header-field">Field</span>
                            <span class="header-status">Status</span>
                            <span class="header-drag">Drag</span>
                        </div>
                        
                        <draggable 
                            v-model="cardFields" 
                            group="fields" 
                            item-key="key"
                            handle=".drag-handle"
                            class="fields-list"
                            @end="onDragEnd"
                        >
                            <template #item="{ element: field, index }">
                                <div class="field-sortable-item" :class="{ 'field-disabled': !field.enabled }">
                                    <div class="field-info">
                                        <span class="field-label">{{ field.label }}</span>
                                        <span class="field-key">{{ field.key }}</span>
                                    </div>
                                    <div class="field-status">
                                        <div class="form-check form-switch">
                                            <input 
                                                class="form-check-input" 
                                                type="checkbox" 
                                                v-model="field.enabled"
                                                :id="`field_${field.key}`"
                                                @change="updateFieldOrder"
                                            >
                                        </div>
                                    </div>
                                    <div class="field-drag">
                                        <iconify-icon icon="lucide:grip-vertical" class="drag-handle"></iconify-icon>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="preview-section mt-4">
                        <h6 class="preview-title">Preview Order</h6>
                        <div class="preview-list">
                            <div 
                                v-for="field in enabledFields" 
                                :key="field.key"
                                class="preview-item"
                            >
                                <span class="preview-order">{{ field.order }}</span>
                                <span class="preview-label">{{ field.label }}</span>
                            </div>
                            <div v-if="enabledFields.length === 0" class="preview-empty">
                                No fields selected
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button 
                    class="btn btn-primary"
                    @click="saveAllSettings"
                    :disabled="saving"
                >
                    <iconify-icon icon="lucide:save" class="me-2" />
                    {{ saving ? 'Saving...' : 'Save All Changes' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import draggable from 'vuedraggable'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const loading = ref(true)
const saving = ref(false)
const error = ref(null)

const cardFields = ref([])
const revertHours = ref(24)
const allFields = ref([])

// Get enabled fields sorted by order
const enabledFields = computed(() => {
    return cardFields.value
        .filter(field => field.enabled)
        .sort((a, b) => a.order - b.order)
})

const formatGroupName = (group) => {
    return group.charAt(0).toUpperCase() + group.slice(1)
}

const fetchSettings = async () => {
    loading.value = true
    error.value = null
    
    try {
        const response = await api.get('/settings/kanban')
        const data = response.data.data
        
        cardFields.value = data.card_fields || []
        revertHours.value = data.revert_hours || 24
        allFields.value = data.all_fields || []
        
        // Update order numbers if not set
        cardFields.value = cardFields.value.map((field, index) => ({
            ...field,
            order: field.order || index + 1
        }))
        
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load settings'
    } finally {
        loading.value = false
    }
}

const updateFieldOrder = () => {
    // Update order numbers based on current array position
    cardFields.value = cardFields.value.map((field, index) => ({
        ...field,
        order: index + 1
    }))
}

const onDragEnd = () => {
    updateFieldOrder()
}

const saveAllSettings = async () => {
    saving.value = true
    
    try {
        // Update order numbers before saving
        updateFieldOrder()
        
        // Save card fields
        await api.post('/settings/kanban/card-fields', {
            fields: cardFields.value
        })
        
        // Save revert hours
        await api.post('/settings/kanban/revert-hours', {
            hours: revertHours.value
        })
        
        Swal.fire({
            icon: 'success',
            title: 'Saved!',
            text: 'Settings updated successfully',
            timer: 2000,
            showConfirmButton: false
        })
        
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.response?.data?.message || 'Failed to save settings'
        })
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    fetchSettings()
})
</script>

<style scoped>
.kanban-settings-container {
    padding: 24px;
    background-color: #f8fafc;
    min-height: 100vh;
}

.settings-content {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.settings-section {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.section-desc {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 20px;
}

.revert-settings-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.revert-input {
    width: 120px !important;
    text-align: center;
}

/* Sortable Fields Styles */
.fields-sortable {
    background: white;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.fields-sortable-header {
    display: grid;
    grid-template-columns: 1fr 100px 50px;
    padding: 12px 16px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 600;
    font-size: 13px;
    color: #475569;
}

.fields-list {
    max-height: 500px;
    overflow-y: auto;
}

.field-sortable-item {
    display: grid;
    grid-template-columns: 1fr 100px 50px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    align-items: center;
    background: white;
    transition: background 0.2s;
}

.field-sortable-item:hover {
    background: #f8fafc;
}

.field-sortable-item.field-disabled {
    opacity: 0.7;
    background: #f1f5f9;
}

.field-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.field-label {
    font-weight: 600;
    font-size: 14px;
    color: #1e293b;
}

.field-key {
    font-size: 12px;
    color: #64748b;
    font-family: monospace;
}

.field-status {
    display: flex;
    justify-content: center;
}

.field-drag {
    display: flex;
    justify-content: center;
    cursor: grab;
}

.drag-handle {
    font-size: 20px;
    color: #94a3b8;
    transition: color 0.2s;
}

.drag-handle:hover {
    color: #faa300;
}

.drag-handle:active {
    cursor: grabbing;
}

/* Preview Section */
.preview-section {
    background: white;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    padding: 16px;
}

.preview-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12px;
}

.preview-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.preview-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.preview-order {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #faa300;
    color: white;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.preview-label {
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
}

.preview-empty {
    text-align: center;
    padding: 20px;
    color: #94a3b8;
    font-size: 14px;
    background: #f8fafc;
    border-radius: 6px;
}

.form-check-input:checked {
    background-color: #faa300;
    border-color: #faa300;
}

@media (max-width: 768px) {
    .fields-sortable-header {
        grid-template-columns: 1fr 80px 40px;
        font-size: 12px;
    }
    
    .field-sortable-item {
        grid-template-columns: 1fr 80px 40px;
        padding: 10px 12px;
    }
    
    .field-label {
        font-size: 13px;
    }
    
    .field-key {
        font-size: 10px;
    }
}
</style>