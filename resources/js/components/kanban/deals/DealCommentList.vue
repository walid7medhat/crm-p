<template>
    <!-- Lead Comments List: only show when there are comments -->
    <div v-if="groupedComments.length > 0" class=" bg-white p-3 radius-12 shadow-sm mt-3 ">
        <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="modal-title">Deal Comments</span>
        </div>
        </div>
        <div class="activity-timeline">
            <div 
                v-for="(group, groupIndex) in groupedComments" 
                :key="groupIndex"
                class="activity-group"
            >
                <!-- Timeline Line -->
                <div 
                    class="timeline-line"
                    :class="{ 'last-group': groupIndex === groupedComments.length - 1 }"
                ></div>
                
                <!-- Date Header -->
                <div class="timeline-date-header">
                    <div class="timeline-indicator-wrapper">
                        <div 
                            class="timeline-icon timeline-icon-orange"
                        >
                            <iconify-icon 
                                icon="lucide:message-square-text" 
                                class="timeline-icon-content"
                            ></iconify-icon>
                        </div>
                    </div>
                    <div class="date-header-text">{{ group.dateLabel }}</div>
                </div>

                <!-- Comment Cards -->
                <div class="activity-cards-wrapper">
                    <div 
                        v-for="(comment, commentIndex) in group.comments" 
                        :key="comment.id || commentIndex"
                        class="comment-card-item"
                    >
                        <!-- Comment Header -->
                        <div class="comment-card-header">
                            <span class="comment-label">Comment</span>
                            <div class="comment-header-right">
                                <span class="comment-time">{{ comment.time }}</span>
                                <div
                                    class="comment-avatar-hover-anchor"
                                    @mouseenter="activeHoverUserId = comment.id"
                                    @mouseleave="activeHoverUserId = null"
                                     @click="openPersonProfile(props.lead, comment.userId, $event)"
                                >
                                    <img 
                                        v-if="comment.userAvatar" 
                                        :src="comment.userAvatar" 
                                        class="comment-user-avatar"
                                        alt="User"
                                        :title="comment.userName"
                                    />
                                    <div v-else class="comment-user-avatar-placeholder">
                                        <iconify-icon icon="lucide:user" class="comment-avatar-icon"></iconify-icon>
                                    </div>
                                    <transition name="person-card-pop">
                                        <div v-if="activeHoverUserId === comment.id" class="person-hover-card">
                                            <div class="person-hover-head">
                                                <img
                                                    v-if="comment.userAvatar"
                                                    :src="comment.userAvatar"
                                                    alt=""
                                                    class="person-hover-avatar"
                                                />
                                                <div v-else class="person-hover-avatar person-hover-avatar-fallback">
                                                    <iconify-icon icon="lucide:user" class="comment-avatar-icon"></iconify-icon>
                                                </div>
                                                <div>
                                                    <div class="person-hover-name">{{ comment.userName || '—' }}</div>
                                                    <div class="person-hover-role">{{ comment.userRole || 'Team Member' }}</div>
                                                </div>
                                            </div>
                                            <div class="person-hover-line">
                                                <span>Reports To</span>
                                                <b>{{ comment.userParentName || 'Not specified' }}</b>
                                            </div>
                                            <div class="person-hover-line">
                                                <span>Branch</span>
                                                <b>{{ comment.userBranchName || 'Not specified' }}</b>
                                            </div>
                                        </div>
                                    </transition>
                                </div>
                            </div>
                        </div>

                        <!-- Comment Body -->
                        <div class="comment-card-body">
                            <div class="comment-content-wrapper">
                                <div class="comment-icon-square">
                                    <iconify-icon 
                                        icon="lucide:message-square-text" 
                                        class="comment-icon-content"
                                    ></iconify-icon>
                                </div>
                                <div class="comment-text">{{ comment.comment }}</div>
                            </div>

                            <!-- Attachments Section -->
                            <div v-if="comment.attachments && comment.attachments.length > 0" class="comment-attachments">
                                <div 
                                    v-for="(attachment, attIndex) in comment.attachments" 
                                    :key="attIndex"
                                    class="attachment-item"
                                >
                                    <div class="attachment-left">
                                        <iconify-icon icon="lucide:file-text" class="attachment-file-icon"></iconify-icon>
                                        <div class="attachment-info">
                                            <div class="attachment-name">{{ attachment.file_name || attachment.name }}</div>
                                            <div class="attachment-size">{{ formatFileSize(attachment.file_size || attachment.size) }}</div>
                                        </div>
                                    </div>
                                    <div class="attachment-actions">
                                        <button class="attachment-action-btn" @click="downloadAttachment(attachment)">
                                            <iconify-icon icon="lucide:download" class="attachment-action-icon"></iconify-icon>
                                        </button>
                                        <button class="attachment-action-btn delete-btn" @click="deleteAttachment(comment, attachment, attIndex)">
                                            <iconify-icon icon="lucide:trash-2" class="attachment-action-icon delete-icon"></iconify-icon>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Comment Footer -->
                        <div class="comment-card-footer">
                            <div class="comment-footer-right">
                                <button class="icon-btn d-flex align-items-center gap-2">
                                    <iconify-icon icon="lucide:file-text" class="icon-btn-icon"></iconify-icon>
                                    <span class="icon-btn-text">Comment</span>
                                </button>
                                <button class="comment-kebab-btn" @click="showCommentMenu(comment)">
                                    <iconify-icon icon="lucide:more-vertical" class="kebab-icon"></iconify-icon>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Show Older Link -->
            <div v-if="hasNextPage" class="show-older-wrapper">
                <button 
                    class="show-older-link" 
                    @click="loadOlderComments"
                    :disabled="loadingOlder"
                >
                    <iconify-icon icon="lucide:chevron-down" class="show-older-icon"></iconify-icon>
                    <span v-if="loadingOlder">Loading...</span>
                    <span v-else>Show older</span>
                </button>
            </div>
        </div>
    </div>
      <ProfilePopup 
        v-model="showProfilePopup"
        :user-id="profileUserId"
        @update:model-value="closeProfilePopup"
    />
</template>

<script setup>
import { ref, computed, onMounted, watch, getCurrentInstance } from 'vue'
import api from '@/plugins/axios'
import ProfilePopup from '../shared/ProfilePopup.vue'

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
    dealId: {
        type: [Number, String],
        default: null
    },
     key: {  
        type: Number,
        default: 0
    }
})

const comments = ref([])
const activeHoverUserId = ref(null)
const loading = ref(false)
const loadingOlder = ref(false)
const nextPageUrl = ref(null)
const hasNextPage = computed(() => !!nextPageUrl.value)



const showProfilePopup = ref(false)
const profileUserId = ref(null)
const profileTriggerType = ref(null)


const openPersonProfile = (lead, userId, event) => {
    if (event) event.stopPropagation()
    
    if (!userId) {
        console.warn('No user ID provided')
        return
    }
    
    profileUserId.value = userId
    showProfilePopup.value = true
}


watch(() => props.key, () => {
    fetchComments()
}, { immediate: true })

// Format date to match image format
const formatDateLabel = (dateString) => {
    const date = new Date(dateString)
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    
    const commentDate = new Date(date)
    commentDate.setHours(0, 0, 0, 0)
    
    if (commentDate.getTime() === today.getTime()) {
        return 'TODAY'
    }
    
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']
    const day = date.getDate()
    const month = months[date.getMonth()]
    const year = date.getFullYear()
    
    return `${day} ${month}, ${year}`
}

// Format time to match image format (e.g., "10:10 AM")
const formatTime = (dateString) => {
    const date = new Date(dateString)
    const hours = date.getHours()
    const minutes = date.getMinutes()
    const ampm = hours >= 12 ? 'PM' : 'AM'
    const displayHours = hours % 12 || 12
    const displayMinutes = minutes < 10 ? `0${minutes}` : minutes
    
    return `${displayHours}:${displayMinutes} ${ampm}`
}

// Format file size
const formatFileSize = (bytes) => {
    if (!bytes) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// Download attachment
const downloadAttachment = (attachment) => {
    console.log('Download attachment:', attachment)
    // Add download logic here
    if (attachment.file_url) {
        window.open(attachment.file_url, '_blank')
    }
}

// Delete attachment
const deleteAttachment = async (comment, attachment, attachmentIndex) => {
    if (!comment.id || !attachment.id) {
        console.error('Comment ID or Attachment ID is missing')
        $showNotification('Comment ID or Attachment ID is missing', 'error')
        return
    }
    
    // Get confirmation function from instance
    const $showConfirmation = instance?.appContext?.config?.globalProperties?.$showConfirmation || 
                              window.$showConfirmation
    
    if (!$showConfirmation) {
        console.error('Confirmation modal not available')
        return
    }
    
    // Show confirmation dialog
    const confirmed = await $showConfirmation({
        title: 'Delete Attachment',
        message: 'Are you sure you want to delete this attachment? This action cannot be undone.',
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    })
    
    if (!confirmed) {
        return
    }
    
    try {
        // Call API to delete attachment
        await api.delete(`/deals/comments/${comment.id}/attachments/${attachment.id}`)
        
        // Remove attachment from the comment's attachments array
        const commentIndex = comments.value.findIndex(c => c.id === comment.id)
        if (commentIndex !== -1) {
            comments.value[commentIndex].attachments.splice(attachmentIndex, 1)
        }
        
        // Show success notification
        $showNotification('Attachment deleted successfully', 'success')
    } catch (error) {
        console.error('Error deleting attachment:', error)
        
        // Show error notification
        const errorMessage = error.response?.data?.message || 'Failed to delete attachment. Please try again.'
        $showNotification(errorMessage, 'error')
    }
}

// Show comment menu
const showCommentMenu = (comment) => {
    console.log('Show comment menu:', comment)
    // Add menu logic here
}

// Transform comment data
const transformComment = (comment) => {
    return {
        id: comment.id,
        comment: comment.comment,
        time: formatTime(comment.created_at),
        dateLabel: formatDateLabel(comment.created_at),
        userAvatar: comment.user_avatar,
         userId: comment.user_id,
        userName: comment.user_name,
        userRole: comment.user_role_name ? formatRoleName(comment.user_role_name) : null,
        userParentName: comment.user_parent_name || null,
        userBranchName: comment.user_branch_name || null,
        attachments: comment.attachments || [],
        mentions: comment.mentions || [],
        mentioned_users: comment.mentioned_users || [],
        created_at: comment.created_at,
        updated_at: comment.updated_at
    }
}

const formatRoleName = (role) => {
    if (!role) return ''
    return String(role).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

// Group comments by date
const groupedComments = computed(() => {
    const groups = {}
    
    comments.value.forEach(comment => {
        const dateKey = comment.dateLabel
        
        if (!groups[dateKey]) {
            groups[dateKey] = {
                dateLabel: dateKey,
                type: dateKey === 'TODAY' ? 'today' : 'past',
                comments: []
            }
        }
        
        groups[dateKey].comments.push(comment)
    })
    
    // Convert to array and sort by date (newest first)
    return Object.values(groups).sort((a, b) => {
        if (a.dateLabel === 'TODAY') return -1
        if (b.dateLabel === 'TODAY') return 1
        return new Date(b.dateLabel) - new Date(a.dateLabel)
    })
})

// Fetch comments from API (first page)
const fetchComments = async () => {
    if (!props.dealId) {
        return
    }
    
    try {
        loading.value = true
        const response = await api.get(`/deals/${props.dealId}/comments`)
        
        // Handle paginated response
        const responseData = response.data
        const commentsData = responseData.data || []
        
        // Transform comments to match the expected structure
        comments.value = commentsData.map(transformComment)
        
        // Store pagination info
        nextPageUrl.value = responseData.links?.next || null
    } catch (error) {
        console.error('Error fetching comments:', error)
        comments.value = []
        nextPageUrl.value = null
    } finally {
        loading.value = false
    }
}

// Load older comments (next page)
const loadOlderComments = async () => {
    if (!nextPageUrl.value || loadingOlder.value) {
        return
    }
    
    try {
        loadingOlder.value = true
        
        // Handle both absolute and relative URLs
        let apiPath = nextPageUrl.value
        try {
            // If it's an absolute URL, extract the path after /api
            const url = new URL(nextPageUrl.value)
            let pathname = url.pathname
            
            // Remove /api prefix if it exists (since baseURL already includes /api)
            if (pathname.startsWith('/api')) {
                pathname = pathname.substring(4) // Remove '/api'
            }
            
            apiPath = pathname + url.search
        } catch (e) {
            // If it's already a relative path, use it as is
            apiPath = nextPageUrl.value
        }
        
        // Fetch next page
        const response = await api.get(apiPath)
        const responseData = response.data
        const commentsData = responseData.data || []
        
        // Transform and append new comments
        const newComments = commentsData.map(transformComment)
        comments.value = [...comments.value, ...newComments]
        
        // Update pagination info
        nextPageUrl.value = responseData.links?.next || null
    } catch (error) {
        console.error('Error loading older comments:', error)
    } finally {
        loadingOlder.value = false
    }
}

// Watch for dealId changes - reset pagination and fetch first page
watch(() => props.dealId, (newLeadId) => {
    if (newLeadId) {
        // Reset pagination state
        comments.value = []
        nextPageUrl.value = null
        loadingOlder.value = false
        // Fetch first page
        fetchComments()
    } else {
        // Clear comments if no dealId
        comments.value = []
        nextPageUrl.value = null
    }
}, { immediate: true })

onMounted(() => {
    if (props.dealId) {
        fetchComments()
    }
})

// Method to add a new comment to the list
const addComment = (newComment) => {
    // Transform the new comment to match the expected structure
    const transformedComment = transformComment(newComment)
    
    // Add to the beginning of the comments array (newest first)
    comments.value.unshift(transformedComment)
}

// Expose the method for parent component to call
defineExpose({
    addComment
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
    max-height: none;
    overflow: visible;
    z-index: 1;
}

.activity-group {
    margin-bottom: 20px;
    position: relative;
    overflow: visible;
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

.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
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

.timeline-icon-orange {
    background: #FAA300;
}

.timeline-icon-content {
    font-size: 10px;
    color: #fff;
}

.timeline-line {
    position: absolute;
    top: 20px;
    left: 10px;
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

.comment-card-item {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.08);
    padding: 12px;
    border: 1px solid #F4F4F4;
    overflow: visible;
}

.comment-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    overflow: visible;
}

.comment-label {
    font-size: 13px;
    font-weight: 400;
    color: #666666;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.comment-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow: visible;
    position: relative;
    z-index: 2;
}

.comment-time {
    font-size: 12px;
    font-weight: 400;
    color: #999999;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.comment-user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #E5E7EB;
}

.comment-user-avatar-placeholder {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.comment-avatar-hover-anchor {
    position: relative;
    overflow: visible;
    z-index: 3;
}

.person-card-pop-enter-active,
.person-card-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-card-pop-enter-from,
.person-card-pop-leave-to {
    opacity: 0;
    transform: translateY(4px) scale(0.98);
}

.person-hover-card {
    position: absolute;
    bottom: calc(100% + 8px);
    right: 0;
    top: auto;
    left: auto;
    transform: none;
    width: 200px;
    z-index: 3000;
    border-radius: 12px;
    border: 1px solid #dbe3ef;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(8px);
    padding: 10px;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.person-hover-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
}

.person-hover-role {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
}

.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 11px;
    padding: 4px 0;
    border-top: 1px dashed #e2e8f0;
}

.person-hover-line span {
    color: #64748b;
}

.person-hover-line b {
    color: #0f172a;
    font-weight: 700;
    text-align: right;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.comment-avatar-icon {
    font-size: 14px;
    color: #9CA3AF;
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

.comment-card-body {
    margin-bottom: 16px;
}

.comment-content-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.comment-icon-square {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #FFF6E6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.comment-icon-content {
    font-size: 16px;
    color: #FAA300;
}

.comment-text {
    font-size: 13px;
    font-weight: 400;
    color: #333333;
    line-height: 1.5;
    flex: 1;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.comment-attachments {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.attachment-item {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.attachment-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.attachment-file-icon {
    font-size: 20px;
    color: #64748B;
    flex-shrink: 0;
}

.attachment-info {
    flex: 1;
}

.attachment-name {
    font-size: 13px;
    font-weight: 500;
    color: #333333;
    margin-bottom: 4px;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.attachment-size {
    font-size: 12px;
    font-weight: 400;
    color: #999999;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.attachment-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.attachment-action-btn {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
}

.attachment-action-btn:hover {
    background: #F1F5F9;
}

.attachment-action-icon {
    font-size: 16px;
    color: #64748B;
}

.attachment-action-btn.delete-btn .attachment-action-icon.delete-icon {
    color: #DC2626;
}

.comment-card-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #F1F5F9;
}

.comment-footer-btn {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 400;
    color: #333333;
    transition: all 0.2s;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.comment-footer-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.footer-btn-icon {
    font-size: 14px;
    color: #3B82F6;
}

.footer-btn-icon.pin-icon {
    color: #3B82F6;
}

.comment-footer-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.comment-kebab-btn {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
}

.comment-kebab-btn:hover {
    background: #F1F5F9;
}

.kebab-icon {
    font-size: 16px;
    color: #64748B;
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
    opacity: 0.6;
    cursor: not-allowed;
}

.show-older-icon {
    font-size: 12px;
    color: inherit;
}
</style>
