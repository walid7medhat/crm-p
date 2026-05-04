<template>
  <template v-if="!deal">
    <div class="col-12 text-muted text-center py-5">No deal data</div>
  </template>
  <template v-else>
    <div class="col-12">
      <div class="view-card p-3 radius-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="info-label mb-1">Completeness Status</div>
            <div class="info-value mb-0" :class="missingSummary.count ? 'text-warning' : 'text-success'">
              {{ missingSummary.count ? 'Incomplete' : 'Complete' }}
            </div>
          </div>
          <div class="text-end">
            <div class="info-label mb-1">Missing Fields</div>
            <div class="info-value mb-0">{{ missingSummary.count }}</div>
          </div>
        </div>
        <div v-if="missingSummary.count" class="small text-muted mt-2">
          {{ missingSummary.labels.join(' • ') }}
        </div>
      </div>
    </div>

    <!-- Deal Information (from card) -->
    <!-- <div class="col-12" v-if="deal.createdBy || deal.source">
     
      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'deal_information' }">
         <div class="section-head mb-3">
            <h6 class="section-title mb-0">Deal Information</h6>
            <button type="button" class="section-edit-btn" @click="requestEdit('deal_information')">
              <iconify-icon icon="lucide:pencil" />
            </button>
          </div>
        <InlineSectionEditor
          v-if="isEditingSection('deal_information')"
          :model-value="inlineEditData"
          section-key="deal_information"
          deal-type="primary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :selected-stage-name="selectedStageName || ''"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          :hide-footer-actions="hideInlineEditActions"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
        />
        <div v-else class="row g-3">
           <div class="col-md-12" v-if="deal.deal_name">
            <div class="info-group">
              <label class="info-label">Deal Name</label>
              <p class="info-value mb-0">{{ deal.deal_name }}</p>
            </div>
          </div>
        
          <div class="col-md-12" v-if="deal.createdBy">
            <div class="info-group">
              <label class="info-label">Created By</label>
              <p class="info-value mb-0">{{ deal.createdBy }}</p>
            </div>
          </div>
          <div class="col-md-12" v-if="deal.source">
            <div class="info-group">
              <label class="info-label">Source</label>
              <p class="info-value mb-0">{{ deal.source }}</p>
            </div>
          </div>
        </div>
      </div>
    </div> -->

   <!-- Buyer Details -->
    <div class="col-12">

      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'buyer_details' }">
          <div class="section-head mb-3">
          <h6 class="section-title mb-0">Buyer Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('buyer_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <InlineSectionEditor
          v-if="isEditingSection('buyer_details')"
          :model-value="inlineEditData"
          section-key="buyer_details"
          deal-type="primary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :selected-stage-name="selectedStageName || ''"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          :hide-footer-actions="hideInlineEditActions"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
        />
        <div v-else class="row g-3">

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer First Name</label>
              <p class="info-value mb-0">{{ val(buyer.first_name) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Last Name</label>
              <p class="info-value mb-0">{{ val(buyer.last_name) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Date Of Birth</label>
              <p class="info-value mb-0">{{ val(buyer.date_of_birth) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Phone Number</label>
              <p class="info-value mb-0">{{ val(buyer.phone) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Email</label>
              <p class="info-value mb-0">{{ val(buyer.email) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Nationality</label>
              <p class="info-value mb-0">{{ val(buyer.nationality) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Residency Status</label>
              <p class="info-value mb-0">{{ val(buyer.residency_status) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer City Of Residence</label>
              <p class="info-value mb-0">{{ val(buyer.city) }}</p>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Country Of Residence</label>
              <p class="info-value mb-0">{{ val(buyer.country) }}</p>
            </div>
          </div>


          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Buyer Language</label>
              <p class="info-value mb-0">{{ formatLanguageSelection(buyer.language) }}</p>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Buyer Documents -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'buyer_documents' }">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Buyer Documents</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('buyer_documents')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <InlineSectionEditor
          v-if="isEditingSection('buyer_documents')"
          :model-value="inlineEditData"
          section-key="buyer_documents"
          deal-type="primary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :selected-stage-name="selectedStageName || ''"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          :hide-footer-actions="hideInlineEditActions"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
        />
        <DealDocumentsReadonly v-else :documents="buyerDocuments" />
      </div>
    </div>

    <!-- Property Details: title + Add Property outside property cards -->
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 property-details-toolbar px-1">
        <h6 class="section-title mb-0">Property Details</h6>
        <button
          v-if="deal.id"
          type="button"
          class="btn-add-property-sm"
          title="Add another property"
          @click="showInlineAddProperty = true"
        >
          <iconify-icon icon="lucide:plus" /> Add Property
        </button>
      </div>

      <template v-if="deal.properties && deal.properties.length > 0">
        <PropertyCardReadonly
          v-for="(property, idx) in deal.properties"
          :key="property.id"
          :property="property"
          :index="idx"
          :deal-id="deal.id"
          :areas="inlineEditLookup.areas || []"
          :property-types="inlineEditLookup.propertyTypes || []"
          :developers="inlineEditLookup.developers || []"
          :selected-stage-name="selectedStageName"
          :readonly="false"
          @property-updated="handlePropertyUpdated"
          @refresh-deal="() => emit('refresh-deal')"
        />
      </template>

      <div
        v-else
        class="view-card p-3 radius-12 mb-3"
        :class="{ 'section-highlight': activeEditSection === 'property_details' }"
      >
        <InlineSectionEditor
          v-if="isEditingSection('property_details')"
          :model-value="inlineEditData"
          section-key="property_details"
          deal-type="primary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :selected-stage-name="selectedStageName || ''"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          :hide-footer-actions="hideInlineEditActions"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
        />
        <template v-else>
          <div class="property-fallback-card-head d-flex justify-content-end mb-2">
            <button
              type="button"
              class="section-edit-btn"
              title="Edit property details"
              @click="requestEdit('property_details')"
            >
              <iconify-icon icon="lucide:pencil" />
            </button>
          </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Property Address</label>
              <p class="info-value mb-0">{{ getAreaName() }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Unit No</label>
              <p class="info-value mb-0">{{ val(deal.unit_no) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Property Type</label>
              <p class="info-value mb-0">{{ val(deal.property_type?.name) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Bedrooms</label>
              <p class="info-value mb-0">{{ val(deal.bedrooms) }}</p>
            </div>
          </div>
          <div class="col-md-6" v-if="deal.listing">
            <div class="info-group">
              <label class="info-label">Listing</label>
              <p class="info-value mb-0">{{ deal.listing?.name }}</p>
            </div>
          </div>
          <div class="col-md-6" v-if="deal.listing">
            <div class="info-group">
              <label class="info-label">Agent</label>
              <p class="info-value mb-0">{{ deal.listing?.agent }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Developer Name</label>
              <p class="info-value mb-0">{{ getDeveloperName() }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Developer sales person name</label>
              <p class="info-value mb-0">{{ val(deal.developer_name) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Developer sales person phone</label>
              <p class="info-value mb-0">{{ val(deal.developer_phone) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Unit Size</label>
              <p class="info-value mb-0">{{ val(deal.unit_size) }}</p>
            </div>
          </div>
        </div>
        </template>
      </div>

      <div
        v-if="deal.id && showInlineAddProperty"
        class="view-card p-3 radius-12 mb-3 property-inline-add-highlight"
      >
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <span class="inline-add-property-title">New property</span>
          <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none p-0" @click="showInlineAddProperty = false">
            Close
          </button>
        </div>
        <AddPropertyForm
          :deal-id="deal.id"
          :areas="inlineEditLookup.areas || []"
          :property-types="inlineEditLookup.propertyTypes || []"
          :developers="inlineEditLookup.developers || []"
          :selected-stage-name="selectedStageName"
          @property-added="onInlinePropertyAdded"
          @cancel="showInlineAddProperty = false"
        />
      </div>
    </div>

    <!-- Deal Financials -->
    <div class="col-12" v-if="showFinancialsSection || (deal.deal_total_amount && deal.deal_commission)">
      
      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'deal_financials' }">
        <div class="section-head mb-3">
            <h6 class="section-title mb-0">Deal Financials</h6>
            <button type="button" class="section-edit-btn" @click="requestEdit('deal_financials')">
              <iconify-icon icon="lucide:pencil" />
            </button>
          </div>
        <InlineSectionEditor
          v-if="isEditingSection('deal_financials')"
          :model-value="inlineEditData"
          section-key="deal_financials"
          deal-type="primary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :selected-stage-name="selectedStageName || ''"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          :hide-footer-actions="hideInlineEditActions"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
        />
        <div v-else class="row g-3">
          <!-- <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Agent Share %</label>
              <p class="info-value mb-0">{{ val(deal.agent_share) }}</p>
            </div>
          </div> -->
          <!-- <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Company Share %</label>
              <p class="info-value mb-0">{{ val(deal.company_share) }}</p>
            </div>
          </div> -->
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Deal Total Commission %</label>
              <p class="info-value mb-0">{{ val(deal.deal_commission) }}</p>
            </div> 
          </div>
            <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Deal Total amount %</label>
              <p class="info-value mb-0">{{ val(deal.deal_total_amount) }}</p>
            </div> 
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup>
import { ref, computed, watch  } from 'vue'
import DealDocumentsReadonly from './DealDocumentsReadonly.vue'
import InlineSectionEditor from './InlineSectionEditor.vue'
import PropertyCardReadonly from './PropertyCardReadonly.vue'
import AddPropertyForm from './AddPropertyForm.vue'
import { formatLanguageSelection } from '@/composables/useLanguageMultiSelect'

const props = defineProps({
  deal: { type: Object, default: null },
  showResponsibleSection: { type: Boolean, default: true },
  activeEditSection: { type: String, default: null },
  inlineEditData: { type: Object, default: () => ({}) },
  inlineEditLookup: { type: Object, default: () => ({}) },
  inlineEditShowErrors: { type: Boolean, default: false },
  inlineEditFieldErrors: { type: Object, default: () => ({}) },
  inlineEditSaving: { type: Boolean, default: false },
  inlineEditLoading: { type: Boolean, default: false },
  selectedStageId: { type: [Number, String], default: null },
  selectedStageName: { type: String, default: '' },
  hideInlineEditActions: { type: Boolean, default: false },
})

const emit = defineEmits(['edit-section', 'update:inline-edit-data', 'inline-edit-save', 'inline-edit-cancel', 'search-areas', 'search-subcommunities', 'refresh-deal'])
const showInlineAddProperty = ref(false)

function handlePropertyAdded(newProperty) {
  if (props.deal && props.deal.properties) {
    props.deal.properties.push(newProperty)
  } else if (props.deal) {
    props.deal.properties = [newProperty]
  }
  emit('refresh-deal')
}

function onInlinePropertyAdded(newProperty) {
  handlePropertyAdded(newProperty)
  showInlineAddProperty.value = false
}
function requestEdit(sectionKey) {
  emit('edit-section', sectionKey)
}

function isEditingSection(...keys) {
  return !!props.activeEditSection && keys.includes(props.activeEditSection)
}

function handlePropertyUpdated(updatedProperty) {
  if (!props.deal?.properties?.length || !updatedProperty?.id) return
  const index = props.deal.properties.findIndex(
    (p) => Number(p.id) === Number(updatedProperty.id)
  )
  if (index !== -1) {
    props.deal.properties.splice(index, 1, {
      ...props.deal.properties[index],
      ...updatedProperty
    })
  }
}

function val(v) {
  if (v === null || v === undefined || v === '') return '----'
  return v
}

const buyer = computed(() => {
  const parties = props.deal?.parties || []
  return parties.find(p => p.party_type === 'buyer' && p.party_role === 'primary') || {}
})

const buyerDocuments = computed(() => {
  const parties = props.deal?.parties || []
  const buyer = parties.find(p => p.party_type === 'buyer' && p.party_role === 'primary')
  return buyer?.documents || []
})

const missingSummary = computed(() => {
  const d = props.deal || {}
  const parties = d.parties || []
  const buyer = parties.find(p => p.party_type === 'buyer' && p.party_role === 'primary') || {}

  const checks = [
    [d.deal_name, 'Deal Name'],
    [d.source, 'Source'],
    [buyer.first_name, 'Buyer First Name'],
    [buyer.last_name, 'Buyer Last Name'],
    [buyer.phone, 'Buyer Phone'],
    [buyer.email, 'Buyer Email'],
  ]

  const labels = checks
    .filter(([value]) => value === null || value === undefined || value === '')
    .map(([, label]) => label)

  return { count: labels.length, labels }
})

const getAreaName = () => {
  if (props.deal?.area?.name) return props.deal.area.name
  if (props.deal?.area_id && props.inlineEditLookup?.areas) {
    const area = props.inlineEditLookup.areas.find(a => a.id === props.deal.area_id)
    return area?.name || '----'
  }
  return '----'
}

const getDeveloperName = () => {
  if (props.deal?.developer?.name) return props.deal.developer.name
  if (props.deal?.developer_id && props.inlineEditLookup?.developers) {
    const dev = props.inlineEditLookup.developers.find(d => d.id === props.deal.developer_id)
    return dev?.name || '----'
  }
  return '----'
}
const showFinancialsSection = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  
  const stagesToShow = [ 'won', 'deal won']
  const shouldShow = stagesToShow.some(s => stageName.includes(s))
  
  return shouldShow
})
watch(() => props.inlineEditLookup, (newVal) => {
  console.log('🔵 inlineEditLookup changed:', {
    areas: newVal?.areas?.length,
    propertyTypes: newVal?.propertyTypes?.length,
    developers: newVal?.developers?.length
  })
}, { deep: true, immediate: true })
</script>
<style scoped>
.section-title,
h6.section-title {
  font-size: 16px !important;
  font-weight: 600;
  color: #01062C;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  margin-bottom: 12px;
}
.view-card { background: #fff; border: 1px solid #F3F3F3; box-shadow: 1px 1px 5px rgba(0,0,0,0.03); }
.radius-12 { border-radius: 12px; }
.info-label { font-size: 12px !important; font-weight: 500; color: #64748B; display: block; margin-bottom: 6px; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.info-value { font-size: 14px !important; font-weight: 500; color: #01062C; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.department-badge { font-size: 12px; color: #475569; }
.avatar-sm { width: 40px; height: 40px; }
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.section-edit-btn {
  width: 15px;
  height: 15px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #fcb600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.section-highlight {
  border-color: #faa300 !important;
  box-shadow: 0 0 0 2px rgba(250, 163, 0, 0.12);
}
.btn-add-property-sm {
  background: transparent;
  border: 1px solid #01062C;
  border-radius: 100px;
  padding: 4px 12px;
  font-size: 11px;
  font-weight: 500;
  color: #01062C;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
}

.btn-add-property-sm:hover {
  background: #01062C;
  color: #fff;
}
.btn-add-property-sm {
  background: transparent;
  border: 1px solid #01062C;
  border-radius: 100px;
  padding: 4px 12px;
  font-size: 11px;
  font-weight: 500;
  color: #01062C;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
}

.btn-add-property-sm:hover {
  background: #01062C;
  color: #fff;
}

.property-details-toolbar .section-title {
  margin-bottom: 0 !important;
}

.inline-add-property-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--deal-navy-deep, #01062c);
}

.property-inline-add-highlight {
  border: 2px solid rgba(252, 182, 0, 0.55);
  box-shadow: 0 0 0 4px rgba(252, 182, 0, 0.1);
}
</style>
