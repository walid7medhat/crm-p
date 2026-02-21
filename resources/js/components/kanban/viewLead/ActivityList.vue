<template>
    <!-- Lead Activity List -->
    <div class="info-card bg-white p-3 radius-12 shadow-sm mt-3">
        <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="modal-title">Lead Activity</span>
        </div>
        </div>
        <div class="activity-timeline">
            <div 
                v-for="(group, groupIndex) in groupedActivities" 
                :key="groupIndex"
                class="activity-group"
            >
                <!-- Timeline Line -->
                <div 
                    class="timeline-line"
                    :class="{ 'last-group': groupIndex === groupedActivities.length - 1 }"
                ></div>
                
                <!-- Date Header -->
                <div class="timeline-date-header">
                    <div class="timeline-indicator-wrapper">
                        <div 
                            class="timeline-icon timeline-icon-check"
                        >
                            <iconify-icon 
                                icon="lucide:circle-check-big" 
                                class="timeline-icon-content"
                            ></iconify-icon>
                        </div>
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
                                    <div class="task-details">
                                        <div class="task-icon-wrapper">
                                            <div class="task-icon" :class="{'task-icon-blue': activity.status === 'Pending', 'task-icon-grey': activity.status === 'Completed', 'task-icon-red': activity.status === 'Overdue'}">
                                                <iconify-icon icon="lucide:alarm-clock" :class="{'task-icon-content-red': activity.status === 'Overdue'}" class="task-icon-content" ></iconify-icon>
                                            </div>
                                        </div>
                                        <div class="task-info">
                                            <!-- Editable Title -->
                                            <div v-if="editingActivityId === activity.id" class="edit-mode">
                                                <input 
                                                    v-model="editForm.title"
                                                    type="text"
                                                    class="edit-input edit-title-input"
                                                    placeholder="Activity title"
                                                />
                                            </div>
                                            <div v-else class="task-title">{{ activity.taskTitle }}</div>
                                            
                                            <!-- Editable Deadline -->
                                            <div v-if="editingActivityId === activity.id" class="edit-mode">
                                                <div class="task-deadline-edit">
                                                    <iconify-icon icon="lucide:calendar" class="deadline-icon"></iconify-icon>
                                                    <button 
                                                        class="deadline-picker-btn"
                                                        @click.stop="openDatePicker(activity)"
                                                    >
                                                        {{ formattedEditDeadline(activity) }}
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-else class="task-deadline">
                                                <iconify-icon icon="lucide:calendar" class="deadline-icon"></iconify-icon>
                                                <span>Deadline : {{ activity.deadline }}</span>
                                            </div>
                                        </div>
                                    </div>
                            </div>

                            <!-- User Avatar -->
                            <div class="activity-user-avatar">
                                <img 
                                    v-if="activity.userAvatar" 
                                    :src="activity.userAvatar" 
                                    class="user-avatar-img"
                                    alt="User"
                                    :title="activity.userName"
                                />
                                <div v-else class="user-avatar-placeholder">
                                    <iconify-icon icon="lucide:user" class="user-avatar-icon"></iconify-icon>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Actions -->
                        <div class="activity-card-footer">
                            <div class="activity-actions-left">
                                <!-- Save/Cancel Buttons (when editing) -->
                                <template v-if="editingActivityId === activity.id">
                                    <button 
                                        class="btn-save-edit"
                                        @click="saveTask(activity)"
                                        :disabled="savingActivityId === activity.id"
                                    >
                                        <iconify-icon icon="lucide:check" class="btn-icon"></iconify-icon>
                                        <span v-if="savingActivityId === activity.id">Saving...</span>
                                        <span v-else>Save</span>
                                    </button>
                                    <button 
                                        class="btn-cancel-edit"
                                        @click="cancelEdit(activity)"
                                        :disabled="savingActivityId === activity.id"
                                    >
                                        Cancel
                                    </button>
                                </template>
                                <!-- Complete/Edit Buttons (when not editing) -->
                                <template v-else>
                                    <!-- Complete Button (for pending/overdue tasks) -->
                                    <button 
                                        v-if="activity.status !== 'Completed'"
                                        class="btn-complete"
                                        @click="completeTask(activity)"
                                    >
                                        <iconify-icon icon="lucide:check" class="btn-icon"></iconify-icon>
                                        Complete
                                    </button>
                                    <!-- Edit Button (for pending/overdue tasks) -->
                                    <button 
                                        v-if="activity.status !== 'Completed'"
                                        class="btn-edit"
                                        @click="editTask(activity)"
                                    >
                                        <iconify-icon icon="lucide:pencil" class="btn-icon"></iconify-icon>
                                        Edit
                                    </button>
                                </template>
                            </div>
                            <div class="activity-actions-right">
                                <!-- Editable Reminders Dropdown (when editing) -->
                                <div 
                                    v-if="editingActivityId === activity.id"
                                    class="reminder-dropdown-wrapper position-relative"
                                >
                                    <button 
                                        class="action-icon-btn"
                                        @click="toggleReminderDropdown(activity)"
                                    >
                                        <iconify-icon icon="lucide:bell" class="action-icon"></iconify-icon>
                                    </button>
                                    <!-- Editable Reminder Options Dropdown -->
                                    <div 
                                        v-if="openReminderDropdownId === activity.id"
                                        class="reminder-dropdown-menu"
                                        @click.stop
                                    >
                                        <div class="reminder-options">
                                            <div 
                                                v-for="option in reminderOptions"
                                                :key="option.value"
                                                class="reminder-option"
                                                @click="toggleReminderOption(option.value)"
                                            >
                                                <span class="reminder-option-text">{{ option.label }}</span>
                                                <div 
                                                    class="reminder-checkbox"
                                                    :class="{ 'checked': editForm.reminders.includes(parseInt(option.value)) }"
                                                >
                                                    <iconify-icon 
                                                        v-if="editForm.reminders.includes(parseInt(option.value))"
                                                        icon="lucide:check" 
                                                        class="check-icon"
                                                    ></iconify-icon>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Read-only Reminders Dropdown (when not editing) -->
                                <div 
                                    v-else
                                    class="reminder-dropdown-wrapper position-relative"
                                >
                                    <button 
                                        class="action-icon-btn"
                                        @click="toggleReminderDropdown(activity)"
                                    >
                                        <iconify-icon icon="lucide:bell" class="action-icon"></iconify-icon>
                                    </button>
                                    <!-- Reminder Options Dropdown -->
                                    <div 
                                        v-if="openReminderDropdownId === activity.id"
                                        class="reminder-dropdown-menu"
                                        @click.stop
                                    >
                                        <div class="reminder-options">
                                            <div 
                                                v-for="option in reminderOptions"
                                                :key="option.value"
                                                class="reminder-option readonly"
                                            >
                                                <span class="reminder-option-text">{{ option.label }}</span>
                                                <div 
                                                    class="reminder-checkbox"
                                                    :class="{ 'checked': isReminderSelected(activity, option.value) }"
                                                >
                                                    <iconify-icon 
                                                        v-if="isReminderSelected(activity, option.value)"
                                                        icon="lucide:check" 
                                                        class="check-icon"
                                                    ></iconify-icon>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- More Options Dropdown -->
                                <div class="activity-menu-wrapper position-relative">
                                    <button 
                                        class="action-icon-btn"
                                        @click="toggleActivityMenu(activity)"
                                    >
                                        <iconify-icon icon="lucide:more-vertical" class="action-icon"></iconify-icon>
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div 
                                        v-if="openMenuId === activity.id"
                                        class="activity-dropdown-menu"
                                        @click.stop
                                    >
                                        <button 
                                            class="activity-dropdown-item"
                                            @click="handleDeleteActivity(activity)"
                                        >
                                            <iconify-icon icon="lucide:trash-2" class="dropdown-item-icon"></iconify-icon>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Show Older Link -->
            <div v-if="hasNextPage" class="show-older-wrapper">
                <button 
                    class="show-older-link" 
                    @click="loadOlderActivities"
                    :disabled="loadingOlder"
                >
                    <iconify-icon icon="lucide:chevron-down" class="show-older-icon"></iconify-icon>
                    <span>{{ loadingOlder ? 'Loading...' : 'Show older' }}</span>
                </button>
            </div>
            
            <!-- Loading State -->
            <div v-if="loading" class="text-center p-4">
                <span class="text-muted">Loading activities...</span>
            </div>
            
            <!-- Empty State -->
            <div v-if="!loading && activities.length === 0" class="text-center p-4">
                <span class="text-muted">No activities found</span>
            </div>
        </div>
        
        <!-- DateTime Picker Modal -->
        <DateTimePicker
            v-if="editingActivityId && showDateTimePicker"
            :show="showDateTimePicker"
            :model-value="getDateTimePickerValue()"
            @update:show="showDateTimePicker = $event"
            @update:model-value="handleDateSelected"
            @apply="handleDateApply"
            @cancel="handleDateCancel"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, getCurrentInstance } from 'vue'
import api from '@/plugins/axios'
import showConfirmation from '@/composables/useConfirmation'
import DateTimePicker from '../shared/DateTimePicker.vue'

const instance = getCurrentInstance()
const $showNotification = (message, type = 'info') => {
    if (instance?.appContext?.config?.globalProperties?.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(message, type)
    } else if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
    }
}

const props = defineProps({
    leadId: {
        type: [Number, String],
        default: null
    }
})

const activities = ref([])
const loading = ref(false)
const loadingOlder = ref(false)
const nextPageUrl = ref(null)
const hasNextPage = computed(() => !!nextPageUrl.value)
const openMenuId = ref(null)
const openReminderDropdownId = ref(null)
const editingActivityId = ref(null)
const savingActivityId = ref(null)
const showDateTimePicker = ref(false)
const editForm = ref({
    title: '',
    deadlineDate: null,
    reminders: []
})

// Reminder options
const reminderOptions = [
    { label: 'When event starts', value: '0' },
    { label: '15 minutes before', value: '15' },
    { label: '30 minutes before', value: '30' },
    { label: '1 hour before', value: '60' },
    { label: '2 hours before', value: '120' },
    { label: '1 day before', value: '1440' }
]

// Check if a reminder option is selected for an activity
const isReminderSelected = (activity, optionValue) => {
    if (!activity.reminders || !Array.isArray(activity.reminders)) {
        return false
    }
    // Convert optionValue to number for comparison (reminders are stored as integers)
    const valueAsNumber = parseInt(optionValue, 10)
    // Also check for string comparison in case reminders are stored as strings
    return activity.reminders.includes(valueAsNumber) || activity.reminders.includes(optionValue)
}

// Toggle reminder dropdown
const toggleReminderDropdown = (activity) => {
    if (openReminderDropdownId.value === activity.id) {
        openReminderDropdownId.value = null
    } else {
        openReminderDropdownId.value = activity.id
    }
}

// Format date to match component format
const formatDateLabel = (dateString) => {
    const date = new Date(dateString)
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    
    const activityDate = new Date(date)
    activityDate.setHours(0, 0, 0, 0)
    
    if (activityDate.getTime() === today.getTime()) {
        return 'TODAY'
    }
    
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']
    const day = date.getDate()
    const month = months[date.getMonth()]
    const year = date.getFullYear()
    
    return `${day} ${month}, ${year}`
}

// Format time to match component format (e.g., "10:10 AM")
const formatTime = (dateString) => {
    const date = new Date(dateString)
    const hours = date.getHours()
    const minutes = date.getMinutes()
    const ampm = hours >= 12 ? 'PM' : 'AM'
    const displayHours = hours % 12 || 12
    const displayMinutes = minutes < 10 ? `0${minutes}` : minutes
    
    return `${displayHours}:${displayMinutes} ${ampm}`
}

// Format deadline date
const formatDeadline = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    
    const day = days[date.getDay()]
    const month = months[date.getMonth()]
    const year = date.getFullYear()
    
    return `${day}, ${month} ${year}`
}

// Transform API response to component format
const transformActivity = (activity) => {
    const status = activity.status || (activity.is_completed ? 'Completed' : 'Pending')
    const normalizedStatus = status
    
    return {
        id: activity.id,
        typeLabel: 'Contact Customer', // Default label
        typeIcon: 'lucide:check',
        typeIconClass: normalizedStatus !== 'Completed' ? 'activity-icon-orange' : 'activity-icon-green',
        time: formatTime(activity.reminder_date),
        status: normalizedStatus, // 'pending', 'completed', 'overdue'
        taskTitle: activity.title || '',
        deadline: formatDeadline(activity.reminder_date),
        userAvatar: activity.user_avatar || null,
        userName: activity.user_name || '',
        date: new Date(activity.created_at),
        dateLabel: formatDateLabel(activity.created_at),
        reminders: activity.reminders || [],
        // Keep original data for API calls
        originalData: activity
    }
}

// Group activities by date
const groupedActivities = computed(() => {
    const groups = {}
    
    activities.value.forEach(activity => {
        const dateKey = activity.dateLabel
        
        if (!groups[dateKey]) {
            groups[dateKey] = {
                dateLabel: dateKey,
                type: dateKey === 'TODAY' ? 'today' : 'past',
                activities: [],
                sortDate: activity.date // Store date for sorting
            }
        }
        
        groups[dateKey].activities.push(activity)
    })
    
    // Convert to array and sort by date (newest first)
    return Object.values(groups).sort((a, b) => {
        if (a.dateLabel === 'TODAY') return -1
        if (b.dateLabel === 'TODAY') return 1
        
        // Sort by the actual date object
        const dateA = a.activities[0]?.date || new Date(0)
        const dateB = b.activities[0]?.date || new Date(0)
        
        return dateB - dateA
    })
})

// Fetch activities from API
const fetchActivities = async () => {
    if (!props.leadId) {
        return
    }
    
    try {
        loading.value = true
        const response = await api.get(`/leads/${props.leadId}/activities`)
        
        // Handle paginated response
        const responseData = response.data
        const activitiesData = responseData.data || []
        
        // Transform activities to match the expected structure
        activities.value = activitiesData.map(transformActivity)
        
        // Store pagination info
        nextPageUrl.value = responseData.links?.next || null
    } catch (error) {
        console.error('Error fetching activities:', error)
        activities.value = []
        nextPageUrl.value = null
        $showNotification('Failed to load activities', 'error')
    } finally {
        loading.value = false
    }
}

// Load older activities (next page)
const loadOlderActivities = async () => {
    if (!nextPageUrl.value || loadingOlder.value) {
        return
    }
    
    try {
        loadingOlder.value = true
        
        // Handle both absolute and relative URLs
        let apiPath = nextPageUrl.value
        try {
            // If it's an absolute URL, extract the path after /api
            if (apiPath.startsWith('http')) {
                const url = new URL(apiPath)
                apiPath = url.pathname + url.search
            }
            
            // Remove /api prefix if present (since axios baseURL already includes it)
            if (apiPath.startsWith('/api')) {
                apiPath = apiPath.substring(4)
            }
        } catch (e) {
            // If URL parsing fails, use as-is
        }
        
        const response = await api.get(apiPath)
        const responseData = response.data
        const activitiesData = responseData.data || []
        
        // Transform and append new activities
        const newActivities = activitiesData.map(transformActivity)
        activities.value = [...activities.value, ...newActivities]
        
        // Update pagination info
        nextPageUrl.value = responseData.links?.next || null
    } catch (error) {
        console.error('Error loading older activities:', error)
        $showNotification('Failed to load older activities', 'error')
    } finally {
        loadingOlder.value = false
    }
}

// Activity methods
const completeTask = async (activity) => {
    try {
        const response = await api.patch(`/leads/activities/${activity.originalData.id}/toggle-completion`)
        
        // Update activity status
        activity.status = 'Completed'
        activity.originalData.is_completed = true
        activity.originalData.status = 'Completed'
        
        // Update icon class for completed status
        activity.typeIconClass = 'activity-icon-green'
        
        $showNotification('Activity completed successfully', 'success')
    } catch (error) {
        console.error('Error completing task:', error)
        const errorMessage = error.response?.data?.message || 'Failed to complete activity'
        $showNotification(errorMessage, 'error')
    }
}

const editTask = (activity) => {
    // Initialize edit form with current activity data
    editingActivityId.value = activity.id
    
    // Get original reminder_date from originalData
    const reminderDate = activity.originalData?.reminder_date
    
    // Convert deadline to Date object with both date and time
    let deadlineDateValue = null
    if (reminderDate) {
        // Create Date object from reminder_date string (should include time)
        deadlineDateValue = new Date(reminderDate)
        
        // Ensure it's a valid date
        if (isNaN(deadlineDateValue.getTime())) {
            deadlineDateValue = new Date() // Fallback to current date/time if invalid
        }
    } else {
        deadlineDateValue = new Date() // Default to current date/time
    }
    
    editForm.value = {
        title: activity.taskTitle || '',
        deadlineDate: deadlineDateValue,
        reminders: activity.reminders ? [...activity.reminders] : []
    }
    
    // Close any open dropdowns
    openReminderDropdownId.value = null
    openMenuId.value = null
    showDateTimePicker.value = false
}

const cancelEdit = (activity) => {
    editingActivityId.value = null
    editForm.value = {
        title: '',
        deadlineDate: null,
        reminders: []
    }
    openReminderDropdownId.value = null
    showDateTimePicker.value = false
}

// Get the date/time value for DateTimePicker
const getDateTimePickerValue = () => {
    if (editForm.value.deadlineDate) {
        // Ensure it's a Date object with both date and time
        const date = editForm.value.deadlineDate instanceof Date 
            ? editForm.value.deadlineDate 
            : new Date(editForm.value.deadlineDate)
        
        // Ensure it's a valid date
        if (!isNaN(date.getTime())) {
            return date
        }
    }
    // Default to current date/time if no date is set
    return new Date()
}

// Open date picker for editing deadline
const openDatePicker = (activity) => {
    if (editingActivityId.value === activity.id) {
        // Ensure deadlineDate is set before opening picker
        if (!editForm.value.deadlineDate) {
            // Get the current activity's reminder_date or use current date/time
            const reminderDate = activity.originalData?.reminder_date
            if (reminderDate) {
                editForm.value.deadlineDate = new Date(reminderDate)
            } else {
                editForm.value.deadlineDate = new Date()
            }
        }
        showDateTimePicker.value = true
    }
}

// Handle date selection (while picking)
const handleDateSelected = (date) => {
    // Ensure date is a Date object with both date and time
    if (date instanceof Date) {
        editForm.value.deadlineDate = date
    } else {
        editForm.value.deadlineDate = new Date(date)
    }
}

// Handle date apply (when user confirms)
const handleDateApply = (date) => {
    // Ensure date is a Date object with both date and time
    if (date instanceof Date) {
        editForm.value.deadlineDate = date
    } else {
        editForm.value.deadlineDate = new Date(date)
    }
    showDateTimePicker.value = false
}

// Handle date cancel
const handleDateCancel = () => {
    showDateTimePicker.value = false
}

// Format deadline for display in edit mode
const formattedEditDeadline = (activity) => {
    if (!editForm.value.deadlineDate) {
        return 'Select date and time'
    }
    const date = editForm.value.deadlineDate instanceof Date 
        ? editForm.value.deadlineDate 
        : new Date(editForm.value.deadlineDate)
    
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
}

const toggleReminderOption = (optionValue) => {
    const valueAsNumber = parseInt(optionValue, 10)
    const index = editForm.value.reminders.indexOf(valueAsNumber)
    
    if (index > -1) {
        // Remove if already selected
        editForm.value.reminders.splice(index, 1)
    } else {
        // Add if not selected
        editForm.value.reminders.push(valueAsNumber)
    }
}

const saveTask = async (activity) => {
    if (!editForm.value.title || !editForm.value.title.trim()) {
        $showNotification('Activity title is required', 'error')
        return
    }
    
    if (!editForm.value.deadlineDate) {
        $showNotification('Deadline is required', 'error')
        return
    }
    
    if (!editForm.value.reminders || editForm.value.reminders.length === 0) {
        $showNotification('At least one reminder is required', 'error')
        return
    }
    
    try {
        savingActivityId.value = activity.id
        
        // Convert Date object to ISO string
        const deadlineDate = editForm.value.deadlineDate instanceof Date 
            ? editForm.value.deadlineDate 
            : new Date(editForm.value.deadlineDate)
        const formattedDeadline = deadlineDate.toISOString()
        
        const payload = {
            title: editForm.value.title.trim(),
            reminder_date: formattedDeadline,
            reminders: editForm.value.reminders
        }
        
        const response = await api.put(`/leads/activities/${activity.originalData.id}`, payload)
        
        // Update the activity in the list
        const updatedActivity = response.data?.data || response.data
        if (updatedActivity) {
            const transformedActivity = transformActivity(updatedActivity)
            const index = activities.value.findIndex(a => a.id === activity.id)
            if (index !== -1) {
                activities.value[index] = transformedActivity
            }
        }
        
        $showNotification('Activity updated successfully', 'success')
        
        // Exit edit mode
        editingActivityId.value = null
        editForm.value = {
            title: '',
            deadlineDate: null,
            reminders: []
        }
        openReminderDropdownId.value = null
        showDateTimePicker.value = false
        
    } catch (error) {
        console.error('Error updating activity:', error)
        const errorMessage = error.response?.data?.message || 'Failed to update activity'
        $showNotification(errorMessage, 'error')
    } finally {
        savingActivityId.value = null
    }
}

const toggleNotification = (activity) => {
    console.log('Toggle notification:', activity)
    // Add notification toggle logic here
}

const viewComments = (activity) => {
    console.log('View comments:', activity)
    // Add view comments logic here
}

const toggleActivityMenu = (activity) => {
    if (openMenuId.value === activity.id) {
        openMenuId.value = null
    } else {
        openMenuId.value = activity.id
    }
}

const handleDeleteActivity = async (activity) => {
    // Close the dropdown
    openMenuId.value = null
    
    // Show confirmation modal
    const confirmed = await showConfirmation({
        title: 'Delete Activity',
        message: 'Are you sure you want to delete this activity? This action cannot be undone.',
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    })
    
    if (!confirmed) {
        return
    }
    
    try {
        await api.delete(`/leads/activities/${activity.originalData.id}`)
        
        // Remove activity from the list
        const index = activities.value.findIndex(a => a.id === activity.id)
        if (index !== -1) {
            activities.value.splice(index, 1)
        }
        
        $showNotification('Activity deleted successfully', 'success')
    } catch (error) {
        console.error('Error deleting activity:', error)
        const errorMessage = error.response?.data?.message || 'Failed to delete activity'
        $showNotification(errorMessage, 'error')
    }
}

// Handle click outside to close dropdowns
const handleClickOutside = (event) => {
    if (!event.target.closest('.activity-menu-wrapper')) {
        openMenuId.value = null
    }
    if (!event.target.closest('.reminder-dropdown-wrapper')) {
        openReminderDropdownId.value = null
    }
    // Don't close edit mode on outside click - user needs to explicitly save or cancel
}

// Watch for leadId changes
watch(() => props.leadId, (newLeadId) => {
    if (newLeadId) {
        fetchActivities()
    } else {
        activities.value = []
    }
})

// Add new activity to the list (called from parent when activity is created)
const addActivity = (newActivity) => {
    // Transform the new activity to match the expected structure
    const transformedActivity = transformActivity(newActivity)
    
    // Add to the beginning of the activities array (newest first)
    activities.value.unshift(transformedActivity)
}

// Expose the method for parent component to call
defineExpose({
    addActivity
})

// Fetch activities on mount
onMounted(() => {
    if (props.leadId) {
        fetchActivities()
    }
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
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
    max-height: 500px;
    overflow-y: scroll;
}

.activity-group {
    margin-bottom: 20px;
    position: relative;
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
    width: 28px;
    flex-shrink: 0;
}

.timeline-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    position: relative;
}

.timeline-icon-check {
    background: #EAEDFF;
}

.timeline-icon-info {
    background: #1E293B;
}

.timeline-icon-content {
    font-size: 15px;
    font-weight: 500;
    color: #01062C;
}

.timeline-line {
    position: absolute;
    top: 20px;
    left: 14px;
    width: 1px;
    background: #E5E7EB;
    bottom: 0;
    z-index: 1;
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

.activity-icon-green {
    background: #008000;
}

.activity-icon-blue {
    background: #3B82F6;
}

.activity-icon-red {
    background: #EF4444;
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
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.task-icon-grey {
    background: #F2F2F2;
}

.task-icon-blue {
    background: #EAEDFF;
}

.task-icon-red {
    background: #FFE6E7;
}

.task-icon-content {
    font-size: 20px;
    color: #01062C;
}

.task-icon-content-red {
    color: #F11716 !important;
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
    color: #FAA300;
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

.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
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

.show-older-link:hover:not(:disabled) {
    color: #2563EB;
}

.show-older-link:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.show-older-icon {
    font-size: 12px;
    color: inherit;
}

/* Activity Menu Dropdown Styles */
.activity-menu-wrapper {
    position: relative;
}

.activity-dropdown-menu {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    /* min-width: 150px; */
    padding: 0px 4px;
    z-index: 1000;
    border: 1px solid #E2E8F0;
}

.activity-dropdown-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: none;
    border: none;
    text-align: left;
    font-size: 13px;
    font-weight: 400;
    color: #DC2626;
    cursor: pointer;
    transition: all 0.2s;
}

.activity-dropdown-item:hover {
    background: #FEF2F2;
    color: #B91C1C;
}

.dropdown-item-icon {
    font-size: 14px;
    color: inherit;
}

/* Reminder Dropdown Styles */
.reminder-dropdown-wrapper {
    position: relative;
}

.reminder-dropdown-menu {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    padding: 12px 0;
    z-index: 1000;
    border: 1px solid #E2E8F0;
    min-width: 280px;
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
    cursor: default;
    transition: background 0.2s;
}

.reminder-option.readonly {
    cursor: default;
    pointer-events: none;
}

.reminder-option.readonly:hover {
    background: transparent;
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

/* Edit Mode Styles */
.edit-mode {
    margin-bottom: 8px;
}

.edit-mode:last-child {
    margin-bottom: 0;
}

.edit-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 400;
    color: #000000;
    background: #fff;
    transition: all 0.2s;
}

.edit-input:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.edit-title-input {
    font-weight: 600;
    margin-bottom: 8px;
}

.edit-deadline-input {
    font-weight: 400;
}

.task-deadline-edit {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 400;
    color: #000000;
}

.deadline-picker-btn {
    background: transparent;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 400;
    color: #000000;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
    flex: 1;
}

.deadline-picker-btn:hover {
    border-color: #3B82F6;
    background: #F8FAFC;
}

.deadline-picker-btn:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-save-edit {
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

.btn-save-edit:hover:not(:disabled) {
    background: #060a2b;
}

.btn-save-edit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-cancel-edit {
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

.btn-cancel-edit:hover:not(:disabled) {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.btn-cancel-edit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.reminder-option {
    cursor: pointer;
}

.reminder-option:hover {
    background: #F8FAFC;
}
</style>
