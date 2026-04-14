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
    <div v-if="show" class="view-deal-modal-content view-deal-modal-padding deal-figma-ui">
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
              <iconify-icon icon="lucide:pencil" class="deal-type-pencil-icon" aria-hidden="true" />
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

      <!-- Pipeline steps (completed / active / upcoming) -->
      <div class="deal-progress-wrapper py-2">
        <div class="deal-progress-label">Pipeline</div>
        <div class="deal-progress-bar">
          <template v-for="(stage, index) in currentStages" :key="stage.id">
            <button
              type="button"
              class="deal-stage-pill"
              :class="{
                active: selectedStageIndex === index,
                completed: selectedStageIndex > index,
                upcoming: selectedStageIndex < index
              }"
              :aria-current="selectedStageIndex === index ? 'step' : undefined"
              @click="selectStage(index)"
            >
              <div class="stage-circle" :class="{ 'is-done': selectedStageIndex > index }">
                <iconify-icon
                  v-if="selectedStageIndex > index"
                  icon="lucide:check"
                  class="stage-check-icon"
                />
                <div
                  v-else
                  class="stage-dot"
                  :style="{ backgroundColor: stage.dotColor }"
                />
              </div>
              <span class="stage-text">{{ stage.name }}</span>
            </button>
            <iconify-icon
              v-if="index < currentStages.length - 1"
              icon="lucide:chevron-right"
              class="stage-arrow"
            />
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
            <!-- Left column: Deal Information (with edit icon) or full-width edit form -->
            <div :class="isEditingDeal ? 'col-12' : 'col-md-5'">
              <div class="info-card bg-white p-3 radius-12 shadow-sm">
                <div class="info-card-header d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
                  <span class="info-card-title">{{ isEditingDeal ? 'Edit Deal' : 'Deal Information' }}</span>
                  <button
                    v-if="!isEditingDeal"
                    type="button"
                    class="btn-edit-icon"
                    aria-label="Edit"
                    @click="startEditDeal"
                  >
                    <iconify-icon icon="lucide:pencil"></iconify-icon>
                  </button>
                </div>
                <template v-if="isEditingDeal">
                  <div v-if="editLoading" class="text-center py-4 text-muted">
                    Loading form...
                  </div>
                  <div v-else class="edit-deal-form-wrap">
                    <DealForm
                      v-model="editFormData"
                      :deal-type="dealType"
                      :users="editLookup.users"
                      :sources="editLookup.sources"
                      :property-types="editLookup.propertyTypes"
                      :developers="editLookup.developers"
                      :areas="editLookup.areas"
                      :selected-stage-id="deal?.stage_id ?? deal?.stage?.id"
                      :show-errors="editShowErrors"
                      :field-errors="editFieldErrors"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                    />
                    <div class="edit-deal-actions deal-edit-footer-sticky mt-3 pt-3 border-top">
                      <button type="button" class="btn-history-cancel me-2" @click="cancelEditDeal">
                        Cancel
                      </button>
                      <button type="button" class="btn-save-deal-view" :disabled="editSaving" @click="saveEditDeal">
                        <span v-if="editSaving">Saving…</span>
                        <span v-else>Save</span>
                      </button>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <div v-if="dealType === 'primary'" class="row g-3 view-deal-content">
                    <ViewPrimaryDeal :deal="deal" />
                  </div>
                  <div v-else-if="dealType === 'secondary'" class="row g-3 view-deal-content">
                    <ViewSecondaryDeal :deal="deal" />
                  </div>
                  <div v-else class="row g-3 view-deal-content">
                    <ViewRentalDeal :deal="deal" />
                  </div>
                </template>
              </div>
            </div>

            <!-- Right column: Activity | Comments (hidden when editing) -->
            <div v-if="!isEditingDeal" class="col-md-7">
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

        <!-- History tab (Figma: search chips + sidebar quick filters + advanced form + table + pagination) -->
        <div v-if="activeTab === 'history'" class="deal-history-tab-pane">
          <DealHistoryPanel
            :deal-id="deal?.id"
            :is-active="show && activeTab === 'history'"
          />
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
import DealForm from './DealForm.vue'
import DealCreatedCard from './DealCreatedCard.vue'
import DealHistoryPanel from './DealHistoryPanel.vue'
import ActivitySection from '../viewLead/ActivitySection.vue'
import CommentsSection from '../viewLead/CommentsSection.vue'
import ActivityList from '../viewLead/ActivityList.vue'
import CommentList from '../viewLead/CommentList.vue'
import LeadActivityTimeline from '../viewLead/LeadActivityTimeline.vue'
import axios from '@/plugins/axios'
import { useStageTransition } from '@/composables/useStageTransition'

const props = defineProps({
  modelValue: Boolean,
  deal: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'deal-updated'])

const show = ref(props.modelValue)
const dealType = ref('primary')
const selectedStageIndex = ref(0)
const activeTab = ref('general')
const activeViewTab = ref('activity')
// Edit deal state
const isEditingDeal = ref(false)
const editFormData = ref({})
const editLoading = ref(false)
const editSaving = ref(false)
const editShowErrors = ref(false)
const editFieldErrors = ref({})
const editLookup = ref({
  users: [],
  sources: [],
  propertyTypes: [],
  developers: [],
  areas: []
})
const { updateAndChangeStage } = useStageTransition()

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

// --- Edit deal ---
function getParty(deal, type) {
  const parties = deal?.parties || []
  return parties.find((p) => p.party_type === type && (p.party_role === 'primary' || !p.party_role)) || {}
}

function dealToFormData(deal) {
  if (!deal) return {}
  const buyer = getParty(deal, 'buyer')
  const seller = getParty(deal, 'seller')
  const tenant = getParty(deal, 'tenant')
  const landlord = getParty(deal, 'landlord')
  return {
    source: deal.source ?? '',
    deal_name: deal.deal_name ?? '',
    unit_no: deal.unit_no ?? '',
    property_type_id: deal.property_type_id ?? deal.property_type?.id ?? null,
    subcommunity_id: deal.subcommunity_id ?? deal.subcommunity?.id ?? null,
    bedrooms: deal.bedrooms ?? null,
    unit_size: deal.unit_size ?? '',
    project_id: deal.project_id ?? deal.project?.id ?? null,
    developer_id: deal.developer_id ?? deal.developer?.id ?? null,
    area_id: deal.area_id ?? deal.area?.id ?? null,
    property_link: deal.property_link ?? '',
    property_reference: deal.property_reference ?? '',
    deal_total_amount: deal.deal_total_amount ?? null,
    deal_commission: deal.deal_commission ?? null,
    agent_share: deal.agent_share ?? null,
    company_share: deal.company_share ?? null,
    currency: deal.currency ?? 'AED',
    responsible_person_id: deal.responsible_person_id ?? deal.responsible_person?.id ?? null,
    lost_reason: deal.lost_reason ?? '',
    buyer_first_name: buyer.first_name ?? '',
    buyer_last_name: buyer.last_name ?? '',
    buyer_dob: buyer.date_of_birth ?? buyer.dob ?? '',
    buyer_phone: buyer.phone ?? '',
    buyer_email: buyer.email ?? '',
    buyer_nationality: buyer.nationality ?? '',
    buyer_residency_status: buyer.residency_status ?? '',
    buyer_city: buyer.city ?? '',
    buyer_country: buyer.country ?? '',
    buyer_language: buyer.language ?? '',
    buyer_amount: buyer.amount ?? null,
    buyer_documents: [],
    seller_first_name: seller.first_name ?? '',
    seller_last_name: seller.last_name ?? '',
    seller_dob: seller.date_of_birth ?? seller.dob ?? '',
    seller_phone: seller.phone ?? '',
    seller_email: seller.email ?? '',
    seller_nationality: seller.nationality ?? '',
    seller_residency_status: seller.residency_status ?? '',
    seller_city: seller.city ?? '',
    seller_country: seller.country ?? '',
    seller_language: seller.language ?? '',
    seller_documents: [],
    tenant_first_name: tenant.first_name ?? '',
    tenant_last_name: tenant.last_name ?? '',
    tenant_dob: tenant.date_of_birth ?? tenant.dob ?? '',
    tenant_phone: tenant.phone ?? '',
    tenant_email: tenant.email ?? '',
    tenant_nationality: tenant.nationality ?? '',
    tenant_residency_status: tenant.residency_status ?? '',
    tenant_city: tenant.city ?? '',
    tenant_country: tenant.country ?? '',
    tenant_language: tenant.language ?? '',
    tenant_amount: tenant.amount ?? null,
    tenant_documents: [],
    landlord_first_name: landlord.first_name ?? '',
    landlord_last_name: landlord.last_name ?? '',
    landlord_dob: landlord.date_of_birth ?? landlord.dob ?? '',
    landlord_phone: landlord.phone ?? '',
    landlord_email: landlord.email ?? '',
    landlord_nationality: landlord.nationality ?? '',
    landlord_residency_status: landlord.residency_status ?? '',
    landlord_city: landlord.city ?? '',
    landlord_country: landlord.country ?? '',
    landlord_language: landlord.language ?? '',
    landlord_documents: []
  }
}

async function fetchEditLookups() {
  const base = axios
  const [usersRes, sourcesRes, propertyTypesRes, developersRes, areasRes] = await Promise.all([
    base.get('/available-responsible-persons').catch(() => ({ data: {} })),
    base.get('/sources').catch(() => ({ data: {} })),
    base.get('/listings/property-types').catch(() => ({ data: {} })),
    base.get('/listings/developers').catch(() => ({ data: {} })),
    base.get('/listings/areas').catch(() => ({ data: {} }))
  ])
  editLookup.value = {
    users: usersRes.data?.data ?? usersRes.data ?? [],
    sources: sourcesRes.data?.data ?? sourcesRes.data ?? [],
    propertyTypes: Array.isArray(propertyTypesRes.data?.data) ? propertyTypesRes.data.data : (propertyTypesRes.data?.data ? [propertyTypesRes.data.data] : propertyTypesRes.data ?? []),
    developers: Array.isArray(developersRes.data?.data) ? developersRes.data.data : (developersRes.data?.data ? [developersRes.data.data] : developersRes.data ?? []),
    areas: areasRes.data?.data?.data ?? areasRes.data?.data ?? areasRes.data ?? []
  }
}

async function editSearchAreas(search, parentId) {
  const params = parentId ? { parent_id: parentId } : {}
  if (search) params.search = search
  const { data } = await axios.get('/listings/areas', { params })
  editLookup.value.areas = data?.data?.data ?? data?.data ?? data ?? []
  return editLookup.value.areas
}

async function editSearchCommunities() {
  return editSearchAreas('')
}

async function editSearchSubCommunities(search) {
  const { data } = await axios.get('/listings/areas', { params: { search: search || '', type: 'sub_community' } })
  return data?.data?.data ?? data?.data ?? data ?? []
}

async function editSearchProjects(search) {
  const { data } = await axios.get('/listings/projects', { params: search ? { search } : {} })
  return data?.data?.data ?? data?.data ?? data ?? []
}

async function startEditDeal() {
  if (!props.deal?.id) return
  isEditingDeal.value = true
  editLoading.value = true
  editShowErrors.value = false
  editFieldErrors.value = {}
  try {
    const [dealRes] = await Promise.all([
      axios.get(`/deals/${props.deal.id}`),
      fetchEditLookups()
    ])
    const raw = dealRes.data?.data ?? dealRes.data
    editFormData.value = dealToFormData(raw)
  } catch (e) {
    console.error('Failed to load deal for edit', e)
    isEditingDeal.value = false
  } finally {
    editLoading.value = false
  }
}

function cancelEditDeal() {
  isEditingDeal.value = false
  editFormData.value = {}
}

async function saveEditDeal() {
  if (!props.deal?.id) return
  const stageId =
    props.deal.stage_id ??
    props.deal.stage?.id ??
    props.deal.stageId ??
    editFormData.value?.stage_id ??
    editFormData.value?.stageId ??
    currentStages.value[selectedStageIndex.value]?.id ??
    currentStages.value[0]?.id
  if (!stageId) {
    console.error('No stage_id for deal')
    return
  }
  editSaving.value = true
  editShowErrors.value = false
  try {
    const res = await updateAndChangeStage({
      dealId: props.deal.id,
      payload: editFormData.value,
      documents: [],
      stageId
    })
    const updated = res?.data?.data ?? res?.data
    emit('deal-updated', updated)
    isEditingDeal.value = false
  } catch (err) {
    if (err?.response?.status === 422) {
      editShowErrors.value = true
      editFieldErrors.value = err.response?.data?.errors ?? {}
    }
    console.error('Failed to save deal', err)
  } finally {
    editSaving.value = false
  }
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
  if (!val) isEditingDeal.value = false
  emit('update:modelValue', val)
})

function close() {
  show.value = false
}
</script>

<style>
/* Global: modal large, 12px radius, like image */
#view-deal-modal .modal-dialog {
  max-width: min(1200px, 95vw) !important;
  width: min(1200px, 95vw) !important;
  max-height: 92vh !important;
  margin: 2vh auto !important;
}
#view-deal-modal .modal-content {
  max-height: 92vh !important;
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
  max-width: min(1200px, 95vw) !important;
  width: min(1200px, 95vw) !important;
  max-height: 92vh !important;
}
:deep(.view-deal-modal-outer .modal-content.view-deal-modal-content-wrap),
:deep(#view-deal-modal .modal-content) {
  max-height: 92vh !important;
  border-radius: 12px !important;
  overflow: hidden !important;
}
:deep(.view-deal-modal-outer .modal-body) {
  overflow: hidden !important;
}

.view-deal-modal-content {
  background: #fff;
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
  padding: 0.65rem 1rem 0.9rem 1rem;
}

/* Header: title 18px + deal type tag pill + close */
.view-deal-header {
  padding: 0.45rem 0.35rem 0.55rem;
  border-bottom: none;
  position: relative;
  z-index: 3;
  background: #fff;
}
.view-deal-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--deal-navy-deep, #01062c);
  letter-spacing: -0.02em;
  line-height: 1.2;
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
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #f1f5f9;
  border: none;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  color: #64748b;
  transition: background 0.2s, color 0.2s;
}
.close-btn:hover {
  background: #E2E8F0;
  color: #1E293B;
}

:deep(.deal-type-dropdown-toggle) {
  height: 28px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.deal-type-dropdown-label {
  font-size: 11px;
  font-weight: 500;
  color: #475569;
  line-height: 1;
}

.deal-type-dropdown-chevron {
  font-size: 12px;
  color: #94a3b8;
}

:deep(.deal-type-dropdown-menu) {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 4px;
}

:deep(.deal-type-dropdown-item) {
  font-size: 12px;
  color: #334155;
  border-radius: 8px;
}

.deal-progress-label {
  display: none;
}

/* Stage progress (match Create Deal modal) */
.deal-progress-wrapper {
  overflow-x: auto;
  scrollbar-width: none;
  padding: 0 0.35rem 0.55rem;
  border-bottom: 1px solid #f1f5f9;
  position: relative;
  z-index: 2;
  background: #fff;
  display: block !important;
  margin-top: 2px;
  min-height: 42px;
}
.deal-progress-wrapper::-webkit-scrollbar {
  display: none;
}
.deal-progress-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: nowrap;
  min-height: 30px;
}
.deal-stage-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  min-height: 30px;
  padding: 0 10px;
  border-radius: 100px;
  border: 1px solid #e2e8f0;
  background: #fff;
  transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
  white-space: nowrap;
  box-sizing: border-box;
  cursor: pointer;
  flex-shrink: 0;
}
.deal-stage-pill:hover {
  border-color: #cbd5e1;
}
.deal-stage-pill.completed {
  border-color: #8ee3f8;
  background: linear-gradient(90deg, #8ee3f8 0%, #55d6f4 100%);
}
.deal-stage-pill.active {
  border-color: #299de9;
  background: linear-gradient(90deg, #2ea7ef 0%, #2d92dc 100%);
  box-shadow: 0 2px 8px rgba(41, 157, 233, 0.3);
}
.deal-stage-pill.active .stage-text {
  color: #fff !important;
  font-weight: 600;
}
.deal-stage-pill.completed .stage-text {
  color: #fff !important;
}
.deal-stage-pill.upcoming {
  opacity: 0.72;
}
.deal-stage-pill .stage-circle {
  width: 18px;
  height: 18px;
  min-width: 18px;
  border-radius: 50%;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.deal-stage-pill .stage-circle.is-done {
  background: #22c55e;
  border-color: #16a34a;
}
.deal-stage-pill.active .stage-circle {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.35);
}
.stage-check-icon {
  font-size: 12px;
  color: #fff;
}
.deal-stage-pill .stage-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}
.deal-stage-pill .stage-text {
  font-size: 12px;
  color: var(--deal-text-muted, #64748b);
  font-weight: 500;
  font-family: var(--deal-font, 'Inter', sans-serif);
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1;
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
  position: relative;
  z-index: 2;
  background: #fff;
  margin-top: 2px;
}
.tab-item {
  background: none;
  border: none;
  padding: 12px 0;
  margin-right: 24px;
  font-size: 13px;
  font-weight: 500;
  color: var(--deal-text-muted, #64748b);
  position: relative;
  cursor: pointer;
  font-family: var(--deal-font, 'Inter', sans-serif);
}
.tab-item.active {
  color: #01062c;
  font-weight: 600;
}
.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  width: 100%;
  height: 2px;
  background: #faa300;
}

.radius-12 { border-radius: 12px; }

/* Left column: Deal Information card with edit icon */
.info-card-header {
  padding: 0;
}
.info-card-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--deal-navy-deep, #01062c);
  letter-spacing: -0.01em;
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
  border: 1px solid #eef2f7;
  border-radius: 8px;
  box-shadow: none !important;
}
.toggle-buttons-container {
  width: fit-content;
}
.w-fit-content { width: fit-content; }
.btn-toggle {
  height: 30px;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 100px;
  border: none;
  font-size: 12px;
  font-weight: 500;
  font-family: var(--deal-font, 'Inter', sans-serif);
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

:deep(.info-card) {
  border: 1px solid #eef2f7 !important;
  box-shadow: none !important;
}

.deal-history-tab-pane {
  padding: 0 0.25rem 0.5rem;
}

.deal-type-pencil-icon {
  font-size: 14px;
  color: #faa300;
  margin-left: 2px;
  flex-shrink: 0;
}

.edit-deal-form-wrap {
  max-width: 100%;
}
.edit-deal-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.deal-edit-footer-sticky {
  position: sticky;
  bottom: 0;
  z-index: 4;
  background: linear-gradient(to top, #fff 75%, rgba(255, 255, 255, 0.92));
  box-shadow: 0 -8px 20px rgba(15, 23, 42, 0.06);
  margin-left: 0;
  margin-right: 0;
  padding-left: 0 !important;
  padding-right: 0 !important;
  padding-bottom: 10px !important;
}
.btn-history-cancel {
  height: 40px;
  width: 95px;
  padding: 0;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.btn-history-cancel:hover {
  background: #f1f5f9;
}
.btn-save-deal-view {
  height: 40px;
  width: 95px;
  padding: 0;
  border-radius: 999px;
  border: none;
  background: #0f172a;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  transition: background 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.btn-save-deal-view:hover:not(:disabled) {
  background: #020617;
}
.btn-save-deal-view:disabled {
  opacity: 0.65;
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
  font-size: 13px !important;
  font-weight: 500;
  color: var(--deal-navy-deep, #01062c);
  margin-bottom: 12px;
  font-family: var(--deal-font, 'Inter', sans-serif);
  letter-spacing: -0.02em;
}
:deep(.info-card .info-label) {
  font-size: 12px !important;
  font-weight: 500;
  color: var(--deal-text-muted, #64748b);
  margin-bottom: 4px;
  font-family: var(--deal-font, 'Inter', sans-serif);
}
:deep(.info-card .info-value) {
  font-size: 13px !important;
  font-weight: 500;
  color: var(--deal-text-strong, #0f172a);
  font-family: var(--deal-font, 'Inter', sans-serif);
}

:deep(.activity-input-section .custom-textarea) {
  min-height: 86px;
}

:deep(.activity-input-section .custom-textarea::placeholder) {
  color: #9ca3af;
  font-size: 12px;
}

:deep(.activity-input-section .modal-footer-custom) {
  border-top: none;
  padding-top: 12px;
  margin-top: 8px;
}

:deep(.activity-input-section .btn-cancel),
:deep(.activity-input-section .btn-save) {
  width: 92px;
  height: 38px;
  padding: 0;
  border-radius: 999px;
  font-size: 13px;
  justify-content: center;
}

:deep(.activity-input-section .btn-save) {
  background: #02014f;
}
</style>
