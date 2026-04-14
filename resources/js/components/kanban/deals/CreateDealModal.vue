<template>
  <b-modal
    id="create-deal-modal"
    v-model="show"
    hide-header
    hide-footer
    size="xl"
    centered
    body-class="p-0 create-deal-modal-body"
    modal-class="create-deal-modal-wrap"
    @hidden="resetForm"
    @shown="onModalShown"
  >
    <div class="create-deal-modal-content create-deal-modal-padding deal-figma-ui">
      <!-- Header: extra padding so text doesn't start at edge -->
      <div class="modal-header-deal d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-grow-1 header-left-with-padding">
          <span class="modal-title">Create New Deal</span>
          <div class="deals-type-select-wrap">
            <select
              v-model="dealType"
              class="deals-type-select"
              @change="selectDealType(dealType)"
            >
              <option v-for="tab in dealTypeTabs" :key="tab.id" :value="tab.id">
                {{ tab.name }}
              </option>
            </select>
          </div>
        </div>
        <button type="button" class="close-btn" @click="close">
          <iconify-icon icon="lucide:x"></iconify-icon>
        </button>
      </div>

      <!-- Lead Conversion Banner -->
      <div v-if="leadId" class="lead-info-banner py-2">
        <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
          <iconify-icon icon="lucide:info" class="text-info"></iconify-icon>
          <span>Converting Lead #{{ leadId }} to Deal</span>
          <b-button size="sm" variant="outline-info" @click="loadLeadData" class="ms-auto">
            Load Lead Data
          </b-button>
        </div>
      </div>
     
      <!-- Deal progress / stages (same pill system as View Deal: navy active, colored dot only) -->
      <div class="deal-progress-wrapper py-2 px-1">
        <div class="deal-progress-label">Pipeline</div>
        <div class="deal-progress-bar">
          <template v-for="(stage, index) in currentStages" :key="stage.id">
            <button
              type="button"
              class="deal-stage-pill"
              :class="{ active: selectedStageId === stage.id }"
              @click="selectedStageId = stage.id"
            >
              <div class="stage-circle">
                <div
                  class="stage-dot"
                  :style="{ backgroundColor: stage.color || '#3b82f6' }"
                />
              </div>
              <span class="stage-text">{{ stage.name }}</span>
            </button>
            <iconify-icon
              v-if="index < currentStages.length - 1"
              icon="lucide:chevron-right"
              class="stage-arrow"
              aria-hidden="true"
            />
          </template>
        </div>
      </div>
      <!-- <div v-if="validationErrors.length > 0" class="validation-errors px-1 mb-3">-->
      <!--  <div class="alert alert-danger">-->
      <!--    <strong class="d-block mb-2">Please fix the following errors:</strong>-->
      <!--    <ul class="mb-0">-->
      <!--      <li v-for="(error, index) in validationErrors" :key="index">{{ error }}</li>-->
      <!--    </ul>-->
      <!--  </div>-->
      <!--</div>-->
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
               @search-projects="searchProjects" 
              :show-errors="showFieldErrors"     
              :field-errors="fieldErrors"           
              :selected-stage-id="selectedStageId"  
            />
      </div>

      <!-- Footer -->
       <div class="modal-footer-custom">
        <div class="d-flex align-items-center justify-content-center gap-3">
          <button class="btn-clear" @click="resetForm" :disabled="isSubmitting">Clear</button>
          <button class="btn-next-step" @click="validateAndSubmit" :disabled="isSubmitting">
            <span v-if="isSubmitting">
              <b-spinner small></b-spinner> Creating...
            </span>
            <span v-else class="d-inline-flex align-items-center gap-1">
              <template v-if="leadId">
                Convert Lead to Deal
                <iconify-icon icon="lucide:chevron-right" class="ms-1" aria-hidden="true" />
              </template>
              <template v-else>Save</template>
            </span>
          </button>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script setup>
import { ref, watch, computed, onMounted ,nextTick} from 'vue'
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
const validationErrors = ref([])
const showFieldErrors = ref(false)

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
const fieldErrors = ref({})

const dealTypeTabs = [
  { id: 'primary', name: 'Primary / Off Plan' },
  { id: 'secondary', name: 'Secondary' },
  { id: 'rental', name: 'Rental' }
]

function selectDealType(id) {
  dealType.value = id
}

// Get stages for current deal type
const currentStages = computed(() => {
  if (!stages.value || stages.value.length === 0) {
    return []
  }

  const filtered = stages.value.filter(stage => {
    const stageType = stage.stage_type || 'deal'
    const stageDealType = stage.deal_type || dealType.value
    return stageType === 'deal' && stageDealType === dealType.value
  })

  return filtered.sort((a, b) => (a.order || 0) - (b.order || 0))
})

// Watch for deal type changes
watch(dealType, async () => {
  selectedStageId.value = null
  validationErrors.value = []
  showFieldErrors.value = false
  await fetchStages()
  resetFormData()
})

// Watch for changes in the dealType prop from parent
watch(() => props.dealType, (newVal) => {
  if (newVal && newVal !== dealType.value) {
    dealType.value = newVal
  }
})

// Watch for modal visibility
watch(() => props.modelValue, async (val) => {
  show.value = val
  if (val) {
    validationErrors.value = []
    showFieldErrors.value = false
    await loadInitialData()
    if (props.leadId) {
      checkLeadConversionStatus()
    }
  }
})

watch(show, (val) => {
  emit('update:modelValue', val)
  if (!val) {
    resetForm()
    validationErrors.value = []
    showFieldErrors.value = false
  }
})

// عندما يظهر المودال
function onModalShown() {}

// Load all initial data
async function loadInitialData() {
  try {
    await Promise.all([
      fetchUsers(),
      fetchSources(),
      fetchStages(),
      fetchPropertyTypes(),
      fetchDevelopers()
    ])
  } catch (error) {
    console.error('Error loading initial data:', error)
  }
}

// API Calls
async function fetchUsers() {
  usersLoading.value = true
  try {
    const response = await api.get('/available-responsible-persons')
    const responseData = response.data
    if (responseData?.data) {
      users.value = Array.isArray(responseData.data) ? responseData.data : []
    } else if (Array.isArray(responseData)) {
      users.value = responseData
    } else {
      users.value = []
    }
    
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
    const response = await api.get('/stages', {
      params: {
        stage_type: 'deal',
        deal_type: dealType.value
      }
    })
    
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
    
    const filteredStages = stages.value.filter(stage => {
      const stageType = stage.stage_type || 'deal'
      const stageDealType = stage.deal_type || dealType.value
      return stageType === 'deal' && stageDealType === dealType.value
    })
    
    if (filteredStages.length > 0 && !selectedStageId.value) {
      selectedStageId.value = filteredStages[0].id
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
// في CreateDealModal.vue

// دالة المناطق - تجيب كل المناطق
async function searchAreas(search = '', parentId = null) {
  try {
    const params = {}
    
    // لو في parentId (للمناطق الفرعية) نضيفه، لكن مش شرط يكون في search
    if (parentId) {
      params.parent_id = parentId
    }
    
    // لو في search نضيفه، لو مفيش هات كل المناطق
    if (search) {
      params.search = search
    }
    
    const response = await api.get('/listings/areas', { params })
    
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
    
    // تحديث المتغير
    areas.value = areasData
    return areasData
    
  } catch (error) {
    console.error('Error fetching areas:', error)
    return []
  }
}
async function searchProjects(search = '') {
  try {
    const params = {}
    
    // لو في search نضيفه، لو مفيش هات كل المشاريع
    if (search) {
      params.search = search
    }
    
    const response = await api.get('/listings/projects', { params })
    
    let projectsData = []
    const responseData = response.data
    
    if (responseData?.data?.data) {
      projectsData = responseData.data.data
    } else if (responseData?.data && Array.isArray(responseData.data)) {
      projectsData = responseData.data
    } else if (Array.isArray(responseData)) {
      projectsData = responseData
    }
    
    projects.value = projectsData
    return projectsData
    
  } catch (error) {
    console.error('Error fetching projects:', error)
    return []
  }
}


// أيضاً أضف دالة searchSubCommunities المفقودة:
async function searchSubCommunities(search) {
  try {
    const response = await api.get('/listings/areas', {
      params: {
        search,
        type: 'sub_community' // إذا كان API يدعم تصفية حسب النوع
      }
    })
    
    const responseData = response.data
    let subCommunitiesData = []
    
    if (responseData?.data?.data) {
      subCommunitiesData = responseData.data.data
    } else if (responseData?.data && Array.isArray(responseData.data)) {
      subCommunitiesData = responseData.data
    } else if (Array.isArray(responseData)) {
      subCommunitiesData = responseData
    } else {
      subCommunitiesData = []
    }
    
    subCommunities.value = subCommunitiesData
    return subCommunitiesData
    
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
      subcommunity_id: lead.subcommunity_id,
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
      buyer_residency_status: lead.residency_status || '',
      buyer_city: lead.city || '',
      buyer_language: lead.language || '',
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

// Validation function
function validateForm() {
  const errors = []
  const fieldErrorsObj = {}
  
  // Check stage
  if (!selectedStageId.value) {
    errors.push('Please select a stage for the deal')
    fieldErrorsObj.stage_id = 'Stage is required'
  }
  
  // Required fields check
  if (!formData.value.source) {
    errors.push('Source is required')
    fieldErrorsObj.source = 'Source is required'
  }
  if (!formData.value.deal_name) {
    errors.push('Deal name is required')
    fieldErrorsObj.deal_name = 'Deal name is required'
  }
  if (!formData.value.unit_no) {
    errors.push('Unit number is required')
    fieldErrorsObj.unit_no = 'Unit number is required'
  }
  if (!formData.value.property_type_id) {
    errors.push('Property type is required')
    fieldErrorsObj.property_type_id = 'Property type is required'
  }
  if (!formData.value.subcommunity_id) {
    errors.push('Subcommunity is required')
    fieldErrorsObj.subcommunity_id = 'Subcommunity is required'
  }
  if (!formData.value.responsible_person_id) {
    errors.push('Responsible person is required')
    fieldErrorsObj.responsible_person_id = 'Responsible person is required'
  }
  
  // Validate based on deal type
  if (dealType.value === 'primary' || dealType.value === 'secondary') {
    if (!formData.value.buyer_first_name) {
      errors.push('Buyer first name is required')
      fieldErrorsObj.buyer_first_name = 'First name is required'
    }
    if (!formData.value.buyer_last_name) {
      errors.push('Buyer last name is required')
      fieldErrorsObj.buyer_last_name = 'Last name is required'
    }
    if (!formData.value.buyer_phone) {
      errors.push('Buyer phone is required')
      fieldErrorsObj.buyer_phone = 'Phone is required'
    }
    if (!formData.value.buyer_email) {
      errors.push('Buyer email is required')
      fieldErrorsObj.buyer_email = 'Email is required'
    }
    if (!formData.value.buyer_nationality) {
      errors.push('Buyer nationality is required')
      fieldErrorsObj.buyer_nationality = 'Nationality is required'
    }
    if (!formData.value.buyer_dob) {
      errors.push('Buyer date of birth is required')
      fieldErrorsObj.buyer_dob = 'Date of birth is required'
    }
    if (!formData.value.buyer_residency_status) {
      errors.push('Buyer residency status is required')
      fieldErrorsObj.buyer_residency_status = 'Residency status is required'
    }
    if (!formData.value.buyer_city) {
      errors.push('Buyer city is required')
      fieldErrorsObj.buyer_city = 'City is required'
    }
    if (!formData.value.buyer_language) {
      errors.push('Buyer language is required')
      fieldErrorsObj.buyer_language = 'Language is required'
    }
  }
  
  if (dealType.value === 'secondary') {
    if (!formData.value.seller_first_name) {
      errors.push('Seller first name is required')
      fieldErrorsObj.seller_first_name = 'First name is required'
    }
    if (!formData.value.seller_last_name) {
      errors.push('Seller last name is required')
      fieldErrorsObj.seller_last_name = 'Last name is required'
    }
    if (!formData.value.seller_phone) {
      errors.push('Seller phone is required')
      fieldErrorsObj.seller_phone = 'Phone is required'
    }
    if (!formData.value.seller_email) {
      errors.push('Seller email is required')
      fieldErrorsObj.seller_email = 'Email is required'
    }
    if (!formData.value.seller_nationality) {
      errors.push('Seller nationality is required')
      fieldErrorsObj.seller_nationality = 'Nationality is required'
    }
    if (!formData.value.seller_dob) {
      errors.push('Seller date of birth is required')
      fieldErrorsObj.seller_dob = 'Date of birth is required'
    }
    if (!formData.value.seller_residency_status) {
      errors.push('Seller residency status is required')
      fieldErrorsObj.seller_residency_status = 'Residency status is required'
    }
    if (!formData.value.seller_city) {
      errors.push('Seller city is required')
      fieldErrorsObj.seller_city = 'City is required'
    }
    if (!formData.value.seller_language) {
      errors.push('Seller language is required')
      fieldErrorsObj.seller_language = 'Language is required'
    }
  }
  
  if (dealType.value === 'rental') {
    // Tenant validation
    if (!formData.value.tenant_first_name) {
      errors.push('Tenant first name is required')
      fieldErrorsObj.tenant_first_name = 'First name is required'
    }
    if (!formData.value.tenant_last_name) {
      errors.push('Tenant last name is required')
      fieldErrorsObj.tenant_last_name = 'Last name is required'
    }
    if (!formData.value.tenant_phone) {
      errors.push('Tenant phone is required')
      fieldErrorsObj.tenant_phone = 'Phone is required'
    }
    if (!formData.value.tenant_email) {
      errors.push('Tenant email is required')
      fieldErrorsObj.tenant_email = 'Email is required'
    }
    if (!formData.value.tenant_nationality) {
      errors.push('Tenant nationality is required')
      fieldErrorsObj.tenant_nationality = 'Nationality is required'
    }
    if (!formData.value.tenant_residency_status) {
      errors.push('Tenant residency status is required')
      fieldErrorsObj.tenant_residency_status = 'Residency status is required'
    }
    if (!formData.value.tenant_city) {
      errors.push('Tenant city is required')
      fieldErrorsObj.tenant_city = 'City is required'
    }
    if (!formData.value.tenant_language) {
      errors.push('Tenant language is required')
      fieldErrorsObj.tenant_language = 'Language is required'
    }
    
    // Landlord validation
    if (!formData.value.landlord_first_name) {
      errors.push('Landlord first name is required')
      fieldErrorsObj.landlord_first_name = 'First name is required'
    }
    if (!formData.value.landlord_last_name) {
      errors.push('Landlord last name is required')
      fieldErrorsObj.landlord_last_name = 'Last name is required'
    }
    if (!formData.value.landlord_phone) {
      errors.push('Landlord phone is required')
      fieldErrorsObj.landlord_phone = 'Phone is required'
    }
    if (!formData.value.landlord_email) {
      errors.push('Landlord email is required')
      fieldErrorsObj.landlord_email = 'Email is required'
    }
    if (!formData.value.landlord_nationality) {
      errors.push('Landlord nationality is required')
      fieldErrorsObj.landlord_nationality = 'Nationality is required'
    }
    if (!formData.value.landlord_dob) {
      errors.push('Landlord date of birth is required')
      fieldErrorsObj.landlord_dob = 'Date of birth is required'
    }
    if (!formData.value.landlord_residency_status) {
      errors.push('Landlord residency status is required')
      fieldErrorsObj.landlord_residency_status = 'Residency status is required'
    }
    if (!formData.value.landlord_city) {
      errors.push('Landlord city is required')
      fieldErrorsObj.landlord_city = 'City is required'
    }
    if (!formData.value.landlord_language) {
      errors.push('Landlord language is required')
      fieldErrorsObj.landlord_language = 'Language is required'
    }
  }
  
  // Set field errors
  fieldErrors.value = fieldErrorsObj
  validationErrors.value = errors
  
  return errors
}


// Validate and submit
// Validate and submit
async function validateAndSubmit() {
  // Show field errors
  showFieldErrors.value = true
  
  // Validate form from DealForm
  if (dealFormRef.value && dealFormRef.value.validateForm) {
    const { errors, fieldErrorsObj } = dealFormRef.value.validateForm()
    validationErrors.value = errors
    fieldErrors.value = fieldErrorsObj
    
    if (errors.length > 0) {
      // Scroll to first error
      await nextTick()
      const firstErrorField = document.querySelector('.is-invalid')
      if (firstErrorField) {
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
      return
    }
  } else {
    // لو مفيش validateForm في DealForm، استخدمي validateForm المحلية
    const errors = validateForm()
    if (errors.length > 0) {
      return
    }
  }
  
  await submitForm()
}

// Submit form
async function submitForm() {
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
              submitData.append(`documents[${index}][document_type]`, doc.document_type)
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
      validationErrors.value = [errors]
      Swal.fire({ 
        icon: 'error', 
        title: 'Validation Error', 
        text: errors,
        confirmButtonText: 'OK'
      })
    } else {
      const backendDetail = error.response?.data?.error
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: backendDetail
          ? `${error.response?.data?.message || 'Failed to create deal'}: ${backendDetail}`
          : (error.response?.data?.message || 'Failed to create deal'),
        confirmButtonText: 'OK'
      })
    }
  } finally {
    isSubmitting.value = false
  }
}

function resetFormData() {
  formData.value = {
    // Common fields
    source: null,
    deal_name: '',
    unit_no: '',
    property_type_id: null,
    subcommunity_id: null,
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
    buyer_residency_status: '',
    buyer_city: '',
    buyer_country: '',
    buyer_language: '',
    buyer_documents: [],
    amount: null,
    
    // Seller fields (secondary)
    seller_first_name: '',
    seller_last_name: '',
    seller_dob: '',
    seller_phone: '',
    seller_email: '',
    seller_nationality: '',
    seller_residency_status: '',
    seller_city: '',
    seller_country: '',
    seller_language: '',
    seller_documents: [],
    
    // Tenant fields (rental)
    tenant_first_name: '',
    tenant_last_name: '',
    tenant_dob: '',
    tenant_phone: '',
    tenant_email: '',
    tenant_nationality: '',
    tenant_residency_status: '',
    tenant_city: '',
    tenant_country: '',
    tenant_language: '',
    tenant_documents: [],
    
    // Landlord fields (rental)
    landlord_first_name: '',
    landlord_last_name: '',
    landlord_dob: '',
    landlord_phone: '',
    landlord_email: '',
    landlord_nationality: '',
    landlord_residency_status: '',
    landlord_city: '',
    landlord_country: '',
    landlord_language: '',
    landlord_documents: []
  }
  
  if (dealFormRef.value && dealFormRef.value.clearAllDocuments) {
    dealFormRef.value.clearAllDocuments()
  }
}
function resetForm() {
  selectedStageId.value = null
  validationErrors.value = []
  fieldErrors.value = {}
  showFieldErrors.value = false
  resetFormData()
}

function close() {
  resetForm()
  show.value = false
}

onMounted(() => {
  resetFormData()
  loadInitialData()
})
</script>

<style>
#create-deal-modal .modal-content {
  border-radius: 12px !important;
  overflow: hidden !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12) !important;
}
</style>

<style scoped>
/* Modal wider – no tight width constraint */
:deep(.create-deal-modal-wrap .modal-dialog) {
  max-width: min(1200px, 95vw) !important;
  width: min(1200px, 95vw) !important;
  max-height: var(--deal-modal-max-h, 92vh) !important;
  margin: 2vh auto !important;
}
:deep(.create-deal-modal-body) {
  max-height: var(--deal-modal-max-h, 92vh);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.lead-info-banner {
  border-bottom: 1px solid #E2E8F0;
}

.alert-info {
  background-color: #EFF6FF;
  border-color: #BFDBFE;
  color: #1E40AF;
  border-radius: 8px;
}

@media (max-width: 768px) {
  .deal-type-dropdown {
    margin-top: 4px;
  }
}

.create-deal-modal-content {
  background: #fff;
  border-radius: 12px;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-size: 14px;
  display: flex;
  flex-direction: column;
  min-height: 0;
  flex: 1;
}
.create-deal-modal-padding {
  padding: 1rem 1.75rem 1rem 1.75rem;
}

.modal-header-deal {
  padding: 0.35rem 0 0.55rem;
  border-bottom: 1px solid #F4F4F4;
}

.header-left-with-padding {
  padding-left: 0.5rem;
}

.modal-title {
  font-weight: 600;
  font-size: 14px;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--deal-navy-deep, #01062c);
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

/* Deal type: clickable tabs (Primary / Secondary / Rental) */
.deals-type-select-wrap {
  display: inline-flex;
}

.deals-type-select {
  height: 30px;
  min-height: 30px;
  padding: 0 28px 0 10px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 12px;
  font-weight: 500;
  color: #334155;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  outline: none;
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  color: #64748B;
  font-size: 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, color 0.2s;
}

.close-btn:hover {
  background: #F1F5F9;
  color: #1E293B;
}

.deal-progress-label {
  display: none;
}

.deal-progress-wrapper {
  overflow-x: auto;
  scrollbar-width: none;
  padding: 0.45rem 0 0.5rem;
  border-bottom: 1px solid #f1f5f9;
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
  gap: 6px;
  height: 30px;
  min-height: 30px;
  padding: 0 10px;
  border-radius: 100px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
  white-space: nowrap;
  box-sizing: border-box;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.deal-stage-pill:hover {
  border-color: #cbd5e1;
}

.deal-stage-pill .stage-circle {
  width: 18px;
  height: 18px;
  min-width: 18px;
  border-radius: 50%;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.deal-stage-pill.active .stage-circle {
  background: rgba(255, 255, 255, 0.24);
  border-color: rgba(255, 255, 255, 0.65);
}

.deal-stage-pill .stage-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.deal-stage-pill.active {
  border-color: #299de9;
  background: linear-gradient(90deg, #2ea7ef 0%, #2d92dc 100%);
  box-shadow: 0 2px 6px rgba(41, 157, 233, 0.32);
}

.deal-stage-pill .stage-text {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.deal-stage-pill.active .stage-text {
  color: #fff !important;
  font-weight: 600;
}

.stage-arrow {
  font-size: 14px;
  color: #CBD5E1;
  flex-shrink: 0;
}

.form-scroll-area {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0;
}

.step-content {
  padding: 0.75rem 0;
}

.modal-footer-custom {
  border-top: 1px solid #F4F4F4;
  padding: 14px 20px;
}

.btn-clear {
  background: #F4F4F4;
  border: none;
  width: 110px;
  height: 40px;
  padding: 0;
  border-radius: 100px;
  font-size: 14px;
  font-weight: 500;
  color: #01062C;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.btn-next-step {
  background: #01062C;
  border: none;
  width: 110px;
  height: 40px;
  padding: 0;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s;
  line-height: 1;
}

.btn-next-step:hover:not(:disabled) {
  background: #0f172a;
}

/* Form inside: match image – section titles, labels, inputs same style */
:deep(.deal-form-container .section-title) {
  font-size: 13px !important;
  font-weight: 500;
  color: #01062C;
  margin-bottom: 8px;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

:deep(.deal-form-container .form-label-custom) {
  font-size: 12px !important;
  font-weight: 500;
  color: var(--deal-text-muted, #64748b);
  margin-bottom: 6px;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

:deep(.deal-form-container .custom-input) {
  height: 42px !important;
  min-height: 42px;
  font-size: 13px !important;
  border-radius: 8px;
  border: 1px solid #E2E8F0;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

:deep(.deal-form-container .custom-v-select .vs__dropdown-toggle) {
  height: 42px !important;
  min-height: 42px;
  border-radius: 8px;
  border: 1px solid #E2E8F0;
  font-size: 13px;
}

:deep(.deal-form-container .custom-v-select .vs__selected),
:deep(.deal-form-container .custom-v-select .vs__search) {
  font-size: 13px;
}

:deep(.deal-form-container .custom-v-select-inline .vs__dropdown-toggle) {
  height: 42px !important;
  min-height: 42px;
  font-size: 13px;
}

:deep(.deal-form-container .form-card) {
  padding: 0.9rem 1rem !important;
  border-radius: 8px;
}

:deep(.deal-form-container .form-section) {
  margin-top: 12px;
}

/* Document type tabs: active primary (Figma navy) */
:deep(.deal-form-container .doc-tab) {
  height: 32px;
  min-height: 32px;
  padding: 0 12px;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
}

:deep(.deal-form-container .document-upload-container .upload-zone) {
  border-radius: 8px !important;
  padding: 10px 14px !important;
}

:deep(.deal-form-container .document-upload-container .file-item) {
  border-radius: 8px !important;
}

:deep(.deal-form-container .responsible-person-card) {
  border-radius: 8px !important;
}

:deep(.deal-form-container .responsible-person-card .section-title) {
  font-size: 13px !important;
}

:deep(.deal-form-container .btn-change-person) {
  height: 34px !important;
  padding: 0 16px !important;
  font-size: 12px !important;
}

:deep(.deal-form-container .department-pill) {
  height: 34px !important;
  padding: 0 14px !important;
  font-size: 12px !important;
}

:deep(.deal-form-container .doc-tab.active) {
  background: #0f172a;
  color: #fff;
  border-color: #0f172a;
}
</style>
