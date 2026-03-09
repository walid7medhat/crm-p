<template>
  <Teleport to="body">
    <div v-if="show" class="complete-fields-overlay" @click.self="closeModal">
      <div class="complete-fields-modal">
        <div class="modal-header">
          <h6 class="modal-title">Complete All The Required Fields To Change Deal Stage</h6>
          <button type="button" class="btn-close" @click="closeModal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p v-if="targetStageName" class="mb-3 text-muted">
            Complete the missing fields below to move this deal to <strong>{{ targetStageName }}</strong>.
          </p>

          <!-- Per-stage: show each stage with its missing fields -->
          <template v-if="stagesWithSections.length">
            <div v-for="stageBlock in stagesWithSections" :key="stageBlock.stage_order" class="stage-block mb-4">
              <h5 class="stage-block-title">Stage {{ stageBlock.stage_order }} — {{ stageBlock.stage_name }}</h5>
              <p class="stage-block-hint text-muted small mb-2">Complete the following for this stage:</p>
              <div v-for="section in stageBlock.sections" :key="section.title" class="section-block mb-3">
                <h6 class="section-title">{{ section.title }} <span v-if="isRequiredSection(section.title)" class="text-danger">*</span></h6>
                <div class="section-fields">
                  <template v-for="field in section.fields" :key="field.key">
                    <div v-if="field.key && field.key.startsWith('_')" class="form-group mb-3">
                      <p class="text-muted mb-0">{{ field.label }}</p>
                    </div>
                    <div v-else-if="field.type === 'file'" class="form-group mb-3">
                      <label class="form-label">{{ field.label }} <span class="text-danger">*</span></label>
                      <div class="document-hint border rounded p-3 bg-light">
                        <p class="mb-2 small text-muted">Please upload this document in the deal details.</p>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="openDealForDocuments">Open Deal</button>
                      </div>
                    </div>
                    <div v-else-if="isPartyRequiredKey(field.key)" class="form-group mb-3">
                      <label class="form-label">{{ field.label }}</label>
                      <div class="document-hint border rounded p-3 bg-light">
                        <p class="mb-0 small text-muted">Please complete this section in the deal view.</p>
                      </div>
                    </div>
                    <div v-else class="form-group mb-3">
                      <label class="form-label">{{ field.label }} <span class="text-danger">*</span></label>
                      <input v-if="field.type === 'date'" v-model="formData[field.key]" type="date" class="form-control" />
                      <input v-else-if="field.type === 'number'" v-model.number="formData[field.key]" type="number" step="any" class="form-control" :placeholder="getPlaceholder(field)" />
                      <input v-else v-model="formData[field.key]" type="text" class="form-control" :placeholder="getPlaceholder(field)" />
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </template>

          <!-- Fallback: flat sections when no per-stage data -->
          <template v-else>
          <div v-for="section in sections" :key="section.title" class="section-block mb-4">
            <h6 class="section-title">{{ section.title }} <span v-if="isRequiredSection(section.title)" class="text-danger">*</span></h6>
            <div class="section-fields">
              <template v-for="field in section.fields" :key="field.key">
                <!-- Placeholder / info text (no input) -->
                <div v-if="field.key && field.key.startsWith('_')" class="form-group mb-3">
                  <p class="text-muted mb-0">{{ field.label }}</p>
                </div>
                <div v-else-if="field.type === 'file'" class="form-group mb-3">
                  <label class="form-label">{{ field.label }} <span class="text-danger">*</span></label>
                  <div class="document-hint border rounded p-3 bg-light">
                    <p class="mb-2 small text-muted">Please upload this document in the deal details.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openDealForDocuments">Open Deal</button>
                  </div>
                </div>
                <div v-else-if="isPartyRequiredKey(field.key)" class="form-group mb-3">
                  <label class="form-label">{{ field.label }}</label>
                  <div class="document-hint border rounded p-3 bg-light">
                    <p class="mb-0 small text-muted">Please complete this section in the deal view.</p>
                  </div>
                </div>
                <div v-else class="form-group mb-3">
                  <label class="form-label">{{ field.label }} <span class="text-danger">*</span></label>
                  <input
                    v-if="field.type === 'date'"
                    v-model="formData[field.key]"
                    type="date"
                    class="form-control"
                  />
                  <input
                    v-else-if="field.type === 'number'"
                    v-model.number="formData[field.key]"
                    type="number"
                    step="any"
                    class="form-control"
                    :placeholder="getPlaceholder(field)"
                  />
                  <input
                    v-else
                    v-model="formData[field.key]"
                    type="text"
                    class="form-control"
                    :placeholder="getPlaceholder(field)"
                  />
                </div>
              </template>
            </div>
          </div>
          </template>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" @click="closeModal">Cancel</button>
          <button
            type="button"
            class="btn btn-primary btn-save"
            :disabled="submitting"
            @click="submitForm"
          >
            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
            {{ submitting ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  dealId: { type: [Number, String], default: null },
  dealType: { type: String, default: 'primary' },
  targetStageId: { type: [Number, String], default: null },
  targetStageName: { type: String, default: '' },
  missingFields: { type: Array, default: () => [] },
  missingFieldsGrouped: { type: Object, default: () => ({ sections: [] }) },
  missingFieldsGroupedByStage: { type: Object, default: () => ({ stages: [] }) },
  deal: { type: Object, default: null }
})

const emit = defineEmits(['save', 'closed', 'open-deal'])

const formData = ref({})
const submitting = ref(false)

const sections = computed(() => props.missingFieldsGrouped?.sections || [])

const stagesWithSections = computed(() => {
  const stages = props.missingFieldsGroupedByStage?.stages || []
  return Array.isArray(stages) && stages.length ? stages : []
})

const allSectionsForForm = computed(() => {
  if (stagesWithSections.value.length) {
    return stagesWithSections.value.flatMap(s => s.sections || [])
  }
  return sections.value
})

watch([() => props.show, () => props.missingFieldsGrouped, () => props.missingFieldsGroupedByStage, () => props.deal], () => {
  const secs = stagesWithSections.value.length ? stagesWithSections.value.flatMap(s => s.sections || []) : sections.value
  if (props.show && secs.length) {
    const initial = {}
    secs.forEach(sec => {
      (sec.fields || []).forEach(f => {
        initial[f.key] = getInitialValue(f.key)
      })
    })
    formData.value = { ...initial }
  }
}, { immediate: true, deep: true })

function getInitialValue(key) {
  const deal = props.deal
  if (!deal) return ''
  if (key === 'subcommunity_id' && deal.area_id != null) return deal.area_id
  if (deal[key] !== undefined && deal[key] !== null) return deal[key]
  const partyKeys = [
    { prefix: 'buyer_', type: 'buyer' },
    { prefix: 'seller_', type: 'seller' },
    { prefix: 'tenant_', type: 'tenant' },
    { prefix: 'landlord_', type: 'landlord' }
  ]
  for (const { prefix, type } of partyKeys) {
    if (!key.startsWith(prefix)) continue
    const party = deal.parties?.find(p => p.party_type === type)
    if (!party) return ''
    const field = key.slice(prefix.length)
    const attr = field === 'dob' ? 'date_of_birth' : field
    return party[attr] ?? ''
  }
  return ''
}

function getPlaceholder(field) {
  if (field.type === 'select') return 'Not Selected'
  if (field.label && field.label.includes('%')) return 'Enter ' + field.label
  return 'Enter ' + (field.label || field.key)
}

function isRequiredSection(title) {
  return title !== 'Other'
}

function isPartyRequiredKey(key) {
  return ['buyer_party', 'seller_party', 'tenant_party', 'landlord_party'].includes(key)
}

function openDealForDocuments() {
  emit('open-deal', props.dealId)
  closeModal()
}

function closeModal() {
  formData.value = {}
  submitting.value = false
  emit('closed')
}

function buildPayload() {
  const payload = {}
  const secs = allSectionsForForm.value
  secs.forEach(sec => {
    (sec.fields || []).forEach(f => {
      if (f.key && f.key.startsWith('_')) return
      if (f.type === 'file' || isPartyRequiredKey(f.key)) return
      const v = formData.value[f.key]
      if (v === undefined || v === null) return
      if (f.key === 'subcommunity_id') {
        payload['area_id'] = v
      } else {
        payload[f.key] = v
      }
    })
  })
  return payload
}

async function submitForm() {
  const payload = buildPayload()
  submitting.value = true
  try {
    await emit('save', { payload })
    closeModal()
  } catch (e) {
    console.error('CompleteStageFieldsModal submit error', e)
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.complete-fields-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1060;
  backdrop-filter: blur(2px);
}

.complete-fields-modal {
  background: white;
  border-radius: 12px;
  width: 560px;
  max-width: 95vw;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid #E2E8F0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.modal-title {
  font-size: 18px;
  font-weight: 600;
  color: #1E293B;
  margin: 0;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 20px;
  cursor: pointer;
  padding: 4px;
  color: #64748B;
}

.btn-close:hover { color: #1E293B; }

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.stage-block {
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  padding: 14px 16px;
  background: #F8FAFC;
}

.stage-block-title {
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
  margin: 0 0 4px 0;
}

.stage-block-hint { margin-bottom: 10px; }

.section-block { padding-bottom: 8px; }

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #1E293B;
  margin-bottom: 12px;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #1E293B;
  margin-bottom: 6px;
  display: block;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  line-height: 1.5;
  color: #1E293B;
  background: #fff;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
}

.form-control:focus {
  border-color: #3B82F6;
  outline: 0;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.document-hint { font-size: 13px; }

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #E2E8F0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  flex-shrink: 0;
}

.btn {
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
}

.btn-light {
  background: #F1F5F9;
  border-color: #E2E8F0;
  color: #1E293B;
}

.btn-primary { background: #0F172A; color: white; }

.btn-primary:hover:not(:disabled) { background: #1E293B; }

.btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }

.btn-save { min-width: 100px; }

.text-danger { color: #EF4444; }

.text-muted { color: #64748B; }
</style>
