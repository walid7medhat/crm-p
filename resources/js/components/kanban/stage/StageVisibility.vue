<!-- resources/js/views/settings/StageVisibility.vue -->
<template>
    <div class="stage-visibility-container">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h6 class="page-title">Stage Visibility</h6>
                <p class="page-subtitle">Choose which stages each role can see in the Kanban board.</p>
            </div>
            <div class="page-actions">
                <button
                    class="btn btn-outline-secondary btn-sm"
                    :disabled="loading || saving || !hasChanges"
                    @click="fetchSettings"
                >
                    <iconify-icon icon="lucide:rotate-ccw" class="me-1" />
                    Reset
                </button>
                <button
                    class="btn btn-primary btn-sm"
                    :disabled="loading || saving || !hasChanges"
                    @click="saveAllSettings"
                >
                    <iconify-icon icon="lucide:save" class="me-1" />
                    {{ saving ? 'Saving…' : 'Save changes' }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading settings...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-5">
            <iconify-icon icon="lucide:alert-circle" class="text-danger mb-2" style="font-size: 48px;" />
            <p class="text-danger">{{ error }}</p>
            <button class="btn btn-outline-primary mt-2" @click="fetchSettings">Try Again</button>
        </div>

        <!-- Settings Content -->
        <div v-else class="settings-content">
            <div class="settings-card stage-editor-card mb-3">
                <div class="card-head">
                    <div>
                        <div class="card-title">Stage Setup</div>
                        <div class="card-desc">Edit stage name and color for each stage directly from here.</div>
                    </div>
                </div>

                <div class="stage-editor-list">
                    <div v-for="stage in allStages" :key="stage.id" class="stage-editor-row">
                        <div class="stage-preview-chip" :style="{ '--stage-color': stageDrafts[stage.id]?.color || stage.color || '#e2e8f0' }">
                            <span class="stage-preview-order">{{ stage.order }}</span>
                            <span class="stage-preview-name">{{ stageDrafts[stage.id]?.name || stage.name }}</span>
                        </div>

                        <div class="stage-editor-fields">
                            <input
                                v-model="stageDrafts[stage.id].name"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Stage name"
                            />
                            <input
                                v-model="stageDrafts[stage.id].color"
                                type="color"
                                class="form-control form-control-color stage-color-input"
                                title="Select stage color"
                            />
                            <button
                                class="btn btn-outline-secondary btn-sm"
                                :disabled="!isStageDirty(stage) || savingStageMap[stage.id]"
                                @click="resetStageDraft(stage)"
                            >
                                Reset
                            </button>
                            <button
                                class="btn btn-primary btn-sm"
                                :disabled="!isStageDirty(stage) || savingStageMap[stage.id]"
                                @click="saveStageMeta(stage)"
                            >
                                {{ savingStageMap[stage.id] ? 'Saving…' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Permissions Matrix</div>
                        <div class="card-desc">Enabled means the role can view that stage. Use quick actions to enable all or none.</div>
                    </div>
                    <div class="card-meta">
                        <span class="meta-pill">
                            Roles: <strong>{{ roles.length }}</strong>
                        </span>
                        <span class="meta-pill">
                            Stages: <strong>{{ allStages.length }}</strong>
                        </span>
                    </div>
                </div>
            <!-- Stages Legend -->
            <!--<div class="stages-legend mb-4">-->
            <!--    <span class="legend-label">Available Stages:</span>-->
            <!--    <div class="legend-items">-->
            <!--        <div -->
            <!--            v-for="stage in allStages" -->
            <!--            :key="stage.id"-->
            <!--            class="legend-item"-->
            <!--            :style="{ borderLeftColor: stage.color || '#ccc' }"-->
            <!--        >-->
            <!--            <span class="stage-badge" :style="{ backgroundColor: stage.color || '#e9ecef' }">-->
            <!--                {{ stage.order }}-->
            <!--            </span>-->
            <!--            <span class="stage-name">{{ stage.name }}</span>-->
            <!--        </div>-->
            <!--    </div>-->
            <!--</div>-->

            <!-- Settings Table -->
            <div class="settings-table-wrapper">
                <table class="settings-table">
                    <thead>
                        <tr>
                            <th class="role-column">Role / Stage</th>
                            <th 
                                v-for="stage in allStages" 
                                :key="stage.id"
                                class="stage-column"
                            >
                                <div class="stage-header" :style="{ '--stage-color': stage.color || '#e2e8f0' }">
                                    <span class="stage-order">{{ stage.order }}</span>
                                    <span class="stage-name" :title="stage.name">{{ stage.name }}</span>
                                </div>
                            </th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.name">
                            <!-- Role Info -->
                            <td class="role-column">
                                <div class="role-info">
                                    <div class="role-top">
                                        <span class="role-name">{{ formatRoleName(role.name) }}</span>
                                        <span v-if="changedRoles.has(role.name)" class="unsaved-pill">Unsaved</span>
                                    </div>
                                    <div class="role-bottom">
                                        <span class="role-badge" :class="role.name">{{ role.name }}</span>
                                        <span class="role-count">
                                            {{ (settings[role.name] || []).length }}/{{ allStages.length }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Stage Checkboxes -->
                            <td 
                                v-for="stage in allStages" 
                                :key="stage.id"
                                class="stage-column"
                            >
                                <div class="checkbox-wrapper">
                                    <input 
                                        type="checkbox"
                                        :id="`${role.name}_stage_${stage.id}`"
                                        v-model="settings[role.name]"
                                        :value="stage.id"
                                        class="stage-checkbox"
                                        @change="handleChange(role.name)"
                                    >
                                    <label 
                                        :for="`${role.name}_stage_${stage.id}`"
                                        class="checkbox-label"
                                        :title="`Allow ${formatRoleName(role.name)} to see: ${stage.name}`"
                                    ></label>
                                </div>
                            </td>

                            <!-- Quick Actions -->
                            <td class="actions-column">
                                <div class="quick-actions">
                                    <button 
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="selectAllForRole(role.name)"
                                        title="Select all stages"
                                    >
                                        <iconify-icon icon="lucide:check-square" />
                                    </button>
                                    <button 
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="deselectAllForRole(role.name)"
                                        title="Deselect all stages"
                                    >
                                        <iconify-icon icon="lucide:square" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>

            <!-- Quick Presets -->
            <!--<div class="quick-presets mt-4">-->
            <!--    <span class="presets-label">Quick Presets:</span>-->
            <!--    <div class="presets-buttons">-->
            <!--        <button -->
            <!--            class="btn btn-sm btn-outline-primary"-->
            <!--            @click="applyPreset('all')"-->
            <!--        >-->
            <!--            All Stages-->
            <!--        </button>-->
            <!--        <button -->
            <!--            class="btn btn-sm btn-outline-primary"-->
            <!--            @click="applyPreset('exceptConverted')"-->
            <!--        >-->
            <!--            Except Converted-->
            <!--        </button>-->
            <!--        <button -->
            <!--            class="btn btn-sm btn-outline-primary"-->
            <!--            @click="applyPreset('firstTwo')"-->
            <!--        >-->
            <!--            First 2 Stages-->
            <!--        </button>-->
            <!--        <button -->
            <!--            class="btn btn-sm btn-outline-primary"-->
            <!--            @click="applyPreset('firstFour')"-->
            <!--        >-->
            <!--            First 4 Stages-->
            <!--        </button>-->
            <!--    </div>-->
            <!--</div>-->

            <!-- Save Button (Mobile) -->
            <div class=" mt-4">
                <button 
                    class="btn btn-primary "
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
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

// ================= State =================
const loading = ref(true)
const saving = ref(false)
const error = ref(null)

const allStages = ref([])
const roles = ref([])
const settings = ref({})
const changedRoles = ref(new Set())
const stageDrafts = ref({})
const savingStageMap = ref({})

const hasChanges = computed(() => changedRoles.value.size > 0)

// ================= Methods =================
const fetchSettings = async () => {
    loading.value = true
    error.value = null
    
    try {
        const response = await api.get('/stages/visibility/settings')
        const data = response.data.data
        
        allStages.value = data.all_stages || []
        roles.value = data.roles.map(role => ({ name: role }))

        stageDrafts.value = {}
        allStages.value.forEach(stage => {
            stageDrafts.value[stage.id] = {
                name: stage.name || '',
                color: stage.color || '#3b82f6',
            }
        })
        
        // تحويل الإعدادات إلى شكل سهل للاستخدام
        const settingsObj = {}
        data.settings.forEach(setting => {
            settingsObj[setting.role_name] = JSON.parse(setting.visible_stages)
        })
        
        roles.value.forEach(role => {
            if (!settingsObj[role.name]) {
                settingsObj[role.name] = getDefaultStagesForRole(role.name)
            }
        })
        
        settings.value = settingsObj
        changedRoles.value.clear()
        
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load settings'
    } finally {
        loading.value = false
    }
}

const getDefaultStagesForRole = (role) => {
    return allStages.value.map(s => s.id)  
}

const formatRoleName = (role) => {
    return role.split('_').map(word => 
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ')
}

const handleChange = (roleName) => {
    changedRoles.value.add(roleName)
}

const selectAllForRole = (roleName) => {
    settings.value[roleName] = allStages.value.map(s => s.id)
    changedRoles.value.add(roleName)
}

const deselectAllForRole = (roleName) => {
    settings.value[roleName] = []
    changedRoles.value.add(roleName)
}

const applyPreset = (preset) => {
    const allStageIds = allStages.value.map(s => s.id)
    
    roles.value.forEach(role => {
        switch (preset) {
            case 'all':
                settings.value[role.name] = [...allStageIds]
                break
            case 'exceptConverted':
                settings.value[role.name] = allStageIds.slice(0, -1)
                break
            case 'firstTwo':
                settings.value[role.name] = allStageIds.slice(0, 2)
                break
            case 'firstFour':
                settings.value[role.name] = allStageIds.slice(0, 4)
                break
        }
        changedRoles.value.add(role.name)
    })
}

const saveAllSettings = async () => {
    if (changedRoles.value.size === 0) {
        Swal.fire({
            icon: 'info',
            title: 'No Changes',
            text: 'No changes to save',
            timer: 2000,
            showConfirmButton: false
        })
        return
    }
    
    const result = await Swal.fire({
        title: 'Save Changes?',
        text: `You have changes in ${changedRoles.value.size} role(s)`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, save',
        cancelButtonText: 'Cancel'
    })
    
    if (!result.isConfirmed) return
    
    saving.value = true
    
    try {
        const promises = Array.from(changedRoles.value).map(roleName => {
            return api.post('/stages/visibility/settings', {
                role_name: roleName,
                visible_stages: settings.value[roleName]
            })
        })
        
        await Promise.all(promises)
        
        changedRoles.value.clear()
        
        Swal.fire({
            icon: 'success',
            title: 'Saved!',
            text: 'Stage visibility settings updated successfully',
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

const isStageDirty = (stage) => {
    const draft = stageDrafts.value[stage.id]
    if (!draft) return false
    const originalName = (stage.name || '').trim()
    const originalColor = (stage.color || '').toLowerCase()
    const draftName = (draft.name || '').trim()
    const draftColor = (draft.color || '').toLowerCase()
    return draftName !== originalName || draftColor !== originalColor
}

const resetStageDraft = (stage) => {
    stageDrafts.value[stage.id] = {
        name: stage.name || '',
        color: stage.color || '#3b82f6',
    }
}

const saveStageMeta = async (stage) => {
    const draft = stageDrafts.value[stage.id]
    if (!draft || !draft.name?.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Stage name required',
            text: 'Please enter a valid stage name before saving.',
        })
        return
    }

    savingStageMap.value = { ...savingStageMap.value, [stage.id]: true }

    try {
        await api.put(`/stages/${stage.id}`, {
            name: draft.name.trim(),
            color: draft.color || stage.color,
        })

        stage.name = draft.name.trim()
        stage.color = draft.color || stage.color

        Swal.fire({
            icon: 'success',
            title: 'Updated',
            text: `Stage "${stage.name}" updated successfully`,
            timer: 1400,
            showConfirmButton: false,
        })
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Update failed',
            text: err.response?.data?.message || 'Failed to update stage',
        })
    } finally {
        savingStageMap.value = { ...savingStageMap.value, [stage.id]: false }
    }
}

// ================= Lifecycle =================
onMounted(() => {
    fetchSettings()
})
</script>

<style scoped>
.stage-visibility-container {
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
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.settings-content {
    padding: 0;
}

.settings-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 1px 6px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.stage-editor-card {
    margin-bottom: 12px;
}

.stage-editor-list {
    padding: 12px 16px 16px;
    display: grid;
    gap: 10px;
}

.stage-editor-row {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px;
    background: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.stage-preview-chip {
    position: relative;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 6px 10px 6px 12px;
    min-width: 170px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    overflow: hidden;
}

.stage-preview-chip::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--stage-color);
}

.stage-preview-order {
    font-weight: 700;
    font-size: 12px;
    color: #0f172a;
}

.stage-preview-name {
    font-size: 12px;
    color: #334155;
    font-weight: 500;
    max-width: 130px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stage-editor-fields {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 250px;
}

.stage-color-input {
    width: 44px;
    min-width: 44px;
    padding: 2px;
    border-radius: 8px;
}

.card-head {
    padding: 16px;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.card-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
}

.card-desc {
    margin-top: 4px;
    color: #64748b;
    font-size: 12.5px;
    max-width: 60ch;
}

.card-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: 12px;
}

/* Stages Legend */
.stages-legend {
    background: #f8fafc;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.legend-label {
    font-weight: 600;
    color: #334155;
    margin-right: 16px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 4px 8px;
    background: white;
    border-radius: 20px;
    border-left: 4px solid;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.stage-badge {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 600;
    color: #1e293b;
}

.stage-name {
    font-size: 13px;
    font-weight: 500;
    color: #334155;
}

/* Settings Table */
.settings-table-wrapper {
    overflow-x: auto;
    padding: 0 16px 16px 16px;
}

.settings-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.settings-table th {
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
    padding: 10px 8px;
    border-bottom: 1px solid #e5e7eb;
}

.settings-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #eef2f7;
}

.settings-table tr:hover td {
    background-color: #f8fafc;
}

/* Role Column */
.role-column {
    width: 200px;
    position: sticky;
    left: 0;
    background: white;
    z-index: 5;
    border-right: 1px solid #f1f5f9;
}

.role-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.role-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.role-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.role-name {
    font-weight: 600;
    color: #1e293b;
}

.unsaved-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #0f172a;
    white-space: nowrap;
}

.role-count {
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
}

.role-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    width: fit-content;
}

.role-badge.super_admin {
    background: #818cf8;
    color: white;
}

.role-badge.admin {
    background: #f59e0b;
    color: white;
}

.role-badge.manager {
    background: #10b981;
    color: white;
}

.role-badge.team_lead {
    background: #3b82f6;
    color: white;
}

.role-badge.sales {
    background: #8b5cf6;
    color: white;
}

.role-badge.marketing {
    background: #ec4899;
    color: white;
}

/* Stage Column */
.stage-column {
    text-align: center;
    min-width: 100px;
}

.stage-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px;
    border-radius: 10px;
    font-size: 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    position: relative;
    overflow: hidden;
}

.stage-header::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--stage-color);
    opacity: 0.9;
}

.stage-order {
    font-weight: 700;
    font-size: 14px;
    color: #1e293b;
}

.stage-name {
    font-size: 11px;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90px;
}

/* Checkbox Styling */
.checkbox-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
}

.stage-checkbox {
    display: none;
}

.checkbox-label {
    width: 40px;
    height: 22px;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    cursor: pointer;
    position: relative;
    transition: background-color 0.18s ease, border-color 0.18s ease;
    background: #f1f5f9;
}

.stage-checkbox:checked + .checkbox-label {
    background-color: #2563eb;
    border-color: #2563eb;
}

.checkbox-label::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    background: #ffffff;
    transform: translateY(-50%);
    transition: left 0.18s ease;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
}

.stage-checkbox:checked + .checkbox-label::after {
    left: 20px;
}

.checkbox-label:hover {
    border-color: #cbd5e1;
}

.settings-table tr:hover .checkbox-label {
    border-color: #94a3b8;
}

/* Actions Column */
.actions-column {
    width: 100px;
    text-align: center;
}

.quick-actions {
    display: flex;
    gap: 4px;
    justify-content: center;
}

.quick-actions button {
    padding: 4px 8px;
    font-size: 12px;
}

/* Quick Presets */
.quick-presets {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
}

.presets-label {
    font-weight: 600;
    color: #334155;
}

.presets-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Mobile Save Button */
.mobile-save-btn {
    display: none;
}

/* Responsive */
@media (max-width: 768px) {
    .stage-visibility-container {
        padding: 16px;
    }
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .quick-presets {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .desktop-save-btn {
        display: none;
    }
    
    .mobile-save-btn {
        display: block;
    }
    
    .legend-items {
        gap: 8px;
    }
    
    .legend-item {
        padding: 2px 8px 2px 4px;
    }
    
    .stage-name {
        font-size: 11px;
    }
}
</style>