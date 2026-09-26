<template>
  <div class="add-property-form">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label-custom">Property Address</label>
        <v-select
          v-model="formData.area_id"
          :options="areas"
          :reduce="item => item.id"
          label="name"
          placeholder="Select Address..."
          class="custom-v-select"
            @update:modelValue="onAreaSelected"
        >
          <template #open-indicator="{ attributes }">
            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
          </template>
          <template #option="option">
            <div class="location-option">
              <iconify-icon icon="lucide:map-pin" class="location-icon" />
              <div>
                <div class="fw-semibold">{{ option.name }}</div>
                <div class="small text-muted">{{ option.area_parents_title }}</div>
              </div>
            </div>
          </template>
        </v-select>
      </div>

      <!-- Secondary/rental: unit comes from a linked sold/rented listing — pick it, don't
           type it in. Its data (unit no, type, bedrooms, size) is read-only, filled from
           the listing itself. -->
      <div class="col-md-6" v-if="isListingDeal">
        <label class="form-label-custom">
          Select Unit
          <span v-if="dealType === 'secondary'" class="text-danger">*</span>
        </label>
        <v-select
          :model-value="formData.listing_id"
          @update:modelValue="onListingSelected"
          :options="availableListings"
          :reduce="item => item.id"
          label="display_name"
          placeholder="Select a unit..."
          class="custom-v-select"
          :disabled="loadingListings || !formData.area_id"
          clearable
        >
          <template #open-indicator="{ attributes }">
            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
          </template>
          <template #option="option">
            <div class="unit-select-option">
              <span class="fw-semibold">{{ option.unit_number || 'No Unit' }}</span>
              <span class="small text-muted ms-1">
                {{ option.property_type || 'N/A' }} · {{ option.bedrooms_text || '—' }} ·
                {{ option.size_sqft ? `${option.size_sqft} sqft` : 'N/A' }}
              </span>
            </div>
          </template>
        </v-select>
        <div class="small text-muted mt-1" v-if="loadingListings">
          <b-spinner small></b-spinner> Loading units...
        </div>
        <div class="small text-muted mt-1" v-else-if="!formData.area_id">
          <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
          Select a property address first
        </div>
        <div class="small text-muted mt-1" v-else-if="availableListings.length === 0">
          <iconify-icon icon="lucide:alert-circle" class="me-1"></iconify-icon>
          No {{ dealType === 'secondary' ? 'sold' : 'rented' }} units available for you in this area
        </div>
      </div>

      <!-- Secondary read-only summary once a unit is picked. -->
      <div class="col-12" v-if="isListingDeal && formData.listing_id">
        <div class="listing-summary-card">
          <span><strong>Unit No:</strong> {{ formData.unit_no || '—' }}</span>
          <span><strong>Type:</strong> {{ selectedListingTypeName || '—' }}</span>
          <span v-if="formData.bedrooms"><strong>Bedrooms:</strong> {{ formData.bedrooms === 'studio' ? 'Studio' : formData.bedrooms }}</span>
          <span v-if="formData.unit_size"><strong>Size:</strong> {{ formData.unit_size }} sqft</span>
        </div>
      </div>

      <div class="col-md-6" v-if="!isListingDeal">
        <label class="form-label-custom">Unit No</label>
        <b-form-input v-model="formData.unit_no" placeholder="Enter Unit No" class="custom-input" />
      </div>

      <div class="col-md-6" v-if="!isListingDeal">
        <label class="form-label-custom">Property Type</label>
        <v-select
          v-model="formData.property_type_id"
          :options="propertyTypes"
          :reduce="item => item.id"
          label="name"
          placeholder="Select Type"
          class="custom-v-select"
        >
          <template #open-indicator="{ attributes }">
            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
          </template>
        </v-select>
      </div>

      <div class="col-md-6" v-if="!isListingDeal && showBedroomsField">
        <label class="form-label-custom">Bedrooms</label>
        <v-select
          v-model="formData.bedrooms"
          :options="bedroomOptions"
          :reduce="o => o.value"
          label="text"
          placeholder="Select Bedrooms"
          class="custom-v-select"
        >
          <template #open-indicator="{ attributes }">
            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
          </template>
        </v-select>
      </div>

      <div class="col-md-6" v-if="!isListingDeal">
        <label class="form-label-custom">Unit Size (sq.ft)</label>
        <b-form-input v-model="formData.unit_size" type="number" placeholder="Size" class="custom-input" />
      </div>




       <div v-if="showBudgetFields" class="col-md-6">
                  <label class="form-label-custom">
                    Budget (AED)
                    <span  class="text-danger">*</span>
                  </label>
                  <div
                    ref="budgetTriggerRef"
                    class="budget-field-wrap"
                  >
                    <button
                      type="button"
                      class="custom-date-trigger"
                      @click.stop="toggleBudgetDropdown"
                            >
                    <span>{{ budgetDisplay }}</span>
                    <iconify-icon icon="lucide:chevron-down" />
                  </button>
                </div>
            
                <div
            v-if="showBudgetDropdown"
            ref="budgetDropdownPanelRef"
            class="budget-dropdown budget-dropdown--portal"
            :style="budgetDropdownStyle"
            @click.stop
            @mousedown.stop
          >
            <div class="budget-from-to-row" @click.stop @mousedown.stop>
              <div class="budget-col">
                <label class="budget-input-label">From (AED)</label>
                <input
                  :value="budgetFromDisplay"
                  placeholder="0"
                  inputmode="numeric"
                  @click.stop
                  @mousedown.stop
                  class="custom-input budget-dropdown-input"
                  @keypress="onMoneyKeypress"
                  @input="(e) => setBudgetValue('budget_from', e.target.value)"
                />
              </div>
              <div class="budget-col">
                <label class="budget-input-label">To (AED)</label>
                <input
                  :value="budgetToDisplay"
                  placeholder="0"
                  inputmode="numeric"
                  @click.stop
                  @mousedown.stop
                  class="custom-input budget-dropdown-input"
                  @keypress="onMoneyKeypress"
                  @input="(e) => setBudgetValue('budget_to', e.target.value)"
                />
              </div>
            </div>
          </div>
      </div>

      <div class="col-md-6" v-if="showPurchasePrice">
        <label class="form-label-custom">Purchase Price</label>
        <div class="input-group">
          <b-form-input v-model="formData.purchase_price" type="text" inputmode="numeric" placeholder="Amount" class="custom-input" @keypress="onMoneyKeypress" />
          <span class="input-group-text">AED</span>
        </div>
      </div>

      <div class="col-md-6" v-if="showPropertyCommission">
        <label class="form-label-custom">Property Commission %</label>
        <div class="input-group">
          <b-form-input v-model="formData.commission" type="number" step="0.01" placeholder="Commission %" class="custom-input" />
          <span class="input-group-text">%</span>
        </div>
      </div>

      <!-- Developer/sales-person fields are primary-only (off-plan) — secondary/rental
           units come from a listing and never carry a developer contact. -->
      <div class="col-md-6" v-if="!isListingDeal">
        <label class="form-label-custom">Developer</label>
        <v-select
          v-model="formData.developer_id"
          :options="developers"
          :reduce="item => item.id"
          label="name"
          placeholder="Select Developer"
          class="custom-v-select"
        >
          <template #open-indicator="{ attributes }">
            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
          </template>
        </v-select>
      </div>

      <div class="col-md-6" v-if="!isListingDeal">
        <label class="form-label-custom">Developer Sales Person Name</label>
        <b-form-input v-model="formData.developer_name" placeholder="Sales Person Person" class="custom-input" />
      </div>

      <div class="col-md-6" v-if="!isListingDeal">
        <label class="form-label-custom">Developer Sales Person Phone</label>
        <CrmPhoneInput v-model="formData.developer_phone" placeholder="Phone Number" />
      </div>

      <!-- Property documents — Title Deed (2 slots: Old/New once the deal reaches Won) +
           Proof of Payment. Same types/labels as PropertyCardReadonly.vue's edit mode. -->
      <div class="col-12 mt-2">
        <label class="form-label-custom mb-2">Property Documents</label>
        <DocumentUpload
          v-model="propertyDocs"
          category="property"
          :document-types="propertyDocTypes"
          :box-label-overrides="titleDeedBoxLabelOverrides"
          :deal-id="dealId"
        />
      </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mt-4">
      <button type="button" class="btn-cancel" @click="onCancel">Cancel</button>
      <button type="button" class="btn-save" @click="saveProperty" :disabled="saving">
        <span v-if="saving"><b-spinner small></b-spinner> Saving...</span>
        <span v-else>{{ submitLabel }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch , onMounted ,onBeforeUnmount ,nextTick } from 'vue'
import { BFormInput, BSpinner } from 'bootstrap-vue-3'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import vSelect from 'vue-select'
import axios from 'axios'
import Swal from 'sweetalert2'
import { buildListingFilterParams } from '@/composables/useDealListingPicker'
import DocumentUpload from './DocumentUpload.vue'

const props = defineProps({
  dealId: { type: Number, required: true },
  areas: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  selectedStageName: { type: String, default: '' },
  selectedStageOrder: { type: [Number, String], default: 0 },
  dealType: { type: String, default: 'primary' },
  submitLabel: { type: String, default: 'Add Property' }
})

const emit = defineEmits(['property-added', 'cancel'])

const saving = ref(false)
const formData = ref({
  unit_no: '',
  property_type_id: null,
  bedrooms: null,
  unit_size: '',
  area_id: null,
  listing_id: null,
  developer_id: null,
  developer_name: '',
  developer_phone: '',
  budget_from: null,
  budget_to: null,
  purchase_price: null,
  commission: null,
})

// Secondary/rental: the unit is picked from a sold/rented listing, not typed manually.
const isListingDeal = computed(() => props.dealType === 'secondary' || props.dealType === 'rental')

const availableListings = ref([])
const loadingListings = ref(false)

const selectedListingTypeName = computed(() => {
  const typeId = formData.value.property_type_id
  if (!typeId) return null
  return props.propertyTypes.find((t) => t.id === typeId)?.name || null
})

async function fetchListings(areaId) {
  if (!isListingDeal.value) return
  if (!areaId) {
    availableListings.value = []
    return
  }
  loadingListings.value = true
  try {
    const params = buildListingFilterParams({ dealType: props.dealType, areaId })
    const response = await axios.get('/api/listings/properties', { params })
    const listings = response.data?.data || []
    availableListings.value = listings.map((listing) => ({
      id: listing.id,
      unit_number: listing.unit_number,
      property_type: listing.property_type,
      property_type_id: listing.property_type_id,
      bedrooms: listing.number_of_bedrooms,
      bedrooms_text: listing.number_of_bedrooms === 0 ? 'Studio' : `${listing.number_of_bedrooms} Bed`,
      size_sqft: listing.size_sqft,
      developer_id: listing.developer_id,
      status: listing.status,
      display_name: `${listing.unit_number || 'No Unit'} - ${listing.property_type || 'Property'}`,
    }))
  } catch (error) {
    console.error('Error fetching listings:', error)
    availableListings.value = []
  } finally {
    loadingListings.value = false
  }
}

function onListingSelected(listingId) {
  const listing = availableListings.value.find((l) => l.id === listingId) || null
  if (!listing) {
    formData.value.listing_id = null
    return
  }
  formData.value.listing_id = listing.id
  formData.value.unit_no = listing.unit_number || ''
  formData.value.property_type_id = listing.property_type_id || null
  formData.value.bedrooms = listing.bedrooms === 0 ? 'studio' : (listing.bedrooms ? String(listing.bedrooms) : null)
  formData.value.unit_size = listing.size_sqft || ''
}

const showBudgetFields = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('eoi')
})
const showPurchasePrice = computed(() => {
  const dt = props.dealType
  if (dt !== 'primary' && dt !== 'secondary') return false
  const order = Number(props.selectedStageOrder) || 0
  if (order >= 3) return true
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('booking') || stageName.includes('mou') || stageName.includes('spa') || stageName.includes('won')
})
const showPropertyCommission = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})

const showBedroomsField = computed(() => {
  const propertyTypeId = formData.value.property_type_id
  if (!propertyTypeId) return true
  const selectedType = props.propertyTypes.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  if (typeName.includes('land') || typeName.includes('plot')) return false
  return true
})

const bedroomOptions = [
  { value: 'studio', text: 'Studio' },
  { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' },
  { value: '5', text: '5 Bedrooms' },
  { value: '5+', text: '5+ Bedrooms' }
]

// Property documents — same types/labels as PropertyCardReadonly.vue's edit mode.
const propertyDocs = ref([])
const isWonStage = computed(() => {
  const order = Number(props.selectedStageOrder) || 0
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return order >= 5 || stageName.includes('won')
})
const propertyDocTypes = computed(() => [
  { id: 'title_deed', name: 'Title Deed', required: isWonStage.value },
  { id: 'payment_proof', name: 'Proof of Payment', required: false },
])
// At Won stage, Title Deed needs two fixed slots (Old/New) instead of one open box —
// matches PropertyCardReadonly.vue's titleDeedBoxLabelOverrides.
const titleDeedBoxLabelOverrides = computed(() => {
  if (!isWonStage.value) return {}
  return { title_deed: ['Old Title Deed', 'New Title Deed'] }
})
const onAreaSelected = async (areaId) => {
  
  // Reset property fields (but keep area_id)

  const selectedArea = props.areas.find(a => a.id === areaId)

    if (!selectedArea) return

    // ✅ set area
  formData.value.area_id = areaId

  if (isListingDeal.value) {
    // Unit is picked from the listing list for this area — clear any previous pick.
    formData.value.listing_id = null
    formData.value.unit_no = ''
    formData.value.property_type_id = null
    formData.value.bedrooms = null
    formData.value.unit_size = ''
    fetchListings(areaId)
    return
  }

    // ✅ auto select developer from area or project
    if (selectedArea.project?.developer_id) {
      formData.value.developer_id = selectedArea.project.developer_id
    } else if (selectedArea.developer_id) {
      formData.value.developer_id = selectedArea.developer_id
    }

}

function resetForm() {
  formData.value = {
    unit_no: '',
    property_type_id: null,
    bedrooms: null,
    unit_size: '',
    area_id: null,
    listing_id: null,
    developer_id: null,
    developer_name: '',
    developer_phone: '',
    budget_from: null,
    budget_to: null,
    purchase_price: null,
    commission: null,
  }
  availableListings.value = []
  propertyDocs.value = []
}

defineExpose({ resetForm })

async function saveProperty() {
  saving.value = true
  try {
    const payload = new FormData()
    Object.keys(formData.value).forEach((key) => {
      const val = formData.value[key]
      if (val !== null && val !== undefined && val !== '') {
        payload.append(key, val)
      }
    })

    let titleDeedIdx = 0
    let paymentProofIdx = 0
    propertyDocs.value.forEach((doc) => {
      if (!(doc.file instanceof File)) return
      if (doc.document_type === 'title_deed') {
        payload.append(`title_deed_documents[${titleDeedIdx++}]`, doc.file)
      } else if (doc.document_type === 'payment_proof') {
        payload.append(`payment_proof[${paymentProofIdx++}]`, doc.file)
      }
    })

    const response = await axios.post(`/api/deals/${props.dealId}/properties`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Property added successfully',
        timer: 1500,
        showConfirmButton: false
      })
      emit('property-added', response.data.data)
      resetForm()
    }
  } catch (error) {
    console.error('Error adding property:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to add property'
    })
  } finally {
    saving.value = false
  }
}

function onCancel() {
  resetForm()
  emit('cancel')
}

watch(() => formData.value.property_type_id, () => {
  if (!showBedroomsField.value) {
    formData.value.bedrooms = null
  }
})
// ========== Budget Dropdown (نفس نظام Lead Search) ==========
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)
const budgetDropdownStyle = ref({})

const budgetFromDisplay = computed(() => {
    return formData.value.budget_from ? formatBudgetWithCommas(formData.value.budget_from) : ''
})

const budgetToDisplay = computed(() => {
    return formData.value.budget_to ? formatBudgetWithCommas(formData.value.budget_to) : ''
})

const budgetDisplay = computed(() => {
    const from = budgetFromDisplay.value
    const to = budgetToDisplay.value
    if (!from && !to) return 'Select budget range'
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    return `To ${to}`
})

function normalizeBudgetString(value) {
    return String(value ?? '').replace(/[^\d]/g, '')
}

function formatBudgetWithCommas(value) {
    if (!value && value !== 0) return ''
    const digits = normalizeBudgetString(value)
    if (!digits) return ''
    return Number(digits).toLocaleString('en-US')
}

function onMoneyKeypress(e) {
  if (!/^\d$/.test(e.key)) e.preventDefault()
}

function setBudgetValue(key, value) {
    const digits = normalizeBudgetString(value)
    formData.value[key] = digits ? Number(digits) : null
}

function getBudgetTriggerElement() {
    let el = budgetTriggerRef.value
    if (Array.isArray(el)) el = el.find(Boolean)
    if (el && typeof el.getBoundingClientRect === 'function') return el
    if (el?.$el && typeof el.$el.getBoundingClientRect === 'function') return el.$el
    return null
}

function updateBudgetDropdownPosition() {
    const el = getBudgetTriggerElement()
    if (!el) return
    
    // استخدام getBoundingClientRect للحصول على الموقع بالنسبة للviewport
    const rect = el.getBoundingClientRect()
    
    // حساب الموقع بالنسبة للصفحة
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop
    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft
    
    budgetDropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 6}px`,
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 240)}px`,
        zIndex: '10060'
    }
}

function removeBudgetDropdownListeners() {
    // window.removeEventListener('scroll', updateBudgetDropdownPosition, true)
    // window.removeEventListener('resize', updateBudgetDropdownPosition)
}

async function toggleBudgetDropdown(event) {
    if (event) {
        event.stopPropagation()
        event.preventDefault()
    }
    
    const next = !showBudgetDropdown.value
    showBudgetDropdown.value = next
    if (next) {
        document.body.style.overflow = 'hidden'
        
        await nextTick()
        updateBudgetDropdownPosition()
        
  
    } else {
        document.body.style.overflow = ''
        removeBudgetDropdownListeners()
    }
}

function onDocumentClick(event) {
    if (!showBudgetDropdown.value) return
    const t = event.target
    const triggerEl = getBudgetTriggerElement()
    const dropdownEl = budgetDropdownPanelRef.value
    if (triggerEl?.contains(t) || dropdownEl?.contains(t)) return
    
    showBudgetDropdown.value = false
    removeBudgetDropdownListeners()
}
onMounted(() => {
    // Capture phase: several triggers in this form use @click.stop, which would
    // otherwise stop the click before it ever reaches this document listener,
    // leaving the budget dropdown stuck open no matter what else gets clicked.
    document.addEventListener('click', onDocumentClick, true)

})
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick, true)
    removeBudgetDropdownListeners()
})
</script>

<style scoped>
.unit-select-option {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.listing-summary-card {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 12px;
  color: #334155;
}
.form-label-custom {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 4px;
  display: block;
}
.custom-input {
  height: 40px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  font-size: 13px;
  width: 100%;
  padding: 0 12px;
}
:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  min-height: 40px;
  height: 40px;
}
.input-group-text {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 12px;
  color: #64748b;
}
/* .custom-input's width:100% overrides Bootstrap's .input-group > .form-control
   (flex: 1 1 auto; width: 1%), which is what keeps the AED/% suffix on the same
   line — without this it has no room left and wraps underneath the input. */
.input-group .custom-input {
  width: 1%;
  flex: 1 1 auto;
}
.btn-cancel {
  background: #f4f4f4;
  border: none;
  padding: 8px 24px;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.btn-save {
  background: #0B0736;
  border: none;
  padding: 8px 24px;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  color: #fff;
  cursor: pointer;
}
.btn-save:hover:not(:disabled) {
  background: #1e293b;
}
.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.location-option {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}
.location-icon {
  margin-top: 2px;
  font-size: 14px;
  color: #64748b;
}
.custom-input::placeholder {
    color: #94a3b8 !important;
    opacity: 1;
    font-size: 12px !important;
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__search::placeholder) {
  font-size: 12px;
  color: #94a3b8;
}

/* Budget Dropdown Styles - نفس نظام Lead Search */
.budget-field-wrap {
    position: relative;
}

.budget-dropdown--portal {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
}

.budget-from-to-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.budget-col {
    min-width: 0;
}

.budget-input-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.budget-dropdown-input {
    height: 38px !important;
}

.is-invalid-group .custom-date-trigger {
    border-color: #dc3545 !important;
}

.custom-date-trigger {
    width: 100%;
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 13px;
    color: #64748B;
    font-family: 'Montserrat';
}

.custom-date-trigger:hover {
    border-color: #cbd5e1;
}
.budget-dropdown--portal {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
}
/* Budget Dropdown Styles - نفس نظام Lead Search */
.budget-field-wrap {
    position: relative;
}

.budget-dropdown--portal {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
}

.budget-dropdown--portal {
    will-change: top, left;
}
</style>
<style>
.custom-input::placeholder {
    color: #94a3b8 !important;
    opacity: 1;
    font-size: 12px !important;
    font-family: 'Montserrat';
}</style>
