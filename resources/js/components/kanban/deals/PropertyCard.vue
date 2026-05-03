<template>
  <div class="property-card border rounded-3 p-3 mb-3" :class="{ 'border-primary bg-light': isMain }">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center gap-2">
        <span class="badge" :class="isMain ? 'bg-primary' : 'bg-secondary'">
          Property {{ index + 1 }}
        </span>
        <span v-if="isMain" class="badge bg-info">Main</span>
      </div>
      <button 
        v-if="!isMain && !readonly"
        type="button" 
        class="btn btn-sm btn-outline-danger"
        @click="$emit('remove', index)"
      >
        <iconify-icon icon="lucide:trash-2"></iconify-icon>
      </button>
    </div>

    <div class="row g-3">
      <!-- ========== AREA (Location) ========== -->
      <div class="col-md-6">
        <label class="form-label-custom">
          Property Address <span v-if="isRequired('area_id')" class="text-danger">*</span>
        </label>
        <v-select
          v-model="localProperty.area_id"
          :options="areas"
          :reduce="item => item.id"
          label="name"
          placeholder="Select Address..."
          class="custom-v-select"
          :class="{ 'is-invalid': showErrors && isRequired('area_id') && !localProperty.area_id }"
          @update:modelValue="onAreaSelected"
          @search="(search) => emit('search-areas', search)"
        >
         <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
          <template #option="option">
            <div class="location-option d-flex align-items-start gap-2">
              <iconify-icon icon="lucide:map-pin" class="mt-1 text-muted small"></iconify-icon>
              <div>
                <div class="fw-semibold small">{{ option.name }}</div>
                <div class="small text-muted">{{ option.area_parents_title }}</div>
              </div>
            </div>
          </template>
        </v-select>
      </div>

      <!-- ========== LISTINGS (تظهر بعد اختيار المنطقة) ========== -->
      <div class="col-md-6" v-if="availableListings.length > 0">
        <label class="form-label-custom">
          Select Unit <span v-if="isRequired('listing_id')" class="text-danger">*</span>
        </label>
        <v-select 
          v-model="selectedListing" 
          :options="availableListings" 
          :reduce="item => item" 
          label="display_name" 
          placeholder="Select a unit..." 
          class="custom-v-select"
          :class="{ 'is-invalid': showErrors && isRequired('listing_id') && !selectedListing }"
          @update:modelValue="onListingSelected"
          :disabled="isLoadingListings"
        >
         <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
          <template #option="option">
            <div>
              <strong>{{ option.unit_number || 'No Unit' }}</strong>
              <span class="text-muted ms-2">- {{ option.property_type?.name || 'N/A' }}</span>
              <div class="small text-muted">{{ option.bedrooms_text }} | {{ option.size_sqft || 'N/A' }} sqft</div>
              <div class="small text-success">{{ option.status === 'converted' ? 'Sold' : 'Rented' }}</div>
            </div>
          </template>
        </v-select>
        <div class="small text-muted mt-1" v-if="!isLoadingListings">
          <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
          Showing available units in this location
        </div>
        <div class="small text-muted mt-1" v-else>
          <b-spinner small></b-spinner> Loading units...
        </div>
      </div>

      <!-- ========== BASIC PROPERTY FIELDS (تتعبي تلقائياً من الـ Listing) ========== -->
      <div class="col-md-4">
        <label class="form-label-custom">
          Unit No <span v-if="isRequired('unit_no')" class="text-danger">*</span>
        </label>
        <b-form-input
          v-model="localProperty.unit_no"
          placeholder="Enter Unit No"
          class="custom-input"
          :class="{ 'is-invalid': showErrors && isRequired('unit_no') && !localProperty.unit_no }"
        />
      </div>

      <div class="col-md-4">
        <label class="form-label-custom">
          Property Type <span v-if="isRequired('property_type_id')" class="text-danger">*</span>
        </label>
        <v-select
          v-model="localProperty.property_type_id"
          :options="propertyTypes"
          :reduce="item => item.id"
          label="name"
          placeholder="Select Type"
          class="custom-v-select"
          :class="{ 'is-invalid': showErrors && isRequired('property_type_id') && !localProperty.property_type_id }"
        >
        <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
        </v-select>
      </div>

      <div class="col-md-4">
        <label class="form-label-custom">
          Bedrooms <span v-if="isRequired('bedrooms')" class="text-danger">*</span>
        </label>
        <v-select
          v-model="localProperty.bedrooms"
          :options="bedroomOptions"
          :reduce="o => o.value"
          label="text"
          placeholder="Select Bedrooms"
          class="custom-v-select"
          :class="{ 'is-invalid': showErrors && isRequired('bedrooms') && !localProperty.bedrooms }"
        >
         <template #open-indicator="{ attributes }">
            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
         </template>
       </v-select>
      </div>

      <div class="col-md-4">
        <label class="form-label-custom">Unit Size (sq.ft)</label>
        <b-form-input
          v-model="localProperty.unit_size"
          type="number"
          placeholder="Size in sq.ft"
          class="custom-input"
        />
      </div>

      <!-- ========== BUDGET FIELDS (EOI Stage) ========== -->
      <div class="col-md-3" v-if="showBudgetFields">
        <label class="form-label-custom">
          Budget From <span v-if="isRequired('budget_from')" class="text-danger">*</span>
        </label>
        <div class="input-group">
          
          <b-form-input
            v-model="localProperty.budget_from"
            type="number"
            placeholder="Min"
            class="custom-input"
            :class="{ 'is-invalid': showErrors && isRequired('budget_from') && !localProperty.budget_from }"
          />
          <span class="input-group-text">AED</span>
        </div>
      </div>

      <div class="col-md-3" v-if="showBudgetFields">
        <label class="form-label-custom">
          Budget To <span v-if="isRequired('budget_to')" class="text-danger">*</span>
        </label>
        <div class="input-group">
          
          <b-form-input
            v-model="localProperty.budget_to"
            type="number"
            placeholder="Max"
            class="custom-input"
            :class="{ 'is-invalid': showErrors && isRequired('budget_to') && !localProperty.budget_to }"
          />
          <span class="input-group-text">AED</span>
        </div>
      </div>

      <!-- ========== PURCHASE PRICE (Booking, SPA, Won Stages) ========== -->
      <div class="col-md-3" v-if="showPurchasePrice">
        <label class="form-label-custom">
           <span v-if="isWonStage">Amount</span>
            <span v-else>Purchase Price</span>
          
          <span v-if="isRequired('purchase_price')" class="text-danger">*</span>
        </label>
        <div class="input-group">
          <b-form-input
            v-model="localProperty.purchase_price"
            type="number"
            placeholder="Amount"
            class="custom-input"
            :class="{ 'is-invalid': showErrors && isRequired('purchase_price') && !localProperty.purchase_price }"
          />
          <span class="input-group-text">AED</span>
        </div>
      </div>
        <div class="col-md-3" v-if="showPropertyCommission">
        <label class="form-label-custom">
            Property Commission % <span v-if="isRequired('commission')" class="text-danger">*</span>
        </label>
        <div class="input-group">
            <b-form-input
            v-model="localProperty.commission"
            type="number"
            step="0.01"
            placeholder="Commission %"
            class="custom-input"
            :class="{ 'is-invalid': showErrors && isRequired('commission') && !localProperty.commission }"
            />
            <span class="input-group-text">%</span>
        </div>
        </div>

      <!-- ========== DEVELOPER FIELDS ========== -->
      <div class="col-md-4">
        <label class="form-label-custom">Developer</label>
        <v-select
          v-model="localProperty.developer_id"
          :options="developers"
          :reduce="item => item.id"
          label="name"
          placeholder="Select Developer"
          class="custom-v-select"
        >
         <template #open-indicator="{ attributes }">
           <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
        </template>
        </v-select>
      </div>

      <div class="col-md-4">
        <label class="form-label-custom">Developer Contact Name</label>
        <b-form-input
          v-model="localProperty.developer_name"
          placeholder="Contact Person"
          class="custom-input"
        />
      </div>

      <div class="col-md-4">
        <label class="form-label-custom">Developer Contact Phone</label>
        <b-form-input
          v-model="localProperty.developer_phone"
          placeholder="Phone Number"
          class="custom-input"
        />
      </div>

      <!-- ========== PROPERTY DOCUMENTS (same layout as Buyer Documents: one upload, lines between types) ========== -->
      <div class="col-12 mt-3 property-documents-block" v-if="showPropertyDocuments && propertyDocTypes && propertyDocTypes.length > 0">
        <label class="section-title">Property Documents</label>
        <DocumentUpload
          v-model="propertyDocumentsCombined"
          category="property"
          :document-types="propertyDocTypes"
          :compact="props.inlineMode"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import DocumentUpload from './DocumentUpload.vue'
import api from '@/plugins/axios'

const props = defineProps({
  property: { type: Object, required: true },
  index: { type: Number, required: true },
  isMain: { type: Boolean, default: false },
  propertyTypes: { type: Array, default: () => [] },
  areas: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  showErrors: { type: Boolean, default: false },
  requiredFields: { type: Array, default: () => [] },
  dealType: { type: String, default: 'primary' },
  selectedStageName: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
  showPropertyCommission: { type: Boolean, default: false },
  showPurchasePrice: { type: Boolean, default: false },
  showPropertyDocuments: { type: Boolean, default: false },
  propertyDocTypes: { type: Array, default: () => [] },
  inlineMode:{type: Boolean, default: false }
})

const emit = defineEmits(['update:property', 'remove', 'search-areas'])

const localProperty = ref({ ...props.property })

function ensurePropertyDocumentArrays(obj) {
  if (!obj.payment_proof) obj.payment_proof = []
  if (!obj.spa_document) obj.spa_document = []
}

/** Single array for DocumentUpload (like buyer_documents); persisted as payment_proof + spa_document on submit. */
const propertyDocumentsCombined = computed({
  get() {
    const obj = localProperty.value
    ensurePropertyDocumentArrays(obj)
    const pay = (obj.payment_proof || []).map((d) => ({
      ...d,
      document_type: d.document_type || 'payment_proof'
    }))
    const spa = (obj.spa_document || []).map((d) => ({
      ...d,
      document_type: d.document_type || 'spa'
    }))
    return [...pay, ...spa]
  },
  set(files) {
    const list = Array.isArray(files) ? files : []
    localProperty.value.payment_proof = list.filter(
      (f) => (f.document_type || '') === 'payment_proof'
    )
    localProperty.value.spa_document = list.filter((f) => (f.document_type || '') === 'spa')
  }
})

// Track if we're coming from a listing selection
let isUpdatingFromListing = false

const availableListings = ref([])
const selectedListing = ref(null)
const isLoadingListings = ref(false)
const currentUser = ref(null)

// Stage detection
const showBudgetFields = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('eoi')
})
const isWonStage = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})
const showPurchasePrice = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('booking') || stageName.includes('spa') || stageName.includes('won')
})

const showPropertyDocuments = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return  stageName.includes('booking') || stageName.includes('spa') || stageName.includes('won')
})

// Check if a field is required
function isRequired(fieldName) {
  return props.requiredFields?.includes(`property_${fieldName}`) || false
}

// Get current user
const getCurrentUser = () => {
  try {
    const userData = localStorage.getItem('user')
    if (userData) {
      currentUser.value = JSON.parse(userData)
    }
  } catch (error) {
    console.error('Error getting user:', error)
  }
}

// Fetch available listings when area changes
const fetchAvailableListings = async (areaId) => {
  if (!areaId) {
    availableListings.value = []
    return
  }
  
  if (!currentUser.value?.id) {
    getCurrentUser()
    if (!currentUser.value?.id) return
  }
  
  try {
    isLoadingListings.value = true
    
    const params = {
      area_id: areaId,
      sold_by_agent_id: currentUser.value.id,
      per_page: 100
    }
    
    const response = await api.get('/listings/properties', { params })
    
    const listings = response.data.data || []
    availableListings.value = listings.map(listing => ({
      id: listing.id,
      unit_number: listing.unit_number,
      property_type: listing.property_type,
      property_type_id: listing.property_type_id,
      bedrooms: listing.number_of_bedrooms,
      bedrooms_text: listing.number_of_bedrooms === 0 ? 'Studio' : `${listing.number_of_bedrooms} Bed`,
      bathrooms: listing.number_of_bathrooms,
      size_sqft: listing.size_sqft,
      developer_id: listing.developer_id,
      status: listing.status,
      display_name: `${listing.unit_number || 'No Unit'} - ${listing.property_type?.name || 'Property'} (${listing.status === 'converted' ? 'Sold' : 'Rented'})`
    }))
    
  } catch (error) {
    console.error('Error fetching listings:', error)
  } finally {
    isLoadingListings.value = false
  }
}

// When area is selected
const onAreaSelected = async (areaId) => {
  selectedListing.value = null
  
  // Reset property fields (but keep area_id)
  if (!isUpdatingFromListing) {
    localProperty.value.unit_no = ''
    localProperty.value.property_type_id = null
    localProperty.value.bedrooms = null
    localProperty.value.unit_size = ''
    localProperty.value.developer_id = null
    localProperty.value.developer_name = ''
    localProperty.value.developer_phone = ''
  }
    if (props.dealType === 'secondary' || props.dealType === 'rental') fetchAvailableListings(areaId)

  emitUpdate()
}

// When listing is selected - auto-fill property fields
const onListingSelected = (listing) => {
  if (!listing) {
    isUpdatingFromListing = false
    return
  }
  
  isUpdatingFromListing = true
  
  // Auto-fill property fields from the selected listing
  localProperty.value.unit_no = listing.unit_number || ''
  localProperty.value.property_type_id = listing.property_type_id
  localProperty.value.bedrooms = listing.bedrooms === 0 ? 'studio' : String(listing.bedrooms)
  localProperty.value.unit_size = listing.size_sqft || ''
  localProperty.value.developer_id = listing.developer_id
  localProperty.value.listing_id = listing.id
  localProperty.value.listing_status = listing.status
  
  emitUpdate()
  
  // Reset flag after next tick
  setTimeout(() => {
    isUpdatingFromListing = false
  }, 100)
}

// Emit update to parent
function emitUpdate() {
  emit('update:property', { index: props.index, property: localProperty.value })
}

// Watch for property changes
watch(localProperty, () => {
  if (!isUpdatingFromListing) {
    emitUpdate()
  }
}, { deep: true })

// Watch for area changes from parent
watch(() => props.property.area_id, async (newAreaId) => {
  if (newAreaId !== localProperty.value.area_id && !isUpdatingFromListing) {
    localProperty.value.area_id = newAreaId
    if (newAreaId) {
      await fetchAvailableListings(newAreaId)
    }
  }
})

// Initialize
getCurrentUser()

onMounted(() => {
  ensurePropertyDocumentArrays(localProperty.value)
})

watch(
  () => props.property,
  (p) => {
    if (!p) return
    ensurePropertyDocumentArrays(localProperty.value)
  },
  { deep: true }
)

const bedroomOptions = [
  { value: 'studio', text: 'Studio' },
  { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' },
  { value: '5', text: '5 Bedrooms' },
  { value: '5+', text: '5+ Bedrooms' }
]
const showPropertyCommission = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})
</script>

<style scoped>
.property-card {
  transition: all 0.2s;
}
.property-card.border-primary {
  border-width: 2px !important;
}
.input-group-text {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 12px;
  color: #64748b;
}
.custom-input {
  height: 40px !important;
  min-height: 40px;
  border-radius: 8px !important;
  border: 1px solid #E2E8F0 !important;
  font-size: 13px !important;
  width: 100%;
  padding: 0 12px;
}
.custom-input.is-invalid {
  border-color: #dc3545 !important;
}
:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  min-height: 40px !important;
  height: 40px !important;
  font-size: 13px;
  overflow: hidden;
}
:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) {
  border-color: #dc3545 !important;
}
.form-label-custom {
  font-size: 12px !important;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 6px;
  display: block;
}

.property-documents-block :deep(.document-upload-container .document-type-group:last-child) {
  border-bottom: none;
  padding-bottom: 0;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--deal-navy-deep, #01062c);
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  margin-bottom: 10px;
  letter-spacing: -0.02em;
  line-height: 1.35;
  display: block;
}
</style>