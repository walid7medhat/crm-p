<template>
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
</template>

<script setup>
import { ref, computed } from 'vue'

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

// Activity methods - all handled internally
const completeTask = (activity) => {
    console.log('Complete task:', activity)
    activity.status = 'completed'
}

const editTask = (activity) => {
    console.log('Edit task:', activity)
    // Add edit logic here
}

const repeatTask = (activity) => {
    console.log('Repeat task:', activity)
    // Add repeat logic here
}

const toggleNotification = (activity) => {
    console.log('Toggle notification:', activity)
    // Add notification toggle logic here
}

const viewComments = (activity) => {
    console.log('View comments:', activity)
    // Add view comments logic here
}

const showActivityMenu = (activity) => {
    console.log('Show activity menu:', activity)
    // Add show menu logic here
}

const loadOlderActivities = () => {
    console.log('Load older activities')
    // Add load older activities logic here
}
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
