<template>
    <div class="row g-4">
        <!-- Left Column: Lead Information -->
        <div class="col-md-5">
            <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="modal-title">Lead Information</span>
                </div>
                    <button @click="toggleEditMode" v-if="!isEditMode && lead.can_edit">
                        <iconify-icon class="edit-icon-btn" color="#FAA300" icon="lucide:pencil"></iconify-icon>
                    </button>
                </div>

                <!-- View Mode (read-only; do not use ViewLead.vue here – it is a full modal and would cause infinite recursion) -->
                <LeadInfoView v-if="!isEditMode" :lead="lead" />

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
            />
            <CommentList 
                v-if="activeViewTab === 'comments'" 
                ref="commentListRef"
                :lead-id="lead?.id" 
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
const activeViewTab = ref('comments') // 'activity' or 'comments'
const commentListRef = ref(null)
const activityListRef = ref(null)
const editLeadRef = ref(null)
const resetEditMode = () => {
    console.log('🔄 GeneralTab: Resetting edit mode to false')
    isEditMode.value = false
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
    // Add the new comment to the CommentList
    if (commentListRef.value && commentListRef.value.addComment) {
        commentListRef.value.addComment(newComment)
    }
}

const handleActivityCreated = (newActivity) => {
    // Add the new activity to the ActivityList
    if (activityListRef.value && activityListRef.value.addActivity) {
        activityListRef.value.addActivity(newActivity)
    }
}
onMounted(() => {
    console.log('📝 GeneralTab: Component mounted, isEditMode = false')
    isEditMode.value = false
})
</script>

<style scoped>
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

</style>
