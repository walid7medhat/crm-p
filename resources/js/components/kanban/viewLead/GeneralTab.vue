<template>
    <div class="row g-4">
        <!-- Left Column: Lead Information -->
        <div ref="editSectionAnchorRef" class="col-md-5">
            <div class="info-card bg-white  p-3 radius-12">
                <!-- View Mode (read-only; do not use ViewLead.vue here – it is a full modal and would cause infinite recursion) -->
                <LeadInfoView v-if="!isEditMode" :lead="lead" :show-responsible-section="false" :can-edit="lead?.can_edit" :show-edit-icon="true" @edit-section="handleEditSection" @edit-request="toggleEditMode" @lead-updated="handleLeadUpdated" />

                <!-- Edit Mode (footer Save/Cancel moved to global bottom bar below) -->
                <EditLead 
                    v-else 
                    ref="editLeadRef"
                    :lead="lead"
                    :stage-id="selectedStageId"
                    @updated="handleLeadUpdated"
                    @cancel="handleCancel"
                    :show-only-section="editingSection"
                />
            </div>
        </div>

        <!-- Right Column: Activity & Comments -->
        <div class="col-md-7">
            <div class="activity-card bg-white p-3 radius-12 shadow-sm">
              <div v-if="qualityStatusBadge || callResultBadge || leadTypeBadge" class="info-section compact-status-section mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="info-section-title lead-section-title-match mb-0">Lead Qualification</div>
                    </div>
                    <div class="compact-status-grid">
                        <div class="compact-status-card">
                            <div class="compact-status-label">Quality Status</div>
                            <div class="compact-status-value">
                                <span
                                    class="status-badge"
                                    :class="qualityStatusBadge ? `status-badge--${qualityStatusBadge.tone}` : 'status-badge--neutral'"
                                >
                                    {{ qualityStatusBadge?.label || 'Not Set' }}
                                </span>
                            </div>
                        </div>
                        <div class="compact-status-card">
                            <div class="compact-status-label">Call Result</div>
                            <div class="compact-status-value">
                                <span
                                    class="status-badge"
                                    :class="callResultBadge ? `status-badge--${callResultBadge.tone}` : 'status-badge--neutral'"
                                >
                                    {{ callResultBadge?.label || 'Not Set' }}
                                </span>
                            </div>
                        </div>
                        <div class="compact-status-card">
                            <div class="compact-status-label">Lead Type</div>
                            <div class="compact-status-value">
                                <span
                                    class="status-badge"
                                    :class="leadTypeBadge ? `status-badge--${leadTypeBadge.tone}` : 'status-badge--neutral'"
                                >
                                    {{ leadTypeBadge?.label || 'Not Set' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
               <ResponsiblePersonSection
                    v-if="lead?.id"
                    :lead="lead"
                    @person-updated="handlePersonUpdated"
                />
              
              <div class="d-flex justify-content-between align-items-center mb-4">
                  
                <!-- Activity/Comments Toggle -->
                <div class="d-flex gap-2 mb-4 p-1 radius-100 w-fit-content toggle-buttons-container">
                      <button 
                        class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100"
                        :class="{ active: activeViewTab === 'comments' }"
                        @click="activeViewTab = 'comments'"
                    >
                        <iconify-icon icon="lucide:message-square"></iconify-icon>
                        Comments
                    </button>
                    <button 
                        class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100"
                        :class="{ active: activeViewTab === 'activity' }"
                        @click="activeViewTab = 'activity'"
                    >
                        <iconify-icon icon="lucide:clock-3"></iconify-icon>
                        Activity
                    </button>
                  
                </div>
                    <div v-if="lead?.can_delete" class="admin-controls d-flex gap-2">
                        <button 
                            v-if="activeViewTab === 'comments' && canDeleteAll"
                            class="btn-admin-action btn-delete-all"
                            @click="confirmDeleteAllCommentsActivities"
                            title="Delete all comments"
                        >
                            <iconify-icon icon="lucide:trash-2" width="18" height="18"></iconify-icon>
                            <span>Hide All</span>
                        </button>
                        
                        <button 
                            v-if="activeViewTab === 'activity' && canDeleteAll"
                            class="btn-admin-action btn-delete-all"
                            @click="confirmDeleteAllCommentsActivities"
                            title="Delete all activities"
                        >
                            <iconify-icon icon="lucide:trash-2" width="18" height="18"></iconify-icon>
                            <span>Hide All</span>
                        </button>
                        
                        <!-- <button class="btn-admin-action btn-restore-all">Restore All</button> -->
                    </div>
                </div>

                <!-- Activity View -->
                <ActivitySection 
                    v-if="activeViewTab === 'activity'" 
                    :lead-id="lead?.id"
                    @activity-created="handleActivityCreated"
                />

                <!-- Comments View -->
                <CommentsSection 
                    v-if="activeViewTab === 'comments'" 
                    :lead-id="lead?.id"
                    @comment-created="handleCommentCreated"
                />

            </div>

          

            <!-- Lead Activity List -->
            <ActivityList 
                v-if="activeViewTab === 'activity'" 
                ref="activityListRef"
                :lead-id="lead?.id" 
                 :key-delete="activityListKey"
            />
            <CommentList 
                v-if="activeViewTab === 'comments'" 
                ref="commentListRef"
                :lead-id="lead?.id" 
                 :key="commentListKey"
            />
              
            <!-- Lead Activity timeline: under comments, grouped by date (who assigned, created, history). Key forces refetch when stage changes so "Stage changed" appears immediately. -->
            <LeadActivityTimeline 
                v-if="activeViewTab === 'comments' && lead?.id" 
                :key="`timeline-${lead?.id}-${lead?.stage_id}`"
                :lead-id="lead?.id" 
               
            />
            <!-- Lead Created (first section from bottom) -->
            <div v-if="lead?.id" class="lead-created-section bg-white p-3 radius-12 shadow-sm">
                <LeadCreatedCard :lead="lead" />
            </div>
        </div>

        <!-- Spacer when bar is visible so content isn't hidden behind fixed bar -->
        <div v-if="isEditMode" class="col-12 edit-lead-bar-spacer"></div>

        <!-- Global fixed bottom bar: Save / Cancel when editing lead (centered, fixed on scroll) -->
        <div v-if="isEditMode" class="edit-lead-bottom-bar">
            <button class="edit-bar-btn edit-bar-cancel" @click="onEditBarCancel">
                Cancel
            </button>
            <button
                class="edit-bar-btn edit-bar-save"
                :disabled="isEditBarSaving"
                @click="onEditBarSave"
            >
                <span v-if="isEditBarSaving">Saving...</span>
                <span v-else>Save</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import EditLead from '../editLead/EditLead.vue'
import LeadInfoView from './LeadInfoView.vue'
import LeadCreatedCard from './LeadCreatedCard.vue'
import ActivitySection from './ActivitySection.vue'
import CommentsSection from './CommentsSection.vue'
import ActivityList from './ActivityList.vue'
import CommentList from './CommentList.vue'
import LeadActivityTimeline from './LeadActivityTimeline.vue'
import ResponsiblePersonSection from './ResponsiblePersonSection.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
const props = defineProps({
    lead: {
        type: Object,
        default: null
    },
    stageId: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['update:lead'])
const editingSection = ref(null)

const isEditMode = ref(false)
const selectedStageId = ref(props.stageId || props.lead?.stage?.id || null)
const activeViewTab = ref('comments')
const commentListRef = ref(null)
const activityListRef = ref(null)
const editLeadRef = ref(null)
const editSectionAnchorRef = ref(null)
const commentListKey = ref(0)
const activityListKey = ref(0)

const scrollEditSectionIntoView = async () => {
    await nextTick()
    const modalBody = document.querySelector('#view-lead-modal___BV_modal_body_')
        || document.querySelector('.view-lead-modal')
        || document.querySelector('.modal-body-custom')

    if (modalBody && typeof modalBody.scrollTo === 'function') {
        modalBody.scrollTo({ top: 0, behavior: 'smooth' })
    }

    if (editSectionAnchorRef.value?.scrollIntoView) {
        editSectionAnchorRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}

const showNotification = (message, type = 'success') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(message)
    }
}
const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())
const canDeleteAll = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('super_admin') || user.value.roles?.includes('admin') 
    
    return isAdminUser
})

const selectedRequirementSource = computed(() => {
    const rows = Array.isArray(props.lead?.extra_client_requirements)
        ? props.lead.extra_client_requirements
        : []
    const meta = rows.find((item) => item?._kind === 'qualification_meta')
    const source = meta?.source || 'primary'
    if (source === 'primary') return null
    return rows.find((item) => item?._kind !== 'qualification_meta' && item?.id === source) || null
})

const selectedStatusLead = computed(() => {
    return selectedRequirementSource.value?.status_lead ?? props.lead?.status_lead
})

const selectedLeadType = computed(() => {
    return selectedRequirementSource.value?.lead_type ?? props.lead?.lead_type
})

const qualityStatusBadge = computed(() => {
    const status = String(selectedStatusLead.value || '').toLowerCase()
    if (!status) return null
    if (status === 'hot') return { label: 'Hot', tone: 'hot' }
    if (status === 'warm') return { label: 'Warm', tone: 'warm' }
    if (status === 'cold') return { label: 'Cold', tone: 'cold' }
    if (status === 'no_answer') return { label: 'No Answer', tone: 'muted' }
    return { label: status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()), tone: 'neutral' }
})

const callResultBadge = computed(() => {
    const status = String(selectedStatusLead.value || '').toLowerCase()
    if (!status) return null
    if (status === 'no_answer') return { label: 'No Answer', tone: 'muted' }
    return { label: 'Answered', tone: 'ok' }
})

const leadTypeBadge = computed(() => {
    const leadType = String(selectedLeadType.value || '').toLowerCase().trim()
    if (!leadType) return null
    if (leadType === 'sale') return { label: 'Sale', tone: 'sale' }
    if (leadType === 'rent') return { label: 'Rent', tone: 'rent' }
    return { label: leadType.replace(/\b\w/g, c => c.toUpperCase()), tone: 'neutral' }
})
const resetEditMode = () => {
    console.log('🔄 GeneralTab: Resetting edit mode to false')
    isEditMode.value = false
}
const handlePersonUpdated = (updatedPerson) => {
    if (!props.lead) return
    emit('update:lead', {
        ...props.lead,
        responsible_person_id: updatedPerson.id,
        responsible_person: {
            ...(props.lead.responsible_person || {}),
            id: updatedPerson.id,
            name: updatedPerson.name,
            avatar: updatedPerson.avatar,
            role_name: updatedPerson.role_name || props.lead?.responsible_person?.role_name,
            manager_name: updatedPerson.manager_name || props.lead?.responsible_person?.manager_name,
            branch_name: updatedPerson.branch_name || props.lead?.responsible_person?.branch_name,
        }
    })
}

const confirmDeleteAllCommentsActivities = () => {
    Swal.fire({
        title: 'Delete All ?',
        html: `
            <div class="text-center">
                <p class="mb-3">Are you sure you want to delete <strong>ALL comments and activities</strong> for this lead?</p>
                <p class="text-danger small">This action can be reversed by an administrator.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete all!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await api.delete(`/leads/${props.lead.id}/activities_comments/all`)
                return response.data
            } catch (error) {
                window.$swal.showValidationMessage(
                    error.response?.data?.message || 'Failed to delete comments and activities'
                )
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showNotification(`Successfully deleted ${result.value?.data?.deleted_count || 0} comments and activities`, 'success')
            commentListKey.value++
            
        }
    })
}

const confirmDeleteAllComments = () => {
    Swal.fire({
        title: 'Delete All Comments?',
        html: `
            <div class="text-center">
                <p class="mb-3">Are you sure you want to delete <strong>ALL comments</strong> for this lead?</p>
                <p class="text-danger small">This action can be reversed by an administrator.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete all!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await api.delete(`/leads/${props.lead.id}/comments/all`)
                return response.data
            } catch (error) {
                window.$swal.showValidationMessage(
                    error.response?.data?.message || 'Failed to delete comments'
                )
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showNotification(`Successfully deleted ${result.value?.data?.deleted_count || 0} comments`, 'success')
            commentListKey.value++
            
        }
    })
}

const confirmDeleteAllActivities = () => {
    Swal.fire({
        title: 'Delete All Activities?',
        html: `
            <div class="text-center">
                <p class="mb-3">Are you sure you want to delete <strong>ALL activities</strong> for this lead?</p>
                <p class="text-danger small">This action can be reversed by an administrator.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete all!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await api.delete(`/leads/${props.lead.id}/activities/all`)
                return response.data
            } catch (error) {
                window.$swal.showValidationMessage(
                    error.response?.data?.message || 'Failed to delete activities'
                )
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showNotification(`Successfully deleted ${result.value?.data?.deleted_count || 0} activities`, 'success')
            activityListKey.value++
        }
    })
}

defineExpose({
    resetEditMode
})

// Watch for stageId changes from parent
watch(() => props.stageId, (newStageId) => {
    if (newStageId) {
        selectedStageId.value = newStageId
    }
})

// Watch for lead stage id (for stage selector)
watch(() => props.lead?.stage?.id, (newStageId) => {
    if (newStageId && !props.stageId) {
        selectedStageId.value = newStageId
    }
})

// Watch only lead id to avoid deep reactivity and repeated runs when lead is undefined (fixes SweetAlert2 stack overflow)
watch(() => props.lead?.id, (newId, oldId) => {
    if (newId == null && oldId == null) return
    if (newId && newId !== oldId) {
        isEditMode.value = false
        activeViewTab.value = 'comments'
    }
    const lead = props.lead
    if (lead) {
        if (lead.stage_id) selectedStageId.value = lead.stage_id
        else if (lead.stage?.id) selectedStageId.value = lead.stage.id
    }
})

const toggleEditMode = () => {
    isEditMode.value = true
      editingSection.value = null
    selectedStageId.value = props.stageId || props.lead?.stage?.id || null
    scrollEditSectionIntoView()
}
const handleEditSection = (sectionName) => {
      editingSection.value = sectionName
     selectedStageId.value = props.stageId || props.lead?.stage?.id || null
    isEditMode.value = true
    scrollEditSectionIntoView()
}

const handleCancel = () => {
    isEditMode.value = false
}

const onEditBarCancel = () => {
    editLeadRef.value?.handleCancel?.()
}

const onEditBarSave = () => {
    editLeadRef.value?.handleSave?.()
}

const isEditBarSaving = computed(() => editLeadRef.value?.isSubmitting?.value ?? false)

const handleLeadUpdated = (responseData) => {
    try {
        console.log('✅ Lead updated successfully, received data:', responseData)
        
        // Extract the lead data from response
        const updatedLead = responseData?.data || responseData
        
        // Emit update event to parent with the latest lead data
        emit('update:lead', updatedLead)
        
        // Switch back to view mode
        isEditMode.value = false
        
        // Update stage if it changed
        if (updatedLead.stage_id) {
            selectedStageId.value = updatedLead.stage_id
        }
    } catch (error) {
        console.error('Error handling lead update:', error)
    }
}

const handleCommentCreated = (newComment) => {
    if (commentListRef.value && commentListRef.value.addComment) {
        commentListRef.value.addComment(newComment)
    }
}

const handleActivityCreated = (newActivity) => {
    if (activityListRef.value && activityListRef.value.addActivity) {
        activityListRef.value.addActivity(newActivity)
    }
}

onMounted(() => {
    console.log('📝 GeneralTab: Component mounted, isEditMode = false')
    isEditMode.value = false
})
</script><style scoped>
/* GeneralTab Wrapper Styles */
.info-card {
    border: none !important;
    box-shadow: none !important;
}

.edit-icon-btn {
    vertical-align: middle !important;  
}

.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
}

/* Activity/Comment Toggle Styles */
.toggle-buttons-container {
    border: 1px solid #EDEDED;
    box-shadow: 2px 2px 20px 4px #7090B014;
} 

.btn-toggle {
    background: none;
    border: none;
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.compact-status-section {
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    background: #fff;
    padding: 4px !important;
    box-shadow: none !important;
    position: relative;
    top: 0;
    margin-bottom: 8px !important;
}

.compact-status-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
}

.compact-status-card {
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    padding: 4px 6px;
    background: #ffffff;
    min-height: 46px;
}

.compact-status-label {
    font-size: 9px;
    color: #64748b;
    margin-bottom: 3px;
    line-height: 1.1;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 600;
    border-radius: 999px;
    padding: 2px 6px;
    border: 1px solid transparent;
    line-height: 1.15;
}

@media (max-width: 768px) {
    .compact-status-grid {
        grid-template-columns: 1fr;
    }
}

.status-badge--hot { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.status-badge--warm { color: #b45309; background: #fffbeb; border-color: #fde68a; }
.status-badge--cold { color: #0369a1; background: #f0f9ff; border-color: #bae6fd; }
.status-badge--ok { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
.status-badge--sale { color: #7c3aed; background: #f5f3ff; border-color: #ddd6fe; }
.status-badge--rent { color: #0f766e; background: #f0fdfa; border-color: #99f6e4; }
.status-badge--muted { color: #475569; background: #f1f5f9; border-color: #cbd5e1; }
.status-badge--neutral { color: #334155; background: #f8fafc; border-color: #e2e8f0; }

/* Match LeadInfoView section-title typography exactly */
.lead-section-title-match {
    font-size: 12px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
    border-bottom: none !important;
    line-height: 1.2;
}

.btn-toggle.active {
    background: #01062C;
    color: #fff;
    font-weight: 400;
    box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}

/* Spacer so content can scroll above the fixed bar */
.edit-lead-bar-spacer {
    height: 56px;
    width: 100%;
    flex-shrink: 0;
}

/* Global fixed bottom bar: centered Cancel / Save */
.edit-lead-bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px 1rem;
    background: #fff;
    border-top: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
    z-index: 1050;
}

.edit-bar-btn {
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.edit-bar-cancel {
    background: #F4F4F4;
    color: #01062C;
}

.edit-bar-cancel:hover {
    background: #E2E8F0;
}

.edit-bar-save {
    background: #01062C;
    color: #fff;
}

.edit-bar-save:hover:not(:disabled) {
    background: #060a2b;
}

.edit-bar-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.activity-card {
    border: 1px solid #F4F4F4;
}

.lead-created-section {
    border: 1px solid #F4F4F4;
    margin-top: 12px;
}

/* Utility Classes */
.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

.w-fit-content {
    width: fit-content;
}
.admin-controls {
    position: relative;
}

.btn-admin-action {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.btn-delete-all {
    background-color: #FEF2F2;
    color: #EF4444;
    border-color: #FEE2E2;
}

.btn-delete-all:hover {
    background-color: #FEE2E2;
    color: #DC2626;
}

.btn-restore-all {
    background-color: #F0F9FF;
    color: #0284C7;
    border-color: #E0F2FE;
}

.btn-restore-all:hover {
    background-color: #E0F2FE;
    color: #0369A1;
}
</style>
