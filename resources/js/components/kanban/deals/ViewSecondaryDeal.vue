<template>
  <template v-if="!deal">
    <div class="col-12 text-muted text-center py-5">No deal data</div>
  </template>
  <template v-else>
    <!-- Deal Information (from card) -->
    <div class="col-12" v-if="deal.createdBy || deal.source || deal.project">
      <h6 class="section-title mb-3">Deal Information</h6>
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
      <h6 class="section-title mb-3">Buyer Details</h6>
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
      <h6 class="section-title mb-3">Buyer Documents</h6>
      <div class="view-card p-3 radius-12">
        <p v-if="!(deal.buyer_documents?.length)" class="info-value text-muted mb-0">No documents uploaded</p>
        <div v-else class="d-flex flex-wrap gap-2">
          <span v-for="(doc, i) in (deal.buyer_documents || [])" :key="i" class="doc-tag">{{ doc.name || `Document ${i + 1}` }}</span>
        </div>
      </div>
    </div>

    <!-- Property Details -->
    <div class="col-12">
      <h6 class="section-title mb-3">Property Details</h6>
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
              <label class="info-label">Sub Community</label>
              <p class="info-value mb-0">{{ tagsDisplay(deal.sub_communities) }}</p>
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
              <label class="info-label">Unit Size</label>
              <p class="info-value mb-0">{{ val(deal.unit_size) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Seller Details -->
    <div class="col-12">
      <h6 class="section-title mb-3">Seller Details</h6>
      <div class="view-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller First Name</label>
              <p class="info-value mb-0">{{ val(deal.seller_first_name) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller Last Name</label>
              <p class="info-value mb-0">{{ val(deal.seller_last_name) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller Date Of Birth</label>
              <p class="info-value mb-0">{{ val(deal.seller_dob) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller Phone Number</label>
              <p class="info-value mb-0">{{ val(deal.seller_phone) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller Email</label>
              <p class="info-value mb-0">{{ val(deal.seller_email) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller City Of Residence</label>
              <p class="info-value mb-0">{{ val(deal.seller_city) }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Seller Language</label>
              <p class="info-value mb-0">{{ val(deal.seller_language) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Seller Documents -->
    <div class="col-12">
      <h6 class="section-title mb-3">Seller Documents</h6>
      <div class="view-card p-3 radius-12">
        <p v-if="!(deal.seller_documents?.length)" class="info-value text-muted mb-0">No documents uploaded</p>
        <div v-else class="d-flex flex-wrap gap-2">
          <span v-for="(doc, i) in (deal.seller_documents || [])" :key="i" class="doc-tag">{{ doc.name || `Document ${i + 1}` }}</span>
        </div>
      </div>
    </div>

    <!-- Deal Financials -->
    <div class="col-12">
      <h6 class="section-title mb-3">Deal Financials</h6>
      <div class="view-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Deal Total Amount And Currency</label>
              <p class="info-value mb-0">{{ amountCurrency }}</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-group">
              <label class="info-label">Deal Total Commission %</label>
              <p class="info-value mb-0">{{ val(deal.deal_commission) }}</p>
            </div>
          </div>
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
        </div>
      </div>
    </div>

    <!-- Responsible Person -->
    <div class="col-12">
      <h6 class="section-title mb-3">Responsible Person</h6>
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

const props = defineProps({
  deal: { type: Object, default: null }
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
</script>

<style scoped>
.section-title,
h6.section-title {
  font-size: 16px !important;
  font-weight: 600;
  color: #01062C;
  font-family: 'Montserrat', sans-serif;
  margin-bottom: 12px;
}
.view-card { background: #fff; border: 1px solid #F3F3F3; box-shadow: 1px 1px 5px rgba(0,0,0,0.03); }
.radius-12 { border-radius: 12px; }
.info-label { font-size: 12px !important; font-weight: 500; color: #64748B; display: block; margin-bottom: 6px; }
.info-value { font-size: 14px !important; font-weight: 500; color: #01062C; }
.doc-tag { padding: 4px 10px; background: #F1F5F9; border-radius: 100px; font-size: 13px; color: #334155; }
.department-badge { font-size: 12px; color: #475569; }
.avatar-sm { width: 40px; height: 40px; }
</style>
