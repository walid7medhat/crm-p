<template>
    <div class="row g-4">
        <!-- Left Column: Lead Information -->
        <div class="col-md-4">
            <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="modal-title">Lead Information</span>
                </div>
                    <button @click="toggleEditMode" v-if="!isEditMode">
                        <iconify-icon class="edit-icon-btn" color="#FAA300" icon="lucide:pencil"></iconify-icon>
                    </button>
                </div>

                <!-- View Mode -->
                <ViewLead v-if="!isEditMode" :lead="lead" />

                <!-- Edit Mode -->
                <EditLead 
                    v-else 
                    :lead="lead"
                    :stage-id="selectedStageId"
                    @updated="handleLeadUpdated"
                    @cancel="handleCancel"
                />
            </div>
        </div>

        <!-- Right Column: Activity & Comments -->
        <div class="col-md-8">
            <div class="activity-card bg-white p-3 radius-12 shadow-sm">
                <!-- Activity/Comments Toggle -->
                <div class="d-flex gap-2 mb-4 p-1 radius-100 w-fit-content toggle-buttons-container">
                    <button 
                        class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100"
                        :class="{ active: activeViewTab === 'activity' }"
                        @click="activeViewTab = 'activity'"
                    >
                        <iconify-icon icon="lucide:clock-3"></iconify-icon>
                        Activity
                    </button>
                    <button 
                        class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100"
                        :class="{ active: activeViewTab === 'comments' }"
                        @click="activeViewTab = 'comments'"
                    >
                        <iconify-icon icon="lucide:message-square"></iconify-icon>
                        Comments
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
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import EditLead from './EditLead.vue'
import ViewLead from './ViewLead.vue'
import ActivitySection from './ActivitySection.vue'
import CommentsSection from './CommentsSection.vue'
import ActivityList from './ActivityList.vue'
import CommentList from './CommentList.vue'

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

// Watch for stageId changes from parent
watch(() => props.stageId, (newStageId) => {
    if (newStageId) {
        selectedStageId.value = newStageId
    }
})

// Watch for lead changes
watch(() => props.lead?.stage?.id, (newStageId) => {
    if (newStageId && !props.stageId) {
        selectedStageId.value = newStageId
    }
})

const toggleEditMode = () => {
    isEditMode.value = true
    selectedStageId.value = props.stageId || props.lead?.stage?.id || null
}

const handleCancel = () => {
    isEditMode.value = false
}

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
