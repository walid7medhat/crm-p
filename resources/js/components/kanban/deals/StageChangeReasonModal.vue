<template>
  <Teleport to="body">
    <div v-if="show" class="stage-reason-modal-overlay" @click.self="closeModal">
      <div class="stage-reason-modal deal-figma-ui">
        <div class="modal-header">
          <h6 class="modal-title">Reason for Stage Change</h6>
          <button type="button" class="btn-close" @click="closeModal" aria-label="Close"></button>
        </div>
        
        <div class="modal-body">
          <p class="mb-3">
            Moving deal from <strong>{{ originalStageName }}</strong> to <strong>{{ targetStageName }}</strong>
          </p>
          
          <div class="form-group mb-3">
            <label class="form-label fw-semibold">Please provide a reason <span class="text-danger">*</span></label>
            <textarea 
              v-model="reason" 
              class="form-control" 
              rows="4" 
              placeholder="Enter reason for moving this deal..."
              :class="{ 'is-invalid': showError && !reason.trim() }"
            ></textarea>
            <div v-if="showError && !reason.trim()" class="invalid-feedback">
              Reason is required
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-light" @click="closeModal">
            Cancel
          </button>
          <button 
            type="button" 
            class="btn btn-primary" 
            @click="submitReason"
            :disabled="submitting"
          >
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            {{ submitting ? 'Submitting...' : 'Submit' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  dealId: {
    type: [Number, String],
    default: null
  },
  targetStageId: {
    type: [Number, String],
    default: null
  },
  targetStageName: {
    type: String,
    default: ''
  },
  originalStageName: {
    type: String,
    default: 'Previous Stage'
  }
})

const emit = defineEmits(['submit', 'closed'])

const show = ref(false)
const reason = ref('')
const submitting = ref(false)
const showError = ref(false)

// Watch for props changes to open modal
watch(() => props.dealId, (newVal) => {
  if (newVal) {
    show.value = true
    reason.value = ''
    showError.value = false
    submitting.value = false
  }
}, { immediate: true })

function closeModal() {
  show.value = false
  reason.value = ''
  showError.value = false
  submitting.value = false
  emit('closed')
}

async function submitReason() {
  if (!reason.value.trim()) {
    showError.value = true
    return
  }
  
  submitting.value = true
  
  try {
    await emit('submit', {
      dealId: props.dealId,
      targetStageId: props.targetStageId,
      reason: reason.value.trim()
    })
    closeModal()
  } catch (error) {
    console.error('Error submitting reason:', error)
    submitting.value = false
  }
}
</script>

<style scoped>
.stage-reason-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1060;
  backdrop-filter: blur(2px);
}

.stage-reason-modal {
  background: white;
  border-radius: 10px;
  width: 500px;
  max-width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-size: 12px;
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid #E2E8F0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--deal-navy-deep, #0B0736);
  margin: 0;
  letter-spacing: -0.02em;
  line-height: 1.35;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 20px;
  cursor: pointer;
  padding: 4px;
  color: #64748B;
}

.btn-close:hover {
  color: #1E293B;
}

.modal-body {
  padding: 16px 20px;
}

.modal-footer {
  padding: 12px 20px;
  border-top: 1px solid #E2E8F0;
  display: flex;
  justify-content: center;
  gap: 12px;
}

.form-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--deal-text-muted, #64748b);
  margin-bottom: 8px;
  display: block;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  font-size: 12px;
  line-height: 1.5;
  color: #1E293B;
  background-color: #fff;
  border: 1px solid #E2E8F0;
  border-radius: var(--deal-input-r, 10px);
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control::placeholder {
  font-size: 12px;
  color: #9ca3af;
}

.form-control:focus {
  border-color: var(--deal-navy, #0f172a);
  outline: 0;
  box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.12);
}

.form-control.is-invalid {
  border-color: #EF4444;
}

.form-control.is-invalid:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.invalid-feedback {
  color: #EF4444;
  font-size: 12px;
  margin-top: 4px;
}

.btn {
  width: 96px;
  height: 38px;
  padding: 0;
  font-size: 13px;
  font-weight: 500;
  border-radius: 999px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.btn-light {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
}

.btn-light:hover {
  background-color: #E2E8F0;
}

.btn-primary {
  background-color: var(--deal-navy, #0f172a);
  color: white;
}

.btn-primary:hover {
  background-color: #020617;
}

.btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.spinner-border {
  width: 16px;
  height: 16px;
  border-width: 2px;
}

.text-danger {
  color: #EF4444;
}

.fw-semibold {
  font-weight: 600;
}
</style>