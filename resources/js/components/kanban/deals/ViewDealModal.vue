<template>
  <b-modal
    id="view-deal-modal"
    v-model="show"
    hide-header
    hide-footer
    size="xl"
    centered
    body-class="p-0"
    modal-class="view-deal-modal-outer"
    content-class="view-deal-modal-content-wrap"
  >
    <div v-if="show" class="view-deal-modal-content p-3">
      <!-- Header (same as View Lead): title + deal type pill + close -->
      <div class="modal-header-custom d-flex justify-content-between align-items-center px-1">
        <div class="d-flex align-items-center gap-3">
          <span class="modal-title">{{ dealTitle }}</span>
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
        <button class="close-btn" @click="close" type="button">
          <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
      </div>

      <!-- Tabs (same as View Lead): General | History -->
      <div class="tabs-container mb-3 border-bottom">
        <div class="d-flex gap-4">
          <button
            class="tab-item"
            :class="{ active: activeTab === 'general' }"
            @click="activeTab = 'general'"
          >
            General
          </button>
          <button
            class="tab-item"
            :class="{ active: activeTab === 'history' }"
            @click="activeTab = 'history'"
          >
            History
          </button>
        </div>
      </div>

      <!-- Main content (same structure as View Lead: modal-body-custom p-4) -->
      <div class="modal-body-custom p-4">
        <!-- General tab: two columns like Lead -->
        <template v-if="activeTab === 'general'">
          <div class="row g-4">
            <!-- Left column: Deal Information -->
            <div class="col-md-5">
              <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
                  <div class="d-flex align-items-center gap-2">
                    <span class="modal-title">Deal Information</span>
                  </div>
                </div>
                <div v-if="dealType === 'primary'" class="row g-3 view-deal-content">
                  <ViewPrimaryDeal :deal="deal" />
                </div>
                <div v-else-if="dealType === 'secondary'" class="row g-3 view-deal-content">
                  <ViewSecondaryDeal :deal="deal" />
                </div>
                <div v-else class="row g-3 view-deal-content">
                  <ViewRentalDeal :deal="deal" />
                </div>
              </div>
            </div>

            <!-- Right column: Activity & Comments (exact same as View Lead GeneralTab) -->
            <div class="col-md-7">
              <div class="activity-card bg-white p-3 radius-12 shadow-sm">
                <div class="d-flex gap-2 mb-4 p-1 radius-100 w-fit-content toggle-buttons-container">
                  <button
                    class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100"
                    :class="{ active: activeViewTab === 'comments' }"
                    @click="activeViewTab = 'comments'"
                  >
                    <iconify-icon icon="lucide:message-square"></iconify-icon>
                    Comments
                  </button>
                  <button
                    class="btn-toggle d-flex align-items-center gap-2 px-3 py-1 radius-100"
                    :class="{ active: activeViewTab === 'activity' }"
                    @click="activeViewTab = 'activity'"
                  >
                    <iconify-icon icon="lucide:clock-3"></iconify-icon>
                    Activity
                  </button>
                </div>

                <ActivitySection
                  v-if="activeViewTab === 'activity'"
                  :lead-id="dealLeadId"
                  @activity-created="handleActivityCreated"
                />

                <CommentsSection
                  v-if="activeViewTab === 'comments'"
                  :lead-id="dealLeadId"
                  @comment-created="handleCommentCreated"
                />
              </div>

              <ActivityList
                v-if="activeViewTab === 'activity'"
                ref="activityListRef"
                :lead-id="dealLeadId"
              />
              <CommentList
                v-if="activeViewTab === 'comments'"
                ref="commentListRef"
                :lead-id="dealLeadId"
              />
              <LeadActivityTimeline
                v-if="activeViewTab === 'comments' && dealLeadId"
                :key="`timeline-deal-${deal?.id}-${dealLeadId}`"
                :lead-id="dealLeadId"
              />
              <DealCreatedCard v-if="deal?.id" :deal="deal" />
            </div>
          </div>
        </template>

        <!-- History tab placeholder -->
        <div v-if="activeTab === 'history'" class="py-4 text-center text-muted">
          Deal history will appear here.
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
import DealCreatedCard from './DealCreatedCard.vue'
import ActivitySection from '../viewLead/ActivitySection.vue'
import CommentsSection from '../viewLead/CommentsSection.vue'
import ActivityList from '../viewLead/ActivityList.vue'
import CommentList from '../viewLead/CommentList.vue'
import LeadActivityTimeline from '../viewLead/LeadActivityTimeline.vue'

const props = defineProps({
  modelValue: Boolean,
  deal: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue'])

const show = ref(props.modelValue)
const dealType = ref('primary')
const selectedStageIndex = ref(0)
const activeTab = ref('general')
const activeViewTab = ref('activity')

const dealTitle = computed(() => {
  if (!props.deal) return 'View Deal'
  const name = props.deal.project_name || props.deal.project || props.deal.deal_name
  if (name) return `Deal Done From "${name}"`
  return props.deal.id ? `Deal #${props.deal.id}` : 'View Deal'
})

const dealLeadId = computed(() => props.deal?.lead_id ?? null)

const activityListRef = ref(null)
const commentListRef = ref(null)

function handleCommentCreated(newComment) {
  if (commentListRef.value?.addComment) commentListRef.value.addComment(newComment)
}

function handleActivityCreated(newActivity) {
  if (activityListRef.value?.addActivity) activityListRef.value.addActivity(newActivity)
}

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

function selectStage(index) {
  selectedStageIndex.value = index
}

watch(() => props.modelValue, (val) => {
  show.value = val
  if (val && props.deal?.deal_type) dealType.value = props.deal.deal_type
  if (val) selectedStageIndex.value = currentStageIndex.value
})

watch(() => props.deal?.stageId, () => {
  if (show.value && props.deal) selectedStageIndex.value = currentStageIndex.value
})
watch(dealType, () => {
  if (show.value) selectedStageIndex.value = currentStageIndex.value
})

watch(show, (val) => {
  if (val && props.deal) selectedStageIndex.value = currentStageIndex.value
  emit('update:modelValue', val)
})

function close() {
  show.value = false
}
</script>

<style>
/* Global: modal size smaller (not full screen) and border */
#view-deal-modal .modal-dialog {
  max-width: 85vw !important;
  width: 85vw !important;
  max-height: 92vh !important;
  min-height: 88vh !important;
  margin: 1vh auto !important;
}
#view-deal-modal .modal-content {
  max-height: 92vh !important;
  min-height: 88vh !important;
  border-radius: 8px !important;
  overflow: hidden !important;
  border: 1px solid rgba(0, 0, 0, 0.1) !important;
}
#view-deal-modal .modal-body {
  overflow: hidden !important;
  height: 100%;
  display: flex;
  flex-direction: column;
}
</style>

<style scoped>
/* Scoped fallback (when modal is not teleported) */
:deep(.view-deal-modal-outer .modal-dialog) {
  max-width: 85vw !important;
  width: 85vw !important;
  max-height: 92vh !important;
  min-height: 88vh !important;
}
:deep(.view-deal-modal-outer .modal-content.view-deal-modal-content-wrap),
:deep(#view-deal-modal .modal-content) {
  max-height: 92vh !important;
  min-height: 88vh !important;
  border-radius: 8px !important;
  overflow: hidden !important;
}
:deep(.view-deal-modal-outer .modal-body) {
  overflow: hidden !important;
}

.view-deal-modal-content {
  background: #fff;
  font-family: 'Montserrat', sans-serif;
  display: flex;
  flex-direction: column;
  min-height: 100%;
  height: 100%;
  overflow: hidden;
  max-width: 100%;
}

/* Header (match View Lead) */
.modal-header-custom {
  background: #fff;
}
.modal-title {
  font-size: 16px;
  font-weight: 600;
  color: #01062C;
}
.deals-type-tabs-inline {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.deals-type-tab-inline {
  padding: 6px 14px;
  border-radius: 100px;
  border: none;
  font-size: 13px;
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
  background: none;
  border: none;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 20px;
  color: #000;
}
.close-btn:hover {
  color: #1E293B;
}

/* Tabs (match View Lead) */
.tab-item {
  background: none;
  border: none;
  padding: 12px 10px;
  font-size: 13px;
  font-weight: 500;
  color: #64748B;
  position: relative;
  cursor: pointer;
}
.tab-item.active {
  color: #01062C;
}
.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  width: 100%;
  height: 2px;
  background: #FAA300;
}

.radius-12 { border-radius: 12px; }
.radius-100 { border-radius: 100px; }

/* Two-column cards (match GeneralTab) */
.info-card .modal-header-custom.pb-9 { padding-bottom: 0.5rem; }
.activity-card {
  border: 1px solid #F4F4F4;
}
.toggle-buttons-container {
  border: 1px solid #EDEDED;
  box-shadow: 2px 2px 20px 4px #7090B014;
}
.w-fit-content { width: fit-content; }
.btn-toggle {
  background: none;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: #64748B;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-toggle.active {
  background: #01062C;
  color: #fff;
  box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}
.btn-primary {
  background: #01062C;
  border: none;
  font-weight: 500;
}
.btn-light {
  background: #F1F5F9;
  border: none;
  color: #475569;
  font-weight: 500;
}
.view-deal-content {
  max-width: 100%;
  min-width: 0;
}

.modal-body-custom {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
}

/* Scrollable content area: vertical scroll only, no horizontal scrollbar */
.form-scroll-area {
  flex: 1 1 auto;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 0 0.25rem;
  max-width: 100%;
}
.form-scroll-area::-webkit-scrollbar {
  width: 8px;
}
.form-scroll-area::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
.form-scroll-area::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.step-content {
  padding: 1rem 0;
  max-width: 100%;
  min-width: 0;
}
.view-deal-row {
  padding: 0 1rem 1rem;
  max-width: 100%;
  min-width: 0;
}

</style>
