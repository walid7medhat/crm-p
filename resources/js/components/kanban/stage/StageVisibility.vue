<!-- resources/js/views/settings/StageVisibility.vue -->
<template>
    <div class="stage-visibility-container">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-1">Stage Visibility Settings</h4>
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
                                <div class="stage-header" :style="{ backgroundColor: stage.color || '#f8f9fa' }">
                                    <span class="stage-order">{{ stage.order }}</span>
                                    <span class="stage-name">{{ stage.name }}</span>
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
                                    <span class="role-name">{{ formatRoleName(role.name) }}</span>
                                    <span class="role-badge" :class="role.name">{{ role.name }}</span>
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
import { ref, onMounted } from 'vue'
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

// ================= Methods =================
const fetchSettings = async () => {
    loading.value = true
    error.value = null
    
    try {
        const response = await api.get('/stages/visibility/settings')
        const data = response.data.data
        
        allStages.value = data.all_stages || []
        roles.value = data.roles.map(role => ({ name: role }))
        
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

// ================= Lifecycle =================
onMounted(() => {
    fetchSettings()
})
</script>

<style scoped>
.stage-visibility-container {
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
    margin: 0 -24px;
    padding: 0 24px;
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
    padding: 12px 8px;
}

.settings-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #e2e8f0;
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
}

.role-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.role-name {
    font-weight: 600;
    color: #1e293b;
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
    border-radius: 8px;
    font-size: 12px;
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
    width: 22px;
    height: 22px;
    border: 2px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
}

.stage-checkbox:checked + .checkbox-label {
    background-color: #3b82f6;
    border-color: #3b82f6;
}

.stage-checkbox:checked + .checkbox-label::after {
    content: '';
    position: absolute;
    left: 7px;
    top: 3px;
    width: 6px;
    height: 11px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
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
    
    .settings-content {
        padding: 16px;
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