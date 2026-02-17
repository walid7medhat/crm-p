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
      <!-- Header: same as Create - Title + Deal Type Tabs + Close -->
      <div class="modal-header-deal d-flex justify-content-between align-items-center flex-wrap gap-2 px-1">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
          <span class="modal-title">View Deal</span>
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

      <!-- Deal progress / stages (read-only, highlight current) -->
      <div class="deal-progress-wrapper py-3 px-1">
        <div class="deal-progress-bar">
          <template v-for="(stage, index) in currentStages" :key="stage.id">
            <div
              class="deal-stage-pill view-only"
              :class="{ active: index <= currentStageIndex }"
              :style="{
                backgroundColor: index <= currentStageIndex ? stage.bg : 'transparent',
                borderColor: index <= currentStageIndex ? stage.dotColor : '#E2E8F0'
              }"
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

      <!-- View content: same sections as Create but read-only -->
      <div class="form-scroll-area">
        <div class="step-content">
          <div v-if="dealType === 'primary'" class="row g-4 p-4">
            <ViewPrimaryDeal :deal="deal" />
          </div>
          <div v-else-if="dealType === 'secondary'" class="row g-4 p-4">
            <ViewSecondaryDeal :deal="deal" />
          </div>
          <div v-else class="row g-4 p-4">
            <ViewRentalDeal :deal="deal" />
          </div>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { BModal } from 'bootstrap-vue-3'
import ViewPrimaryDeal from './ViewPrimaryDeal.vue'
import ViewSecondaryDeal from './ViewSecondaryDeal.vue'
import ViewRentalDeal from './ViewRentalDeal.vue'

const props = defineProps({
  modelValue: Boolean,
  deal: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue'])

const show = ref(props.modelValue)
const dealType = ref('primary')

const dealTypeTabs = [
  { id: 'primary', name: 'Primary / Off Plan' },
  { id: 'secondary', name: 'Secondary' },
  { id: 'rental', name: 'Rental' }
]

const primaryStages = [
  { id: 'eoi', name: 'EOI', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'booking', name: 'Booking', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'spa-signed', name: 'SPA Signed (Deal Done)', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'deal-lost', name: 'Deal Lost', bg: '#FEE2E2', dotColor: '#DC2626' },
  { id: 'deal-won', name: 'Deal Won', bg: '#D1FAE5', dotColor: '#059669' }
]

const secondaryStages = [
  { id: 'security-deposit', name: 'Security Deposit', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'mou-signed', name: 'MOU / Contract If Signed', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'noc', name: 'NOC', bg: '#D1FAE5', dotColor: '#059669' },
  { id: 'deal-lost', name: 'Deal Lost', bg: '#FEE2E2', dotColor: '#DC2626' },
  { id: 'deal-won', name: 'Deal Won', bg: '#D1FAE5', dotColor: '#059669' }
]

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

const currentStageIndex = computed(() => {
  const d = props.deal
  if (!d?.stageId) return 0
  let stageId = d.stageId
  if (stageId === 'deal-lost-sec') stageId = 'deal-lost'
  if (stageId === 'deal-won-sec') stageId = 'deal-won'
  if (stageId === 'lease-off') stageId = 'lease-offer'
  if (stageId === 'guarantee-letter') stageId = 'guarantee'
  const idx = currentStages.value.findIndex(s => s.id === stageId)
  return idx >= 0 ? idx : 0
})

watch(() => props.modelValue, (val) => {
  show.value = val
  if (val && props.deal?.deal_type) dealType.value = props.deal.deal_type
})

watch(show, (val) => {
  emit('update:modelValue', val)
})

function close() {
  show.value = false
}
</script>

<style scoped>
.view-deal-modal-content { background: #fff; border-radius: 12px; font-family: 'Montserrat', sans-serif; }
.modal-header-deal { padding: 0.5rem 0; border-bottom: 1px solid #F4F4F4; }
.modal-title { font-weight: 600; font-size: 16px; color: #01062C; }
.deals-type-tabs-inline { flex-wrap: wrap; }
.deals-type-tab-inline { padding: 6px 14px; border-radius: 100px; border: none; font-size: 12px; font-weight: 500; color: #64748B; background: #F1F5F9; cursor: pointer; transition: all 0.2s; }
.deals-type-tab-inline:hover { color: #1E293B; background: #E2E8F0; }
.deals-type-tab-inline.active { background: #0F172A; color: #fff; }
.close-btn { width: 32px; height: 32px; border-radius: 50%; cursor: pointer; color: #64748B; transition: background 0.2s, color 0.2s; }
.close-btn:hover { background: #F1F5F9; color: #1E293B; }
.deal-progress-wrapper { overflow-x: auto; scrollbar-width: none; }
.deal-progress-wrapper::-webkit-scrollbar { display: none; }
.deal-progress-bar { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.deal-stage-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 30px; border: 1px solid #E2E8F0; white-space: nowrap; transition: all 0.2s; }
.deal-stage-pill.view-only { cursor: default; }
.deal-stage-pill .stage-circle { width: 14px; height: 14px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; }
.deal-stage-pill .stage-dot { width: 8px; height: 8px; border-radius: 50%; }
.deal-stage-pill .stage-text { font-size: 12px; color: #64748B; }
.deal-stage-pill.active .stage-text { color: #01062C; font-weight: 500; }
.stage-arrow { font-size: 14px; color: #CBD5E1; flex-shrink: 0; }
.form-scroll-area { max-height: 60vh; overflow-y: auto; padding: 0 0.5rem; }
.step-content { padding: 0.5rem 0; }
</style>
