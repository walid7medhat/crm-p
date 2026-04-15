<template>
    <div class="comment-input-section">
        <label class="mb-2 d-block modal-title">Contact Customer</label>
        <div
            ref="commentBoxRef"
            class="comment-box border radius-12 p-3 position-relative"
            :class="{ 'has-error': validationErrors.comment || validationErrors.deal_id }"
        >
            <textarea
                ref="textareaRef"
                class="form-control border-0 p-0 text-sm shadow-none custom-textarea"
                :class="{ 'is-invalid': validationErrors.comment || validationErrors.deal_id }"
                placeholder="Type @ to mention someone"
                rows="4"
                :value="commentText"
                @input="onCommentInput"
                @keydown="onCommentKeydown"
            ></textarea>
            <Transition name="mention-fade">
                <div
                    v-if="showMentionDropdown && (mentionAgents.length > 0 || mentionLoading)"
                    class="mention-dropdown"
                >
                    <div v-if="mentionLoading" class="mention-loading">
                        <span class="mention-loading-text">Searching...</span>
                    </div>
                    <template v-else>
                        <button
                            v-for="(agent, index) in mentionAgents"
                            :key="agent.id"
                            type="button"
                            class="mention-item"
                            :class="{ 'mention-item-active': index === mentionHighlightedIndex }"
                            @mousedown.prevent="selectMention(agent)"
                        >
                            <img
                                v-if="agent.avatar"
                                :src="agent.avatar"
                                class="mention-avatar"
                                alt=""
                            />
                            <div v-else class="mention-avatar mention-avatar-placeholder">
                                <iconify-icon icon="lucide:user" class="mention-avatar-icon"></iconify-icon>
                            </div>
                            <div class="mention-item-info">
                                <span class="mention-item-name">{{ agent.name }}</span>
                            </div>
                        </button>
                    </template>
                </div>
            </Transition>

            <div v-if="validationErrors.comment" class="invalid-feedback">
                {{ validationErrors.comment[0] }}
            </div>
            <div v-if="validationErrors.deal_id" class="invalid-feedback">
                {{ validationErrors.deal_id[0] }}
            </div>
            <div v-if="errorMessage && !validationErrors.comment && !validationErrors.deal_id" class="invalid-feedback">
                {{ errorMessage }}
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex align-items-center gap-3">
                    <button class="icon-btn d-flex align-items-center gap-2" @click="showFileModal = true">
                        <iconify-icon icon="lucide:paperclip" class="icon-btn-icon"></iconify-icon>
                        <span class="icon-btn-text">File</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showFileModal" class="file-upload-card mt-3">
            <button class="btn-close-card" @click="handleCancelFileModal">
                <iconify-icon icon="lucide:x"></iconify-icon>
            </button>

            <div class="file-upload-content">
                <div
                    class="file-dropzone"
                    :class="{ 'dragover': isDragging, 'has-error': fileUploadError }"
                    @dragover.prevent="handleDragOver"
                    @dragleave.prevent="handleDragLeave"
                    @drop.prevent="handleDrop"
                    @click="triggerFileInput"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/jpg,image/png,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain"
                        @change="handleFileSelect"
                        class="file-input-hidden"
                    />
                    <div class="d-flex align-items-center gap-2 w-100">
                        <iconify-icon icon="lucide:file-text" class="dropzone-icon"></iconify-icon>
                        <div class="flex-grow-1">
                            <p class="dropzone-text mb-1">Drag and drop your files</p>
                            <p class="dropzone-subtext">Images, PDF, documents, text files. Max 2MB per file, 5 files total</p>
                        </div>
                        <button class="btn-select-file" @click.stop="triggerFileInput">
                            Select File
                        </button>
                    </div>
                </div>

                <div v-if="fileUploadError" class="file-upload-error mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <iconify-icon icon="lucide:alert-circle" class="error-icon"></iconify-icon>
                        <span class="error-text">{{ fileUploadError }}</span>
                    </div>
                </div>

                <div v-if="selectedFiles.length > 0" class="selected-files-list mt-3">
                    <div class="files-summary mb-2">
                        <span class="files-count">{{ selectedFiles.length }} of 5 files</span>
                        <span class="files-total-size">Total: {{ formatFileSize(totalFilesSize) }}</span>
                    </div>
                    <div
                        v-for="(file, index) in selectedFiles"
                        :key="index"
                        class="selected-file-item"
                        :class="{ 'file-size-warning': file.size > 1.5 * 1024 * 1024 }"
                    >
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <iconify-icon icon="lucide:file" class="file-item-icon"></iconify-icon>
                            <div class="flex-grow-1">
                                <p class="file-item-name mb-0">{{ file.name }}</p>
                                <p class="file-item-size mb-0">
                                    {{ formatFileSize(file.size) }}
                                    <span v-if="file.size > 1.5 * 1024 * 1024" class="size-warning-text">(Large file)</span>
                                </p>
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

        <div class="modal-footer-custom">
            <button class="btn-cancel" @click="handleCancel">
                Cancel
            </button>
            <button
                class="btn-save"
                @click="handleSave"
                :disabled="isSubmitting"
            >
                <span v-if="isSubmitting">Saving...</span>
                <span v-else>Save</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, getCurrentInstance, nextTick, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    dealId: {
        type: [Number, String],
        required: true,
    },
})

const emit = defineEmits(['comment-created'])

const commentText = ref('')
const showFileModal = ref(false)
const selectedFiles = ref([])
const isDragging = ref(false)
const fileInput = ref(null)
const isSubmitting = ref(false)
const validationErrors = ref({})
const errorMessage = ref('')
const fileUploadError = ref('')

const textareaRef = ref(null)
const showMentionDropdown = ref(false)
const mentionQuery = ref('')
const mentionAgents = ref([])
const mentionLoading = ref(false)
const mentionHighlightedIndex = ref(0)
const mentionedUsers = ref([])
let mentionFetchTimer = null
const MENTION_DEBOUNCE_MS = 200

const instance = getCurrentInstance()
const $showNotification = (message, type = 'info') => {
    if (instance?.appContext?.config?.globalProperties?.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(message, type)
    } else if (window.$showNotification) {
        window.$showNotification(message, type)
    }
}

const fetchMentionAgents = async () => {
    const search = mentionQuery.value.trim()
    if (search.length === 0) {
        mentionAgents.value = []
        return
    }
    try {
        mentionLoading.value = true
        const response = await api.get('/leads/mentions/agents', { params: { search } })
        const data = response.data?.data ?? response.data
        mentionAgents.value = Array.isArray(data) ? data : []
        mentionHighlightedIndex.value = 0
    } catch {
        mentionAgents.value = []
    } finally {
        mentionLoading.value = false
    }
}

const onCommentInput = (e) => {
    const el = e.target
    const value = el.value
    const cursor = el.selectionStart ?? value.length
    commentText.value = value

    const textBefore = value.slice(0, cursor)
    const lastAt = textBefore.lastIndexOf('@')
    if (lastAt === -1) {
        showMentionDropdown.value = false
        return
    }
    if (lastAt > 0) {
        const charBeforeAt = textBefore[lastAt - 1]
        if (charBeforeAt && !/\s/.test(charBeforeAt)) {
            showMentionDropdown.value = false
            return
        }
    }
    const query = textBefore.slice(lastAt + 1)
    if (/\s/.test(query)) {
        showMentionDropdown.value = false
        return
    }
    mentionQuery.value = query
    showMentionDropdown.value = true
    mentionHighlightedIndex.value = 0

    clearTimeout(mentionFetchTimer)
    mentionFetchTimer = setTimeout(() => {
        fetchMentionAgents()
    }, MENTION_DEBOUNCE_MS)
}

const onCommentKeydown = (e) => {
    if (!showMentionDropdown.value || mentionAgents.value.length === 0) {
        if (e.key === 'Escape') showMentionDropdown.value = false
        return
    }
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        mentionHighlightedIndex.value = Math.min(mentionHighlightedIndex.value + 1, mentionAgents.value.length - 1)
        return
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault()
        mentionHighlightedIndex.value = Math.max(mentionHighlightedIndex.value - 1, 0)
        return
    }
    if (e.key === 'Enter' && mentionAgents.value[mentionHighlightedIndex.value]) {
        e.preventDefault()
        selectMention(mentionAgents.value[mentionHighlightedIndex.value])
        return
    }
    if (e.key === 'Escape') {
        e.preventDefault()
        showMentionDropdown.value = false
    }
}

const selectMention = (agent) => {
    const el = textareaRef.value
    if (!el) return
    const value = commentText.value
    const cursor = el.selectionStart ?? value.length
    const textBefore = value.slice(0, cursor)
    const lastAt = textBefore.lastIndexOf('@')
    const textAfter = value.slice(cursor)
    const insert = `@${agent.name} `
    const newText = value.slice(0, lastAt) + insert + textAfter
    commentText.value = newText
    mentionedUsers.value = [...mentionedUsers.value, { id: agent.id, name: agent.name }]
    showMentionDropdown.value = false
    mentionAgents.value = []
    nextTick(() => {
        el.focus()
        const newCursor = lastAt + insert.length
        el.setSelectionRange(newCursor, newCursor)
    })
}

const handleCancel = () => {
    commentText.value = ''
    mentionedUsers.value = []
    selectedFiles.value = []
    showFileModal.value = false
    isDragging.value = false
    fileUploadError.value = ''
    showMentionDropdown.value = false
    if (fileInput.value) {
        fileInput.value.value = ''
    }
    validationErrors.value = {}
    errorMessage.value = ''
}

const commentBoxRef = ref(null)
const onDocumentClick = (e) => {
    if (showMentionDropdown.value && commentBoxRef.value && !commentBoxRef.value.contains(e.target)) {
        showMentionDropdown.value = false
    }
}
onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))

const handleSave = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        validationErrors.value = {}

        if (!props.dealId) {
            errorMessage.value = 'Deal ID is required'
            $showNotification('Deal ID is required', 'error')
            isSubmitting.value = false
            return
        }

        const maxSizePerFile = 2 * 1024 * 1024
        const maxTotalSize = 10 * 1024 * 1024
        let totalSize = 0

        for (const file of selectedFiles.value) {
            if (file.size > maxSizePerFile) {
                $showNotification(`${file.name} exceeds the 2MB size limit per file.`, 'error')
                isSubmitting.value = false
                return
            }
            totalSize += file.size
        }

        if (totalSize > maxTotalSize) {
            $showNotification(`Total file size (${formatFileSize(totalSize)}) exceeds the ${formatFileSize(maxTotalSize)} limit. Please reduce file sizes or remove some files.`, 'error')
            isSubmitting.value = false
            return
        }

        if (selectedFiles.value.length > 5) {
            $showNotification('Maximum 5 files allowed. Please remove some files.', 'error')
            isSubmitting.value = false
            return
        }

        const formData = new FormData()
        formData.append('deal_id', String(props.dealId))
        formData.append('comment', commentText.value || '')

        selectedFiles.value.forEach((file) => {
            formData.append('attachments[]', file)
        })

        mentionedUsers.value.forEach((u) => {
            formData.append('mentioned_users[]', u.id)
        })

        const response = await api.post('/deals/comments', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            maxContentLength: Infinity,
            maxBodyLength: Infinity,
        })

        if (response.data?.data) {
            emit('comment-created', response.data.data)
        }

        handleCancel()
        $showNotification('Comment created successfully!', 'success')
    } catch (error) {
        if (error.response && (error.response.status === 413 || error.response.status === 431)) {
            errorMessage.value = 'File size is too large. Please reduce file sizes or remove some files. Maximum 2MB per file, 5 files total.'
            $showNotification('File size is too large. Please reduce file sizes or remove some files.', 'error')
            validationErrors.value = {
                attachments: ['Total file size exceeds server limit. Please reduce file sizes.'],
            }
        } else if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
            errorMessage.value = 'Request timed out. File size may be too large. Please try with smaller files.'
            $showNotification('Request timed out. Please try with smaller files.', 'error')
        } else if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors || {}
            validationErrors.value = errors

            const errorMessages = []
            Object.keys(errors).forEach((key) => {
                if (Array.isArray(errors[key])) {
                    errorMessages.push(...errors[key])
                } else {
                    errorMessages.push(errors[key])
                }
            })

            errorMessage.value = errorMessages[0] || 'Please fix the validation errors below.'
            $showNotification('Please check the form for errors', 'warning')
        } else if (error.response) {
            const status = error.response.status
            let errorMsg = error.response?.data?.message || 'Failed to create comment. Please try again.'

            if (status === 413 || status === 431) {
                errorMsg = 'File size is too large. Please reduce file sizes or remove some files.'
            } else if (status === 500) {
                errorMsg = 'Server error. Please try again later or contact support.'
            } else if (status === 503) {
                errorMsg = 'Service temporarily unavailable. Please try again later.'
            }

            errorMessage.value = errorMsg
            $showNotification(errorMsg, 'error')
        } else if (error.request) {
            errorMessage.value = 'Network error. Please check your connection and try again.'
            $showNotification('Network error. Please check your connection.', 'error')
        } else {
            errorMessage.value = error.message || 'Failed to create comment. Please try again.'
            $showNotification(errorMessage.value, 'error')
        }
    } finally {
        isSubmitting.value = false
    }
}

const triggerFileInput = () => {
    fileInput.value?.click()
}

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files)
    addFiles(files)
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
    const maxSizePerFile = 2 * 1024 * 1024
    const maxFiles = 5
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'image/jpg', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain']

    fileUploadError.value = ''

    if (selectedFiles.value.length + files.length > maxFiles) {
        const errorMsg = `Maximum ${maxFiles} files allowed. Please remove some files first.`
        fileUploadError.value = errorMsg
        $showNotification(errorMsg, 'error')
        return
    }

    let hasError = false
    const errorMessages = []

    files.forEach((file) => {
        if (!allowedTypes.includes(file.type)) {
            const errorMsg = `${file.name} is not a valid file type. Allowed types: images, PDF, documents, text files.`
            errorMessages.push(errorMsg)
            hasError = true
            return
        }

        if (file.size > maxSizePerFile) {
            const errorMsg = `${file.name} exceeds the 2MB size limit per file.`
            errorMessages.push(errorMsg)
            hasError = true
            return
        }

        const exists = selectedFiles.value.some((f) => f.name === file.name && f.size === file.size)
        if (!exists) {
            selectedFiles.value.push(file)
        } else {
            $showNotification(`${file.name} is already added.`, 'warning')
        }
    })

    if (hasError) {
        fileUploadError.value = errorMessages[0] || 'One or more files failed validation.'
        if (errorMessages.length > 0) {
            $showNotification(errorMessages[0], 'error')
        }
    } else {
        fileUploadError.value = ''
    }
}

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1)
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

const totalFilesSize = computed(() => {
    return selectedFiles.value.reduce((total, file) => total + file.size, 0)
})

const handleCancelFileModal = () => {
    showFileModal.value = false
    selectedFiles.value = []
    isDragging.value = false
    fileUploadError.value = ''
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>

<style scoped>
.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
}

.custom-textarea {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.custom-textarea:focus {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
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

.modal-footer-custom {
    padding-top: 15px;
    margin-top: 15px;
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

.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

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
    top: 1px;
    right: 3px;
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
    padding: 20px;
    flex: 1;
}

.file-dropzone {
    background: #F8FAFC;
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    padding: 20px;
    margin: 10px 0;
    cursor: pointer;
    transition: all 0.2s;
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

.file-dropzone.has-error {
    border-color: #DC2626;
    background: #FEE2E2;
}

.file-input-hidden {
    display: none;
}

.dropzone-icon {
    font-size: 35px;
    color: #94A3B8;
    flex-shrink: 0;
}

.dropzone-text {
    font-size: 14px;
    font-weight: 500;
    color: #01062C;
    margin: 0;
}

.dropzone-subtext {
    font-size: 12px;
    color: #64748B;
    margin: 0;
}

.btn-select-file {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 20px;
    padding: 8px 10px;
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

.files-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-size: 12px;
    color: #64748B;
}

.files-count {
    font-weight: 500;
    color: #01062C;
}

.files-total-size {
    font-weight: 500;
    color: #64748B;
}

.selected-file-item {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s;
}

.selected-file-item.file-size-warning {
    border-color: #F59E0B;
    background: #FFFBEB;
}

.size-warning-text {
    color: #F59E0B;
    font-size: 11px;
    margin-left: 4px;
    font-weight: 500;
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

.file-upload-error {
    background: #FEE2E2;
    border: 1px solid #DC2626;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.error-icon {
    font-size: 20px;
    color: #DC2626;
    flex-shrink: 0;
}

.error-text {
    font-size: 13px;
    color: #DC2626;
    font-weight: 500;
    margin: 0;
}

.mention-dropdown {
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    margin-top: 4px;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(1, 6, 44, 0.08);
    max-height: 220px;
    overflow-y: auto;
    z-index: 20;
}

.mention-loading {
    padding: 12px 14px;
    text-align: center;
}

.mention-loading-text {
    font-size: 13px;
    color: #64748B;
}

.mention-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 14px;
    border: none;
    background: none;
    cursor: pointer;
    text-align: left;
    font-size: 13px;
    color: #01062C;
    transition: background 0.15s;
    border-radius: 0;
}

.mention-item:hover,
.mention-item.mention-item-active {
    background: #F1F5F9;
}

.mention-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.mention-avatar-placeholder {
    background: #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mention-avatar-icon {
    font-size: 16px;
    color: #64748B;
}

.mention-item-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.mention-item-name {
    font-weight: 500;
    color: #01062C;
}

.mention-fade-enter-active,
.mention-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.mention-fade-enter-from,
.mention-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.has-error {
    border-color: #dc3545 !important;
}

.is-invalid {
    border-color: #dc3545 !important;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 4px;
    font-size: 12px;
    color: #dc3545;
}

.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
