<template>
  <b-modal
    id="view-deal-modal"
    v-model="show"
    hide-header
    hide-footer
    size="xl"
    centered
    body-class="p-0"
  >
    <div class="view-deal-modal-content p-3">
      <!-- Header -->
      <div class="modal-header-custom d-flex justify-content-between align-items-center px-1">
        <span class="modal-title">{{ deal?.project || 'Deal' }}</span>
        <button class="close-btn border-0 bg-transparent p-0 d-flex align-items-center justify-content-center" @click="close">
          <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
      </div>

      <!-- Deal info (same structure as view lead) -->
      <div class="deal-info-card bg-white p-4 radius-12 shadow-sm mt-3">
        <h6 class="mb-3 fw-semibold">Deal Information</h6>
        <div class="info-group mb-3">
          <label class="info-label">Project</label>
          <p class="info-value mb-0">{{ deal?.project || '----' }}</p>
        </div>
        <div class="info-group mb-3">
          <label class="info-label">Created By</label>
          <span class="info-value">{{ deal?.createdBy || '----' }}</span>
        </div>
        <div class="info-group mb-3">
          <label class="info-label">Buyer Name</label>
          <span class="info-value">{{ deal?.buyerName || '----' }}</span>
        </div>
        <div class="info-group mb-3">
          <label class="info-label">Source</label>
          <span class="info-value">{{ deal?.source || '----' }}</span>
        </div>
        <div class="info-group mb-0">
          <label class="info-label mb-2">Assigned By</label>
          <div class="d-flex align-items-center justify-content-between">
            <span class="info-value">{{ deal?.assignedBy || '----' }}</span>
            <div class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
              <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
            </div>
          </div>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { BModal } from 'bootstrap-vue-3'

const props = defineProps({
  modelValue: Boolean,
  deal: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue'])

const show = ref(props.modelValue)

watch(() => props.modelValue, (val) => {
  show.value = val
})

watch(show, (val) => {
  emit('update:modelValue', val)
})

function close() {
  show.value = false
}
</script>

<style scoped>
.view-deal-modal-content {
  font-family: 'Montserrat', sans-serif;
}

.modal-header-custom {
  padding: 0.5rem 0;
}

.modal-title {
  font-weight: 600;
  font-size: 18px;
  color: #01062C;
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  color: #64748B;
  transition: background 0.2s, color 0.2s;
}

.close-btn:hover {
  background: #F1F5F9;
  color: #1E293B;
}

.info-label {
  font-size: 11px;
  font-weight: 500;
  color: #979797;
  display: block;
  margin-bottom: 4px;
}

.info-value {
  font-size: 13px;
  font-weight: 500;
  color: #353535;
}

.avatar-sm {
  width: 32px;
  height: 32px;
  object-fit: cover;
}
</style>
