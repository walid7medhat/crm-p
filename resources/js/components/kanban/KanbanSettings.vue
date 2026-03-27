<template>
    <div class="kanban-settings-container">
        <div class="page-header">
            <div>
                <h6 class="page-title">Kanban Settings</h6>
                <p class="page-subtitle">Build the exact layout of your lead card and preview it live.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="resetToSaved">
                    <iconify-icon icon="lucide:rotate-ccw" class="me-1" />
                    Reset
                </button>
                <button class="btn btn-primary btn-sm" @click="saveAllSettings" :disabled="saving || loading">
                    <iconify-icon icon="lucide:save" class="me-1" />
                    {{ saving ? 'Saving…' : 'Save changes' }}
                </button>
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

        <div v-else>
            <!-- Card Fields builder + Live preview -->
            <div class="builder-card">
                <div class="builder-header">
                    <div>
                        <div class="builder-title">Card Fields</div>
                        <div class="builder-desc">Click to enable/disable fields, then drag to reorder. Preview updates instantly.</div>
                    </div>
                    <div class="builder-actions">
                        <button class="btn btn-outline-secondary btn-sm" @click="selectAll(true)" :disabled="cardFields.length === 0">Select all</button>
                        <button class="btn btn-outline-secondary btn-sm" @click="selectAll(false)" :disabled="cardFields.length === 0">Deselect all</button>
                    </div>
                </div>

                <div class="builder-body">
                    <div class="card-behavior-panel mb-3">
                        <div class="card-behavior-head">
                            <div class="card-behavior-title">Stage card behavior</div>
                            <div class="card-behavior-desc">Control how much data appears by stage position.</div>
                        </div>
                        <div class="card-behavior-grid">
                            <label class="behavior-toggle">
                                <input v-model="cardBehavior.showMoreFromQualified" type="checkbox" />
                                <span>Show more data from Qualified stage</span>
                            </label>
                            <label class="behavior-input-row">
                                <span>Qualified starts at stage order</span>
                                <input
                                    v-model.number="cardBehavior.qualifiedStartOrder"
                                    type="number"
                                    min="1"
                                    class="form-control form-control-sm behavior-number"
                                    :disabled="!cardBehavior.showMoreFromQualified"
                                />
                            </label>
                            <div v-if="cardBehavior.showMoreFromQualified" class="qualified-fields-picker">
                                <div class="qualified-fields-head">
                                    <span>After Qualified: choose fields to show on card</span>
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="selectAllQualifiedFields"
                                        :disabled="allFields.length === 0"
                                    >
                                        Select all
                                    </button>
                                </div>
                                <div class="qualified-fields-list">
                                    <button
                                        v-for="field in allFields"
                                        :key="`qualified_${field.key}`"
                                        type="button"
                                        class="qualified-field-chip"
                                        :class="{ active: isQualifiedFieldSelected(field.key) }"
                                        @click="toggleQualifiedField(field.key)"
                                    >
                                        {{ field.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="builder-layout">
                        <!-- Fields list -->
                        <div class="fields-panel">
                            <div class="fields-header">
                                <div class="fields-header-left">
                                    <div class="fields-title">Card fields</div>
                                    <div class="fields-subtitle">Add, remove, and reorder what appears on the lead card.</div>
                                </div>
                                <div class="fields-header-right"></div>
                            </div>

                            <div class="fields-section">
                                <div class="section-head">
                                    <div>
                                        <div class="section-title">Visible on card</div>
                                        <div class="section-help">Toggle fields on/off. Drag to reorder.</div>
                                    </div>
                                    <div class="count-pill">{{ enabledFields.length }}</div>
                                </div>

                                <draggable
                                    v-model="cardFields"
                                    group="fields"
                                    item-key="key"
                                    handle=".drag-handle"
                                    class="fields-list"
                                    @end="onDragEnd"
                                >
                                    <template #item="{ element: field }">
                                        <div
                                            class="field-row field-row--compact"
                                            :class="{ 'is-disabled': !field.enabled, 'is-enabled': field.enabled }"
                                            role="button"
                                            tabindex="0"
                                            @click="toggleField(field)"
                                            @keydown.enter.prevent="toggleField(field)"
                                            @keydown.space.prevent="toggleField(field)"
                                        >
                                            <div class="field-left">
                                                <input
                                                    class="field-checkbox"
                                                    type="checkbox"
                                                    :checked="field.enabled"
                                                    :id="`field_${field.key}`"
                                                    @click.stop
                                                    @change="setFieldEnabled(field, $event.target.checked)"
                                                />
                                                <div class="field-text">
                                                    <div class="field-label">{{ field.label }}</div>
                                                    <div class="field-key">{{ field.key }}</div>
                                                </div>
                                            </div>

                                            <div class="field-right">
                                                <iconify-icon
                                                    icon="lucide:grip-vertical"
                                                    class="drag-handle"
                                                    :class="{ 'drag-disabled': !field.enabled }"
                                                    @click.stop
                                                />
                                            </div>
                                        </div>
                                    </template>
                                </draggable>
                            </div>

                            <div class="fields-footer">
                                <div class="fields-footer-meta">
                                    Enabled: <strong>{{ enabledFields.length }}</strong> / {{ cardFields.length }}
                                </div>
                                <button class="btn btn-outline-secondary btn-sm" @click="resetToSaved" :disabled="loading">Reset to saved</button>
                            </div>
                        </div>

                        <!-- Live preview -->
                        <div class="preview-panel preview-panel--hero">
                            <div class="preview-header">
                                <div>
                                    <div class="preview-title">Live card preview</div>
                                    <div class="preview-subtitle">Polished mock — updates as you toggle fields.</div>
                                </div>
                                <div v-if="enabledFields.length" class="preview-live-pill">
                                    <span class="preview-live-dot" aria-hidden="true" />
                                    {{ enabledFields.length }} visible
                                </div>
                            </div>

                            <div class="preview-surface preview-surface--hero">
                                <div class="preview-hero-mesh" aria-hidden="true" />
                                <div v-if="enabledFields.length === 0" class="preview-empty preview-empty--floating">
                                    Enable at least one field on the left to see the card preview.
                                </div>

                                <div v-else class="preview-card-stage">
                                    <div class="preview-card-aura" aria-hidden="true" />
                                    <div class="preview-card-frame">
                                <div class="preview-card-wrap">
                                    <div class="kanban-card kanban-card--preview bg-white p-12 radius-12 shadow-sm border-0">
                                        <div class="task-header d-flex align-items-center justify-content-between gap-2 mb-12">
                                            <p class="task-title flex-grow-1 mb-0">{{ previewTask.lead_name }}</p>

                                            <div v-if="isEnabled('duplicate_count')" class="duplicate-badge position-relative">
                                                <div class="duplicate-icon-wrapper">
                                                    <div class="duplicate-rectangle duplicate-rectangle-back"></div>
                                                    <div class="duplicate-rectangle duplicate-rectangle-front">
                                                        <span class="duplicate-number">{{ previewTask.duplicate_no }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="task-info">
                                            <template v-for="field in enabledFields" :key="field.key">
                                                <div
                                                    v-if="field.key === 'created_by' || field.key === 'created_at'"
                                                    class="info-item date-info d-flex align-items-center gap-1 mb-8"
                                                >
                                                    <span v-if="field.key === 'created_by'">Created By</span>
                                                    <span>{{ formatPreviewDate(previewTask.created_at) }}</span>
                                                </div>

                                                <div v-else-if="field.key === 'first_name'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Name</div>
                                                    <div class="info-value">{{ previewTask.salutation }} {{ previewTask.first_name }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'lead_source'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                                                    <div class="info-value">{{ previewTask.lead_source }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'lead_branch_source'" class="info-item mb-12">
                                                    <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                                                    <div class="info-value">{{ previewTask.lead_branch_source }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'work_phone'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Phone</div>
                                                    <div class="info-value">{{ previewTask.work_phone }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'email'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Email</div>
                                                    <div class="info-value">{{ previewTask.email }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'bedrooms'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Bedrooms</div>
                                                    <div class="info-value">{{ previewTask.bedrooms }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'budget'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Budget</div>
                                                    <div class="info-value">{{ previewTask.budget }} {{ previewTask.currency }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'whatsapp_number'" class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">WhatsApp</div>
                                                    <div class="info-value">{{ previewTask.whatsapp_number }}</div>
                                                </div>

                                                <div v-else-if="field.key === 'responsible_person'" class="responsible-info d-flex align-items-center justify-content-between mb-12">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                            <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="info-value">{{ previewTask.responsible_person?.name }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-else-if="field.key === 'assigned_by'">
                                                    <hr class="mb-2 border-neutral-200">
                                                    <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                                        <div class="info-item">
                                                            <div class="info-label text-secondary-light text-xs mb-1">Assigned By</div>
                                                            <div class="info-value">{{ formatPreviewDate(previewTask.assigned_at) }}</div>
                                                        </div>
                                                        <div class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                            <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import draggable from 'vuedraggable'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const KANBAN_CARD_BEHAVIOR_STORAGE_KEY = 'kanban_card_behavior_settings_v1'
const loading = ref(true)
const saving = ref(false)
const error = ref(null)

const cardFields = ref([])
const allFields = ref([])
const initialSnapshot = ref(null)
const defaultCardBehavior = {
    showMoreFromQualified: true,
    qualifiedStartOrder: 4,
    qualifiedFieldKeys: [],
}
const cardBehavior = ref({ ...defaultCardBehavior })

const enabledFields = computed(() => {
    return cardFields.value
        .filter(field => field.enabled)
        .sort((a, b) => a.order - b.order)
})

const fetchSettings = async () => {
    loading.value = true
    error.value = null

    try {
        const response = await api.get('/settings/kanban')
        const data = response.data.data

        cardFields.value = data.card_fields || []
        allFields.value = data.all_fields || []
        const apiBehavior = data.card_behavior || {}
        const localBehaviorRaw = localStorage.getItem(KANBAN_CARD_BEHAVIOR_STORAGE_KEY)
        let localBehavior = {}
        try {
            localBehavior = localBehaviorRaw ? JSON.parse(localBehaviorRaw) : {}
        } catch {
            localBehavior = {}
        }
        cardBehavior.value = {
            ...defaultCardBehavior,
            ...apiBehavior,
            ...localBehavior,
        }
        if (!Number.isFinite(Number(cardBehavior.value.qualifiedStartOrder)) || Number(cardBehavior.value.qualifiedStartOrder) < 1) {
            cardBehavior.value.qualifiedStartOrder = 4
        }

        cardFields.value = cardFields.value.map((field, index) => ({
            ...field,
            order: field.order || index + 1
        }))

        const allKeys = allFields.value.map(f => f.key)
        const hasQualifiedKeysInApi = Object.prototype.hasOwnProperty.call(apiBehavior, 'qualifiedFieldKeys')
        const hasQualifiedKeysInLocal = Object.prototype.hasOwnProperty.call(localBehavior, 'qualifiedFieldKeys')
        if (!Array.isArray(cardBehavior.value.qualifiedFieldKeys)) {
            cardBehavior.value.qualifiedFieldKeys = [...allKeys]
        } else if (!hasQualifiedKeysInApi && !hasQualifiedKeysInLocal && cardBehavior.value.qualifiedFieldKeys.length === 0) {
            // Default: all lead fields available after qualified
            cardBehavior.value.qualifiedFieldKeys = [...allKeys]
        } else {
            cardBehavior.value.qualifiedFieldKeys = cardBehavior.value.qualifiedFieldKeys.filter(key => allKeys.includes(key))
        }

        initialSnapshot.value = {
            cardFields: JSON.parse(JSON.stringify(cardFields.value)),
            cardBehavior: JSON.parse(JSON.stringify(cardBehavior.value)),
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load settings'
    } finally {
        loading.value = false
    }
}

const updateFieldOrder = () => {
    cardFields.value = cardFields.value.map((field, index) => ({
        ...field,
        order: index + 1
    }))
}

const onDragEnd = () => {
    updateFieldOrder()
}

const setFieldEnabled = (field, enabled) => {
    field.enabled = !!enabled
    updateFieldOrder()
}

const toggleField = (field) => {
    field.enabled = !field.enabled
    updateFieldOrder()
}

const selectAll = (enabled) => {
    cardFields.value = cardFields.value.map((f) => ({ ...f, enabled: !!enabled }))
    updateFieldOrder()
}

const isQualifiedFieldSelected = (key) => {
    return Array.isArray(cardBehavior.value.qualifiedFieldKeys) && cardBehavior.value.qualifiedFieldKeys.includes(key)
}

const toggleQualifiedField = (key) => {
    const current = Array.isArray(cardBehavior.value.qualifiedFieldKeys) ? [...cardBehavior.value.qualifiedFieldKeys] : []
    if (current.includes(key)) {
        cardBehavior.value.qualifiedFieldKeys = current.filter(k => k !== key)
    } else {
        cardBehavior.value.qualifiedFieldKeys = [...current, key]
    }
}

const selectAllQualifiedFields = () => {
    cardBehavior.value.qualifiedFieldKeys = allFields.value.map(field => field.key)
}

const resetToSaved = () => {
    if (!initialSnapshot.value) return
    cardFields.value = JSON.parse(JSON.stringify(initialSnapshot.value.cardFields || []))
    cardBehavior.value = {
        ...defaultCardBehavior,
        ...(initialSnapshot.value.cardBehavior || {}),
    }
    updateFieldOrder()
}

const saveAllSettings = async () => {
    saving.value = true

    try {
        updateFieldOrder()

        await api.post('/settings/kanban/card-fields', { fields: cardFields.value })
        localStorage.setItem(KANBAN_CARD_BEHAVIOR_STORAGE_KEY, JSON.stringify(cardBehavior.value))

        initialSnapshot.value = {
            cardFields: JSON.parse(JSON.stringify(cardFields.value)),
            cardBehavior: JSON.parse(JSON.stringify(cardBehavior.value)),
        }

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

const previewTask = computed(() => {
    return {
        id: 1,
        lead_name: 'Mamsha Gardens Plot #A-102',
        duplicate_no: 2,
        created_at: new Date().toISOString(),
        assigned_at: new Date(Date.now() - 1000 * 60 * 60 * 6).toISOString(),
        salutation: 'Mr.',
        first_name: 'Ahmed',
        lead_source: 'Website',
        lead_branch_source: 'Dubai Marina',
        work_phone: '+971 50 123 4567',
        email: 'ahmed@example.com',
        bedrooms: 2,
        budget: '1,250,000',
        currency: 'AED',
        whatsapp_number: '+971 50 123 4567',
        responsible_person: { name: 'Sarah Ali' }
    }
})

const isEnabled = (key) => enabledFields.value.some((f) => f.key === key)

const formatPreviewDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    const options = { month: 'short', day: 'numeric', year: 'numeric' }
    const formattedDate = date.toLocaleDateString('en-US', options)
    const formattedTime = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    })
    return `${formattedDate}  |  ${formattedTime}`
}

onMounted(fetchSettings)

watch(
    () => cardFields.value.map(f => `${f.key}:${f.enabled}`).join('|'),
    () => {
        const allKeys = allFields.value.map(f => f.key)
        cardBehavior.value.qualifiedFieldKeys = (cardBehavior.value.qualifiedFieldKeys || []).filter(key => allKeys.includes(key))
    },
)
</script>

<style scoped>
.kanban-settings-container {
    padding: 16px 18px 20px;
    min-height: auto;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #ffffff;
}

.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
    margin-top: 12px;
}

.page-title {
    margin: 0;
    font-weight: 700;
    font-size: 15px;
    color: #0f172a;
}

.page-subtitle {
    margin: 6px 0 0 0;
    color: #64748b;
    font-size: 13px;
}

.page-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.builder-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 1px 6px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.builder-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    border-bottom: 1px solid #eef2f7;
}

.builder-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
}

.builder-desc {
    margin-top: 4px;
    color: #64748b;
    font-size: 12.5px;
}

.builder-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.builder-body {
    padding: 16px;
}

.card-behavior-panel {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    padding: 12px;
}

.card-behavior-head {
    margin-bottom: 8px;
}

.card-behavior-title {
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
}

.card-behavior-desc {
    margin-top: 2px;
    font-size: 12px;
    color: #64748b;
}

.card-behavior-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.qualified-fields-picker {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    padding: 10px;
}

.qualified-fields-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
}

.qualified-fields-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.qualified-field-chip {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}

.qualified-field-chip.active {
    border-color: #0f172a;
    background: #0f172a;
    color: #ffffff;
}

.behavior-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
}

.behavior-toggle input {
    accent-color: #0f172a;
}

.behavior-input-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
}

.behavior-number {
    width: 96px;
}

.builder-layout {
    display: grid;
    grid-template-columns: minmax(260px, 340px) minmax(320px, 1fr);
    gap: 18px;
    align-items: start;
}

.fields-panel {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #ffffff;
}

.fields-header {
    display: flex;
    justify-content: space-between;
    padding: 10px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
}

.fields-header-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.fields-title {
    font-weight: 800;
    font-size: 13px;
    color: #0f172a;
}

.fields-subtitle {
    font-weight: 500;
    font-size: 12px;
    color: #64748b;
}

.fields-header-right {
    padding-right: 4px;
    display: flex;
    align-items: center;
}

.mini-btn {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #334155;
    border-radius: 10px;
    height: 28px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 700;
}

.mini-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.fields-section {
    padding: 10px 10px 2px 10px;
}

.section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 2px 2px 8px 2px;
}

.section-title {
    font-weight: 800;
    font-size: 12.5px;
    color: #0f172a;
}

.section-help {
    margin-top: 2px;
    font-weight: 500;
    font-size: 12px;
    color: #64748b;
}

.count-pill {
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.icon-btn {
    width: 28px;
    height: 28px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.icon-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 2px 2px 10px 2px;
}

.chip {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #0f172a;
    border-radius: 12px;
    padding: 8px 10px;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-width: 200px;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.chip:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.chip.active {
    border-color: #0f172a;
}

.chip-label {
    font-size: 12px;
    font-weight: 800;
}

.chip-action {
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
}

.chip.active .chip-action {
    color: #0f172a;
}

.add-custom {
    margin: 0 2px 10px 2px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: #ffffff;
}

.add-title {
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
}

.add-subtitle {
    margin-top: 2px;
    font-size: 12px;
    color: #64748b;
}

.add-custom-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.add-select {
    min-width: 240px;
    border-radius: 10px;
}

.fields-list {
    max-height: min(520px, 52vh);
    overflow-y: auto;
    padding: 4px 6px 8px;
    background: #ffffff;
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 10px;
    border-radius: 12px;
    transition: background 0.15s ease, transform 0.12s ease, box-shadow 0.15s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.field-row--compact {
    padding: 6px 8px;
    border-radius: 10px;
    gap: 6px;
}

.field-row--compact.is-enabled::before {
    width: 3px;
    margin-right: 8px;
}

.field-row:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.field-row.is-enabled {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
}

.field-row.is-enabled::before {
    content: '';
    width: 4px;
    align-self: stretch;
    border-radius: 999px;
    background: #0f172a;
    margin-right: 10px;
}

.field-row.is-disabled {
    opacity: 0.72;
}

.field-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.field-checkbox {
    width: 16px;
    height: 16px;
    accent-color: #0f172a;
    cursor: pointer;
}

.field-text {
    min-width: 0;
}

.field-row--compact .field-label {
    font-size: 11px;
    font-weight: 700;
}

.field-row--compact .field-key {
    font-size: 9px;
    margin-top: 1px;
}

.field-label {
    font-weight: 700;
    font-size: 13px;
    color: #0f172a;
    line-height: 1.1;
}

.field-key {
    margin-top: 2px;
    font-size: 11px;
    color: #64748b;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.field-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.field-row--compact .drag-handle {
    font-size: 15px;
    padding: 4px;
}

.field-row--compact .field-checkbox {
    width: 14px;
    height: 14px;
}

.drag-handle {
    font-size: 18px;
    color: #94a3b8;
    cursor: grab;
    padding: 6px;
    border-radius: 10px;
    transition: background 0.15s ease, color 0.15s ease;
}

.drag-handle:hover {
    background: #eef2f7;
    color: #0f172a;
}

.drag-handle:active {
    cursor: grabbing;
}

.drag-disabled {
    opacity: 0.35;
    pointer-events: none;
}

.fields-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
}

.fields-footer-meta {
    font-size: 12px;
    color: #64748b;
}

.preview-panel {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #ffffff;
}

.preview-panel--hero {
    border: none;
    border-radius: 16px;
    overflow: visible;
    background: transparent;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
}

.preview-panel--hero .preview-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #0f172a 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px 16px 0 0;
}

.preview-panel--hero .preview-title {
    color: #f8fafc;
    font-size: 14px;
    letter-spacing: 0.02em;
}

.preview-panel--hero .preview-subtitle {
    color: rgba(248, 250, 252, 0.72);
    font-size: 12px;
}

.preview-live-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    color: #e0f2fe;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.14);
    white-space: nowrap;
}

.preview-live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.25);
    animation: preview-pulse 2s ease-in-out infinite;
}

@keyframes preview-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.55;
    }
}

.preview-header {
    padding: 12px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.preview-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 13px;
}

.preview-subtitle {
    margin-top: 2px;
    color: #64748b;
    font-size: 12px;
}

.preview-surface {
    padding: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 320px;
    background: linear-gradient(180deg, #ffffff, #fbfdff);
}

.preview-surface--hero {
    position: relative;
    min-height: min(420px, 48vh);
    padding: 28px 22px 32px;
    border-radius: 0 0 16px 16px;
    overflow: hidden;
    align-items: stretch;
    justify-content: center;
    background: #0b1220;
}

.preview-hero-mesh {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 80% 60% at 20% 20%, rgba(59, 130, 246, 0.35), transparent 55%),
        radial-gradient(ellipse 70% 50% at 85% 75%, rgba(16, 185, 129, 0.22), transparent 50%),
        radial-gradient(ellipse 50% 40% at 50% 100%, rgba(99, 102, 241, 0.2), transparent 45%),
        linear-gradient(180deg, #0b1220 0%, #0f172a 100%);
    pointer-events: none;
}

.preview-surface--hero .preview-empty--floating {
    position: relative;
    z-index: 1;
    margin: auto;
    max-width: 320px;
    background: rgba(15, 23, 42, 0.65);
    border: 1px dashed rgba(148, 163, 184, 0.45);
    color: #cbd5e1;
    backdrop-filter: blur(8px);
}

.preview-card-stage {
    position: relative;
    z-index: 1;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 4px 12px;
}

.preview-card-aura {
    position: absolute;
    width: min(400px, 92%);
    height: 70%;
    left: 50%;
    top: 52%;
    transform: translate(-50%, -50%);
    background: radial-gradient(circle, rgba(59, 130, 246, 0.35) 0%, transparent 70%);
    filter: blur(28px);
    pointer-events: none;
}

.preview-card-frame {
    position: relative;
    width: 100%;
    max-width: 420px;
    padding: 14px;
    border-radius: 20px;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.02));
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow:
        0 4px 6px rgba(0, 0, 0, 0.15),
        0 24px 48px rgba(0, 0, 0, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.preview-card-wrap {
    width: 100%;
    max-width: 360px;
}

.preview-surface--hero .preview-card-wrap {
    max-width: 100%;
}

.kanban-card--preview {
    border-radius: 14px !important;
    border: 1px solid rgba(15, 23, 42, 0.06) !important;
    box-shadow:
        0 2px 4px rgba(15, 23, 42, 0.04),
        0 18px 40px rgba(15, 23, 42, 0.1) !important;
    background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%) !important;
}

.preview-empty {
    text-align: center;
    padding: 18px;
    color: #94a3b8;
    font-size: 13px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px dashed #e2e8f0;
}

/* Preview card typography matching leads.vue */
.task-title {
    font-family: Montserrat;
    font-weight: 700;
    font-size: 12px;
    line-height: 19px;
    letter-spacing: -0.25px;
    color: #01062C;
}

.task-header {
    align-items: flex-start;
}

.date-info {
    font-family: Montserrat;
    font-weight: 500;
    font-size: 10px;
    line-height: 9px;
    color: #64748b;
}

.date-info span {
    color: #1e293b;
}

.info-label {
    color: #979797;
    font-weight: 500;
    font-size: 11px !important;
}

.info-value {
    font-weight: 500;
    font-size: 11px;
    line-height: 12px;
    color: #353535;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.assignedBy .avatar-sm {
    width: 28px;
    height: 28px;
}

.border-neutral-200 {
    opacity: 1;
    border-width: 1px;
}

.duplicate-badge {
    flex-shrink: 0;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-icon-wrapper {
    position: relative;
    width: 24px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-rectangle {
    position: absolute;
    width: 20px;
    height: 24px;
    background-color: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-rectangle-back {
    top: 4px;
    left: 4px;
    z-index: 1;
}

.duplicate-rectangle-front {
    top: 0;
    left: 0;
    z-index: 2;
}

.duplicate-number {
    font-family: Montserrat;
    font-weight: 600;
    font-size: 11px;
    line-height: 1;
    color: #01062C;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 1200px) {
    .builder-layout {
        grid-template-columns: 1fr;
    }
    .preview-surface {
        min-height: 260px;
    }
}

@media (max-width: 768px) {
    .kanban-settings-container {
        padding: 16px;
        padding-top: 40px;
    }
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    .page-actions {
        justify-content: flex-start;
    }
}
</style>