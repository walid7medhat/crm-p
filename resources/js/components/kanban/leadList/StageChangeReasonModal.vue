<!-- components/leads/StageChangeReasonModal.vue -->
<template>
    <div class="modal fade" id="stageChangeReasonModal" tabindex="-1" aria-labelledby="stageChangeReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-xl mb-0" id="stageChangeReasonModalLabel">
                        Change Stage Reason
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="stageChangeReasonForm" @submit.prevent="submitReason">
                        <div class="mb-3">
                            <label for="reason" class="form-label fw-semibold text-primary-light text-sm mb-8">
                                Please provide a reason for moving this lead <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                class="form-control" 
                                id="reason" 
                                v-model="reason" 
                                rows="4" 
                                placeholder="Enter reason for stage change..."
                                required
                            ></textarea>
                        </div>
                        <div class="mb-3" v-if="error">
                            <div class="alert alert-danger py-2">
                                {{ error }}
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center gap-3">
                    <button 
                        type="button"
                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8"
                        data-bs-dismiss="modal"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        class="btn btn-primary border border-primary-600 text-md px-28 py-12 radius-8"
                        @click="submitReason"
                        :disabled="submitting"
                    >
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        {{ submitting ? 'Submitting...' : 'Submit' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import * as bootstrap from 'bootstrap'

const props = defineProps({
    leadId: {
        type: [Number, String],
        required: true
    },
    targetStageId: {
        type: [Number, String],
        required: true
    },
    targetStageName: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['submit', 'cancel', 'closed'])

const reason = ref('')
const submitting = ref(false)
const error = ref(null)
let modalInstance = null

// Show modal
const show = () => {
    reason.value = ''
    error.value = null
    submitting.value = false
    
    const modalElement = document.getElementById('stageChangeReasonModal')
    if (modalElement) {
        modalInstance = new bootstrap.Modal(modalElement)
        modalInstance.show()
        
        // Focus on textarea after modal is shown
        modalElement.addEventListener('shown.bs.modal', () => {
            const textarea = document.getElementById('reason')
            if (textarea) textarea.focus()
        }, { once: true })
    }
}

// Hide modal
const hide = () => {
    if (modalInstance) {
        modalInstance.hide()
        emit('closed')
    }
}

// Submit reason
const submitReason = async () => {
    if (!reason.value.trim()) {
        error.value = 'Reason is required'
        return
    }
    
    submitting.value = true
    error.value = null
    
    try {
        await emit('submit', {
            leadId: props.leadId,
            targetStageId: props.targetStageId,
            reason: reason.value.trim()
        })
        hide()
    } catch (err) {
        error.value = err.message || 'Failed to submit reason'
    } finally {
        submitting.value = false
    }
}

// Cancel
const cancel = () => {
    emit('cancel')
    hide()
}

// Expose show method
defineExpose({
    show
})
</script>