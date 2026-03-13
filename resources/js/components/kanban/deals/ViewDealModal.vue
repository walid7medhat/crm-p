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
    <div v-if="show" class="view-deal-modal-content view-deal-modal-padding">
      <!-- Header: title + deal type dropdown + close -->
      <div class="view-deal-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <span class="view-deal-title">{{ dealTitle }}</span>
          <b-dropdown
            class="deal-type-dropdown"
            menu-class="deal-type-dropdown-menu"
            toggle-class="deal-type-dropdown-toggle"
            variant="none"
            no-caret
          >
            <template #button-content>
              <span class="deal-type-dropdown-label">{{ selectedDealTypeName }}</span>
              <iconify-icon icon="lucide:chevron-down" class="deal-type-dropdown-chevron"></iconify-icon>
            </template>
            <b-dropdown-item
              v-for="tab in dealTypeTabs"
              :key="tab.id"
              :active="dealType === tab.id"
              class="deal-type-dropdown-item"
              @click="dealType = tab.id"
            >
              {{ tab.name }}
            </b-dropdown-item>
          </b-dropdown>
        </div>
        <button class="close-btn" @click="close" type="button">
          <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
      </div>

      <!-- Stage progress bar: clickable stage tabs -->
      <div class="deal-progress-wrapper py-3">
        <div class="deal-progress-bar">
          <template v-for="(stage, index) in currentStages" :key="stage.id">
            <div
              class="deal-stage-pill"
              :class="{ active: selectedStageIndex === index }"
              :style="{
                backgroundColor: selectedStageIndex === index ? (stage.bg || '#DBEAFE') : 'transparent',
                borderColor: selectedStageIndex === index ? (stage.dotColor || '#3B82F6') : '#E2E8F0'
              }"
              role="button"
              tabindex="0"
              @click="selectStage(index)"
              @keydown.enter="selectStage(index)"
              @keydown.space.prevent="selectStage(index)"
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

      <!-- Tabs: General | History (orange underline for active) -->
      <div class="tabs-container border-bottom">
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

      <!-- Main content -->
      <div class="modal-body-custom view-deal-body-padding">
        <!-- General tab: two columns like Lead -->
        <template v-if="activeTab === 'general'">
          <div class="row g-4">
            <!-- Left column: Deal Information (with edit icon like image) -->
            <div class="col-md-5">
              <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="info-card-header d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
                  <span class="info-card-title">Deal Information</span>
                  <button type="button" class="btn-edit-icon" aria-label="Edit">
                    <iconify-icon icon="lucide:pencil"></iconify-icon>
                  </button>
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

            <!-- Right column: Activity (first, dark when active) | Comments -->
            <div class="col-md-7">
              <div class="activity-card bg-white p-3 radius-12 shadow-sm">
                <div class="d-flex gap-2 mb-4 w-fit-content toggle-buttons-container">
                  <button
                    class="btn-toggle btn-toggle-activity d-flex align-items-center gap-2"
                    :class="{ active: activeViewTab === 'activity' }"
                    @click="activeViewTab = 'activity'"
                  >
                    <iconify-icon icon="lucide:clock-3"></iconify-icon>
                    Activity
                  </button>
                  <button
                    class="btn-toggle btn-toggle-comments d-flex align-items-center gap-2"
                    :class="{ active: activeViewTab === 'comments' }"
                    @click="activeViewTab = 'comments'"
                  >
                    <iconify-icon icon="lucide:message-square"></iconify-icon>
                    Comments
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
                v-if="activeViewTab === 'activity' && dealLeadId"
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
import { BModal, BDropdown, BDropdownItem } from 'bootstrap-vue-3'
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

const selectedDealTypeName = computed(() => {
  const tab = dealTypeTabs.find(t => t.id === dealType.value)
  return tab ? tab.name : 'Primary / Off Plan'
})

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
  { id: 'new', name: 'New', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'eoi', name: 'EOI', bg: '#DBEAFE', dotColor: '#3B82F6' },
  { id: 'booking', name: 'Booking', bg: '#D1FAE5', dotColor: '#22C55E' },
  { id: 'spa-signed', name: 'SPA Signed (Deal Done)', bg: '#D1FAE5', dotColor: '#22C55E' },
  { id: 'deal-won', name: 'Deal Won', bg: '#D1FAE5', dotColor: '#22C55E' },
  { id: 'deal-lost', name: 'Deal Lost', bg: '#FEE2E2', dotColor: '#EF4444' }
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
  if (!d) return 0
  const stages = currentStages.value
  let stageId = d.stageId ?? d.stage_id
  if (stageId == null) return 0
  if (stageId === 'deal-lost-sec') stageId = 'deal-lost'
  if (stageId === 'deal-won-sec') stageId = 'deal-won'
  if (stageId === 'lease-off') stageId = 'lease-offer'
  if (stageId === 'guarantee-letter') stageId = 'guarantee'
  const idx = stages.findIndex(s => String(s.id) === String(stageId))
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
/* Global: modal large, 12px radius, like image */
#view-deal-modal .modal-dialog {
  max-width: 95vw !important;
  width: 95vw !important;
  max-width: 1200px !important;
  max-height: 92vh !important;
  min-height: 85vh !important;
  margin: 2vh auto !important;
}
#view-deal-modal .modal-content {
  max-height: 92vh !important;
  min-height: 85vh !important;
  border-radius: 12px !important;
  overflow: hidden !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12) !important;
}
#view-deal-modal .modal-body {
  overflow: hidden !important;
  height: 100%;
  display: flex;
  flex-direction: column;
}
</style>

<style scoped>
:deep(.view-deal-modal-outer .modal-dialog) {
  max-width: 95vw !important;
  width: 95vw !important;
  max-width: 1200px !important;
  max-height: 92vh !important;
  min-height: 85vh !important;
}
:deep(.view-deal-modal-outer .modal-content.view-deal-modal-content-wrap),
:deep(#view-deal-modal .modal-content) {
  max-height: 92vh !important;
  min-height: 85vh !important;
  border-radius: 12px !important;
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

.view-deal-body-padding {
  padding: 1rem 2.5rem 1.5rem 2.5rem;
}

/* Header: title 18px + deal type tag pill + close */
.view-deal-header {
  padding: 0.5rem 0.75rem 0.5rem 0.75rem;
  border-bottom: none;
}
.view-deal-title {
  font-size: 18px;
  font-weight: 600;
  color: #01062C;
}
.deal-type-tag-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 32px;
  padding: 0 12px;
  border-radius: 100px;
  background: #F1F5F9;
  color: #64748B;
  font-size: 13px;
  font-weight: 500;
}
.deal-type-tag-icon {
  font-size: 14px;
  opacity: 0.8;
}
.close-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #F1F5F9;
  border: none;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 18px;
  color: #64748B;
  transition: background 0.2s, color 0.2s;
}
.close-btn:hover {
  background: #E2E8F0;
  color: #1E293B;
}

/* Stage progress bar (like Create Deal / image) */
.deal-progress-wrapper {
  overflow-x: auto;
  scrollbar-width: none;
  padding-left: 0.75rem;
  padding-right: 0.75rem;
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
  height: 32px;
  min-height: 32px;
  padding: 0 12px;
  border-radius: 100px;
  border: 1px solid #E2E8F0;
  transition: all 0.2s;
  white-space: nowrap;
  box-sizing: border-box;
}
.deal-stage-pill .stage-circle {
  width: 14px;
  height: 14px;
  min-width: 14px;
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
  font-size: 13px;
  color: #64748B;
  font-weight: 400;
}
.deal-stage-pill.active .stage-text {
  color: #01062C;
  font-weight: 600;
}
.stage-arrow {
  font-size: 14px;
  color: #CBD5E1;
  flex-shrink: 0;
}

/* Tabs: General | History (orange underline when active) */
.tabs-container {
  margin-bottom: 0;
  padding-left: 0.75rem;
  padding-right: 0.75rem;
}
.tab-item {
  background: none;
  border: none;
  padding: 12px 0;
  margin-right: 24px;
  font-size: 14px;
  font-weight: 500;
  color: #64748B;
  position: relative;
  cursor: pointer;
}
.tab-item.active {
  color: #01062C;
  font-weight: 600;
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

/* Left column: Deal Information card with edit icon */
.info-card-header {
  padding: 0;
}
.info-card-title {
  font-size: 16px;
  font-weight: 600;
  color: #01062C;
}
.btn-edit-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #64748B;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, color 0.2s;
}
.btn-edit-icon:hover {
  background: #F1F5F9;
  color: #01062C;
}

.activity-card {
  border: 1px solid #F4F4F4;
}
.toggle-buttons-container {
  width: fit-content;
}
.w-fit-content { width: fit-content; }
.btn-toggle {
  height: 32px;
  min-height: 32px;
  padding: 0 14px;
  border-radius: 100px;
  border: none;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.btn-toggle-activity {
  background: #F1F5F9;
  color: #64748B;
}
.btn-toggle-activity.active {
  background: #0F172A;
  color: #fff;
}
.btn-toggle-comments {
  background: #F1F5F9;
  color: #64748B;
}
.btn-toggle-comments.active {
  background: #0F172A;
  color: #fff;
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

/* Child sections: section titles 16px, labels 12px #64748B, values 14px #01062C */
:deep(.info-card .section-title),
:deep(.info-card h6.section-title) {
  font-size: 16px !important;
  font-weight: 600;
  color: #01062C;
  margin-bottom: 12px;
}
:deep(.info-card .info-label) {
  font-size: 12px !important;
  color: #64748B;
  margin-bottom: 4px;
}
:deep(.info-card .info-value) {
  font-size: 14px !important;
  color: #01062C;
}
</style>
