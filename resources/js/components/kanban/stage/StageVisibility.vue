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
                        <div class="card-desc">Create stages, then edit name and color. Permissions update below.</div>
                    </div>
                </div>

                <div class="new-stage-bar">
                    <div class="new-stage-bar-label">
                        <iconify-icon icon="lucide:plus-circle" class="new-stage-icon" />
                        <span>New stage</span>
                    </div>
                    <div class="new-stage-bar-fields">
                        <input
                            v-model="newStageName"
                            type="text"
                            class="form-control form-control-sm new-stage-input"
                            placeholder="Stage name"
                            :disabled="creatingNewStage"
                            @keydown.enter.prevent="createNewStage"
                        />
                        <input
                            v-model="newStageColor"
                            type="color"
                            class="form-control form-control-color new-stage-color"
                            title="Stage color"
                            :disabled="creatingNewStage"
                        />
                        <button
                            type="button"
                            class="btn btn-primary btn-sm new-stage-add"
                            :disabled="creatingNewStage || !newStageName.trim()"
                            @click="createNewStage"
                        >
                            <span v-if="creatingNewStage">Adding…</span>
                            <span v-else>Add stage</span>
                        </button>
                    </div>
                </div>

                <div class="stage-editor-list">
                    <div v-for="stage in allStages" :key="stage.id" class="stage-editor-row">
                        <div class="stage-preview-chip" :style="{ '--stage-color': stageDrafts[stage.id]?.color || stage.color || '#e2e8f0' }">
                            <span class="stage-preview-order">{{ stage.order }}</span>
                            <span class="stage-preview-name">{{ stageDrafts[stage.id]?.name || stage.name }}</span>
                        </div>

                       <div class="stage-editor-fields">
                           <div class="main">
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
                                
                                <!-- ✅ Auto Revert Switch -->
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        :id="'auto_revert_' + stage.id"
                                        v-model="stageDrafts[stage.id].auto_revert"
                                    >
                                    <label
                                        class="form-check-label small text-muted"
                                        :for="'auto_revert_' + stage.id"
                                    >
                                        Auto revert
                                    </label>
                                </div>
                             </div>
                                <!-- ✅ إعدادات الـ Revert - تظهر فقط عندما يكون Auto Revert مفعلاً -->
                                <div v-if="stageDrafts[stage.id]?.auto_revert" class="revert-settings-group">
                                    <!-- ساعات الرجوع -->
                                    <div class="revert-field">
                                        <label class="small text-muted">Revert after</label>
                                        <input
                                            v-model="stageDrafts[stage.id].revert_after_hours"
                                            type="number"
                                            class="form-control form-control-sm"
                                            placeholder="Hours"
                                            min="1"
                                            max="720"
                                        />
                                    </div>

                                   <div class="revert-field">
                                    <label class="small text-muted">Target Stage</label>
                                    <select 
                                        v-model="stageDrafts[stage.id].revert_to_stage_id"
                                        class="form-control form-control-sm revert-stage-select"
                                        :key="'select_' + stage.id"
                                    >
                                        <option :value="null">⬅️ Previous Stage (Default)</option>
                                        
                                        <option 
                                            v-for="s in allStages" 
                                            :key="'option_' + s.id"
                                            :value="s.id"
                                            :disabled="s.id === stage.id"
                                        >
                                            {{ s.name || 'Unnamed' }}
                                            <span v-if="s.id === stage.id">(current)</span>
                                        </option>
                                    </select>
                                </div>

                                    <div class="revert-field">
                                        <label class="small text-muted">Notification Message</label>
                                        <input
                                            v-model="stageDrafts[stage.id].revert_notification_message"
                                            type="text"
                                            class="form-control form-control-sm notify-message"
                                            placeholder="Custom notification message (optional)"
                                        />
                                    </div>

                                    <div class="revert-field">
                                        <label class="small text-muted">Notify at (min)</label>
                                        <input
                                            v-model="stageDrafts[stage.id].notification_times"
                                            type="text"
                                            class="form-control form-control-sm"
                                            placeholder="30,15,5"
                                            @blur="parseNotificationTimes(stage.id)"
                                        />
                                    </div>
                                </div>
                                 <div class="buttons">
                                    <!-- الأزرار -->
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
            </div>

            <div class="settings-card mb-3">
                <div class="card-head">
                    <div>
                        <div class="card-title">Revert Settings</div>
                        <div class="card-desc">Set after how many hours a lead should revert to the first stage.</div>
                    </div>
                </div>
                <div class="revert-card-body">
                    <div class="revert-control">
                        <div class="revert-row">
                            <label class="form-label mb-0">Revert after</label>
                            <div class="revert-inline">
                                <div class="stepper" role="group" aria-label="Revert hours stepper">
                                    <button type="button" class="stepper-btn stepper-btn-icon" @click="adjustRevertHours(-1)" aria-label="Decrease">
                                        <iconify-icon icon="lucide:minus" />
                                    </button>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        v-model="revertHoursText"
                                        class="form-control revert-input"
                                        @blur="commitRevertHoursText"
                                        @keydown.enter.prevent="commitRevertHoursText"
                                        aria-label="Revert hours"
                                    />
                                    <button type="button" class="stepper-btn stepper-btn-icon" @click="adjustRevertHours(1)" aria-label="Increase">
                                        <iconify-icon icon="lucide:plus" />
                                    </button>
                                </div>
                                <span class="text-muted">hours</span>
                            </div>
                        </div>

                        <div class="revert-presets">
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 12 }" @click="setRevertHours(12)">12h</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 24 }" @click="setRevertHours(24)">1d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 48 }" @click="setRevertHours(48)">2d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 72 }" @click="setRevertHours(72)">3d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 168 }" @click="setRevertHours(168)">7d</button>
                            <button type="button" class="preset-btn" :class="{ active: revertHours === 720 }" @click="setRevertHours(720)">30d</button>
                        </div>

                        <div class="revert-meta">
                            <span class="revert-range">Range: {{ REVERT_MIN }}-{{ REVERT_MAX }} hours</span>
                            <span class="revert-helper">Current: <strong>{{ revertHours }}</strong> hours</span>
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
import { ref, onMounted, computed,watch ,onErrorCaptured } from 'vue'
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

const newStageName = ref('')
const newStageColor = ref('#3b82f6')
const creatingNewStage = ref(false)

const REVERT_MIN = 1
const REVERT_MAX = 720
const revertHours = ref(24)
const revertHoursText = ref('24')
const initialRevertHours = ref(24)

const hasRevertChanges = computed(() => Number(revertHours.value) !== Number(initialRevertHours.value))
const hasChanges = computed(() => changedRoles.value.size > 0 || hasRevertChanges.value)

// ================= Methods =================
const fetchSettings = async () => {
    loading.value = true
    error.value = null
    
    try {
        const [visibilityResponse, kanbanResponse] = await Promise.all([
            api.get('/stages/visibility/settings'),
            api.get('/settings/kanban'),
        ])
        
        // ✅ التحقق من الاستجابة
        if (!visibilityResponse.data || !visibilityResponse.data.data) {
            throw new Error('Invalid response structure')
        }
        
        const data = visibilityResponse.data.data
        const kanbanData = kanbanResponse?.data?.data || {}
        
        // ✅ التأكد من أن all_stages موجودة ومصفوفة
        let stagesData = data.all_stages || []
        
        // ✅ إذا كانت all_stages عبارة عن object وليست array
        if (!Array.isArray(stagesData)) {
            console.warn('all_stages is not an array:', stagesData)
            stagesData = Object.values(stagesData).filter(s => s && s.id)
        }
        
        // ✅ تصفية العناصر الصالحة فقط
        const validStages = stagesData.filter(stage => {
            if (!stage || typeof stage !== 'object') {
                console.warn('Invalid stage object:', stage)
                return false
            }
            if (!stage.id) {
                console.warn('Stage missing id:', stage)
                return false
            }
            return true
        })
        
        console.log('Valid stages:', validStages.length)
        
        allStages.value = validStages
        roles.value = data.roles ? data.roles.map(role => ({ name: role })) : []

        // ✅ تهيئة stageDrafts
        stageDrafts.value = {}
        allStages.value.forEach(stage => {
            if (stage && stage.id) {
                stageDrafts.value[stage.id] = {
                    name: stage.name || '',
                    order: stage.order !== undefined && stage.order !== null ? String(stage.order) : '',
                    color: stage.color || '#3b82f6',
                    auto_revert: stage.auto_revert || false,
                    revert_after_hours: stage.revert_after_hours || null,
                    notify_before_minutes: stage.notify_before_minutes || 30,
                    revert_to_stage_id: stage.revert_to_stage_id || null,
                    revert_notification_message: stage.revert_notification_message || '',
                    notification_times: Array.isArray(stage.notification_times) 
                        ? stage.notification_times 
                        : [],
                }
            }
        })
        
        // ✅ تحويل الإعدادات
        const settingsObj = {}
        if (data.settings && Array.isArray(data.settings)) {
            data.settings.forEach(setting => {
                if (setting && setting.role_name) {
                    try {
                        const visibleStages = JSON.parse(setting.visible_stages)
                        settingsObj[setting.role_name] = Array.isArray(visibleStages) ? visibleStages : []
                    } catch (e) {
                        console.warn('Failed to parse settings for role:', setting.role_name, e)
                        settingsObj[setting.role_name] = []
                    }
                }
            })
        }
        
        // ✅ تعيين الإعدادات الافتراضية للأدوار التي ليس لها إعدادات
        roles.value.forEach(role => {
            if (!settingsObj[role.name]) {
                settingsObj[role.name] = allStages.value.map(s => s.id)
            }
        })
        
        settings.value = settingsObj
        changedRoles.value.clear()

        // ✅ تحديث Revert Hours
        const revertHoursValue = Number(kanbanData.revert_hours)
        revertHours.value = !isNaN(revertHoursValue) && revertHoursValue > 0 ? revertHoursValue : 24
        initialRevertHours.value = revertHours.value
        normalizeRevertHours()
        
    } catch (err) {
        console.error('Error fetching settings:', err)
        error.value = err.response?.data?.message || err.message || 'Failed to load settings'
        allStages.value = []
        roles.value = []
        stageDrafts.value = {}
    } finally {
        loading.value = false
    }
}

const getDefaultStagesForRole = (role) => {
    if (!allStages.value || !Array.isArray(allStages.value)) {
        return []
    }
    return allStages.value
        .filter(stage => stage && stage.id)
        .map(s => s.id)
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
const validateStages = (stages) => {
    if (!stages || !Array.isArray(stages)) {
        return []
    }
    return stages.filter(stage => stage && stage.id)
}


const getDefaultDraft = (stage) => {
    return {
        name: stage.name || '',
        color: stage.color || '#3b82f6',
        order: stage.order || '',
        auto_revert: stage.auto_revert || false,
        revert_after_hours: stage.revert_after_hours || null,
        notify_before_minutes: stage.notify_before_minutes || 30,
        revert_to_stage_id: stage.revert_to_stage_id || null,
        revert_notification_message: stage.revert_notification_message || '',
        notification_times: Array.isArray(stage.notification_times) 
            ? stage.notification_times 
            : [],
    }
}
const saveAllSettings = async () => {
    if (changedRoles.value.size === 0 && !hasRevertChanges.value) {
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
        text: `You have changes in ${changedRoles.value.size} role(s)${hasRevertChanges.value ? ' and revert settings' : ''}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, save',
        cancelButtonText: 'Cancel'
    })
    
    if (!result.isConfirmed) return
    
    saving.value = true
    
    try {
        normalizeRevertHours()

        const promises = Array.from(changedRoles.value).map(roleName => {
            return api.post('/stages/visibility/settings', {
                role_name: roleName,
                visible_stages: settings.value[roleName]
            })
        })
        if (hasRevertChanges.value) {
            promises.push(api.post('/settings/kanban/revert-hours', { hours: revertHours.value }))
        }
        
        if (promises.length > 0) {
            await Promise.all(promises)
        }
        
        changedRoles.value.clear()
        initialRevertHours.value = Number(revertHours.value)
        
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

    return (
        safeString(draft.name) !== safeString(stage.name) ||
        safeString(draft.order) !== safeString(stage.order) ||
        safeString(draft.color).toLowerCase() !== safeString(stage.color).toLowerCase() ||
        Boolean(draft.auto_revert) !== Boolean(stage.auto_revert) ||
        safeNumber(draft.revert_after_hours) !== safeNumber(stage.revert_after_hours) ||
        safeNumber(draft.notify_before_minutes) !== safeNumber(stage.notify_before_minutes) ||
        safeNumber(draft.revert_to_stage_id) !== safeNumber(stage.revert_to_stage_id) ||
        safeString(draft.revert_notification_message) !== safeString(stage.revert_notification_message) ||
        JSON.stringify(draft.notification_times || []) !== JSON.stringify(stage.notification_times || [])
    )
}

const resetStageDraft = (stage) => {
    if (!stage || !stage.id) return
    
    stageDrafts.value[stage.id] = {
        name: stage.name || '',
        order: stage.order !== undefined && stage.order !== null ? String(stage.order) : '',
        color: stage.color || '#3b82f6',
        auto_revert: stage.auto_revert || false,
        revert_after_hours: stage.revert_after_hours || null,
        notify_before_minutes: stage.notify_before_minutes || 30,
        revert_to_stage_id: stage.revert_to_stage_id || null,
        revert_notification_message: stage.revert_notification_message || '',
        notification_times: Array.isArray(stage.notification_times) 
            ? stage.notification_times 
            : [],
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

    let notificationTimes = draft.notification_times
    if (typeof notificationTimes === 'string') {
        notificationTimes = notificationTimes
            .split(',')
            .map(n => parseInt(n.trim()))
            .filter(n => !isNaN(n) && n > 0)
    }

    savingStageMap.value = { ...savingStageMap.value, [stage.id]: true }

    try {
                const orderValue = parseInt(draft.order) || stage.order || 0

        await api.put(`/stages/${stage.id}`, {
            name: draft.name.trim(),
            color: draft.color || stage.color,
             order: orderValue,
            auto_revert: draft.auto_revert,
            revert_after_hours: draft.revert_after_hours,
            notify_before_minutes: draft.notify_before_minutes,
            revert_to_stage_id: draft.revert_to_stage_id,
            revert_notification_message: draft.revert_notification_message?.trim() || null,
            notification_times: notificationTimes,
        })

        stage.name = draft.name.trim()
        stage.color = draft.color || stage.color
        stage.order= orderValue
        stage.auto_revert = draft.auto_revert
        stage.revert_after_hours = draft.revert_after_hours
        stage.notify_before_minutes = draft.notify_before_minutes
        stage.revert_to_stage_id = draft.revert_to_stage_id
        stage.revert_notification_message = draft.revert_notification_message?.trim() || null
        stage.notification_times = notificationTimes

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
const parseNotificationTimes = (stageId) => {
    const draft = stageDrafts.value[stageId]
    if (!draft) return

    let input = draft.notification_times
    
    if (typeof input === 'string') {
        const times = input
            .split(',')
            .map(n => parseInt(n.trim()))
            .filter(n => !isNaN(n) && n > 0)
        
        draft.notification_times = times.length > 0 ? times : []
    }
    
    if (Array.isArray(input)) {
        const times = input.filter(n => !isNaN(n) && n > 0)
        draft.notification_times = times.length > 0 ? times : []
    }
}
const safeString = (value) => {
    if (value === null || value === undefined) return ''
    return String(value).trim()
}

const safeNumber = (value) => {
    const num = Number(value)
    return isNaN(num) ? 0 : num
}

const safeCompare = (val1, val2) => {
    return safeString(val1) === safeString(val2)
}
const normalizeRevertHours = () => {
    const n = Number(revertHours.value)
    if (Number.isNaN(n)) {
        revertHours.value = 24
    } else {
        revertHours.value = Math.max(REVERT_MIN, Math.min(REVERT_MAX, n))
    }
    revertHoursText.value = String(revertHours.value)
}

const setRevertHours = (value) => {
    revertHours.value = Number(value)
    normalizeRevertHours()
}

const adjustRevertHours = (delta) => {
    setRevertHours(Number(revertHours.value) + Number(delta))
}

const commitRevertHoursText = () => {
    const cleaned = String(revertHoursText.value || '').replace(/[^\d]/g, '')
    if (!cleaned) {
        revertHoursText.value = String(revertHours.value)
        return
    }
    setRevertHours(parseInt(cleaned, 10))
}

// ================= Lifecycle =================
onMounted(() => {
    fetchSettings()
})
watch(allStages, (newVal) => {
    if (newVal && Array.isArray(newVal)) {
        const validStages = newVal.filter(stage => stage && stage.id)
        if (validStages.length !== newVal.length) {
            console.warn('Some stages are invalid, filtering...')
            allStages.value = validStages
        }
    }
}, { deep: true, immediate: true })
onErrorCaptured((err, instance, info) => {
    console.error('❌ Error captured:', err)
    console.error('Component:', instance?.$options?.name || 'unknown')
    console.error('Info:', info)
    
    // منع انتشار الخطأ
    return false
})
</script>

<style scoped>
.stage-visibility-container {
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

.new-stage-bar {
    margin: 0 16px 14px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px dashed #cbd5e1;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.new-stage-bar-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
}

.new-stage-icon {
    font-size: 16px;
    color: #2563eb;
}

.new-stage-bar-fields {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.new-stage-input {
    flex: 1 1 200px;
    min-width: 160px;
    border-radius: 10px !important;
    border: 1px solid #e2e8f0 !important;
    font-size: 12px !important;
}

.new-stage-color {
    width: 44px;
    height: 34px;
    padding: 2px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
}

.new-stage-add {
    border-radius: 10px;
    font-weight: 700;
    padding-left: 14px;
    padding-right: 14px;
}

.stage-editor-list {
    padding: 12px 16px 16px;
    display: grid;
    gap: 10px;
}

.revert-card-body {
    padding: 14px 16px 16px;
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
    /* align-items: center; */
    gap: 8px;
    flex: 1;
    min-width: 250px;
    flex-direction:column;
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

.revert-settings-group {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    flex-wrap: wrap;
    width: 100%;
    margin-top: 4px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.revert-settings-group .revert-field {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 120px;
}

.revert-settings-group .revert-field label {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.revert-settings-group .revert-field .form-control-sm {
    padding: 4px 8px !important;
    font-size: 12px !important;
    height: 32px !important;
    border-radius: 6px !important;
    border: 1px solid #e5e7eb !important;
    background: #ffffff !important;
    transition: all 0.2s ease;
}

.revert-settings-group .revert-field .form-control-sm:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1) !important;
}

.revert-settings-group .revert-field .form-control-sm:hover {
    border-color: #94a3b8 !important;
}

/* ✅ تحسين الـ Select داخل الـ Revert Settings */
.revert-settings-group .revert-stage-select {
    min-width: 150px;
}

.revert-settings-group .revert-stage-select optgroup {
    font-weight: 600;
    color: #0f172a;
}

.revert-settings-group .revert-stage-select optgroup[label*="Previous"] {
    color: #2563eb;
}

.revert-settings-group .revert-stage-select optgroup[label*="Current"] {
    color: #94a3b8;
}

.revert-settings-group .revert-stage-select optgroup[label*="Next"] {
    color: #7c3aed;
}

.revert-settings-group .revert-stage-select option:disabled {
    color: #94a3b8;
    background: #f1f5f9;
    font-style: italic;
}

/* ✅ Responsive */
@media (max-width: 768px) {
    .revert-settings-group {
        flex-direction: column;
        align-items: stretch;
        padding: 10px;
    }
    
    .revert-settings-group .revert-field {
        min-width: 100%;
    }
    
    .revert-settings-group .revert-stage-select {
        min-width: 100%;
    }
}
.main,.buttons{
        display: flex;
    flex-direction: row;
}
.notify-message{
    width:300px
}
</style>