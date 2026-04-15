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
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Deal Information</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('deal_information')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-12" v-if="deal.project">
            <div class="info-group">
              <label class="info-label">Project</label>
              <p class="info-value mb-0">{{ deal.project }}</p>
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

    <!-- Source and Deal Name -->
    <div class="col-12">
    
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Source and Deal Name</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('source_deal_name')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Source</label>
              <p class="info-value mb-0">{{ val(deal.source) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Deal Name</label>
              <p class="info-value mb-0">{{ val(deal.deal_name) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Client Details -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Client Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('client_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Name</label>
              <p class="info-value mb-0">{{ val(deal.client_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Phone</label>
              <p class="info-value mb-0">{{ val(deal.client_phone) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Email</label>
              <p class="info-value mb-0">{{ val(deal.client_email) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tenant Details -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Tenant Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('tenant_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Tenant First Name</label>
              <p class="info-value mb-0">{{ val(tenant.first_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Tenant Last Name</label>
              <p class="info-value mb-0">{{ val(tenant.last_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Tenant Phone Number</label>
              <p class="info-value mb-0">{{ val(tenant.phone) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Tenant Email</label>
              <p class="info-value mb-0">{{ val(tenant.email) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Tenant Nationality</label>
              <p class="info-value mb-0">{{ val(tenant.nationality) }}</p>
            </div>
          </div>
           <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Amount & Currency</label>
              <p class="info-value mb-0">{{ val(tenant.amount_formatted) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tenant Documents -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Tenant Documents</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('tenant_documents')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <DealDocumentsReadonly :documents="tenantDocuments" />
      </div>
    </div>

    <!-- Landlord Details -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Landlord Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('landlord_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord First Name</label>
              <p class="info-value mb-0">{{ val(landlord.first_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord Last Name</label>
              <p class="info-value mb-0">{{ val(landlord.last_name) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord Phone Number</label>
              <p class="info-value mb-0">{{ val(landlord.phone) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord Email</label>
              <p class="info-value mb-0">{{ val(landlord.email) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord Nationality</label>
              <p class="info-value mb-0">{{ val(landlord.nationality) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord Residency Status</label>
              <p class="info-value mb-0">{{ val(landlord.residency_status) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord Country Of Residence</label>
              <p class="info-value mb-0">{{ val(landlord.country) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Landlord City Of Residence</label>
              <p class="info-value mb-0">{{ val(landlord.city) }}</p>
            </div>
          </div>
           <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Amount & Currency</label>
              <p class="info-value mb-0">{{ val(landlord.amount_formatted) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Landlord Documents -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Landlord Documents</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('landlord_documents')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <DealDocumentsReadonly :documents="landlordDocuments" />
      </div>
    </div>

    <!-- Property Details -->
    <div class="col-12">
     
      <div class="view-card p-3 radius-12">
         <div class="section-head mb-3">
          <h6 class="section-title mb-0">Property Details</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('property_details')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
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
              <label class="info-label">Property Link</label>
              <p class="info-value mb-0">{{ val(deal.property_link) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Property Reference</label>
              <p class="info-value mb-0">{{ val(deal.property_reference) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Unit Size</label>
              <p class="info-value mb-0">{{ val(deal.unit_size) }}</p>
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
              <label class="info-label">Sub Community</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.sub_communities) }}</p>
            </div>
          </div>
          <div class="col-md-12">
            <div class="info-group">
              <label class="info-label">Building / Project Name</label>
              <p class="info-value mb-0">{{ projectDisplay }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Property Documents -->
    <div class="col-12">
     
      <div class="view-card p-3 radius-12">
         <div class="section-head mb-3">
          <h6 class="section-title mb-0">Property Documents</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('property_documents')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <DealDocumentsReadonly :documents="deal.property_documents || []" />
      </div>
    </div>

    <!-- Deal Financials -->
    <div class="col-12">
      
      <div class="view-card p-3 radius-12">
        <div class="section-head mb-3">
          <h6 class="section-title mb-0">Deal Financials</h6>
          <button type="button" class="section-edit-btn" @click="requestEdit('deal_financials')">
            <iconify-icon icon="lucide:pencil" />
          </button>
        </div>
        <div class="row g-3">
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

const props = defineProps({
  deal: { type: Object, default: null },
  showResponsibleSection: { type: Boolean, default: true },
})
const emit = defineEmits(['edit-section'])

function requestEdit(sectionKey) {
  emit('edit-section', sectionKey)
}
const tenant = computed(() => {
  const parties = props.deal?.parties || []
  return parties.find(p => p.party_type === 'tenant') || {}
})

const landlord = computed(() => {
  const parties = props.deal?.parties || []
  return parties.find(p => p.party_type === 'landlord') || {}
})
function val(v) {
  if (v === null || v === undefined || v === '') return '----'
  return v
}

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
const tenantDocuments = computed(() => {
  const d = props.deal || {}

  // لو API الجديد
  if (d.parties) {
    const tenant = d.parties.find(p => p.party_type === 'tenant')
    return tenant?.documents || []
  }

  // القديم
  return d.tenant_documents || []
})
const landlordDocuments = computed(() => {
  const d = props.deal || {}

  if (d.parties) {
    const landlord = d.parties.find(p => p.party_type === 'landlord')
    return landlord?.documents || []
  }

  return d.landlord_documents || []
})

const missingSummary = computed(() => {
  const d = props.deal || {}

  const checks = [
    [d.deal_name, 'Deal Name'],
    [d.source, 'Source'],
    [d.unit_no, 'Unit No'],
    [d.property_type?.name, 'Property Type'],

    // tenant
    [d.tenant_first_name, 'Tenant First Name'],
    [d.tenant_last_name, 'Tenant Last Name'],
    [d.tenant_phone, 'Tenant Phone'],
    [d.tenant_email, 'Tenant Email'],

    // landlord
    [d.landlord_first_name, 'Landlord First Name'],
    [d.landlord_last_name, 'Landlord Last Name'],
    [d.landlord_phone, 'Landlord Phone'],
    [d.landlord_email, 'Landlord Email'],
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
</style>
