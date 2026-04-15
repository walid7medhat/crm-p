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
    <div class="col-12" v-if="deal.createdBy || deal.source">
      <div class="section-head mb-3">
        <h6 class="section-title mb-0">Deal Information</h6>
        <button type="button" class="section-edit-btn" @click="requestEdit('deal_information')">
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
      <div class="view-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4" v-if="deal.project">
            <div class="info-group">
              <label class="info-label">Project</label>
              <p class="info-value mb-0">{{ deal.project }}</p>
            </div>
          </div>
          <div class="col-md-4" v-if="deal.createdBy">
            <div class="info-group">
              <label class="info-label">Created By</label>
              <p class="info-value mb-0">{{ deal.createdBy }}</p>
            </div>
          </div>
          <div class="col-md-4" v-if="deal.source">
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
      <div class="section-head mb-3">
        <h6 class="section-title mb-0">Buyer Details</h6>
        <button type="button" class="section-edit-btn" @click="requestEdit('buyer_details')">
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
      <div class="view-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4" v-if="deal.buyerName && !deal.buyer_first_name">
            <div class="info-group">
              <label class="info-label">Buyer Name</label>
              <p class="info-value mb-0">{{ deal.buyerName }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer First Name</label>
              <p class="info-value mb-0">{{ val(deal.buyer_first_name) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Last Name</label>
              <p class="info-value mb-0">{{ val(deal.buyer_last_name) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Date Of Birth</label>
              <p class="info-value mb-0">{{ val(deal.buyer_dob) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Phone Number</label>
              <p class="info-value mb-0">{{ val(deal.buyer_phone) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Email</label>
              <p class="info-value mb-0">{{ val(deal.buyer_email) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Nationality</label>
              <p class="info-value mb-0">{{ val(deal.buyer_nationality) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Residency Status</label>
              <p class="info-value mb-0">{{ val(deal.buyer_residency_status) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer City Of Residence</label>
              <p class="info-value mb-0">{{ val(deal.buyer_city) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Country Of Residence</label>
              <p class="info-value mb-0">{{ val(deal.buyer_country) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Amount & Currency</label>
              <p class="info-value mb-0">{{ amountCurrency }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Buyer Language</label>
              <p class="info-value mb-0">{{ val(deal.buyer_language) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Buyer Documents -->
    <div class="col-12">
      <div class="section-head mb-3">
        <h6 class="section-title mb-0">Buyer Documents</h6>
        <button type="button" class="section-edit-btn" @click="requestEdit('buyer_documents')">
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
      <div class="view-card p-3 radius-12">
        <DealDocumentsReadonly :documents="deal.buyer_documents || []" />
      </div>
    </div>

    <!-- Property Details -->
    <div class="col-12">
      <div class="section-head mb-3">
        <h6 class="section-title mb-0">Property Details</h6>
        <button type="button" class="section-edit-btn" @click="requestEdit('property_details')">
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
      <div class="view-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Unit No</label>
              <p class="info-value mb-0">{{ val(deal.unit_no) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Property Type</label>
              <p class="info-value mb-0">{{ val(deal.property_type) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Bedrooms</label>
              <p class="info-value mb-0">{{ val(deal.bedrooms) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Project Name</label>
              <p class="info-value mb-0">{{ projectDisplay }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Developer</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.developers) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Area</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.areas) }}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-group">
              <label class="info-label">Sub Community</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.sub_communities) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Unit Size</label>
              <p class="info-value mb-0">{{ val(deal.unit_size) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Deal Financials -->
    <div class="col-12">
      <div class="section-head mb-3">
        <h6 class="section-title mb-0">Deal Financials</h6>
        <button type="button" class="section-edit-btn" @click="requestEdit('deal_financials')">
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
      <div class="view-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Agent Share %</label>
              <p class="info-value mb-0">{{ val(deal.agent_share) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Company Share %</label>
              <p class="info-value mb-0">{{ val(deal.company_share) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Deal Total Commission %</label>
              <p class="info-value mb-0">{{ val(deal.deal_commission) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Responsible Person / Assigned -->
    <div v-if="showResponsibleSection" class="col-12">
      <div class="section-head mb-3">
        <h6 class="section-title mb-0">Responsible Person</h6>
        <button type="button" class="section-edit-btn" @click="requestEdit('responsible_person')">
          <iconify-icon icon="lucide:pencil" />
        </button>
      </div>
      <div class="view-card p-3 radius-12">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="info-group mb-1">
              <label class="info-label">Name</label>
              <p class="info-value mb-0">{{ val(deal.assignedBy) || val(deal.responsible_person_name) }}</p>
            </div>
            <div class="info-group mb-1">
              <label class="info-label">Email</label>
              <p class="info-value mb-0">{{ val(deal.responsible_person_email) }}</p>
            </div>
            <div class="info-group mb-0">
              <label class="info-label">Position</label>
              <p class="info-value mb-0">{{ val(deal.responsible_person_position) }}</p>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="department-badge">Department: {{ val(deal.department) || 'Sales' }}</span>
            <div class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center overflow-hidden">
              <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
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
  const amt = d.amount ?? d.budget
  const cur = d.currency || 'AED'
  if (amt != null && amt !== '') return `${amt} ${cur}`
  return '----'
})

const missingSummary = computed(() => {
  const d = props.deal || {}
  const checks = [
    ['deal_name', 'Deal Name'],
    ['source', 'Source'],
    ['unit_no', 'Unit No'],
    ['property_type', 'Property Type'],
    ['buyer_first_name', 'Buyer First Name'],
    ['buyer_last_name', 'Buyer Last Name'],
    ['buyer_phone', 'Buyer Phone'],
    ['buyer_email', 'Buyer Email'],
  ]

  const labels = checks
    .filter(([key]) => d[key] === null || d[key] === undefined || d[key] === '')
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
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: none;
  background: #f8fafc;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
