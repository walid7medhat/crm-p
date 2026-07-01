<template>
  <b-modal
    id="view-deal-modal"
    v-model="show"
    hide-header
    hide-footer
    size="xl"
    centered
    body-class="p-0 view-lead-modal"
    dialog-class="kanban-mobile-fullscreen-modal"
    :z-index="1040"
    :no-enforce-focus="true"
    :trap-focus="false"
  >
    <div v-if="show" class="view-lead-modal-content p-3 pb-0">
      <!-- Header — same structure as ViewLeadModal -->
      <div class="modal-header-custom d-flex justify-content-between align-items-center px-1">
        <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1 deal-title-header-group">
          <template v-if="!isEditingTitle">
            <div
              class="deal-title-read-row d-flex align-items-center gap-2 min-w-0"
              role="group"
              aria-label="Deal name"
            >
              <span
                class="modal-title view-deal-title-truncate min-w-0"
                @click="startEditTitle"
              >
                {{ deal.value?.deal_name || dealTitle }}
              </span>
              <button
                type="button"
                class="deal-title-edit-btn"
                aria-label="Edit deal name"
                title="Edit deal name"
                @click.stop="startEditTitle"
              >
                <span class="deal-title-edit-btn-inner">
                  <iconify-icon icon="lucide:pencil" class="deal-title-edit-icon" />
                </span>
              </button>
            </div>
          </template>
          <template v-else>
            <div class="deal-title-input-shell min-w-0">
              <input
                ref="dealTitleInputRef"
                v-model="dealTitleInput"
                type="text"
                class="view-deal-title-input"
                placeholder="Deal name"
                @keyup.enter="saveTitle"
                @blur="onDealTitleBlur"
                @keydown.esc.prevent="cancelTitleEdit"
              />
            </div>
          </template>
        </div>
        <button class="close-btn" type="button" aria-label="Close" @click="close">
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
               :style="{
                            ...getDealStagePillStyle(dealType, stage, index, selectedStageIndex),
                            zIndex: currentStages.length - index,
                        }"
              :aria-current="selectedStageIndex === index ? 'step' : undefined"
              @click="selectStage(index)"
            >
              
              <span class="stage-text">{{ stage.name }}</span>
            </button>
            
          </template>
        </div>
      </div>

      <!-- Tabs — same classes as ViewLeadModal -->
      <div class="tabs-container mb- border-bottom">
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

      <!-- Main content — same padding as ViewLeadModal (p-4) -->
      <div class="modal-body-custom p-4">
        <!-- General tab: two columns like Lead -->
        <template v-if="activeTab === 'general'">
          <div class="row g-3 g-lg-4">
            <!-- Left column: Deal Information (with edit icon) or full-width edit form -->
            <div class="col-md-6">
              <div class="info-card bg-white p-3 radius-12 shadow-sm">
                  <div v-if="dealType === 'primary'" class="row g-3 view-deal-content">
                    <ViewPrimaryDeal
                      :deal="deal"
                      :show-responsible-section="false"
                      :hide-inline-edit-actions="isEditingDeal"
                      :active-edit-section="activeEditSection"
                      :inline-edit-data="editFormData"
                      :inline-edit-lookup="editLookup"
                      :inline-edit-loading="editLoading"
                      :inline-edit-saving="editSaving"
                      :inline-edit-show-errors="editShowErrors"
                      :inline-edit-field-errors="editFieldErrors"
                      :selected-stage-id="deal?.stage?.id"
                      :selected-stage-name="deal?.stage?.name || deal?.stage?.title || ''"
                      :selected-stage-order="deal?.stage?.order || 0"
                      @edit-section="startEditDealFromSection"
                      @update:inline-edit-data="onInlineEditDataUpdate"
                      @inline-edit-save="saveEditDeal"
                      @inline-edit-cancel="cancelEditDeal"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                        @refresh-deal="hydrateDealForView"
                    />
                  </div>
                  <div v-else-if="dealType === 'secondary'" class="row g-3 view-deal-content">
                    <ViewSecondaryDeal
                      :deal="deal"
                      :show-responsible-section="false"
                      :hide-inline-edit-actions="isEditingDeal"
                      :active-edit-section="activeEditSection"
                      :inline-edit-data="editFormData"
                      :inline-edit-lookup="editLookup"
                      :inline-edit-loading="editLoading"
                      :inline-edit-saving="editSaving"
                      :inline-edit-show-errors="editShowErrors"
                      :inline-edit-field-errors="editFieldErrors"
                      :selected-stage-id="deal?.stage?.id"
                      :selected-stage-name="deal?.stage?.name || deal?.stage?.title || ''"
                      :selected-stage-order="deal?.stage?.order || 0"
                      @edit-section="startEditDealFromSection"
                      @update:inline-edit-data="onInlineEditDataUpdate"
                      @inline-edit-save="saveEditDeal"
                      @inline-edit-cancel="cancelEditDeal"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                        @refresh-deal="hydrateDealForView"
                    />
                  </div>
                  <div v-else class="row g-3 view-deal-content">
                    <ViewRentalDeal
                      :deal="deal"
                      :show-responsible-section="false"
                      :hide-inline-edit-actions="isEditingDeal"
                      :active-edit-section="activeEditSection"
                      :inline-edit-data="editFormData"
                      :inline-edit-lookup="editLookup"
                      :inline-edit-loading="editLoading"
                      :inline-edit-saving="editSaving"
                      :inline-edit-show-errors="editShowErrors"
                      :inline-edit-field-errors="editFieldErrors"
                      :selected-stage-id="deal?.stage?.id"
                      :selected-stage-name="deal?.stage?.name || deal?.stage?.title || ''"
                      :selected-stage-order="deal?.stage?.order || 0"
                      @edit-section="startEditDealFromSection"
                      @update:inline-edit-data="onInlineEditDataUpdate"
                      @inline-edit-save="saveEditDeal"
                      @inline-edit-cancel="cancelEditDeal"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                        @refresh-deal="hydrateDealForView"
                    />
                  </div>
              </div>
            </div>

            <!-- Right column: Activity | Comments (hidden when editing) -->
            <div class="col-md-6">
              <ResponsiblePersonSection
                v-if="deal?.id"
                :deal="deal"
                @person-updated="handlePersonUpdated"
              />
              <DealLeadInformationSection
                v-if="deal?.id && linkedLeadId"
                :lead-id="linkedLeadId"
                :lead="deal.lead"
                @view-more="showLinkedLeadModal = true"
              />
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

                <DealActivitySection
                  v-if="activeViewTab === 'activity'"
                  :deal-id="dealEntityId"
                  @activity-created="handleActivityCreated"
                />

                <DealCommentsSection
                  v-if="activeViewTab === 'comments'"
                  :deal-id="dealEntityId"
                  @comment-created="handleCommentCreated"
                />
              </div>

              <DealActivityList
                v-if="activeViewTab === 'activity'"
                ref="activityListRef"
                :deal-id="dealEntityId"
              />
              <DealCommentList
                v-if="activeViewTab === 'comments'"
                ref="commentListRef"
                :deal-id="dealEntityId"
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

        <template v-if="isEditingDeal">
          <div class="edit-lead-bar-spacer"></div>
        </template>
      </div>

      <!-- Same fixed Save/Cancel bar as ViewLeadModal GeneralTab when editing -->
      <div v-if="isEditingDeal" class="edit-lead-bottom-bar">
        <button type="button" class="edit-bar-btn edit-bar-cancel" @click="cancelEditDeal">
          Cancel
        </button>
        <button
          type="button"
          class="edit-bar-btn edit-bar-save"
          :disabled="editSaving"
          @click="saveEditDeal"
        >
          <span v-if="editSaving">Saving...</span>
          <span v-else>Save</span>
        </button>
      </div>
    </div>
  </b-modal>

  <ViewLeadModal
    v-if="linkedLeadId"
    v-model="showLinkedLeadModal"
    :lead-id="linkedLeadId"
    :z-index="2100"
    @lead-updated="handleLinkedLeadUpdated"
  />
</template>

<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { BModal, BDropdown, BDropdownItem } from 'bootstrap-vue-3'
import ViewPrimaryDeal from './ViewPrimaryDeal.vue'
import ViewSecondaryDeal from './ViewSecondaryDeal.vue'
import ViewRentalDeal from './ViewRentalDeal.vue'
import DealCreatedCard from './DealCreatedCard.vue'
import DealHistoryPanel from './DealHistoryPanel.vue'
import DealActivitySection from './DealActivitySection.vue'
import DealCommentsSection from './DealCommentsSection.vue'
import DealActivityList from './DealActivityList.vue'
import DealCommentList from './DealCommentList.vue'
import ResponsiblePersonSection from './ResponsiblePersonSection.vue'
import DealLeadInformationSection from './DealLeadInformationSection.vue'
import ViewLeadModal from '../viewLead/ViewLeadModal.vue'
import axios from '@/plugins/axios'
import { enrichDealStage, getDealStagePillStyle } from '@/config/dealStageStyles.js'
import { useStageTransition } from '@/composables/useStageTransition'
import { normalizeLanguageSelection } from '@/composables/useLanguageMultiSelect'
const props = defineProps({
  modelValue: Boolean,
  deal: { type: Object, default: null },
  autoEditSection: { type: String, default: null } 
})

const emit = defineEmits(['update:modelValue', 'deal-updated', 'stage-change-request'])

const show = ref(props.modelValue)
const dealType = ref('primary')
const hydratedDeal = ref(null)
const deal = computed(() => hydratedDeal.value || props.deal || null)
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
const activeEditSection = ref(null)
const editLookup = ref({
  users: [],
  sources: [],
  propertyTypes: [],
  developers: [],
  areas: []
})
const editHydrationRequestId = ref(0)

const isEditingTitle = ref(false)
const dealTitleInput = ref('')
const dealTitleInputRef = ref(null)
let dealTitleBlurTimer = null


const { updateAndChangeStage } = useStageTransition()

const dealTitle = computed(() => {
  if (!deal.value) return 'View Deal'
  const name = deal.value.project_name || deal.value.project || deal.value.deal_name
  if (name) return `Deal Done From "${name}"`
  return deal.value.id ? `Deal #${deal.value.id}` : 'View Deal'
})

const dealEntityId = computed(() => deal.value?.id ?? null)

/** Present when deal was converted from a lead (API `lead_id` or nested `lead.id`). */
const linkedLeadId = computed(() => {
  const d = deal.value
  if (!d) return null
  const raw = d.lead_id ?? d.lead?.id
  if (raw === null || raw === undefined || raw === '') return null
  return raw
})

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

async function handlePersonUpdated(updatedPerson) {
  if (!deal.value) return
  // Optimistic local update so the section reflects the new person immediately.
  if (hydratedDeal.value) {
    hydratedDeal.value = {
      ...hydratedDeal.value,
      responsible_person_id: updatedPerson?.id ?? hydratedDeal.value?.responsible_person_id,
      responsible_person: {
        ...(hydratedDeal.value?.responsible_person || {}),
        ...(updatedPerson || {}),
      },
    }
  }
  // Refetch the full deal so assigned_at / histories / parties update for the rest of the modal.
  await hydrateDealForView()
  emit('deal-updated', deal.value)
}
async function handleRefreshDeal() {
  await hydrateDealForView()
}
const showLinkedLeadModal = ref(false)

function handleLinkedLeadUpdated() {
  hydrateDealForView()
}

const dealTypeTabs = [
  { id: 'primary', name: 'Primary / Off Plan' },
  { id: 'secondary', name: 'Secondary' },
  { id: 'rental', name: 'Rental' }
]

// إضافة ref لتخزين stages من الـ API
const dynamicStages = ref({
  primary: [],
  secondary: [],
  rental: []
})

// تبسيط currentStages - يعتمد فقط على dynamicStages
const currentStages = computed(() => {
  const stagesForType = dynamicStages.value[dealType.value]
  if (stagesForType && Array.isArray(stagesForType)) {
    return stagesForType
  }
  return []
})

// دالة جلب الـ stages من الـ API
async function fetchStagesFromAPI(dealTypeValue = null) {
  const type = dealTypeValue || dealType.value
  try {
    const response = await axios.get('/stages', {
      params: { 
        stage_type: 'deal',
        deal_type: type 
      }
    })
    
    const responseData = response.data
    let stagesData = []
    
    if (responseData?.data?.data) {
      stagesData = responseData.data.data
    } else if (responseData?.data && Array.isArray(responseData.data)) {
      stagesData = responseData.data
    } else if (Array.isArray(responseData)) {
      stagesData = responseData
    }
    
    // تحويل البيانات إلى الفورمات المطلوب
    const formattedStages = stagesData.map((stage) =>
      enrichDealStage(type, {
        id: stage.id,
        name: stage.name,
        order: stage.order,
        color: stage.color,
      })
    )
    
    // ترتيب حسب order
    formattedStages.sort((a, b) => (a.order || 0) - (b.order || 0))
    
    // تخزين في dynamicStages
    dynamicStages.value[type] = formattedStages
    
    return formattedStages
  } catch (error) {
    console.error('Error fetching stages:', error)
    return []
  }
}
function handleDealUpdatedFromModal(updatedDeal) {
  selectedDeal.value = {
    ...selectedDeal.value,
    ...updatedDeal
  }
}
function getDefaultColor(order) {
  const colors = ['#3B82F6', '#22C55E', '#059669', '#DC2626']
  return colors[(order || 0) % colors.length] || '#3B82F6'
}
const currentStageIndex = computed(() => {
  const d = deal.value
  if (!d) return 0
  
  const stages = currentStages.value
  // تأكد أن stages موجودة ومصفوفة
  if (!stages || !Array.isArray(stages) || stages.length === 0) return 0
  
  let stageId = d.stage?.id ?? d.stage_id ?? d.stageId
  if (stageId == null) return 0
  
  // معالجة الـ mapping
  const stageMapping = {
    'deal-lost-sec': 'deal-lost',
    'deal-won-sec': 'deal-won',
    'lease-off': 'lease-offer',
    'guarantee-letter': 'guarantee'
  }
  
  stageId = stageMapping[stageId] || stageId
  
  const idx = stages.findIndex(s => s && String(s.id) === String(stageId))
  return idx >= 0 ? idx : 0
})
function selectStage(index) {
  if (!deal.value) return
  const targetStage = currentStages.value[index]
  const currentStage = currentStages.value[selectedStageIndex.value]
  if (!targetStage) return

  const originalStageId = deal.value.stage_id ?? deal.value.stage?.id ?? deal.value.stageId ?? currentStage?.id
  const targetStageId = targetStage.id
  if (String(originalStageId) === String(targetStageId)) return

  selectedStageIndex.value = index
    if (!activeEditSection.value) {

      emit('stage-change-request', {
        dealId: deal.value.id,
        originalStageId,
        targetStageId,
        targetStageName: targetStage.name,
         targetStageOrder: targetStage.order,
        dealData: deal.value,
      })
    }
}
async function hydrateDealForView() {
  if (!show.value || !props.deal?.id) return
  try {
    const response = await axios.get(`/deals/${props.deal.id}`)
    const fullDeal = response.data?.data ?? response.data
    hydratedDeal.value = fullDeal || null
    if (fullDeal?.deal_type) {
      dealType.value = fullDeal.deal_type
    }
  } catch (error) {
    console.error('Error hydrating deal for view:', error)
    hydratedDeal.value = null
  }
}
async function loadDealForEdit() {
    if (!props.deal?.id) return
    const requestId = Date.now()
    editHydrationRequestId.value = requestId
    editLoading.value = true
    try {
        const response = await axios.get(`/deals/${props.deal.id}`)
        const dealData = response.data?.data || response.data
        hydratedDeal.value = dealData || hydratedDeal.value
        // Ignore stale async responses when user switches deals quickly.
        if (editHydrationRequestId.value !== requestId) return
        editFormData.value = dealToFormData(dealData)
        await fetchEditLookups()
    } catch (error) {
        console.error('Error loading deal for edit:', error)
    } finally {
        editLoading.value = false
    }
}
// --- Edit deal ---
function getParty(deal, type) {
  const parties = deal?.parties || []
  return parties.find((p) => p.party_type === type && (p.party_role === 'primary' || !p.party_role)) || {}
}

function mapPartyDocuments(party, category) {
  const docs = Array.isArray(party?.documents) ? party.documents : []
  return docs.map((doc, idx) => ({
    id: doc.id || doc.doc_id || `${category}-doc-${idx}-${Date.now()}`,
    name: doc.file_name || doc.filename || doc.original_name || doc.name || `document-${idx + 1}`,
    url: doc.url || doc.file_url || doc.path || doc.link || null,
    size: doc.size || doc.file_size || 0,
    type: doc.mime_type || doc.type || '',
    mime_type: doc.mime_type || doc.type || '',
    document_type: doc.document_type || doc.type_name || null,
    category,
    party_type: category,
    status: doc.status || 'existing',
    is_existing: true,
    raw: doc,
  }))
}

function mapPropertyDocsForUpload(docs, documentTypeSlug) {
  let list = docs
  if (list && typeof list === 'string') {
    try {
      list = JSON.parse(list)
    } catch {
      list = []
    }
  }
  if (!Array.isArray(list)) list = []
  const dt = documentTypeSlug === 'spa' ? 'spa' : 'payment_proof'
  return list.map((doc, idx) => ({
    id: doc.id || `property-${dt}-${idx}`,
    name: doc.original_name || doc.file_name || doc.name || `document-${idx + 1}`,
    url: doc.url || doc.file_url || null,
    size: doc.size || doc.file_size || 0,
    type: doc.mime_type || '',
    mime_type: doc.mime_type || '',
    document_type: dt,
    category: 'property',
    party_type: 'property',
    status: doc.status || 'existing',
    is_existing: true,
    raw: doc,
    path: doc.path ?? null,
  }))
}

function dealToFormData(deal) {
  if (!deal) return {}
  const buyer = getParty(deal, 'buyer')
  const seller = getParty(deal, 'seller')
  const tenant = getParty(deal, 'tenant')
  const landlord = getParty(deal, 'landlord')
  const firstProp = Array.isArray(deal.properties) && deal.properties.length > 0 ? deal.properties[0] : null
  return {
    deal_id: deal.id ?? null,
    source: deal.source ?? '',
    deal_name: deal.deal_name ?? '',
 
    deal_commission: deal.deal_commission ?? null,
    agent_share: deal.agent_share ?? null,
    company_share: deal.company_share ?? null,
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
    buyer_language: normalizeLanguageSelection(buyer.language),
    buyer_documents: mapPartyDocuments(buyer, 'buyer'),
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
    seller_documents: mapPartyDocuments(seller, 'seller'),
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
    tenant_documents: mapPartyDocuments(tenant, 'tenant'),
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
    landlord_documents: mapPartyDocuments(landlord, 'landlord'),
    responsible_person: deal.responsible_person ?? null,

    area_id: firstProp?.area_id ?? deal.area?.id ?? deal.area_id ?? null,
    unit_no: firstProp?.unit_no ?? deal.unit_no ?? '',
    property_type_id: firstProp?.property_type_id ?? null,
    bedrooms: firstProp?.bedrooms ?? deal.bedrooms ?? null,
    unit_size: firstProp?.unit_size ?? deal.unit_size ?? '',
    developer_id: firstProp?.developer_id ?? deal.developer?.id ?? null,
    developer_name: firstProp?.developer_name ?? deal.developer_name ?? '',
    developer_phone: firstProp?.developer_phone ?? deal.developer_phone ?? '',
    budget_from: firstProp?.budget_from ?? null,
    budget_to: firstProp?.budget_to ?? null,
    purchase_price: firstProp?.purchase_price ?? null,
    listing_id: deal.listing_id ?? null,
    property_id: firstProp?.id ?? null,
    payment_proof: mapPropertyDocsForUpload(firstProp?.payment_proof, 'payment_proof'),
    spa_document: mapPropertyDocsForUpload(firstProp?.spa_document, 'spa'),

    eoi_date: deal.eoi_date ?? null,
    booking_date: deal.booking_date ?? null,
    spa_date: deal.spa_date ?? null,
    security_deposit_date: deal.security_deposit_date ?? null,
    mou_date: deal.mou_date ?? null,
    noc_date: deal.noc_date ?? null,
    won_date: deal.won_date ?? null,
  }
}

function collectEditDocuments(formData) {
  if (!formData || typeof formData !== 'object') return []

  const mapKeyToParty = {
    buyer_documents: 'buyer',
    seller_documents: 'seller',
    tenant_documents: 'tenant',
    landlord_documents: 'landlord',
  }

  const docs = []
  Object.entries(mapKeyToParty).forEach(([key, party]) => {
    const list = Array.isArray(formData[key]) ? formData[key] : []
    list.forEach((doc) => {
      if (!doc?.file) return
      docs.push({
        file: doc.file,
        document_type: doc.document_type || 'other',
        category: doc.category || party,
        party_type: doc.party_type || party,
      })
    })
  })

  return docs
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

async function startEditDeal(sectionKey = null) {
  if (!deal.value?.id) return
  activeEditSection.value = sectionKey
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

function startEditDealFromSection(sectionKey) {
  startEditDeal(sectionKey)
}
function onInlineEditDataUpdate(value) {
  editFormData.value = value
}
function cancelEditDeal() {
  isEditingDeal.value = false
  activeEditSection.value = null
  editFormData.value = {}
}

async function hydrateAutoEditSection() {
  const section = props.autoEditSection
  if (!show.value || !props.deal?.id || !section) return
if (section === 'add_new_property') {
 
    return
  }
  // Always rehydrate on open/deal change to avoid stale previous form data.
  activeEditSection.value = section
  isEditingDeal.value = true
  editShowErrors.value = false
  editFieldErrors.value = {}
  editFormData.value = {}
  await loadDealForEdit()
}

async function saveEditDeal() {
  if (!deal.value?.id) return
  const stageId =
    deal.value.stage_id ??
    deal.value.stage?.id ??
    deal.value.stageId ??
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
    const documents = collectEditDocuments(editFormData.value)
    const res = await updateAndChangeStage({
      dealId: props.deal.id,
      payload: editFormData.value,
      documents,
      stageId
    })
    const updated = res?.data?.data ?? res?.data ?? {}
    const selectedResponsibleId = editFormData.value?.responsible_person_id ?? null
    const selectedResponsible = editLookup.value?.users?.find(
      (u) => String(u.id) === String(selectedResponsibleId),
    )
    if (selectedResponsibleId) {
      updated.responsible_person_id = selectedResponsibleId
      if (selectedResponsible) {
        updated.responsible_person = {
          id: selectedResponsible.id,
          name: selectedResponsible.name,
          avatar: selectedResponsible.avatar || selectedResponsible.profile_image || null,
        }
      }
    }
    // View mode reads from hydratedDeal first; without a refetch it stays stale after save
    // (e.g. buyer name in parties) while the edit form reloads fresh data on next open.
    await hydrateDealForView()
    if (hydratedDeal.value && selectedResponsibleId) {
      hydratedDeal.value.responsible_person_id = selectedResponsibleId
      if (selectedResponsible) {
        hydratedDeal.value.responsible_person = {
          id: selectedResponsible.id,
          name: selectedResponsible.name,
          avatar: selectedResponsible.avatar || selectedResponsible.profile_image || null,
        }
      }
    }
    emit('deal-updated', hydratedDeal.value ?? updated)
    isEditingDeal.value = false
    activeEditSection.value = null
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
function startEditTitle() {
  isEditingTitle.value = true
  dealTitleInput.value = deal.value?.deal_name || ''
  nextTick(() => {
    const el = dealTitleInputRef.value
    if (el) {
      el.focus()
      el.select()
    }
  })
}

function cancelTitleEdit() {
  if (dealTitleBlurTimer) {
    clearTimeout(dealTitleBlurTimer)
    dealTitleBlurTimer = null
  }
  dealTitleInput.value = deal.value?.deal_name || ''
  isEditingTitle.value = false
}

function onDealTitleBlur() {
  if (dealTitleBlurTimer) clearTimeout(dealTitleBlurTimer)
  dealTitleBlurTimer = setTimeout(() => {
    dealTitleBlurTimer = null
    if (show.value && isEditingTitle.value) saveTitle()
  }, 120)
}

async function saveTitle() {
  if (dealTitleBlurTimer) {
    clearTimeout(dealTitleBlurTimer)
    dealTitleBlurTimer = null
  }
  if (!deal.value?.id || !show.value) return

  const newName = dealTitleInput.value

  try {
    await axios.put(`/deals/${deal.value.id}`, {
      deal_name: newName
    })

    // 🔥 أهم خطوة
    if (hydratedDeal.value) {
      hydratedDeal.value.deal_name = newName
    }

    emit('deal-updated', {
      ...deal.value,
      deal_name: newName
    })

  } catch (e) {
    console.error('Error updating deal name', e)
  } finally {
    isEditingTitle.value = false
  }
}
watch(() => props.modelValue, async (val) => {
  show.value = val
  if (val && props.deal?.deal_type) {
    dealType.value = props.deal.deal_type
    await hydrateDealForView()
    await fetchStagesFromAPI(props.deal.deal_type)
    selectedStageIndex.value = currentStageIndex.value
  }
})
watch(() => show.value, async (isOpen) => {
  if (isOpen && props.deal?.id) {
    await fetchEditLookups()  // ✅ حمل البيانات أولاً
    await hydrateDealForView()
    await fetchStagesFromAPI(props.deal.deal_type)
    selectedStageIndex.value = currentStageIndex.value
  }
})

watch(() => props.deal?.id, async (newId, oldId) => {
  if (!show.value || !newId || newId === oldId) return
  isEditingTitle.value = false
  isEditingDeal.value = false
  activeEditSection.value = null
  editFormData.value = {}
  hydratedDeal.value = null
  await hydrateDealForView()
  selectedStageIndex.value = currentStageIndex.value
})

watch(
  [show, () => props.deal?.id, () => props.autoEditSection],
  async ([isOpen, dealId, section], [prevOpen, prevDealId, prevSection]) => {
    if (!isOpen || !dealId || !section) return
    const dealChanged = dealId !== prevDealId
    const justOpened = isOpen && !prevOpen
    const sectionChanged = section !== prevSection
    if (dealChanged || justOpened || sectionChanged) {
      await hydrateAutoEditSection()
    }
  },
  { immediate: true },
)

watch(() => props.deal?.stageId, () => {
  if (show.value && props.deal) selectedStageIndex.value = currentStageIndex.value
})
watch(dealType, async (newType) => {
  if (show.value) {
    await fetchStagesFromAPI(newType)
    selectedStageIndex.value = currentStageIndex.value
  }
})
watch(() => deal.value, (val) => {
  if (val) {
    dealTitleInput.value = val.deal_name || ''
  }
}, { immediate: true })
watch(show, (val) => {
  if (val && props.deal) selectedStageIndex.value = currentStageIndex.value
  if (!val) {
    if (dealTitleBlurTimer) {
      clearTimeout(dealTitleBlurTimer)
      dealTitleBlurTimer = null
    }
    showLinkedLeadModal.value = false
    hydratedDeal.value = null
    editHydrationRequestId.value = 0
    isEditingTitle.value = false
    isEditingDeal.value = false
    activeEditSection.value = null
    editFormData.value = {}
    editFieldErrors.value = {}
    editShowErrors.value = false
  }
  emit('update:modelValue', val)
})

function close() {
  show.value = false
}
</script>

<style>
/* Mirrors ViewLeadModal non-scoped modal shell — fluid width for all viewports */
.modal#view-deal-modal .modal-dialog {
  max-width: min(1200px, calc(100vw - 24px)) !important;
  width: min(1200px, calc(100vw - 24px)) !important;
  max-height: min(98vh, 100dvh) !important;
  margin: 1vh auto !important;
}

#view-deal-modal .modal-content {
  overflow: visible !important;
  border: 1px solid #e5e7eb !important;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08) !important;
  max-height: min(98vh, 100dvh) !important;
}

.view-lead-modal {
  padding: 0 !important;
  height: min(98vh, 100dvh);
  max-height: 100vh;
  display: flex;
  flex-direction: column;
}

@media (max-width: 768px) {
  .modal#view-deal-modal .modal-dialog {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    min-height: 100dvh;
  }

  .view-lead-modal {
    height: 100dvh !important;
    max-height: 100dvh !important;
    min-height: 100dvh;
  }
}
</style>

<style scoped>
/* Mirror ViewLeadModal shell — shared class names: view-lead-modal, view-lead-modal-content */
.view-lead-modal {
  z-index: 1000 !important;
}

.view-lead-modal-content {
  background: #fff;
  border-radius: 16px;
  overflow: visible;
  font-family: 'Montserrat', sans-serif;
  position: relative;
  display: flex;
  flex-direction: column;
  height: 100%;
  max-width: 100%;
  --deal-font: 'Montserrat', sans-serif;
}

.modal-header-custom,
.deal-progress-wrapper,
.tabs-container {
  flex-shrink: 0;
}

.modal-header-custom {
  background: #fff;
  position: relative;
}

.modal-title {
  font-size: clamp(14px, 2.8vw, 16px);
  font-weight: 600;
  color: #0B0736;
}

.view-deal-title-truncate {
  display: inline-block;
  min-width: 0;
  max-width: min(560px, calc(100vw - 280px));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.deal-title-header-group {
  max-width: calc(100% - 48px);
}

.deal-title-read-row {
  padding: 2px 0;
  width: fit-content;
  max-width: min(620px, calc(100vw - 220px));
}

.deal-title-edit-btn {
  flex-shrink: 0;
  border: none;
  padding: 0;
  background: transparent;
  cursor: pointer;
  border-radius: 11px;
  line-height: 0;
}

.deal-title-edit-btn-inner {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  border: 1px solid #c7d2fe;
  background: linear-gradient(155deg, #eef2ff 0%, #e0e7ff 48%, #c7d2fe 100%);
  color: #312e81;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.06),
    inset 0 1px 0 rgba(255, 255, 255, 0.85);
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease,
    color 0.18s ease;
}

.deal-title-edit-btn:hover .deal-title-edit-btn-inner {
  background: linear-gradient(155deg, #eef2ff 0%, #e0e7ff 45%, #c7d2fe 100%);
  color: #3730a3;
  box-shadow:
    0 6px 16px rgba(99, 102, 241, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.9);
  transform: translateY(-1px);
}

.deal-title-edit-btn:active .deal-title-edit-btn-inner {
  transform: translateY(0);
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
}

.deal-title-edit-icon {
  font-size: 18px;
}

.deal-title-input-shell {
  padding: 2px 0;
  flex: 0 0 auto;
  width: min(440px, calc(100vw - 210px));
  max-width: 100%;
}

.view-deal-title-input {
  display: block;
  width: 100%;
  min-width: 0;
  margin: 0;
  box-sizing: border-box;
  font-size: clamp(14px, 2.8vw, 16px);
  font-weight: 600;
  font-family: var(--deal-font, 'Montserrat', sans-serif);
  color: #0B0736;
  line-height: 1.35;
  padding: 6px 14px;
  border-radius: 11px;
  border: 1px solid #e2e8f0;
  background: #fff;
  outline: none;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.view-deal-title-input::placeholder {
  color: #94a3b8;
  font-weight: 500;
}

.view-deal-title-input:hover {
  border-color: #cbd5e1;
}

.view-deal-title-input:focus {
  border-color: #6366f1;
  background: #fafbff;
  box-shadow:
    0 0 0 1px rgba(99, 102, 241, 0.35),
    0 0 0 4px rgba(99, 102, 241, 0.12);
}

.settings-btn,
.close-btn,
.notification-btn {
  background: none;
  border: none;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
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
  z-index: 2000 !important;
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
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x;
  scrollbar-width: none;
  padding: 0.75rem 0.75rem 0;
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
  padding: 4px 4px 8px;
  box-shadow: 1px 1px 5px 5px #00000005;
}
.deal-stage-pill {
    display: flex;
    align-items: center;
    min-width: 140px;
    max-width: 170px;
    padding: 2px 10px;
    /*border-radius: 30px;*/
    cursor: pointer;
    transition: background-color 0.1s ease, border-color 0.1s ease, color 0.1s ease;
    position: relative;
    overflow: hidden;
    /*border: 1px solid transparent;*/
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.55);
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    clip-path: polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%);
    height: 25px;
}

.deal-stage-pill:not(.active) {
    color: #94A3B8;
}

.stage-text {
    font-family: Montserrat;
    font-weight: 400;
    font-size: 13px;
    color: #0B0736;
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.deal-stage-pill.active .stage-text {
    color: #0B0736;
    font-weight: 400;
}

@media (max-width: 991.98px) {
  .deal-stage-pill {
    min-width: 96px;
    max-width: min(138px, 28vw);
    padding: 1px 6px;
  }

  .stage-text {
    font-size: 12px;
  }
}

@media (max-width: 768px) {
  .deal-stage-pill {
    min-width: 104px;
    max-width: 138px;
    padding: 1px 8px;
  }

  .stage-text {
    font-size: 11px;
    font-weight: 500;
  }
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
  padding:  10px;
  font-size: 13px;
  font-weight: 500;
  color: var(--deal-text-muted, #64748b);
  position: relative;
  cursor: pointer;
  font-family: var(--deal-font, 'Inter', sans-serif);
}
.tab-item.active {
  color: #0B0736;
}
.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  width: 100%;
  height: 2px;
  background: #733E87;
}

.radius-12 { border-radius: 12px; }

/* Left column: Deal Information card with edit icon */
.info-card-header {
  padding: 0;
}
.info-card-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--deal-navy-deep, #0B0736);
  letter-spacing: -0.01em;
}
.btn-edit-icon {
  width: 15px;
  height: 15px;
  border: none;
  background: transparent;
  color: fcb600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, color 0.2s;
}
.btn-edit-icon:hover {
  background: #F1F5F9;
  color: #0B0736;
}

.activity-card {
  border: 1px solid #eef2f7;
  border-radius: 8px;
  box-shadow: none !important;
}

.responsible-person-card {
  border: 1px solid #eef2f7;
  box-shadow: none !important;
}

.responsible-avatar-wrap {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.responsible-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}

.responsible-avatar-fallback {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.responsible-name {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.responsible-meta {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
}

.responsible-change-btn {
  height: 30px;
  border: 1px solid #fee2a8;
  background: #fffaf0;
  color: #b45309;
  border-radius: 999px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
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
  background: #0B0736;
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
  color: #733E87;
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
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
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

/* Child sections: section titles 16px, labels 12px #64748B, values 14px #0B0736 */
:deep(.info-card .section-title),
:deep(.info-card h6.section-title) {
  font-size: 13px !important;
  font-weight: 500;
  color: var(--deal-navy-deep, #0B0736);
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
/* Close pill — matches ViewLeadModal / compiled selector values */
.close-btn {
  position: absolute;
  top: 2px;
  right: -61px;
  width: 83px;
  height: 49px;
  border: 1px solid rgba(115, 62, 135, 0.75);
  border-radius: 999px;
  background: var(--gradient-crm, linear-gradient(135deg, #0b0736 0%, #733e87 100%));
  color: #ffffff;
  font-size: 18px;
  line-height: 1;
  padding: 0 14px 0 18px;
  box-shadow: 0 8px 16px rgba(15, 23, 42, 0.2);
  z-index: -1;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  transition: filter 0.2s ease;
}

.close-btn iconify-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.close-btn:hover {
  filter: brightness(0.96);
}

/* Match GeneralTab.vue — spacer + fixed Save/Cancel while editing deal */
.edit-lead-bar-spacer {
  height: 56px;
  width: 100%;
  flex-shrink: 0;
}

.edit-lead-bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 1rem;
  padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
  background: #fff;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
  z-index: 1050;
}

.edit-bar-btn {
  padding: 8px 20px;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.edit-bar-cancel {
  background: #f4f4f4;
  color: #0B0736;
}

.edit-bar-cancel:hover {
  background: #e2e8f0;
}

.edit-bar-save {
  background: #0B0736;
  color: #fff;
}

.edit-bar-save:hover:not(:disabled) {
  background: #060a2b;
}

.edit-bar-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ≤992px: floating pill clips off-screen — keep close in header row (all tablets / small laptops) */
@media (max-width: 991.98px) {
  .close-btn {
    position: relative;
    top: auto;
    right: auto;
    width: auto;
    min-width: 44px;
    height: 44px;
    padding: 0 16px;
    justify-content: flex-end;
    margin-left: auto;
    flex-shrink: 0;
    z-index: 2;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.18);
  }

  .modal-header-custom {
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
  }

  .view-lead-modal-content {
    padding-left: clamp(10px, 3vw, 1rem) !important;
    padding-right: clamp(10px, 3vw, 1rem) !important;
  }

  :deep(#view-deal-modal .modal-content) {
    height: auto !important;
    max-height: min(92vh, 100dvh) !important;
  }

  :deep(#view-deal-modal .modal-body.view-lead-modal) {
    height: auto !important;
    max-height: min(98vh, 100dvh) !important;
    min-height: min(98vh, 100dvh);
  }

  .tabs-container .d-flex {
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 1rem !important;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }

  .tabs-container .d-flex::-webkit-scrollbar {
    display: none;
  }
}

/* Allow close pill (right: -61px) to paint outside — same idea as ViewLeadModal */
:deep(.kanban-mobile-fullscreen-modal .modal-content),
:deep(.kanban-mobile-fullscreen-modal .modal-body),
.modal-body-custom {
  overflow-x: hidden !important;
}

:deep(.kanban-mobile-fullscreen-modal .modal-content) {
  overflow: visible !important;
}

:deep(#view-deal-modal .modal-content) {
  height: 92vh;
  max-height: 92vh;
  border-radius: 16px;
  position: relative;
  overflow: visible !important;
}
.modal-body-custom::-webkit-scrollbar {
    width: 6px;
}

.modal-body-custom::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}

.modal-body-custom::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

.modal-body-custom::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

/* Same as ViewLeadModal :deep(.view-lead-modal) — scroll lives in .modal-body-custom */
:deep(#view-deal-modal .modal-body.view-lead-modal) {
  padding: 0 !important;
  height: min(98vh, 100dvh);
  max-height: min(98vh, 100dvh);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

@media (max-width: 768px) {
  :deep(#view-deal-modal .modal-body.view-lead-modal),
  :deep(#view-deal-modal .modal-content) {
    height: 100dvh;
    max-height: 100dvh;
    border-radius: 0;
  }

  .view-lead-modal-content {
    height: 100dvh;
    border-radius: 0 !important;
    padding: 10px !important;
    display: flex;
    flex-direction: column;
    background: #f8fbff;
  }

  .modal-body-custom {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 8px 4px calc(16px + env(safe-area-inset-bottom, 0px)) !important;
  }

  .modal-header-custom,
  .deal-progress-wrapper,
  .tabs-container {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #eef2f7;
    padding-left: 12px !important;
    padding-right: 12px !important;
  }

  .tabs-container {
    margin-top: 8px;
  }

  /* Same as ViewLeadModal: inline close on small screens */
  .close-btn {
    position: static;
    transform: none;
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    left: auto;
    top: auto;
    right: auto;
    margin-left: auto;
    padding: 0;
    justify-content: center;
    box-shadow: none;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #64748b;
    flex-shrink: 0;
  }

  .close-btn iconify-icon {
    width: 12px;
    height: 12px;
  }

  .modal-header-custom {
    align-items: flex-start;
  }
}

</style>

