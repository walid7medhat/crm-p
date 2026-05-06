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
      <div class="col-md-6">
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

      <div class="col-md-4" v-if="showBedroomsField">
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
        <div v-if="showBudgetFields" class="col-md-4">
                  <label class="form-label-custom">
                    Budget (AED)
                    <span v-if="isRequired('budget_from') || isRequired('budget_to')" class="text-danger">*</span>
                  </label>
                  <div
                    ref="budgetTriggerRef"
                    class="budget-field-wrap"
                    :class="{ 'is-invalid-group': (showErrors && (isRequired('budget_from') || isRequired('budget_to')) && !localProperty.budget_from && !localProperty.budget_to) }"
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
            <div v-if="showErrors && (isRequired('budget_from') || isRequired('budget_to')) && !localProperty.budget_from && !localProperty.budget_to" class="invalid-feedback d-block">
              Budget range is required
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
                  @click.stop
                  @mousedown.stop
                  class="custom-input budget-dropdown-input"
                  @input="(e) => setBudgetValue('budget_from', e.target.value)"
                />
              </div>
              <div class="budget-col">
                <label class="budget-input-label">To (AED)</label>
                <input
                  :value="budgetToDisplay"
                  placeholder="0"
                  @click.stop
                  @mousedown.stop
                  class="custom-input budget-dropdown-input"
                  @input="(e) => setBudgetValue('budget_to', e.target.value)"
                />
              </div>
            </div>
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
        <label class="form-label-custom">Developer Sales Person Name</label>
        <b-form-input
          v-model="localProperty.developer_name"
          placeholder="Contact Person"
          class="custom-input"
        />
      </div>

      <div class="col-md-4">
        <label class="form-label-custom">Developer Sales Person Phone</label>
        <CrmPhoneInput
          v-model="localProperty.developer_phone"
          placeholder="Phone Number"
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
import { ref, computed, watch, onMounted ,onBeforeUnmount ,nextTick } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
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
// ========== Budget Dropdown (نفس نظام Lead Search) ==========
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)
const budgetDropdownStyle = ref({})

const budgetFromDisplay = computed(() => {
    return localProperty.value.budget_from ? formatBudgetWithCommas(localProperty.value.budget_from) : ''
})

const budgetToDisplay = computed(() => {
    return localProperty.value.budget_to ? formatBudgetWithCommas(localProperty.value.budget_to) : ''
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

function setBudgetValue(key, value) {
    const digits = normalizeBudgetString(value)
    localProperty.value[key] = digits ? Number(digits) : null
    emitUpdate()
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
        // top: `${rect.bottom + 6}px`,
        // left: `${rect.left}px`,
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
  return stageName.includes('booking') || stageName.includes('spa') || stageName.includes('won')
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
const fetchAllAreas = async () => {
  try {
      const response = await api.get('/listings/areas')
    
    // معالجة البيانات
    const responseData = response.data
    let areasData = []
    
    if (responseData?.data?.data) {
      areasData = responseData.data.data
    } else if (responseData?.data && Array.isArray(responseData.data)) {
      areasData = responseData.data
    } else if (Array.isArray(responseData)) {
      areasData = responseData
    } else {
      areasData = []
    }
     props.areas = areasData
     emit('update:areas', areasData)
   
    
    console.log(`Loaded ${props.areas.length} areas`)
  } catch (error) {
    console.error('Error loading areas:', error)
  }
}
onMounted(() => {
  ensurePropertyDocumentArrays(localProperty.value)
  fetchAllAreas()
    document.addEventListener('click', onDocumentClick)

})
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    removeBudgetDropdownListeners()
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
// بعد الـ computed الموجودة
const showBedroomsField = computed(() => {
  const propertyTypeId = localProperty.value.property_type_id
  if (!propertyTypeId) return true // لو لسه مجاش
  
  const selectedType = props.propertyTypes.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  
  // لو الاسم يحتوي على land أو plot، نخفي الـ Bedrooms
  if (typeName.includes('land') || typeName.includes('plot')) {
    return false
  }
  
  return true
})

// كمان لو اختار Land أو Plot، نحذف قيمة الـ bedrooms
watch(() => localProperty.value.property_type_id, (newTypeId) => {
  if (!showBedroomsField.value) {
    localProperty.value.bedrooms = null
  }
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
  font-size: 12px !important;
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
  font-size: 12px !important;
  overflow: hidden;
  display: flex !important;
  align-items: stretch !important;
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



/* Figma deal forms — Inter, 16px sections, 12px labels, 14px inputs */
.section-title { font-size: 16px !important; font-weight: 600; color: var(--deal-navy-deep, #01062c); font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); margin-bottom: 10px; letter-spacing: -0.02em; line-height: 1.35; }
.form-card { background: #fff; border: 1px solid #e5e7eb; box-shadow: none; padding: 0.875rem 1rem !important; }
.radius-12 { border-radius: 8px; }
.form-label-custom { font-size: 12px !important; font-weight: 500; color: var(--deal-text-muted, #64748b); margin-bottom: 4px; display: block; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input { height: 42px !important; min-height: 42px; border-radius: 8px !important; border: 1px solid #e5e7eb !important; font-size: 12px !important; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input::placeholder { font-size: 12px !important; color: #9ca3af; text-align: left; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input.is-invalid { border-color: #dc3545 !important; }
.input-group-custom { display: flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.input-group-custom .custom-input { border: none !important; flex: 1; border-radius: 8px 0 0 8px !important; }
:deep(.custom-v-select) { font-size: 12px !important; }
:deep(.custom-v-select .vs__dropdown-toggle) { height: 42px !important; min-height: 42px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 12px !important; padding: 2px 8px; overflow: hidden; display: flex !important; align-items: stretch !important; }
:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) { border-color: #dc3545 !important; }
:deep(.custom-v-select .vs__selected), :deep(.custom-v-select .vs__search) { font-size: 12px !important; }
:deep(.custom-v-select .vs__search::placeholder) { font-size: 12px !important; color: #9ca3af; text-align: left; }
:deep(.custom-v-select .vs__placeholder) { font-size: 12px !important; color: #9ca3af; text-align: left; }
:deep(.buyer-language-select .vs__selected) {     height: 26px !important;background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; margin:5px !important}
:deep(.buyer-language-select .vs__dropdown-option--highlight) { background: #eff6ff; color: #1e3a8a; }
:deep(.buyer-language-select .vs__dropdown-option--selected) { background: #dbeafe; color: #1d4ed8; font-weight: 600; }
:deep(.custom-v-select-inline) { min-width: 120px; }
:deep(.custom-v-select-inline .vs__dropdown-toggle) { height: 42px !important; min-height: 42px; border: none; border-left: 1px solid #e5e7eb; border-radius: 0 8px 8px 0; font-size: 11px; }
:deep(.custom-v-select-inline .vs__selected) { font-size: 11px; font-weight: 500; color: #64748b; }
:deep(.custom-v-select-inline .vs__search::placeholder) { font-size: 9px !important; color: #9ca3af; }
:deep(.custom-v-select-inline .vs__placeholder) { font-size: 9px !important; color: #9ca3af; }
.doc-tabs { gap: 8px; }
.doc-tab { height: 32px; min-height: 32px; padding: 0 14px; border-radius: 100px; border: 1px solid #E2E8F0; background: #fff; font-size: 12px; font-weight: 500; color: #64748B; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.doc-tab.active { background: #0F172A; color: #fff; border-color: #0F172A; }
.upload-zone { border-style: dashed !important; border-color: #E2E8F0 !important; background: #F8FAFC; }
.upload-icon { font-size: 36px; color: #94A3B8; }
.upload-text { font-size: 14px; color: #475569; margin: 0; }
.tag-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #F1F5F9; border-radius: 100px; font-size: 13px; }
.tag-remove { cursor: pointer; font-size: 16px; }
.btn-tag-search { background: transparent; border: none; color: var(--deal-navy, #0f172a); font-size: 14px; font-weight: 500; cursor: pointer; }
.add-custom-field-link { font-size: 14px; color: var(--deal-navy, #0f172a); font-weight: 500; text-decoration: underline; }
.form-section { margin-top: 14px; }
.form-section:first-of-type { margin-top: 0; }

/* Inline per-section edit mode */
.inline-mode .section-title {
  display: none !important;
}
.inline-mode .form-card {
  border: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
}
.inline-mode .form-section {
  margin-top: 0 !important;
}
.inline-mode :deep(.row.g-3) {
  display: flex;
  flex-direction: row;
  gap: 9px !important;
}
.inline-mode :deep(.row.g-3 > [class*='col-']) {
  width: 49% !important;
  max-width: 100% !important;
  /* flex: 0 0 100% !important; */
  /* padding-left: 0 !important;
  padding-right: 0 !important; */
}
:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 13px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
}
  /* Location dropdown options: 2 lines with icon (like image) */
    .location-option {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 4px 0;
      min-height: 40px;
    }
    
    .location-option-icon {
      font-size: 1.1rem;
      color: #64748b;
      flex-shrink: 0;
      margin-top: 2px;
    }
    
    .location-option-text {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    
    .location-option-name {
      font-weight: 600;
      font-size: 0.75rem;
      color: #01062d;
      line-height: 1.2;
    }
    
    .location-option-subtitle {
      font-size: 0.65rem;
      color: #64748b;
      line-height: 1.2;
    }
    
    /* Location dropdown list: wider */
    :deep(.location-select + .vs__dropdown-menu),
    :deep(.location-select .vs__dropdown-menu) {
      min-width: 320px !important;
      width: 100% !important;
      max-width: 400px;
    }
    .document-upload-container.is-compact .all-boxes-grid{
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

:deep(.vs__open-indicator) {
  color: #94a3b8 !important;
      /* margin-bottom: 10px; */
}

:deep(.vs__deselect) {
  border: none !important;
  box-shadow: none !important;
}
:deep(.custom-v-select.vs--single .vs__selected) {
  text-align: left !important;
  font-size: 13px;
  padding-left: 8px;
  margin: 0 !important;
  align-self: stretch !important;
  height: 100% !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.custom-v-select .vs__search::placeholder),
:deep(.custom-v-select .vs__placeholder) {
  text-align: left !important;
  font-size: 12px;
  color: #9ca3af;
}

:deep(.custom-v-select .vs__dropdown-menu) {
  overflow-y: auto;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

:deep(.custom-v-select .vs__clear) {
  fill: #94a3b8;
  padding: 4px;
  cursor: pointer;
}


:deep(.custom-v-select .vs__clear svg) {
  display: none !important;
}

:deep(.custom-v-select .vs__clear) {
  background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="%2394a3b8" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>') !important;
  background-repeat: no-repeat !important;
  background-position: center !important;
  background-size: 14px !important;
  width: 24px !important;
  height: 24px !important;
}
:deep(.custom-v-select .vs__deselect svg) {
  display: none !important;
}

:deep(.custom-v-select .vs__deselect) {
  background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="%2394a3b8" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>') !important;
  background-repeat: no-repeat !important;
  background-position: center !important;
  background-size: 14px !important;
  width: 24px !important;
  height: 24px !important;
}
.custom-remove-icon {
  display: flex;
  align-items: center;
  cursor: pointer;
  color: #94a3b8; 
  font-size: 12px;
}


.btn-add-property {
  background: transparent;
  border: 1px solid #01062C;
  border-radius: 100px;
  padding: 8px 20px;
  font-size: 13px;
  font-weight: 500;
  color: #01062C;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-add-property:hover {
  background: #01062C;
  color: #fff;
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
.advanced-date-trigger{
  border:none !important;
}</style>