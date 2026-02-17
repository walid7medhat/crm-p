<template>
  <b-modal
    id="create-deal-modal"
    v-model="show"
    hide-header
    hide-footer
    size="xl"
    centered
    body-class="p-0"
  >
    <div class="create-deal-modal-content p-3">
      <!-- Header: Title + Deal Type Tabs + Close -->
      <div class="modal-header-deal d-flex justify-content-between align-items-center flex-wrap gap-2 px-1">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
          <span class="modal-title">Create New Deal</span>
          <div class="deals-type-tabs-inline d-flex gap-2">
            <button
              v-for="tab in dealTypeTabs"
              :key="tab.id"
              class="deals-type-tab-inline"
              :class="{ active: dealType === tab.id }"
              @click="dealType = tab.id"
            >
              {{ tab.name }}
            </button>
          </div>
        </div>
        <button class="close-btn border-0 bg-transparent p-0 d-flex align-items-center justify-content-center" @click="close">
          <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
      </div>

      <!-- Deal progress / stages (changes by deal type) -->
      <div class="deal-progress-wrapper py-3 px-1">
        <div class="deal-progress-bar">
          <template v-for="(stage, index) in currentStages" :key="stage.id">
            <div
              class="deal-stage-pill"
              :class="{ active: index <= selectedStageIndex }"
              :style="{
                backgroundColor: index <= selectedStageIndex ? stage.bg : 'transparent',
                borderColor: index <= selectedStageIndex ? stage.dotColor : '#E2E8F0'
              }"
              @click="selectedStageIndex = index"
            >
              <div class="stage-circle">
                <div class="stage-dot" :style="{ backgroundColor: stage.dotColor }"></div>
              </div>
              <span class="stage-text">{{ stage.name }}</span>
            </div>
            <iconify-icon v-if="index < currentStages.length - 1" icon="lucide:chevron-right" class="stage-arrow"></iconify-icon>
          </template>
        </div>
      </div>

      <!-- Form content (Primary / Secondary / Rental) -->
      <div class="form-scroll-area">
        <!-- Primary / Off Plan -->
        <div v-if="dealType === 'primary'" class="step-content">
          <PrimaryDealForm ref="primaryFormRef" v-model="primaryForm" :users="users" />
        </div>
        <!-- Secondary -->
        <div v-else-if="dealType === 'secondary'" class="step-content">
          <SecondaryDealForm ref="secondaryFormRef" v-model="secondaryForm" :users="users" />
        </div>
        <!-- Rental -->
        <div v-else class="step-content">
          <RentalDealForm ref="rentalFormRef" v-model="rentalForm" :users="users" />
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer-custom">
        <div class="d-flex align-items-center justify-content-end gap-3">
          <button class="btn-clear" @click="resetForm" :disabled="isSubmitting">Clear</button>
          <button class="btn-next-step" @click="submitForm" :disabled="isSubmitting">
            <span v-if="isSubmitting">Creating...</span>
            <span v-else>Next Step</span>
            <iconify-icon icon="lucide:chevron-right" class="ms-1"></iconify-icon>
          </button>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { BModal } from 'bootstrap-vue-3'
import PrimaryDealForm from './PrimaryDealForm.vue'
import SecondaryDealForm from './SecondaryDealForm.vue'
import RentalDealForm from './RentalDealForm.vue'
import api from '@/plugins/axios'

const props = defineProps({
  modelValue: Boolean
})

const emit = defineEmits(['update:modelValue', 'deal-created'])

const show = ref(props.modelValue)
const dealType = ref('primary')
const selectedStageIndex = ref(0)
const isSubmitting = ref(false)
const users = ref([])

const primaryFormRef = ref(null)
const secondaryFormRef = ref(null)
const rentalFormRef = ref(null)

const dealTypeTabs = [
  { id: 'primary', name: 'Primary / Off Plan' },
  { id: 'secondary', name: 'Secondary' },
  { id: 'rental', name: 'Rental' }
]

// Primary stages: EOI, Booking, SPA Signed, Deal Lost, Deal Won
const primaryStages = [
  { id: 'eoi', name: 'EOI', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'booking', name: 'Booking', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'spa-signed', name: 'SPA Signed (Deal Done)', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'deal-lost', name: 'Deal Lost', bg: '#FEE2E2', dotColor: '#DC2626' },
  { id: 'deal-won', name: 'Deal Won', bg: '#D1FAE5', dotColor: '#059669' }
]

// Secondary stages
const secondaryStages = [
  { id: 'security-deposit', name: 'Security Deposit', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'mou-signed', name: 'MOU / Contract If Signed', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'noc', name: 'NOC', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'deal-lost', name: 'Deal Lost', bg: '#FEE2E2', dotColor: '#DC2626' },
  { id: 'deal-won', name: 'Deal Won', bg: '#D1FAE5', dotColor: '#059669' }
]

// Rental stages
const rentalStages = [
  { id: 'lease-offer', name: 'Lease Offer Letter', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'guarantee', name: 'Guarantee Letter / Cheque Collected', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'internal-contract', name: 'Internal Contract Signed', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'ejari', name: 'Ejari / Tawtheq Issued', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'tenant-moved', name: 'Tenant moved in', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'close-deal', name: 'Close Deal', bg: '#D1FAE5', dotColor: '#059669' }
]

const currentStages = computed(() => {
  if (dealType.value === 'secondary') return secondaryStages
  if (dealType.value === 'rental') return rentalStages
  return primaryStages
})

// Reset stage index when switching deal type
watch(dealType, () => {
  selectedStageIndex.value = 0
})

const primaryForm = ref({})
const secondaryForm = ref({})
const rentalForm = ref({})

watch(() => props.modelValue, (val) => {
  show.value = val
})

watch(show, (val) => {
  emit('update:modelValue', val)
  if (!val) resetForm()
})

async function fetchUsers() {
  try {
    const response = await api.get('/available-responsible-persons')
    const data = response.data?.data ?? response.data
    users.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.error('Error fetching users:', e)
  }
}

onMounted(() => {
  fetchUsers()
})

function close() {
  show.value = false
}

function resetForm() {
  selectedStageIndex.value = 0
  primaryForm.value = {}
  secondaryForm.value = {}
  rentalForm.value = {}
}

function submitForm() {
  // TODO: when backend exists, validate and POST; for now just close and emit
  const stage = currentStages.value[selectedStageIndex.value]
  const payload = {
    deal_type: dealType.value,
    stage_id: stage?.id,
    stage_index: selectedStageIndex.value,
    form: dealType.value === 'primary' ? primaryForm.value : dealType.value === 'secondary' ? secondaryForm.value : rentalForm.value
  }
  emit('deal-created', payload)
  close()
}
</script>

<style scoped>
.create-deal-modal-content {
  background: #fff;
  border-radius: 12px;
  font-family: 'Montserrat', sans-serif;
}

.modal-header-deal {
  padding: 0.5rem 0;
  border-bottom: 1px solid #F4F4F4;
}

.modal-title {
  font-weight: 600;
  font-size: 16px;
  color: #01062C;
}

.deals-type-tabs-inline {
  flex-wrap: wrap;
}

.deals-type-tab-inline {
  padding: 6px 14px;
  border-radius: 100px;
  border: none;
  font-size: 12px;
  font-weight: 500;
  color: #64748B;
  background: #F1F5F9;
  cursor: pointer;
  transition: all 0.2s;
}

.deals-type-tab-inline:hover {
  color: #1E293B;
  background: #E2E8F0;
}

.deals-type-tab-inline.active {
  background: #0F172A;
  color: #fff;
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

.deal-progress-wrapper {
  overflow-x: auto;
  scrollbar-width: none;
}

.deal-progress-wrapper::-webkit-scrollbar {
  display: none;
}

.deal-progress-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.deal-stage-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 30px;
  border: 1px solid #E2E8F0;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.deal-stage-pill .stage-circle {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.deal-stage-pill .stage-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.deal-stage-pill .stage-text {
  font-size: 12px;
  color: #64748B;
}

.deal-stage-pill.active .stage-text {
  color: #01062C;
  font-weight: 500;
}

.stage-arrow {
  font-size: 14px;
  color: #CBD5E1;
  flex-shrink: 0;
}

.form-scroll-area {
  max-height: 60vh;
  overflow-y: auto;
  padding: 0 0.5rem;
}

.step-content {
  padding: 0.5rem 0;
}

.modal-footer-custom {
  border-top: 1px solid #F4F4F4;
  padding: 15px;
}

.btn-clear {
  background: #F4F4F4;
  border: none;
  padding: 10px 25px;
  border-radius: 100px;
  font-size: 14px;
  color: #01062C;
  cursor: pointer;
}

.btn-next-step {
  background: #01062C;
  border: none;
  padding: 10px 20px;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-next-step:hover:not(:disabled) {
  background: #0f172a;
}
</style>
