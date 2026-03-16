<template>
    <div class="row g-4">
        <!-- Left Column: Lead Information -->
        <div class="col-md-5">
            <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="modal-title">Lead Information</span>
                </div>
                    <button @click="toggleEditMode" v-if="!isEditMode && lead?.can_edit">
                        <iconify-icon class="edit-icon-btn" color="#FAA300" icon="lucide:pencil"></iconify-icon>
                    </button>
                </div>

                <!-- View Mode (read-only; do not use ViewLead.vue here – it is a full modal and would cause infinite recursion) -->
                <LeadInfoView v-if="!isEditMode" :lead="lead"   @person-updated="handlePersonUpdated" />

                <!-- Edit Mode (footer Save/Cancel moved to global bottom bar below) -->
                <EditLead 
                    v-else 
                    ref="editLeadRef"
                    :lead="lead"
                    :stage-id="selectedStageId"
                    @updated="handleLeadUpdated"
                    @cancel="handleCancel"
                />
            </div>
        </div>

        <!-- Right Column: Activity & Comments -->
        <div class="col-md-7">
            <div class="activity-card bg-white p-3 radius-12 shadow-sm">
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
                        <!-- زر حذف الكل للتعليقات -->
                        <button 
                            v-if="activeViewTab === 'comments'"
                            class="btn-admin-action btn-delete-all"
                            @click="confirmDeleteAllComments"
                            title="Delete all comments"
                        >
                            <iconify-icon icon="lucide:trash-2" width="18" height="18"></iconify-icon>
                            <span>Delete All </span>
                        </button>
                        
                        <!-- زر حذف الكل للأنشطة -->
                        <button 
                            v-if="activeViewTab === 'activity'"
                            class="btn-admin-action btn-delete-all"
                            @click="confirmDeleteAllActivities"
                            title="Delete all activities"
                        >
                            <iconify-icon icon="lucide:trash-2" width="18" height="18"></iconify-icon>
                            <span>Delete All </span>
                        </button>
                        
                        <!-- زر استعادة (اختياري) - يمكن إضافته في وضع خاص -->
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
            <LeadCreatedCard v-if="lead?.id" :lead="lead" />
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
import { ref, computed, watch, onMounted } from 'vue'
import EditLead from '../editLead/EditLead.vue'
import LeadInfoView from './LeadInfoView.vue'
import LeadCreatedCard from './LeadCreatedCard.vue'
import ActivitySection from './ActivitySection.vue'
import CommentsSection from './CommentsSection.vue'
import ActivityList from './ActivityList.vue'
import CommentList from './CommentList.vue'
import LeadActivityTimeline from './LeadActivityTimeline.vue'
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

const isEditMode = ref(false)
const selectedStageId = ref(props.stageId || props.lead?.stage?.id || null)
const activeViewTab = ref('comments')
const commentListRef = ref(null)
const activityListRef = ref(null)
const editLeadRef = ref(null)
const commentListKey = ref(0)
const activityListKey = ref(0)

// استخدام window.$showNotification بدلاً من toast
const showNotification = (message, type = 'success') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(message)
    }
}

const resetEditMode = () => {
    console.log('🔄 GeneralTab: Resetting edit mode to false')
    isEditMode.value = false
}
const handlePersonUpdated = (updatedPerson) => {
    // تحديث بيانات الـ lead في الـ parent
    if (selectedLead.value) {
        selectedLead.value.responsible_person = {
            id: updatedPerson.id,
            name: updatedPerson.name,
            avatar: updatedPerson.avatar
        }
        selectedLead.value.responsible_person_id = updatedPerson.id
    }
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
    selectedStageId.value = props.stageId || props.lead?.stage?.id || null
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
    border: 1px solid #F4F4F4;
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
