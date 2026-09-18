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
  background:
    radial-gradient(ellipse at 15% 10%, rgba(124, 58, 237, 0.12), transparent 42%),
    radial-gradient(ellipse at 90% 90%, rgba(168, 85, 247, 0.1), transparent 40%),
    rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1060;
}

.stage-reason-modal {
  background: #ffffff;
  border-radius: 20px;
  width: 500px;
  max-width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  border: 1px solid #e9e5f5;
  box-shadow:
    0 28px 70px rgba(15, 23, 42, 0.18),
    0 0 0 1px rgba(124, 58, 237, 0.04);
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-size: 12px;
  color: #0f172a;
  --deal-accent: #a855f7;
  --deal-accent-text: #7c3aed;
  --deal-navy: #7c3aed;
  --deal-navy-deep: #0f172a;
  --deal-link: #7c3aed;
}

.modal-header {
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
}

.modal-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.02em;
  line-height: 1.35;
}

.btn-close {
  width: 34px;
  height: 34px;
  border: 1px solid #e2e8f0;
  border-radius: 50%;
  background: #f8fafc;
  color: #64748b;
  font-size: 16px;
  cursor: pointer;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.btn-close:hover {
  background: #f3e8ff;
  border-color: #d8b4fe;
  color: #7c3aed;
}

.modal-body {
  padding: 18px 24px;
  background: #ffffff;
}

.modal-footer {
  padding: 14px 24px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: center;
  gap: 10px;
  background: #ffffff;
}

.form-label {
  font-size: 12px;
  font-weight: 650;
  color: #1f2937;
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
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control::placeholder {
  font-size: 12px;
  color: #9ca3af;
}

.form-control:focus {
  border-color: #a855f7;
  outline: 0;
  box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.16);
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
  min-width: 108px;
  height: 42px;
  padding: 0 18px;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 12px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  gap: 7px;
}

.btn-light {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #475569;
}

.btn-light:hover {
  background-color: #f1f5f9;
  border-color: #cbd5e1;
}

.btn-primary {
  background: linear-gradient(135deg, #d946ef 0%, #a855f7 45%, #7c3aed 100%);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 10px 24px rgba(168, 85, 247, 0.32);
}

.btn-primary:hover {
  filter: brightness(1.06);
  color: #fff;
}

.btn-primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
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