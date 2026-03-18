<template>
    <div class="kanban-settings-container">
        <div class="page-header">
            <div>
                <h6 class="page-title">Kanban Settings</h6>
                <p class="page-subtitle">Configure revert rules and build the exact layout of your lead card.</p>
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

        <div v-else class="settings-grid">
            <!-- Revert Settings -->
            <div class="panel-card">
                <div class="panel-header">
                    <div class="panel-icon">
                        <iconify-icon icon="lucide:timer-reset" />
                    </div>
                    <div>
                        <div class="panel-title">Revert Settings</div>
                        <div class="panel-desc">Set after how many hours a lead will be reverted and assigned to the “New Leads” stage.</div>
                    </div>
                </div>

                <div class="panel-body">
                    <div class="revert-control">
                        <div class="revert-row">
                            <label class="form-label mb-0">Revert after</label>
                            <div class="revert-inline">
                                <div class="stepper" role="group" aria-label="Revert hours stepper">
                                    <button type="button" class="stepper-btn stepper-btn-icon" @click="adjustHours(-1)" aria-label="Decrease">
                                        <iconify-icon icon="lucide:minus" />
                                    </button>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        v-model="revertHoursText"
                                        class="form-control revert-input"
                                        @blur="commitHoursText"
                                        @keydown.enter.prevent="commitHoursText"
                                        aria-label="Revert hours"
                                    />
                                    <button type="button" class="stepper-btn stepper-btn-icon" @click="adjustHours(1)" aria-label="Increase">
                                        <iconify-icon icon="lucide:plus" />
                                    </button>
                                </div>
                                <span class="text-muted">hours</span>
                            </div>
                        </div>

                        <div class="revert-presets">
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 12 }" @click="setHours(12)">12h</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 24 }" @click="setHours(24)">1d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 48 }" @click="setHours(48)">2d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 72 }" @click="setHours(72)">3d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 168 }" @click="setHours(168)">7d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 720 }" @click="setHours(720)">30d</button>
                        </div>

                        <div class="revert-meta">
                            <span class="revert-range">Range: {{ REVERT_MIN }}–{{ REVERT_MAX }} hours</span>
                            <span class="revert-helper">Leads will revert after <strong>{{ revertHours }}</strong> hours</span>
                        </div>
                    </div>
                </div>
            </div>

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
                                            class="field-row"
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
                        <div class="preview-panel">
                            <div class="preview-header">
                                <div class="preview-title">Live Preview</div>
                                <div class="preview-subtitle">Matches your Kanban lead card style.</div>
                            </div>

                            <div class="preview-surface">
                                <div v-if="enabledFields.length === 0" class="preview-empty">
                                    Enable at least one field to see the preview.
                                </div>

                                <div v-else class="preview-card-wrap">
                                    <div class="kanban-card bg-white p-12 radius-12 shadow-sm border-0">
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
const initialSnapshot = ref(null)

const REVERT_MIN = 1
const REVERT_MAX = 720

const revertHoursText = ref(String(revertHours.value))

const enabledFields = computed(() => {
    return cardFields.value
        .filter(field => field.enabled)
        .sort((a, b) => a.order - b.order)
})

const clampRevertHours = () => {
    const n = Number(revertHours.value)
    if (Number.isNaN(n)) {
        revertHours.value = 24
    } else {
        revertHours.value = Math.max(REVERT_MIN, Math.min(REVERT_MAX, n))
    }
    revertHoursText.value = String(revertHours.value)
}

const setHours = (value) => {
    revertHours.value = Number(value)
    clampRevertHours()
}

const adjustHours = (delta) => {
    setHours(Number(revertHours.value) + Number(delta))
}

const commitHoursText = () => {
    const cleaned = String(revertHoursText.value || '').replace(/[^\d]/g, '')
    if (!cleaned) {
        revertHoursText.value = String(revertHours.value)
        return
    }
    setHours(parseInt(cleaned, 10))
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

        cardFields.value = cardFields.value.map((field, index) => ({
            ...field,
            order: field.order || index + 1
        }))

        clampRevertHours()

        initialSnapshot.value = {
            revertHours: revertHours.value,
            cardFields: JSON.parse(JSON.stringify(cardFields.value))
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

const resetToSaved = () => {
    if (!initialSnapshot.value) return
    revertHours.value = initialSnapshot.value.revertHours
    cardFields.value = JSON.parse(JSON.stringify(initialSnapshot.value.cardFields || []))
    clampRevertHours()
    updateFieldOrder()
}

const saveAllSettings = async () => {
    saving.value = true

    try {
        updateFieldOrder()
        clampRevertHours()

        await api.post('/settings/kanban/card-fields', { fields: cardFields.value })
        await api.post('/settings/kanban/revert-hours', { hours: revertHours.value })

        initialSnapshot.value = {
            revertHours: revertHours.value,
            cardFields: JSON.parse(JSON.stringify(cardFields.value))
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
</script>

<style scoped>
.kanban-settings-container {
    padding: 24px;
    padding-top: 40px;
    min-height: 100vh;
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

.settings-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 16px;
    align-items: start;
}

.panel-card,
.builder-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 1px 6px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.panel-header {
    display: flex;
    gap: 12px;
    padding: 16px 16px 12px 16px;
    border-bottom: 1px solid #eef2f7;
}

.panel-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f172a;
    flex-shrink: 0;
    font-size: 18px;
}

.panel-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
    line-height: 1.2;
}

.panel-desc {
    margin-top: 4px;
    color: #64748b;
    font-size: 12.5px;
    max-width: 46ch;
}

.panel-body {
    padding: 14px 16px 16px 16px;
}

.revert-control {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.revert-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: nowrap;
}

.revert-row .form-label {
    white-space: nowrap;
}

.revert-inline {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stepper {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.stepper-btn {
    height: 34px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #0f172a;
    font-weight: 700;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.1s ease;
}

.stepper-btn-icon {
    width: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.stepper-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.stepper-btn:active {
    transform: translateY(1px);
}

.revert-input {
    width: 88px !important;
    text-align: center;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    box-shadow: none;
}

.revert-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.preset-btn {
    height: 32px;
    padding: 0 10px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.preset-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.preset-btn.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
}

.revert-meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    color: #64748b;
    font-size: 12px;
}

.revert-helper strong {
    color: #0f172a;
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

.builder-layout {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 16px;
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
    max-height: 540px;
    overflow-y: auto;
    padding: 6px;
    background: #ffffff;
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 10px;
    border-radius: 12px;
    transition: background 0.15s ease, transform 0.12s ease, box-shadow 0.15s ease;
    border: 1px solid transparent;
    cursor: pointer;
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

.preview-card-wrap {
    width: 100%;
    max-width: 360px;
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
    .settings-grid {
        grid-template-columns: 1fr;
    }
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