<template>
  <b-modal
    id="create-deal-modal"
    v-model="show"
    hide-header
    hide-footer
    size="xl"
    centered
    body-class="p-0"
    @hidden="resetForm"
    @shown="onModalShown"
  >
    <div class="create-deal-modal-content p-3">
      <!-- Header -->
      <div class="modal-header-deal d-flex justify-content-between align-items-center flex-wrap gap-2 px-1">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
          <span class="modal-title">Create New Deal</span>
          <div class="deals-type-tabs-inline d-flex gap-2">
            <button
              v-for="tab in dealTypeTabs"
              :key="tab.id"
              class="deals-type-tab-inline"
              :class="{ active: dealType === tab.id }"
              @click="dealType = tab.id"
            >
              {{ tab.name }}
            </button>
          </div>
        </div>
        <button class="close-btn" @click="close">
          <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
      </div>

      <!-- Lead Conversion Banner -->
      <div v-if="leadId" class="lead-info-banner px-1 py-2">
        <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
          <iconify-icon icon="lucide:info" class="text-info"></iconify-icon>
          <span>Converting Lead #{{ leadId }} to Deal</span>
          <b-button size="sm" variant="outline-info" @click="loadLeadData" class="ms-auto">
            Load Lead Data
          </b-button>
        </div>
      </div>

        <!-- Deal progress / stages (changes by deal type) -->
      <div class="deal-progress-wrapper py-3 px-1">
        <div class="deal-progress-bar">
          <template v-for="(stage, index) in currentStages" :key="stage.id">
            <div
                    class="deal-stage-pill"
                    :class="{ active: selectedStageId === stage.id }"
                    :style="{
                      backgroundColor: selectedStageId === stage.id ? (stage.color || '#DBEAFE') : 'transparent',
                      borderColor: selectedStageId === stage.id ? (stage.color || '#3B82F6') : '#E2E8F0'
                    }"
                    @click="selectedStageId = stage.id"
            >
              <div class="stage-circle">
                <div class="stage-dot" :style="{ backgroundColor: stage.color }"></div>
              </div>
              <span class="stage-text">{{ stage.name }}</span>
            </div>
            <iconify-icon v-if="index < currentStages.length - 1" icon="lucide:chevron-right" class="stage-arrow"></iconify-icon>
          </template>
        </div>
      </div>
      <!-- Unified Form -->
      <div class="form-scroll-area">
        <DealForm
          ref="dealFormRef"
          v-model="formData"
          :deal-type="dealType"
          :users="users"
          :sources="sources"
          :property-types="propertyTypes"
          :developers="developers"
          :areas="areas"
          @search-areas="searchAreas"
          @search-communities="searchCommunities"
          @search-sub-communities="searchSubCommunities"
        />
      </div>

      <!-- Footer -->
      <div class="modal-footer-custom">
        <div class="d-flex align-items-center justify-content-end gap-3">
          <button class="btn-clear" @click="resetForm" :disabled="isSubmitting">Clear</button>
          <button class="btn-next-step" @click="submitForm" :disabled="isSubmitting">
            <span v-if="isSubmitting">
              <b-spinner small></b-spinner> Creating...
            </span>
            <span v-else>
              {{ leadId ? 'Convert Lead to Deal' : 'Create Deal' }}
              <iconify-icon icon="lucide:chevron-right" class="ms-1" />
            </span>
          </button>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { BModal, BSpinner, BButton } from 'bootstrap-vue-3'
import DealForm from './DealForm.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const props = defineProps({
  modelValue: Boolean,
  leadId: { type: [Number, String], default: null },
  dealType: { type: String, default: 'primary' }
})

const emit = defineEmits(['update:modelValue', 'deal-created'])

const show = ref(props.modelValue)
const dealType = ref(props.dealType || 'primary')
const selectedStageId = ref(null)
const isSubmitting = ref(false)
const stagesLoading = ref(false)
const dealFormRef = ref(null)

// Data from API
const users = ref([])
const usersLoading = ref(false)
const sources = ref([])
const stages = ref([])
const propertyTypes = ref([])
const developers = ref([])
const areas = ref([])
const communities = ref([])
const subCommunities = ref([])

// Form data
const formData = ref({})

const dealTypeTabs = [
  { id: 'primary', name: 'Primary / Off Plan' },
  { id: 'secondary', name: 'Secondary' },
  { id: 'rental', name: 'Rental' }
]

// Get stages for current deal type
const currentStages = computed(() => {
  console.log('All stages:', stages.value)
  
  if (!stages.value || stages.value.length === 0) {
    return []
  }
  
  // لو الـ stages ملهمش stage_type أو deal_type، نستخدم الكل
  const hasStageType = stages.value.some(s => s.stage_type)
  const hasDealType = stages.value.some(s => s.deal_type)
  
  if (!hasStageType && !hasDealType) {
    console.log('Stages have no type info, showing all')
    return stages.value
  }
  
  const filtered = stages.value.filter(stage => {
    const stageType = stage.stage_type || 'deal'
    const stageDealType = stage.deal_type || dealType.value
    return stageType === 'deal' && stageDealType === dealType.value
  })
  
  console.log('Filtered stages:', filtered)
  return filtered.sort((a, b) => (a.order || 0) - (b.order || 0))
})

// Watch for deal type changes
watch(dealType, async (newVal, oldVal) => {
  console.log('Deal type changed from', oldVal, 'to', newVal)
  selectedStageId.value = null
  await fetchStages()
  resetFormData()
})

// Watch for changes in the dealType prop from parent
watch(() => props.dealType, (newVal) => {
  if (newVal && newVal !== dealType.value) {
    console.log('Prop dealType changed to', newVal)
    dealType.value = newVal
  }
})

// Watch for modal visibility
watch(() => props.modelValue, async (val) => {
  console.log('Modal visibility changed to', val)
  show.value = val
  if (val) {
    await loadInitialData()
    if (props.leadId) {
      checkLeadConversionStatus()
    }
  }
})

watch(show, (val) => {
  emit('update:modelValue', val)
  if (!val) resetForm()
})

// عندما يظهر المودال
function onModalShown() {
  console.log('Modal shown, current stages:', currentStages.value)
}

// Load all initial data
async function loadInitialData() {
  console.log('Loading initial data...')
  await Promise.all([
    fetchUsers(),
    fetchSources(),
    fetchStages(),
    fetchPropertyTypes(),
    fetchDevelopers()
  ])
}

// API Calls
async function fetchUsers() {
  usersLoading.value = true
  try {
    const response = await api.get('/available-responsible-persons')
    console.log('Users response:', response.data)
    
    const responseData = response.data
    if (responseData?.data) {
      users.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      users.value = responseData
    } else {
      users.value = []
    }
    
    console.log('Processed users:', users.value)
  } catch (error) {
    console.error('Error fetching users:', error)
    users.value = []
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load responsible persons'
    })
  } finally {
    usersLoading.value = false
  }
}

async function fetchSources() {
  try {
    const response = await api.get('/sources')
    const responseData = response.data
    if (responseData?.data) {
      sources.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      sources.value = responseData
    } else {
      sources.value = []
    }
  } catch (error) {
    console.error('Error fetching sources:', error)
    sources.value = []
  }
}

async function fetchStages() {
  stagesLoading.value = true
  try {
    console.log('Fetching stages for deal type:', dealType.value)
    const response = await api.get('/stages', {
      params: {
        stage_type: 'deal',
        deal_type: dealType.value
      }
    })
    
    console.log('Stages API response:', response.data)
    
    const responseData = response.data
    
    if (responseData?.data?.data) {
      stages.value = Array.isArray(responseData.data.data) ? responseData.data.data : []
    } else if (responseData?.data && Array.isArray(responseData.data)) {
      stages.value = responseData.data
    } else if (Array.isArray(responseData)) {
      stages.value = responseData
    } else {
      stages.value = []
    }
    
    console.log('Processed stages:', stages.value)
    
    console.log('Stage types check:', stages.value.map(s => ({
      id: s.id,
      name: s.name,
      stage_type: s.stage_type || 'deal', 
      deal_type: s.deal_type || dealType.value 
    })))
    
    const filteredStages = stages.value.filter(stage => {
      const stageType = stage.stage_type || 'deal'
      const stageDealType = stage.deal_type || dealType.value
      return stageType === 'deal' && stageDealType === dealType.value
    })
    
    console.log('Filtered stages for', dealType.value, ':', filteredStages)
    
    if (filteredStages.length > 0 && !selectedStageId.value) {
      selectedStageId.value = filteredStages[0].id
      console.log('Auto-selected stage:', selectedStageId.value)
    }
  } catch (error) {
    console.error('Error fetching stages:', error)
    stages.value = []
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load stages'
    })
  } finally {
    stagesLoading.value = false
  }
}

async function fetchPropertyTypes() {
  try {
    const response = await api.get('/listings/property-types')
    const responseData = response.data
    if (responseData?.data) {
      propertyTypes.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      propertyTypes.value = responseData
    } else {
      propertyTypes.value = []
    }
  } catch (error) {
    console.error('Error fetching property types:', error)
    propertyTypes.value = []
  }
}

async function fetchDevelopers() {
  try {
    const response = await api.get('/listings/developers')
    const responseData = response.data
    if (responseData?.data) {
      developers.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      developers.value = responseData
    } else {
      developers.value = []
    }
  } catch (error) {
    console.error('Error fetching developers:', error)
    developers.value = []
  }
}

// Area search functions
async function searchAreas(search, parentId = null) {
  try {
    const params = {}
    if (parentId) {
      params.parent_id = parentId
      params.type = 'city'
    } else {
      params.type = 'country'
    }
    
    const response = await api.get('/listings/areas', { params })
    const responseData = response.data
    if (responseData?.data) {
      areas.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      areas.value = responseData
    } else {
      areas.value = []
    }
    return areas.value
  } catch (error) {
    console.error('Error searching areas:', error)
    return []
  }
}

async function searchCommunities(parentId) {
  try {
    const response = await api.get('/listings/areas', {
      params: {
        type: 'community',
        parent_id: parentId
      }
    })
    const responseData = response.data
    if (responseData?.data) {
      communities.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      communities.value = responseData
    } else {
      communities.value = []
    }
    return communities.value
  } catch (error) {
    console.error('Error searching communities:', error)
    return []
  }
}

async function searchSubCommunities(parentId) {
  try {
    const response = await api.get('/listings/areas', {
      params: {
        type: 'sub_community',
        parent_id: parentId
      }
    })
    const responseData = response.data
    if (responseData?.data) {
      subCommunities.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      subCommunities.value = responseData
    } else {
      subCommunities.value = []
    }
    return subCommunities.value
  } catch (error) {
    console.error('Error searching sub-communities:', error)
    return []
  }
}

// Lead conversion functions
async function checkLeadConversionStatus() {
  if (!props.leadId) return
  
  try {
    const response = await api.get(`/leads/${props.leadId}/can-convert`)
    const responseData = response.data?.data ?? response.data
    
    if (!responseData.can_convert) {
      Swal.fire({
        icon: 'warning',
        title: 'Lead Already Converted',
        text: `This lead has already been converted to deal #${responseData.converted_to_deal_id}`,
        confirmButtonText: 'OK'
      })
      close()
    }
  } catch (error) {
    console.error('Error checking lead conversion status:', error)
  }
}

async function loadLeadData() {
  if (!props.leadId) return
  
  try {
    const response = await api.get(`/leads/${props.leadId}`)
    const lead = response.data?.data ?? response.data
    
    // Map lead data to form
    formData.value = {
      ...formData.value,
      source: lead.source || lead.lead_source,
      deal_name: lead.lead_name,
      unit_no: lead.unit_no || '',
      property_type_id: lead.property_type_id,
      bedrooms: lead.bedrooms,
      unit_size: lead.unit_size || '',
      project_id: lead.project_id,
      area_id: lead.area_id,
      developer_id: lead.developer_id,
      responsible_person_id: lead.responsible_person_id,
      deal_total_amount: lead.budget,
      currency: lead.currency || 'AED',
      
      // Buyer/Tenant data based on lead
      buyer_first_name: lead.first_name || '',
      buyer_last_name: lead.last_name || '',
      buyer_dob: lead.date_of_birth || '',
      buyer_phone: lead.phone || lead.mobile || lead.work_phone,
      buyer_email: lead.email || '',
      buyer_nationality: lead.nationality || '',
      amount: lead.budget || ''
    }
    
    Swal.fire({
      icon: 'success',
      title: 'Success',
      text: 'Lead data loaded successfully',
      timer: 1500,
      showConfirmButton: false
    })
    
  } catch (error) {
    console.error('Error loading lead data:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load lead data'
    })
  }
}

// Submit form
async function submitForm() {
  if (!selectedStageId.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Stage Required',
      text: 'Please select a stage for the deal'
    })
    return
  }
  
  isSubmitting.value = true
  
  try {
    // Prepare form data for API
    const submitData = new FormData()
    
    // Add basic fields
    submitData.append('deal_type', dealType.value)
    submitData.append('stage_id', selectedStageId.value)
    
    if (props.leadId) {
      submitData.append('lead_id', props.leadId)
    }
    
    // Add all form fields
    Object.keys(formData.value).forEach(key => {
      if (formData.value[key] !== null && formData.value[key] !== undefined && formData.value[key] !== '') {
        if (key.includes('documents') && Array.isArray(formData.value[key])) {
          // Handle document files
          formData.value[key].forEach((doc, index) => {
            if (doc.file) {
              submitData.append(`documents[${index}]`, doc.file)
              submitData.append(`documents[${index}][category]`, doc.category)
              submitData.append(`documents[${index}][type]`, doc.document_type)
            }
          })
        } else if (!key.includes('documents')) {
          submitData.append(key, formData.value[key])
        }
      }
    })
    
    // Submit to API
    const response = await api.post('/deals/store/new', submitData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: props.leadId ? 'Lead converted successfully' : 'Deal created successfully',
      timer: 2000,
      showConfirmButton: false
    })
    
    emit('deal-created', response.data?.data ?? response.data)
    close()
    
  } catch (error) {
    console.error('Error:', error)
    
    if (error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat().join('\n')
      Swal.fire({ 
        icon: 'error', 
        title: 'Validation Error', 
        text: errors,
        confirmButtonText: 'OK'
      })
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: error.response?.data?.message || 'Failed to create deal',
        confirmButtonText: 'OK'
      })
    }
  } finally {
    isSubmitting.value = false
  }
}

function resetFormData() {
    formData.value = {} 
      if (dealFormRef.value && dealFormRef.value.clearAllDocuments) {
    dealFormRef.value.clearAllDocuments()
  }
  formData.value = {
    // Common fields
    source: null,
    deal_name: '',
    unit_no: '',
    property_type_id: null,
    bedrooms: null,
    unit_size: '',
    project_id: null,
    developer_id: null,
    area_id: null,
    property_link: '',
    property_reference: '',
    deal_total_amount: null,
    deal_commission: null,
    agent_share: null,
    company_share: null,
    currency: 'AED',
    responsible_person_id: null,
    
    // Buyer fields
    buyer_first_name: '',
    buyer_last_name: '',
    buyer_dob: '',
    buyer_phone: '',
    buyer_email: '',
    buyer_nationality: '',
    buyer_documents: [],
    
    // Seller fields (secondary)
    seller_first_name: '',
    seller_last_name: '',
    seller_phone: '',
    seller_email: '',
    seller_documents: [],
    
    // Tenant fields (rental)
    tenant_first_name: '',
    tenant_last_name: '',
    tenant_phone: '',
    tenant_email: '',
    tenant_nationality: '',
    tenant_documents: [],
    
    // Landlord fields (rental)
    landlord_first_name: '',
    landlord_last_name: '',
    landlord_phone: '',
    landlord_email: '',
    landlord_nationality: '',
    landlord_documents: [],
    
    // Property documents
    property_documents: [],
    
    // Secondary buyer
    secondary_first_name: '',
    secondary_last_name: '',
    secondary_phone: '',
    secondary_email: '',
    secondary_amount: null
  }
}

function resetForm() {
  selectedStageId.value = null
  resetFormData()
}

function close() {
     resetForm()
  show.value = false
}

onMounted(() => {
  console.log('CreateDealModal mounted')
  resetFormData()
  loadInitialData()
})
</script>

<style scoped>
/* Add loading state styles */
.lead-info-banner {
  border-bottom: 1px solid #E2E8F0;
}

.alert-info {
  background-color: #EFF6FF;
  border-color: #BFDBFE;
  color: #1E40AF;
  border-radius: 8px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .deals-type-tabs-inline {
    margin-top: 10px;
    width: 100%;
    justify-content: flex-start;
  }
}
.create-deal-modal-content {
  background: #fff;
  border-radius: 12px;
  font-family: 'Montserrat', sans-serif;
}

.modal-header-deal {
  padding: 0.5rem 0;
  border-bottom: 1px solid #F4F4F4;
}

.modal-title {
  font-weight: 600;
  font-size: 16px;
  color: #01062C;
}

.deals-type-tabs-inline {
  flex-wrap: wrap;
}

.deals-type-tab-inline {
  padding: 6px 14px;
  border-radius: 100px;
  border: none;
  font-size: 12px;
  font-weight: 500;
  color: #64748B;
  background: #F1F5F9;
  cursor: pointer;
  transition: all 0.2s;
}

.deals-type-tab-inline:hover {
  color: #1E293B;
  background: #E2E8F0;
}

.deals-type-tab-inline.active {
  background: #0F172A;
  color: #fff;
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  color: #64748B;
  transition: background 0.2s, color 0.2s;
}

.close-btn:hover {
  background: #F1F5F9;
  color: #1E293B;
}

.deal-progress-wrapper {
  overflow-x: auto;
  scrollbar-width: none;
}

.deal-progress-wrapper::-webkit-scrollbar {
  display: none;
}

.deal-progress-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.deal-stage-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 30px;
  border: 1px solid #E2E8F0;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.deal-stage-pill .stage-circle {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.deal-stage-pill .stage-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.deal-stage-pill .stage-text {
  font-size: 12px;
  color: #64748B;
}

.deal-stage-pill.active .stage-text {
  color: #01062C;
  font-weight: 500;
}

.stage-arrow {
  font-size: 14px;
  color: #CBD5E1;
  flex-shrink: 0;
}

.form-scroll-area {
  max-height: 60vh;
  overflow-y: auto;
  padding: 0 0.5rem;
}

.step-content {
  padding: 0.5rem 0;
}

.modal-footer-custom {
  border-top: 1px solid #F4F4F4;
  padding: 15px;
}

.btn-clear {
  background: #F4F4F4;
  border: none;
  padding: 10px 25px;
  border-radius: 100px;
  font-size: 14px;
  color: #01062C;
  cursor: pointer;
}

.btn-next-step {
  background: #01062C;
  border: none;
  padding: 10px 20px;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-next-step:hover:not(:disabled) {
  background: #0f172a;
}
</style>
