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
    <div class="col-12" v-if="deal.createdBy || deal.source || deal.project">
      
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
          deal-type="secondary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
          @search-projects="(v) => emit('search-projects', v)"
        />
        <div v-else class="row g-3">
           <div class="col-md-12" v-if="deal.deal_name">
            <div class="info-group">
              <label class="info-label">Deal Name</label>
              <p class="info-value mb-0">{{ deal.deal_name }}</p>
            </div>
          </div>
          <div class="col-md-12" v-if="deal.project">
            <div class="info-group">
              <label class="info-label">Project</label>
              <p class="info-value mb-0">{{ deal.project?.name }}</p>
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
    </div>

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
          v-if="isEditingSection('buyer_details', 'buyer_documents')"
          :model-value="inlineEditData"
          section-key="buyer_details"
          deal-type="secondary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
          @search-projects="(v) => emit('search-projects', v)"
        />
        <div v-else class="row g-3">
          
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer First Name</label>
              <p class="info-value mb-0">{{ val(buyer.first_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Last Name</label>
              <p class="info-value mb-0">{{ val(buyer.last_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Date Of Birth</label>
              <p class="info-value mb-0">{{ val(buyer.dob) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Phone Number</label>
              <p class="info-value mb-0">{{ val(buyer.phone) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Email</label>
              <p class="info-value mb-0">{{ val(buyer.email) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Nationality</label>
              <p class="info-value mb-0">{{ val(buyer.nationality) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Residency Status</label>
              <p class="info-value mb-0">{{ val(buyer.residency_status) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer City Of Residence</label>
              <p class="info-value mb-0">{{ val(buyer.city) }}</p>
            </div>
          </div>
           <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Amount & Currency</label>
              <p class="info-value mb-0">{{ val(buyer.amount_formatted) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Buyer Language</label>
              <p class="info-value mb-0">{{ val(buyer.language) }}</p>
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
        <DealDocumentsReadonly v-if="!isEditingSection('buyer_details', 'buyer_documents')" :documents="buyerDocuments" />
      </div>
    </div>

    <!-- Property Details -->
    <div class="col-12">
     
      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'property_details' }">
         <div class="section-head mb-3">
          <h6 class="section-title mb-0">Property Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('property_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <InlineSectionEditor
          v-if="isEditingSection('property_details')"
          :model-value="inlineEditData"
          section-key="property_details"
          deal-type="secondary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
          @search-projects="(v) => emit('search-projects', v)"
        />
        <div v-else class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Unit No</label>
              <p class="info-value mb-0">{{ val(deal.unit_no) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Property Type</label>
              <p class="info-value mb-0">{{ val(deal.property_type?.name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Bedrooms</label>
              <p class="info-value mb-0">{{ val(deal.bedrooms) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Project Name</label>
              <p class="info-value mb-0">{{ projectDisplay }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Sub Community</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.sub_communities) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Area</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.areas) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Unit Size</label>
              <p class="info-value mb-0">{{ val(deal.unit_size) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Seller Details -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'seller_details' }">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Seller Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('seller_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <InlineSectionEditor
          v-if="isEditingSection('seller_details', 'seller_documents')"
          :model-value="inlineEditData"
          section-key="seller_details"
          deal-type="secondary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
          @search-projects="(v) => emit('search-projects', v)"
        />
        <div v-else class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller First Name</label>
              <p class="info-value mb-0">{{ val(seller.first_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller Last Name</label>
              <p class="info-value mb-0">{{ val(seller.last_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller Date Of Birth</label>
              <p class="info-value mb-0">{{ val(seller.dob) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller Phone Number</label>
              <p class="info-value mb-0">{{ val(seller.phone) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller Email</label>
              <p class="info-value mb-0">{{ val(seller.email) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller City Of Residence</label>
              <p class="info-value mb-0">{{ val(seller.city) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Seller Language</label>
              <p class="info-value mb-0">{{ val(seller.language) }}</p>
            </div>
          </div>
           <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Amount & Currency</label>
              <p class="info-value mb-0">{{ val(seller.amount_formatted) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Seller Documents -->
    <div class="col-12">
     
      <div class="view-card p-3 radius-12" :class="{ 'section-highlight': activeEditSection === 'seller_documents' }">
         <div class="section-head mb-3">
          <h6 class="section-title mb-0">Seller Documents</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('seller_documents')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <DealDocumentsReadonly v-if="!isEditingSection('seller_details', 'seller_documents')" :documents="sellerDocuments" />
      </div>
    </div>

    <!-- Deal Financials -->
    <div class="col-12">
      
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
          deal-type="secondary"
          :lookup="inlineEditLookup"
          :selected-stage-id="selectedStageId"
          :show-errors="inlineEditShowErrors"
          :field-errors="inlineEditFieldErrors"
          :saving="inlineEditSaving"
          :loading="inlineEditLoading"
          @update:model-value="(v) => emit('update:inline-edit-data', v)"
          @save="emit('inline-edit-save')"
          @cancel="emit('inline-edit-cancel')"
          @search-areas="(v) => emit('search-areas', v)"
          @search-subcommunities="(v) => emit('search-subcommunities', v)"
          @search-projects="(v) => emit('search-projects', v)"
        />
        <div v-else class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Deal Total Amount And Currency</label>
              <p class="info-value mb-0">{{ amountCurrency }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Deal Total Commission %</label>
              <p class="info-value mb-0">{{ val(deal.deal_commission) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Agent Share %</label>
              <p class="info-value mb-0">{{ val(deal.agent_share) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Company Share %</label>
              <p class="info-value mb-0">{{ val(deal.company_share) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

  </template>
</template>

<script setup>
import { computed } from 'vue'
import DealDocumentsReadonly from './DealDocumentsReadonly.vue'
import InlineSectionEditor from './InlineSectionEditor.vue'

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
})
const emit = defineEmits(['edit-section', 'update:inline-edit-data', 'inline-edit-save', 'inline-edit-cancel', 'search-areas', 'search-subcommunities', 'search-projects'])

function requestEdit(sectionKey) {
  emit('edit-section', sectionKey)
}
function isEditingSection(...keys) {
  return !!props.activeEditSection && keys.includes(props.activeEditSection)
}
function val(v) {
  if (v === null || v === undefined || v === '') return '----'
  return v
}
const buyer = computed(() => {
  const d = props.deal || {}

  if (d.parties) {
    return d.parties.find(
      p => p.party_type === 'buyer' && p.party_role === 'primary'
    ) || {}
  }

  // fallback
  return {
    first_name: d.buyer_first_name,
    last_name: d.buyer_last_name,
    phone: d.buyer_phone,
    email: d.buyer_email,
    nationality: d.buyer_nationality,
    residency_status: d.buyer_residency_status,
    city: d.buyer_city,
    dob: d.buyer_dob,
    language: d.buyer_language,
  }
})

const seller = computed(() => {
  const d = props.deal || {}

  if (d.parties) {
    return d.parties.find(
      p => p.party_type === 'seller' && p.party_role === 'primary'
    ) || {}
  }

  return {
    first_name: d.seller_first_name,
    last_name: d.seller_last_name,
    phone: d.seller_phone,
    email: d.seller_email,
    city: d.seller_city,
    dob: d.seller_dob,
    language: d.seller_language,
  }
})

const buyerDocuments = computed(() => {
  return buyer.value?.documents || []
})

const sellerDocuments = computed(() => {
  return seller.value?.documents || []
})
function tagsDisplay(arr) {
  if (!Array.isArray(arr) || !arr.length) return '----'
  return arr.join(', ')
}

const projectDisplay = computed(() => {
  const d = props.deal
  if (!d) return '----'
  if (d.project) return d.project
  if (Array.isArray(d.project_names) && d.project_names.length) return d.project_names.join(', ')
  return '----'
})

const amountCurrency = computed(() => {
  const d = props.deal
  if (!d) return '----'
  const amt = d.deal_total_amount ?? d.amount ?? d.budget
  const cur = d.currency || 'AED'
  if (amt != null && amt !== '') return `${amt} ${cur}`
  return '----'
})

const missingSummary = computed(() => {
  const d = props.deal || {}

  const checks = [
    [d.deal_name, 'Deal Name'],
    [d.source, 'Source'],
    [d.unit_no, 'Unit No'],
    [d.property_type?.name, 'Property Type'],

    [buyer.value.first_name, 'Buyer First Name'],
    [buyer.value.last_name, 'Buyer Last Name'],
    [buyer.value.phone, 'Buyer Phone'],
    [buyer.value.email, 'Buyer Email'],

    [seller.value.first_name, 'Seller First Name'],
    [seller.value.last_name, 'Seller Last Name'],
    [seller.value.phone, 'Seller Phone'],
    [seller.value.email, 'Seller Email'],
  ]

  const labels = checks
    .filter(([value]) => value === null || value === undefined || value === '')
    .map(([, label]) => label)

  return { count: labels.length, labels }
})

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
</style>
