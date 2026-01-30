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
                <div v-if="!isEditMode">

                    <div class="info-group">
                        <label class="info-label">Salutation</label>
                        <span class="info-value">{{ lead?.salutation || '----' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">First Name</label>
                        <span class="info-value">{{ lead?.first_name || '----' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Last Name</label>
                        <span class="info-value">{{ lead?.last_name || '----' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Contact</label>
                        <div class="d-flex align-items-center gap-2">
                            <span class="info-value">{{ lead?.whatsapp_number || '----' }}</span>
                        </div>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Email</label>
                        <div class="d-flex align-items-center gap-2">
                            <span class="info-value">{{ lead?.email || '----' }}</span>
                        </div>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Secondary Phone</label>
                        <span class="info-value">{{ lead?.work_phone_2 || '----' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Comment</label>
                        <p class="info-value text-xs line-height-1-5">
                            {{ lead?.comment || '----' }}
                        </p>
                    </div>

                    <div class="info-group">
                        <label class="info-label">what's your budget</label>
                        <span class="info-value">{{ lead?.budget || '0' }} {{ lead?.currency || 'AED' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Bedrooms</label>
                        <span class="info-value">{{ lead?.bedrooms !== 'Studio' ? `${lead?.bedrooms} BHK` : 'Studio' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Purpose Of Purchase</label>
                        <span class="info-value">{{ lead?.purpose_buying || '----' }}</span>
                    </div>

                    <div class="info-group">
                        <label class="info-label">Source</label>
                        <span class="info-value">{{ lead?.lead_source || '----' }}</span>
                    </div>

                    <div class="info-group mb-3">
                        <label class="info-label">Source Information</label>
                        <span class="info-value">{{ lead?.source_information || '----' }}</span>
                    </div>

                    <!-- Responsible Person -->
                    <div class="responsible-person-box p-3 radius-8 shadow-sm">
                        <label class="info-label mb-3">Responsible Person</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-wrapper">
                                <img 
                                    v-if="!avatarError && lead?.responsible_person?.avatar" 
                                    :src="lead.responsible_person.avatar" 
                                    class="avatar-md rounded-circle" 
                                    @error="handleAvatarError"
                                />
                                <div v-else class="avatar-placeholder">
                                    <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex mb-1">
                                    <span class="text-xs text-secondary-light">Name</span>
                                    <span class="text-xs fw-medium">: {{ lead?.responsible_person?.name || '----' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                <div v-if="activeViewTab === 'activity'" class="activity-input-section">
                    <label class="mb-2 d-block modal-title">Contact Customer</label>
                    <div class="activity-box border radius-12 p-3 position-relative">
                        <textarea 
                            class="form-control border-0 p-0 text-sm shadow-none custom-textarea" 
                            placeholder="Type @ to mention someone" 
                            rows="4"
                            v-model="activityText"
                        ></textarea>
                        <!-- User Avatar -->
                        <div class="activity-avatar">
                            <div class="avatar-wrapper position-relative">
                                <img 
                                    v-if="!avatarError && lead?.responsible_person?.avatar" 
                                    :src="lead.responsible_person.avatar" 
                                    class="avatar-sm rounded-circle" 
                                    @error="handleAvatarError"
                                />
                                <div v-else class="avatar-placeholder-sm">
                                    <iconify-icon icon="lucide:user" class="avatar-icon-sm"></iconify-icon>
                                </div>
                            </div>
                        </div>
                        <!-- Activity Controls -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="reminder-dropdown-wrapper position-relative">
                                    <button 
                                        class="activity-control-btn d-flex align-items-center gap-2"
                                        @click="showReminderDropdown = !showReminderDropdown"
                                    >
                                        <iconify-icon icon="lucide:calendar" class="activity-control-icon"></iconify-icon>
                                        <span class="activity-control-text">{{ displayReminderText }}</span>
                                    </button>
                                    <!-- Reminder Dropdown -->
                                    <div 
                                        v-if="showReminderDropdown" 
                                        class="reminder-dropdown"
                                        @click.stop
                                    >
                                        <div class="reminder-header-option" @click="selectReminderOption('when_starts')">
                                            <span class="reminder-header-text">When event starts</span>
                                            <div 
                                                class="reminder-checkbox"
                                                :class="{ 'checked': selectedReminder === 'when_starts' }"
                                            >
                                                <iconify-icon 
                                                    v-if="selectedReminder === 'when_starts'"
                                                    icon="lucide:check" 
                                                    class="check-icon"
                                                ></iconify-icon>
                                            </div>
                                        </div>
                                        <div class="reminder-options">
                                            <div 
                                                class="reminder-option"
                                                v-for="option in reminderOptions"
                                                :key="option.value"
                                                @click="selectReminderOption(option.value)"
                                            >
                                                <span class="reminder-option-text">{{ option.label }}</span>
                                                <div 
                                                    class="reminder-checkbox"
                                                    :class="{ 'checked': selectedReminder === option.value }"
                                                >
                                                    <iconify-icon 
                                                        v-if="selectedReminder === option.value"
                                                        icon="lucide:check" 
                                                        class="check-icon"
                                                    ></iconify-icon>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Custom Date Option -->
                                        <div class="custom-date-section">
                                            <div 
                                                class="custom-date-input"
                                                @click.stop="showDateTimePicker = true"
                                            >
                                                <iconify-icon icon="lucide:calendar" class="custom-date-icon"></iconify-icon>
                                                <span class="custom-date-text">{{ formattedCustomDate }}</span>
                                                <iconify-icon icon="lucide:chevron-right" class="custom-date-arrow"></iconify-icon>
                                            </div>
                                        </div>
                                        
                                        <!-- DateTime Picker Modal -->
                                        <DateTimePicker
                                            :show="showDateTimePicker"
                                            :model-value="customDate || selectedDateTime"
                                            @update:show="showDateTimePicker = $event"
                                            @update:model-value="handleCustomDateSelected"
                                            @apply="handleCustomDateApply"
                                            @cancel="handleCustomDateCancel"
                                        />
                                    </div>
                                </div>
                                <button class="activity-control-btn">
                                    <iconify-icon icon="lucide:bell" class="activity-control-icon bell-icon"></iconify-icon>
                                </button>
                                <!-- <div class="dropdown">
                                    <button 
                                        class="activity-control-btn d-flex align-items-center gap-2"
                                        type="button"
                                        @click="showActionsDropdown = !showActionsDropdown"
                                    >
                                        <span class="activity-control-text">Actions</span>
                                        <iconify-icon icon="lucide:chevron-up-down" class="activity-control-icon"></iconify-icon>
                                    </button>
                                    <div v-if="showActionsDropdown" class="dropdown-menu show" style="display: block;">
                                        <a class="dropdown-item" href="#" @click.prevent="showActionsDropdown = false">Action 1</a>
                                        <a class="dropdown-item" href="#" @click.prevent="showActionsDropdown = false">Action 2</a>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments View -->
                <div v-if="activeViewTab === 'comments'" class="comment-input-section">
                    <label class="mb-2 d-block modal-title">Contact Customer</label>
                    <div class="comment-box border radius-12 p-3">
                        <textarea class="form-control border-0 p-0 text-sm shadow-none custom-textarea" placeholder="Type @ to mention someone" rows="4"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center gap-3">
                                <button class="icon-btn d-flex align-items-center gap-2" @click="showFileModal = true">
                                    <iconify-icon icon="lucide:paperclip" class="icon-btn-icon"></iconify-icon>
                                    <span class="icon-btn-text">File</span>
                                </button>
                                <button class="icon-btn d-flex align-items-center gap-2">
                                    <iconify-icon icon="lucide:file-text" class="icon-btn-icon"></iconify-icon>
                                    <span class="icon-btn-text">Create Document</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- File Upload Card (shown inline when File button is clicked) -->
                    <div v-if="showFileModal" class="file-upload-card mt-3">
                        <!-- Close Button -->
                        <button class="btn-close-card" @click="handleCancelFileModal">
                            <iconify-icon icon="lucide:x"></iconify-icon>
                        </button>

                        <!-- File Upload Content -->
                        <div class="file-upload-content">
                            <div 
                                class="file-dropzone"
                                :class="{ 'dragover': isDragging }"
                                @dragover.prevent="handleDragOver"
                                @dragleave.prevent="handleDragLeave"
                                @drop.prevent="handleDrop"
                                @click="triggerFileInput"
                            >
                                <input 
                                    ref="fileInput"
                                    type="file" 
                                    multiple 
                                    accept="image/jpeg,image/png,application/pdf"
                                    @change="handleFileSelect"
                                    class="file-input-hidden"
                                />
                                <div class="d-flex align-items-center gap-3 w-100">
                                    <iconify-icon icon="lucide:file-text" class="dropzone-icon"></iconify-icon>
                                    <div class="flex-grow-1">
                                        <p class="dropzone-text mb-1">Drag and drop your files</p>
                                        <p class="dropzone-subtext">JPEG, PNG and PDF formats, up to 50MB</p>
                                    </div>
                                    <button class="btn-select-file" @click.stop="triggerFileInput">
                                        Select File
                                    </button>
                                </div>
                            </div>

                            <!-- Selected Files List -->
                            <div v-if="selectedFiles.length > 0" class="selected-files-list mt-3">
                                <div 
                                    v-for="(file, index) in selectedFiles" 
                                    :key="index"
                                    class="selected-file-item"
                                >
                                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                                        <iconify-icon icon="lucide:file" class="file-item-icon"></iconify-icon>
                                        <div class="flex-grow-1">
                                            <p class="file-item-name mb-0">{{ file.name }}</p>
                                            <p class="file-item-size mb-0">{{ formatFileSize(file.size) }}</p>
                                        </div>
                                    </div>
                                    <button 
                                        class="btn-remove-file" 
                                        @click="removeFile(index)"
                                    >
                                        <iconify-icon icon="lucide:x"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Action Buttons -->
                <div class="modal-footer-custom">
                    <button class="btn-cancel" @click="handleClose">
                        Cancel
                    </button>
                    <button class="btn-save" @click="handleSave">
                        Save
                    </button>
                </div>
            </div>

            <!-- Lead Activity List -->
            <div class="lead-activity-section mt-4">
                <p class="lead-activity-title mb-4">Lead Activity</p>
                <div class="activity-timeline">
                    <div 
                        v-for="(group, groupIndex) in groupedActivities" 
                        :key="groupIndex"
                        class="activity-group"
                    >
                        <!-- Date Header -->
                        <div class="timeline-date-header">
                            <div class="timeline-indicator-wrapper">
                                <div 
                                    class="timeline-icon"
                                    :class="group.type === 'today' ? 'timeline-icon-check' : 'timeline-icon-info'"
                                >
                                    <iconify-icon 
                                        v-if="group.type === 'today'"
                                        icon="lucide:check" 
                                        class="timeline-icon-content"
                                    ></iconify-icon>
                                    <iconify-icon 
                                        v-else
                                        icon="lucide:info" 
                                        class="timeline-icon-content"
                                    ></iconify-icon>
                                </div>
                                <div 
                                    class="timeline-line"
                                    :class="{ 'last-group': groupIndex === groupedActivities.length - 1 }"
                                ></div>
                            </div>
                            <div class="date-header-text">{{ group.dateLabel }}</div>
                        </div>

                        <!-- Activity Cards -->
                        <div class="activity-cards-wrapper">
                            <div 
                                v-for="(activity, activityIndex) in group.activities" 
                                :key="activityIndex"
                                class="activity-card-item"
                            >
                                <!-- Activity Header -->
                                <div class="activity-card-header">
                                    <div class="activity-type-wrapper">
                                        <div 
                                            class="activity-type-icon"
                                            :class="activity.typeIconClass"
                                        >
                                            <iconify-icon 
                                                :icon="activity.typeIcon" 
                                                class="activity-type-icon-content"
                                            ></iconify-icon>
                                        </div>
                                        <span class="activity-type-label">{{ activity.typeLabel }}</span>
                                    </div>
                                    <div class="activity-time">{{ activity.time }}</div>
                                </div>

                                <!-- Activity Content -->
                                <div class="activity-card-body">
                                    <div class="activity-main-content">
                                        <!-- Task Activity -->
                                        <template v-if="activity.activityType === 'task'">
                                            <div class="task-details">
                                                <div class="task-icon-wrapper">
                                                    <div class="task-icon">
                                                        <iconify-icon icon="lucide:clock" class="task-icon-content"></iconify-icon>
                                                    </div>
                                                </div>
                                                <div class="task-info">
                                                    <div class="task-title">{{ activity.taskTitle }}</div>
                                                    <div class="task-deadline">
                                                        <iconify-icon icon="lucide:calendar" class="deadline-icon"></iconify-icon>
                                                        <span>Deadline : {{ activity.deadline }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Lead Created Activity -->
                                        <template v-else-if="activity.activityType === 'lead_created'">
                                            <div class="lead-created-details">
                                                <div class="detail-row">
                                                    <span class="detail-label">Lead Name</span>
                                                </div>
                                                <div class="detail-value">{{ activity.leadName }}</div>
                                                <div class="detail-row mt-2">
                                                    <span class="detail-label">Source</span>
                                                </div>
                                                <div class="detail-value">{{ activity.source }}</div>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- User Avatar -->
                                    <div class="activity-user-avatar">
                                        <img 
                                            v-if="activity.userAvatar" 
                                            :src="activity.userAvatar" 
                                            class="user-avatar-img"
                                            alt="User"
                                        />
                                        <div v-else class="user-avatar-placeholder">
                                            <iconify-icon icon="lucide:user" class="user-avatar-icon"></iconify-icon>
                                        </div>
                                    </div>
                                </div>

                                <!-- Activity Actions -->
                                <div class="activity-card-footer">
                                    <div class="activity-actions-left">
                                        <!-- Complete Button (for pending tasks) -->
                                        <button 
                                            v-if="activity.status === 'pending'"
                                            class="btn-complete"
                                            @click="completeTask(activity)"
                                        >
                                            <iconify-icon icon="lucide:check" class="btn-icon"></iconify-icon>
                                            Complete
                                        </button>
                                        <!-- Edit Button (for pending tasks) -->
                                        <button 
                                            v-if="activity.status === 'pending'"
                                            class="btn-edit"
                                            @click="editTask(activity)"
                                        >
                                            <iconify-icon icon="lucide:pencil" class="btn-icon"></iconify-icon>
                                            Edit
                                        </button>
                                        <!-- Repeat Button (for completed tasks) -->
                                        <button 
                                            v-if="activity.status === 'completed' && activity.activityType === 'task'"
                                            class="btn-repeat"
                                            @click="repeatTask(activity)"
                                        >
                                            <iconify-icon icon="lucide:repeat" class="btn-icon"></iconify-icon>
                                            Repeat
                                        </button>
                                    </div>
                                    <div class="activity-actions-right">
                                        <!-- Notification Bell (only for pending tasks) -->
                                        <button 
                                            v-if="activity.status === 'pending'"
                                            class="action-icon-btn"
                                            @click="toggleNotification(activity)"
                                        >
                                            <iconify-icon icon="lucide:bell" class="action-icon"></iconify-icon>
                                        </button>
                                        <!-- Comment -->
                                        <button 
                                            v-if="activity.hasComment"
                                            class="action-icon-btn"
                                            @click="viewComments(activity)"
                                        >
                                            <iconify-icon icon="lucide:file-text" class="action-icon"></iconify-icon>
                                            <span class="action-text">Comment</span>
                                        </button>
                                        <!-- More Options -->
                                        <button 
                                            class="action-icon-btn"
                                            @click="showActivityMenu(activity)"
                                        >
                                            <iconify-icon icon="lucide:more-vertical" class="action-icon"></iconify-icon>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Show Older Link -->
                    <div class="show-older-wrapper">
                        <button class="show-older-link" @click="loadOlderActivities">
                            <iconify-icon icon="lucide:chevron-down" class="show-older-icon"></iconify-icon>
                            <span>Show older</span>
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue'
import EditLead from './EditLead.vue'
import DateTimePicker from './DateTimePicker.vue'

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
const avatarError = ref(false)
const selectedStageId = ref(props.stageId || props.lead?.stage?.id || null)
const activeViewTab = ref('comments') // 'activity' or 'comments'
const showFileModal = ref(false)
const selectedFiles = ref([])
const files = ref([]) // Persistent files array
const isDragging = ref(false)
const fileInput = ref(null)
const activityText = ref('')
const showActionsDropdown = ref(false)
const selectedDateTime = ref(new Date())
const showReminderDropdown = ref(false)
const selectedReminder = ref(null) // Single select - changed from array to single value
const customDate = ref(null)
const showDateTimePicker = ref(false)

// Dummy activities data
const activities = ref([
    {
        id: 1,
        activityType: 'task',
        typeLabel: 'Contact Customer',
        typeIcon: 'lucide:check',
        typeIconClass: 'activity-icon-orange',
        time: '10:10 AM',
        status: 'pending',
        taskTitle: 'Today Meeting with Customer',
        deadline: 'Thu, January 2025',
        userAvatar: null,
        hasComment: true,
        date: new Date(),
        dateLabel: 'TODAY'
    },
    {
        id: 2,
        activityType: 'task',
        typeLabel: 'Contact Customer',
        typeIcon: 'lucide:check',
        typeIconClass: 'activity-icon-orange',
        time: '10:10 AM',
        status: 'completed',
        taskTitle: 'Today Meeting with Customer',
        deadline: 'Thu, January 2025',
        userAvatar: null,
        hasComment: true,
        date: new Date(2025, 7, 15), // August 15, 2025
        dateLabel: '15 AUG, 2025'
    },
    {
        id: 3,
        activityType: 'lead_created',
        typeLabel: 'Lead Created',
        typeIcon: 'lucide:info',
        typeIconClass: 'activity-icon-blue',
        time: '3:15 PM',
        status: 'completed',
        leadName: 'Compleate CRM From "Mamsha Gardens Plots"',
        source: 'Mata Ads - Lead Form',
        userAvatar: null,
        hasComment: false,
        date: new Date(2025, 7, 1), // August 1, 2025
        dateLabel: '1 AUG, 2025'
    }
])

// Group activities by date
const groupedActivities = computed(() => {
    const groups = {}
    
    activities.value.forEach(activity => {
        const dateKey = activity.dateLabel
        
        if (!groups[dateKey]) {
            groups[dateKey] = {
                dateLabel: dateKey,
                type: dateKey === 'TODAY' ? 'today' : 'past',
                activities: []
            }
        }
        
        groups[dateKey].activities.push(activity)
    })
    
    // Convert to array and sort by date (newest first)
    return Object.values(groups).sort((a, b) => {
        if (a.dateLabel === 'TODAY') return -1
        if (b.dateLabel === 'TODAY') return 1
        return new Date(b.dateLabel) - new Date(a.dateLabel)
    })
})

// Activity methods
const completeTask = (activity) => {
    console.log('Complete task:', activity)
    activity.status = 'completed'
}

const editTask = (activity) => {
    console.log('Edit task:', activity)
}

const repeatTask = (activity) => {
    console.log('Repeat task:', activity)
}

const toggleNotification = (activity) => {
    console.log('Toggle notification:', activity)
}

const viewComments = (activity) => {
    console.log('View comments:', activity)
}

const showActivityMenu = (activity) => {
    console.log('Show activity menu:', activity)
}

const loadOlderActivities = () => {
    console.log('Load older activities')
}

// Reminder options
const reminderOptions = [
    { label: '15 minutes before', value: '15min' },
    { label: '30 minutes before', value: '30min' },
    { label: '1 hour before', value: '1hour' },
    { label: '2 hours before', value: '2hours' },
    { label: '1 day before', value: '1day' }
]

// Format date/time for display
const formattedDateTime = computed(() => {
    const date = selectedDateTime.value
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    
    const dayName = days[date.getDay()]
    const monthName = months[date.getMonth()]
    const day = date.getDate()
    const hours = date.getHours()
    const minutes = date.getMinutes()
    const ampm = hours >= 12 ? 'pm' : 'am'
    const displayHours = hours % 12 || 12
    const displayMinutes = minutes < 10 ? `0${minutes}` : minutes
    
    return `${dayName}, ${monthName} ${day}, ${displayHours}:${displayMinutes} ${ampm}`
})

// Format custom date for display
const formattedCustomDate = computed(() => {
    if (!customDate.value) {
        return formattedDateTime.value
    }
    const date = new Date(customDate.value)
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    
    const dayName = days[date.getDay()]
    const monthName = months[date.getMonth()]
    const day = date.getDate()
    const hours = date.getHours()
    const minutes = date.getMinutes()
    const ampm = hours >= 12 ? 'pm' : 'am'
    const displayHours = hours % 12 || 12
    const displayMinutes = minutes < 10 ? `0${minutes}` : minutes
    
    return `${dayName}, ${monthName} ${day}, ${displayHours}:${displayMinutes} ${ampm}`
})

// Display text for reminder button
const displayReminderText = computed(() => {
    if (customDate.value) {
        return formattedCustomDate.value
    }
    
    if (!selectedReminder.value) {
        return formattedDateTime.value
    }
    
    if (selectedReminder.value === 'when_starts') {
        return 'When event starts'
    }
    
    const option = reminderOptions.find(opt => opt.value === selectedReminder.value)
    return option ? option.label : formattedDateTime.value
})

// Select reminder option (single select)
const selectReminderOption = (value) => {
    // If clicking the same option, deselect it
    if (selectedReminder.value === value) {
        selectedReminder.value = null
    } else {
        selectedReminder.value = value
        // Clear custom date when selecting preset reminders
        customDate.value = null
    }
}

// Handle custom date selection
const handleCustomDateSelected = (date) => {
    customDate.value = date
    // Clear preset reminders when selecting custom date
    selectedReminder.value = null
}

// Handle custom date apply
const handleCustomDateApply = (date) => {
    customDate.value = date
    selectedReminder.value = null
    showReminderDropdown.value = false
}

// Handle custom date cancel
const handleCustomDateCancel = () => {
    // Just close the picker, don't change anything
}

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

// Watch for lead changes to reset avatar error
watch(() => props.lead?.responsible_person?.avatar, () => {
    avatarError.value = false
})

const handleAvatarError = () => {
    avatarError.value = true
}

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

const handleClose = () => {
    // Reset activity text and close dropdowns
    activityText.value = ''
    showActionsDropdown.value = false
    // Handle other close actions if needed
}

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (!event.target.closest('.dropdown')) {
        showActionsDropdown.value = false
    }
    if (!event.target.closest('.reminder-dropdown-wrapper')) {
        showReminderDropdown.value = false
    }
}

// Add click listener when component mounts
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const handleSave = () => {
    // Add selected files to the persistent files array if any
    if (selectedFiles.value.length > 0) {
        files.value.push(...selectedFiles.value)
        console.log('Files saved:', files.value)
        // Clear selected files after saving
        selectedFiles.value = []
        showFileModal.value = false
    }
    // Handle other save actions here
}

// File upload handlers
const triggerFileInput = () => {
    fileInput.value?.click()
}

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files)
    addFiles(files)
    // Reset input so same file can be selected again
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const handleDragOver = (event) => {
    event.preventDefault()
    isDragging.value = true
}

const handleDragLeave = (event) => {
    event.preventDefault()
    isDragging.value = false
}

const handleDrop = (event) => {
    event.preventDefault()
    isDragging.value = false
    const files = Array.from(event.dataTransfer.files)
    addFiles(files)
}

const addFiles = (files) => {
    const maxSize = 50 * 1024 * 1024 // 50MB in bytes
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf']
    
    files.forEach(file => {
        // Validate file type
        if (!allowedTypes.includes(file.type)) {
            alert(`${file.name} is not a valid file type. Please upload JPEG, PNG, or PDF files only.`)
            return
        }
        
        // Validate file size
        if (file.size > maxSize) {
            alert(`${file.name} exceeds the 50MB size limit.`)
            return
        }
        
        // Check if file already exists
        const exists = selectedFiles.value.some(f => f.name === file.name && f.size === file.size)
        if (!exists) {
            selectedFiles.value.push(file)
        }
    })
}

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1)
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

const handleCancelFileModal = () => {
    showFileModal.value = false
    selectedFiles.value = []
    isDragging.value = false
    // Clear file input
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>

<style scoped>
.info-card {
    border: 1px solid #F4F4F4;
}

.activity-card {
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

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.info-label {
    display: block;
    font-size: 12px;
    font-weight: 300;
    color: #666666;
    margin-top: 5px;
    line-height: 10px;
}

.info-value {
    font-size: 12px;
    font-weight: 500;
    color: #000000;
}

.info-group {
    margin-bottom: 15px;
}

/* Form Styles for Edit Mode */
.form-label-custom {
    display: block;
    font-size: 12px;
    font-weight: 300;
    color: #666666;
    margin-top: 5px;
    margin-bottom: 5px;
    line-height: 10px;
}

.custom-input, .custom-textarea {
    height: 42px !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 13px !important;
    color: #000000 !important;
    font-family: 'Montserrat';
}

.custom-textarea {
    height: 100px !important;
    padding: 12px 15px !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.custom-textarea:focus {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.custom-input::placeholder, .custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

/* Custom v-select styles */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.custom-v-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select .vs__selected) {
    font-size: 13px;
    color: #000000;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px; 
}

:deep(.custom-v-select .vs__search) {
    font-size: 13px;
    color: #000000;
    margin: 0;
    padding: 0;
}

:deep(.custom-v-select .vs__search::placeholder) {
    color: #64748B;
}

:deep(.custom-v-select .vs__actions) {
    padding: 0 8px;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
}

:deep(svg) {
    vertical-align: middle !important;
}

:deep(.custom-v-select .vs__dropdown-menu) {
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    margin-top: 5px;
    z-index: 1100;
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 5px 10px;
    font-size: 14px;
    color: #475569;
    transition: all 0.2s;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #FAA300 !important;
    color: #fff !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: #FAA300;
    color: #fff;
}

/* Inline v-select for input groups */
.input-group-custom {
    display: flex;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    overflow: visible;
    align-items: stretch;
    position: relative;
}

.input-group-custom .custom-input {
    border: none !important;
    flex-grow: 1 !important;
    border-radius: 10px 0 0 10px !important;
    padding: 0 8px !important;
}

:deep(.custom-v-select-inline) {
    width: 100px;
    min-width: 100px;
    position: relative;
}

:deep(.custom-v-select-inline .vs__dropdown-toggle) {
    height: 42px !important;
    border: none !important;
    border-left: 1px solid #E2E8F0 !important;
    border-radius: 0 10px 10px 0 !important;
    padding: 0 !important;
    background: #fff !important;
    display: flex;
    align-items: center;
    cursor: pointer;
}

:deep(.custom-v-select-inline .vs__selected-options) {
    padding: 0 0 0 8px !important;
    margin: 0 !important;
    flex-basis: auto !important;
    flex-grow: 1;
    display: flex;
    align-items: center;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select-inline .vs__selected) {
    color: #000000 !important;
    font-size: 13px !important;
    margin: 0 !important;
    padding: 0 !important;
    position: static !important;
    line-height: normal !important;
    background: transparent !important;
    border: none !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block !important;
}

:deep(.custom-v-select-inline .vs__actions) {
    padding: 0 8px 0 4px !important;
    margin: 0 !important;
    display: flex;
    align-items: center;
    cursor: pointer;
}

:deep(.custom-v-select-inline .vs__search) {
    display: none !important;
}

:deep(.custom-v-select-inline .vs__dropdown-menu) {
    width: 150px !important;
    min-width: 150px !important;
    left: auto !important;
    right: 0 !important;
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0px;
    margin-top: 5px;
    z-index: 9999 !important;
    position: absolute !important;
}

:deep(.custom-v-select-inline .vs__dropdown-option) {
    font-size: 14px;
    color: #475569;
    transition: all 0.2s;
    margin: 1px;
}

:deep(.custom-v-select-inline .vs__dropdown-option--highlight) {
    background: #FAA300 !important;
    color: #fff !important;
}

:deep(.custom-v-select-inline .vs__dropdown-option--selected) {
    background: #FAA300;
    color: #fff;
}

:deep(.custom-v-select-inline .vs__open-indicator) {
    cursor: pointer;
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(.custom-v-select-inline .vs__open-indicator > span) {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(.custom-v-select-inline .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
}

.responsible-person-box {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.match-card {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.match-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.avatar-wrapper {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.avatar-md {
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon {
    font-size: 24px;
    color: #9CA3AF;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.stage-dot-small {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

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

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
}

.activity-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
    padding-right: 60px !important;
}

.activity-avatar {
    position: absolute;
    top: 12px;
    right: 12px;
}

.avatar-status-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background: #FAA300;
    border: 2px solid #fff;
    border-radius: 50%;
}

.avatar-placeholder-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon-sm {
    font-size: 16px;
    color: #9CA3AF;
}

.activity-control-btn {
    background: transparent;
    border: 1px solid rgba(237, 237, 237, 1);
    padding: 6px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.activity-control-btn:hover {
    background: #F8FAFC;
}

.activity-control-icon {
    font-size: 16px;
    color: #64748B;
}

.activity-control-icon.bell-icon {
    color: #FAA300;
}

.activity-control-text {
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1000;
    min-width: 160px;
    padding: 8px 0;
    margin: 4px 0 0;
    background-color: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
    display: block;
    padding: 8px 16px;
    font-size: 14px;
    color: #01062C;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #F8FAFC;
    color: #01062C;
}

.notification-btn {
    background: none;
    border: none;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.icon-btn {
    background: transparent;
    border: none;
    padding: 4px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.icon-btn:hover {
    background: #E9ECEF;
}

.icon-btn-icon {
    font-size: 16px;
    color: #979797;
}

.icon-btn-text {
    font-size: 13px;
    font-weight: 400;
    color: #979797;
    white-space: nowrap;
}

.bg-light-gray {
    background-color: #F8FAFC;
}

.bg-success-soft {
    background-color: #D1FAE5;
}

.text-success {
    color: #10B981;
}

.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

.h-fit-content {
    height: fit-content;
}

.timeline-date {
    padding-left: 44px;
}

.w-fit-content {
    width: fit-content;
}
.modal-footer-custom {
    padding-top: 15px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    border-top: 1px solid #F1F5F9;
}

.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 5px 20px;
    border-radius: 100px;
    font-size: 14px;
    color: #01062C;
    cursor: pointer;
}

.btn-cancel:hover {
    background: #E2E8F0 !important;
}

.btn-save {
    background: #01062C;
    border: none;
    padding: 5px 20px;
    border-radius: 100px;
    font-size: 14px;
    color: #fff;
    font-weight: 400;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-save:hover {
    background: #060a2b;
}

/* File Upload Card Styles */
.file-upload-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.btn-close-card {
    position: absolute;
    top: 12px;
    right: 12px;
    background: transparent;
    border: none;
    padding: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    color: #64748B;
    transition: all 0.2s;
    z-index: 10;
}

.btn-close-card:hover {
    background: #F1F5F9;
    color: #01062C;
}

.btn-close-card iconify-icon {
    font-size: 20px;
}

.file-upload-content {
    padding: 24px;
    flex: 1;
}

.file-dropzone {
    background: #F8FAFC;
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    padding: 32px 24px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 150px;
    display: flex;
    align-items: center;
}

.file-dropzone:hover {
    border-color: #01062C;
    background: #F1F5F9;
}

.file-dropzone.dragover {
    border-color: #01062C;
    background: #E2E8F0;
    border-style: solid;
}

.file-input-hidden {
    display: none;
}

.dropzone-icon {
    font-size: 48px;
    color: #94A3B8;
    flex-shrink: 0;
}

.dropzone-text {
    font-size: 16px;
    font-weight: 500;
    color: #01062C;
    margin: 0;
}

.dropzone-subtext {
    font-size: 13px;
    color: #64748B;
    margin: 0;
}

.btn-select-file {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}

.btn-select-file:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.selected-files-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.selected-file-item {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.file-item-icon {
    font-size: 20px;
    color: #64748B;
    flex-shrink: 0;
}

.file-item-name {
    font-size: 14px;
    font-weight: 500;
    color: #01062C;
    margin: 0;
}

.file-item-size {
    font-size: 12px;
    color: #64748B;
    margin: 0;
}

.btn-remove-file {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: #64748B;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
    flex-shrink: 0;
}

.btn-remove-file:hover {
    background: #FEE2E2;
    color: #DC2626;
}

/* Reminder Dropdown Styles */
.reminder-dropdown-wrapper {
    position: relative;
}

.reminder-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    min-width: 280px;
    padding: 12px 0;
    z-index: 1000;
    border: 1px solid #E2E8F0;
}

.reminder-header-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    cursor: pointer;
    transition: background 0.2s;
    margin-bottom: 4px;
}

.reminder-header-option:hover {
    background: #F8FAFC;
}

.reminder-header-text {
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    flex: 1;
}

.reminder-options {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.reminder-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.2s;
}

.reminder-option:hover {
    background: #F8FAFC;
}

.reminder-option-text {
    font-size: 13px;
    font-weight: 400;
    color: #475569;
    flex: 1;
}

.reminder-checkbox {
    width: 18px;
    height: 18px;
    border: 1.5px solid #CBD5E1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    transition: all 0.2s;
    flex-shrink: 0;
}

.reminder-checkbox.checked {
    background: #FAA300;
    border-color: #FAA300;
}

.check-icon {
    font-size: 12px;
    color: #fff;
    font-weight: bold;
}

.custom-date-section {
    padding: 8px 16px;
    margin-top: 4px;
    border-top: 1px solid #F1F5F9;
}

.custom-date-input {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}

.custom-date-input:hover {
    border-color: #CBD5E1;
    background: #F8FAFC;
}

.custom-date-icon {
    font-size: 16px;
    color: #64748B;
    flex-shrink: 0;
}

.custom-date-text {
    font-size: 13px;
    font-weight: 400;
    color: #475569;
    flex: 1;
    text-align: left;
}

.custom-date-arrow {
    font-size: 16px;
    color: #64748B;
    flex-shrink: 0;
}

/* Lead Activity Timeline Styles */
.lead-activity-section {
    margin-top: 20px;
}

.lead-activity-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
    margin-bottom: 12px;
}

.activity-timeline {
    position: relative;
}

.activity-group {
    margin-bottom: 20px;
}

.timeline-date-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    position: relative;
}

.timeline-indicator-wrapper {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 20px;
    flex-shrink: 0;
}

.timeline-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    position: relative;
}

.timeline-icon-check {
    background: #3B82F6;
}

.timeline-icon-info {
    background: #1E293B;
}

.timeline-icon-content {
    font-size: 10px;
    color: #fff;
}

.timeline-line {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    background: #E5E7EB;
    height: calc(100% + 12px);
    z-index: 1;
}

.timeline-line.last-group {
    display: none;
}

.date-header-text {
    font-size: 12px;
    font-weight: 500;
    color: #666666;
    padding-top: 2px;
}

.activity-cards-wrapper {
    margin-left: 32px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-card-item {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.08);
    padding: 12px;
    border: 1px solid #F4F4F4;
}

.activity-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.activity-type-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
}

.activity-type-icon {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.activity-icon-orange {
    background: #FAA300;
}

.activity-icon-blue {
    background: #3B82F6;
}

.activity-type-icon-content {
    font-size: 10px;
    color: #fff;
}

.activity-type-label {
    font-size: 12px;
    font-weight: 400;
    color: #000000;
}

.activity-time {
    font-size: 12px;
    font-weight: 400;
    color: #999999;
}

.activity-card-body {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.activity-main-content {
    flex: 1;
}

.task-details {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.task-icon-wrapper {
    flex-shrink: 0;
}

.task-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #E0F2FE;
    display: flex;
    align-items: center;
    justify-content: center;
}

.task-icon-content {
    font-size: 18px;
    color: #0EA5E9;
}

.task-info {
    flex: 1;
}

.task-title {
    font-size: 12px;
    font-weight: 600;
    color: #000000;
    margin-bottom: 5px;
}

.task-deadline {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 400;
    color: #000000;
}

.deadline-icon {
    font-size: 12px;
    color: #666666;
}

.lead-created-details {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.detail-row {
    margin-top: 6px;
}

.detail-row:first-child {
    margin-top: 0;
}

.detail-label {
    font-size: 12px;
    font-weight: 400;
    color: #999999;
    display: block;
    margin-bottom: 3px;
}

.detail-value {
    font-size: 12px;
    font-weight: 400;
    color: #000000;
}

.activity-user-avatar {
    flex-shrink: 0;
}

.user-avatar-img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

.user-avatar-placeholder {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.user-avatar-icon {
    font-size: 12px;
    color: #9CA3AF;
}

.activity-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #F1F5F9;
}

.activity-actions-left {
    display: flex;
    align-items: center;
    gap: 6px;
}

.activity-actions-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-complete {
    background: #01062C;
    border: none;
    border-radius: 8px;
    padding: 5px 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
    font-weight: 400;
    color: #fff;
}

.btn-complete:hover {
    background: #060a2b;
}

.btn-edit,
.btn-repeat {
    background: #fff;
    border: 1px solid #EDEDED;
    border-radius: 8px;
    padding: 5px 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
    font-weight: 400;
    color: #666666;
}

.btn-edit:hover,
.btn-repeat:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.btn-icon {
    font-size: 12px;
    color: inherit;
}

.action-icon-btn {
    background: transparent;
    border: none;
    padding: 3px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    color: #666666;
}

.action-icon-btn:hover {
    color: #01062C;
}

.action-icon {
    font-size: 12px;
    color: inherit;
}

.action-text {
    font-size: 12px;
    font-weight: 400;
    color: inherit;
}

.show-older-wrapper {
    margin-left: 32px;
    margin-top: 6px;
    padding-top: 12px;
}

.show-older-link {
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 400;
    color: #3B82F6;
    padding: 0;
    transition: all 0.2s;
}

.show-older-link:hover {
    color: #2563EB;
}

.show-older-icon {
    font-size: 12px;
    color: inherit;
}


</style>
