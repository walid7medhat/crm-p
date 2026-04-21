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
          <!-- <b-dropdown
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
          </b-dropdown> -->
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
               :style="{
                            backgroundColor: index <= selectedStageIndex ? stage.color : 'transparent',
                            borderColor: index <= selectedStageIndex ? stage.color : '#E2E8F0',
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
            <div class="col-md-5">
              <div class="info-card bg-white p-3 radius-12 shadow-sm">
                  <div v-if="dealType === 'primary'" class="row g-3 view-deal-content">
                    <ViewPrimaryDeal
                      :deal="deal"
                      :show-responsible-section="false"
                      :active-edit-section="activeEditSection"
                      :inline-edit-data="editFormData"
                      :inline-edit-lookup="editLookup"
                      :inline-edit-loading="editLoading"
                      :inline-edit-saving="editSaving"
                      :inline-edit-show-errors="editShowErrors"
                      :inline-edit-field-errors="editFieldErrors"
                      :selected-stage-id="deal?.stage?.id"
                      @edit-section="startEditDealFromSection"
                      @update:inline-edit-data="onInlineEditDataUpdate"
                      @inline-edit-save="saveEditDeal"
                      @inline-edit-cancel="cancelEditDeal"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                    />
                  </div>
                  <div v-else-if="dealType === 'secondary'" class="row g-3 view-deal-content">
                    <ViewSecondaryDeal
                      :deal="deal"
                      :show-responsible-section="false"
                      :active-edit-section="activeEditSection"
                      :inline-edit-data="editFormData"
                      :inline-edit-lookup="editLookup"
                      :inline-edit-loading="editLoading"
                      :inline-edit-saving="editSaving"
                      :inline-edit-show-errors="editShowErrors"
                      :inline-edit-field-errors="editFieldErrors"
                      :selected-stage-id="deal?.stage?.id"
                      @edit-section="startEditDealFromSection"
                      @update:inline-edit-data="onInlineEditDataUpdate"
                      @inline-edit-save="saveEditDeal"
                      @inline-edit-cancel="cancelEditDeal"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                    />
                  </div>
                  <div v-else class="row g-3 view-deal-content">
                    <ViewRentalDeal
                      :deal="deal"
                      :show-responsible-section="false"
                      :active-edit-section="activeEditSection"
                      :inline-edit-data="editFormData"
                      :inline-edit-lookup="editLookup"
                      :inline-edit-loading="editLoading"
                      :inline-edit-saving="editSaving"
                      :inline-edit-show-errors="editShowErrors"
                      :inline-edit-field-errors="editFieldErrors"
                      :selected-stage-id="deal?.stage?.id"
                      @edit-section="startEditDealFromSection"
                      @update:inline-edit-data="onInlineEditDataUpdate"
                      @inline-edit-save="saveEditDeal"
                      @inline-edit-cancel="cancelEditDeal"
                      @search-areas="editSearchAreas"
                      @search-subcommunities="editSearchSubCommunities"
                      @search-projects="editSearchProjects"
                    />
                  </div>
              </div>
            </div>

            <!-- Right column: Activity | Comments (hidden when editing) -->
            <div class="col-md-7">
              <ResponsiblePersonSection
                v-if="deal?.id"
                :deal="deal"
                @person-updated="handlePersonUpdated"
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
import DealHistoryPanel from './DealHistoryPanel.vue'
import DealActivitySection from './DealActivitySection.vue'
import DealCommentsSection from './DealCommentsSection.vue'
import DealActivityList from './DealActivityList.vue'
import DealCommentList from './DealCommentList.vue'
import ResponsiblePersonSection from './ResponsiblePersonSection.vue'
import axios from '@/plugins/axios'
import { useStageTransition } from '@/composables/useStageTransition'
const props = defineProps({
  modelValue: Boolean,
  deal: { type: Object, default: null },
  autoEditSection: { type: String, default: null } 
})

const emit = defineEmits(['update:modelValue', 'deal-updated', 'stage-change-request'])

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
const activeEditSection = ref(null)
const editLookup = ref({
  users: [],
  sources: [],
  propertyTypes: [],
  developers: [],
  areas: []
})
const editHydrationRequestId = ref(0)



const { updateAndChangeStage } = useStageTransition()

const dealTitle = computed(() => {
  if (!props.deal) return 'View Deal'
  const name = props.deal.project_name || props.deal.project || props.deal.deal_name
  if (name) return `Deal Done From "${name}"`
  return props.deal.id ? `Deal #${props.deal.id}` : 'View Deal'
})

const dealEntityId = computed(() => props.deal?.id ?? null)

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

function handlePersonUpdated(updatedPerson) {
  if (!props.deal) return
  emit('deal-updated', {
    ...props.deal,
    responsible_person_id: updatedPerson?.id ?? props.deal?.responsible_person_id,
    responsible_person: {
      ...(props.deal?.responsible_person || {}),
      ...(updatedPerson || {}),
    },
  })
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
    const formattedStages = stagesData.map(stage => ({
      id: stage.id,
      name: stage.name,
      order: stage.order,
      dotColor: stage.color || getDefaultColor(stage.order),
      bg: stage.bg_color || '#F1F5F9',
      color: stage.color || '#3B82F6'
    }))
    
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

// دالة مساعدة للحصول على لون افتراضي
function getDefaultColor(order) {
  const colors = ['#3B82F6', '#22C55E', '#059669', '#DC2626']
  return colors[(order || 0) % colors.length] || '#3B82F6'
}
const currentStageIndex = computed(() => {
  const d = props.deal
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
  if (!props.deal) return
  const targetStage = currentStages.value[index]
  const currentStage = currentStages.value[selectedStageIndex.value]
  if (!targetStage) return

  const originalStageId = props.deal.stage_id ?? props.deal.stage?.id ?? props.deal.stageId ?? currentStage?.id
  const targetStageId = targetStage.id
  if (String(originalStageId) === String(targetStageId)) return

  selectedStageIndex.value = index
    if (!activeEditSection.value) {

      emit('stage-change-request', {
        dealId: props.deal.id,
        originalStageId,
        targetStageId,
        targetStageName: targetStage.name,
        dealData: props.deal,
      })
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
    tenant_amount: tenant.amount ?? null,
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
  if (!props.deal?.id) return
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

  // Always rehydrate on open/deal change to avoid stale previous form data.
  activeEditSection.value = section
  isEditingDeal.value = true
  editShowErrors.value = false
  editFieldErrors.value = {}
  editFormData.value = {}
  await loadDealForEdit()
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
    emit('deal-updated', updated)
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

watch(() => props.modelValue, async (val) => {
  show.value = val
  if (val && props.deal?.deal_type) {
    dealType.value = props.deal.deal_type
    await fetchStagesFromAPI(props.deal.deal_type)
    selectedStageIndex.value = currentStageIndex.value
  }
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

watch(show, (val) => {
  if (val && props.deal) selectedStageIndex.value = currentStageIndex.value
  if (!val) {
    editHydrationRequestId.value = 0
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
  overflow: visible !important;
  border: 1px solid #e5e7eb !important;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08) !important;
}
#view-deal-modal .modal-body {
  overflow: visible !important;
  height: 100%;
  display: flex;
  flex-direction: column;
    padding: 12px;
    background-color: #fff;
    border-radius: 12px;
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
  overflow: visible !important;
  border: 1px solid #e5e7eb !important;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08) !important;
}
:deep(.view-deal-modal-outer .modal-body) {
  overflow: visible !important;
}
:deep(.view-deal-modal-outer .modal-content.view-deal-modal-content-wrap),
:deep(#view-deal-modal .modal-content) {
  overflow: hidden !important; /* 👈 بدل visible */
  position: relative;
}
.view-deal-modal-content {
  /* background: #fff; */
  display: flex;
  flex-direction: column;
  min-height: 100%;
  height: 100%;
  overflow: visible;
  max-width: 100%;
  font-family: 'Montserrat', sans-serif;
  --deal-font: 'Montserrat', sans-serif;
      background: #fff;
    border-radius: 16px;
}

.view-deal-modal-padding {
  padding: 1rem !important; /* match ViewLeadModal p-3 */
}

/* Header (match View Lead) */
.modal-header-custom {
  background: #fff;
}

.view-deal-body-padding {
  padding: 1.5rem !important; /* match ViewLeadModal p-4 */
}

/* Header: title 18px + deal type tag pill + close */
.view-deal-header {
  padding: 0 0.25rem;
  border-bottom: none;
  position: relative;
  /* background: #fff; */
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
    color: #01062C;
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.deal-stage-pill.active .stage-text {
    color: #01062C;
    font-weight: 400;
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
  padding: 12px 10px;
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
  color: #01062C;
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
.close-btn {
    position: absolute;
    top: 13px;
    left: -11px;
    transform: translate(-56%);
    padding: 0;
    box-shadow: none;
    z-index: -1;
        justify-content: center;
    display: flex;
    align-items: center;
}

:deep(.view-deal-modal-outer .modal-content) {
  position: relative;
  z-index: 2;
}
</style>
