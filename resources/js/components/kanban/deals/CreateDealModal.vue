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
          <div class="deals-type-select-wrap custom-dropdown">
            <div class="selected" @click="toggleDropdown"   :class="{ open: dropdownOpen }">
              {{ selectedDealTypeName }}
                  <iconify-icon
                    icon="lucide:chevrons-up-down"
                    class="dropdown-icon"
                  ></iconify-icon>
            </div>

            <div v-if="dropdownOpen" class="dropdown">
              <div
                v-for="tab in dealTypeTabs"
                :key="tab.id"
                class="dropdown-item"
                :class="{ active: dealType === tab.id }"
                @click="selectDeal(tab)"
              >
                {{ tab.name }}
                <span v-if="dealType === tab.id" class="check">✔</span>
              </div>
            </div>
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
              :class="{ active: selectedStageIndex === index }"
                :style="{
                            backgroundColor: index <= selectedStageIndex ? stage.color : 'transparent',
                            borderColor: index <= selectedStageIndex ? stage.color : '#E2E8F0',
                            zIndex: currentStages.length - index,
                        }"
              @click="selectedStageId = stage.id"
            >
            
              <span class="stage-text">{{ stage.name }}</span>
            </button>
       
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
              @search-subcommunities="searchSubCommunities" 
              :show-errors="showFieldErrors"     
              :field-errors="fieldErrors"           
              :selected-stage-id="selectedStageId"  
              :selected-stage-name="selectedStage?.name || ''"
               @update:hasListingId="handleHasListingIdUpdate"
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
import { normalizeLanguageSelection, hasLanguageSelection } from '@/composables/useLanguageMultiSelect'
import { isNonEmptyPhoneValid } from '@/utils/phone'

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
// const projects = ref([])

// Form data
const formData = ref({})
const fieldErrors = ref({})

const dealTypeTabs = [
  { id: 'primary', name: 'Primary' },
  { id: 'secondary', name: 'Secondary' },
  { id: 'rental', name: 'Rental' }
]
const hasListingId = ref(false)

// دالة لاستقبال التحديث من DealForm
const handleHasListingIdUpdate = (value) => {
  hasListingId.value = value
  console.log('hasListingId updated:', value)
}
function selectDealType(id) {
  dealType.value = id
}
const dropdownOpen = ref(false)

const toggleDropdown = () => {
  dropdownOpen.value = !dropdownOpen.value
}

const selectDeal = (tab) => {
  dealType.value = tab.id
  selectDealType(tab.id)
  dropdownOpen.value = false
}

const selectedDealTypeName = computed(() => {
  return dealTypeTabs.find(t => t.id === dealType.value)?.name || ''
})
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
watch(() => hasListingId.value, (hasId) => {
  if (hasId && dealType.value === 'secondary') {
    // Clear seller data in formData
    formData.value.seller_first_name = ''
    formData.value.seller_last_name = ''
    formData.value.seller_dob = ''
    formData.value.seller_phone = ''
    formData.value.seller_email = ''
    formData.value.seller_nationality = ''
    formData.value.seller_residency_status = ''
    formData.value.seller_city = ''
    formData.value.seller_country = ''
    formData.value.seller_language = ''
    formData.value.seller_documents = []
  } else if (hasId && dealType.value === 'rental') {
    // Clear landlord data in formData
    formData.value.landlord_first_name = ''
    formData.value.landlord_last_name = ''
    formData.value.landlord_dob = ''
    formData.value.landlord_phone = ''
    formData.value.landlord_email = ''
    formData.value.landlord_nationality = ''
    formData.value.landlord_residency_status = ''
    formData.value.landlord_city = ''
    formData.value.landlord_country = ''
    formData.value.landlord_language = ''
    formData.value.landlord_documents = []
  }
})
const selectedStageIndex = computed(() => {
  return currentStages.value.findIndex(
    stage => stage.id === selectedStageId.value
  )
})
const selectedStage = computed(() => {
  return currentStages.value.find((stage) => stage.id === selectedStageId.value) || null
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
// async function searchProjects(search = '') {
//   try {
//     const params = {}
    
//     // لو في search نضيفه، لو مفيش هات كل المشاريع
//     if (search) {
//       params.search = search
//     }
    
//     const response = await api.get('/listings/projects', { params })
    
//     let projectsData = []
//     const responseData = response.data
    
//     if (responseData?.data?.data) {
//       projectsData = responseData.data.data
//     } else if (responseData?.data && Array.isArray(responseData.data)) {
//       projectsData = responseData.data
//     } else if (Array.isArray(responseData)) {
//       projectsData = responseData
//     }
    
//     projects.value = projectsData
//     return projectsData
    
//   } catch (error) {
//     console.error('Error fetching projects:', error)
//     return []
//   }
// }


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
      // subcommunity_id: lead.subcommunity_id,
      bedrooms: lead.bedrooms,
      unit_size: lead.unit_size || '',
      // project_id: lead.project_id,
      area_id: lead.area_id,
      developer_id: lead.developer_id,
      developer_phone: lead.developer_phone,
      developer_name: lead.developer_name,
      responsible_person_id: lead.responsible_person_id,
      
      // Buyer/Tenant data based on lead
      buyer_first_name: lead.first_name || '',
      buyer_last_name: lead.last_name || '',
      buyer_dob: lead.date_of_birth || '',
      buyer_phone: lead.phone || lead.mobile || lead.work_phone,
      buyer_email: lead.email || '',
      buyer_nationality: lead.nationality || '',
      buyer_residency_status: lead.residency_status || '',
      buyer_city: lead.city || '',
      buyer_language: normalizeLanguageSelection(lead.language || ''),
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
    
    // ✅ Buyer city - مطلوب فقط للمقيمين (resident)
    if (formData.value.buyer_residency_status === 'resident') {
      if (!formData.value.buyer_city) {
        errors.push('Buyer city is required')
        fieldErrorsObj.buyer_city = 'City is required'
      }
    }
    
    // ✅ Buyer country - مطلوب فقط لغير المقيمين
    if (formData.value.buyer_residency_status !== 'resident' && formData.value.buyer_residency_status) {
      if (!formData.value.buyer_country) {
        errors.push('Buyer country is required')
        fieldErrorsObj.buyer_country = 'Country is required'
      }
    }
    
    if (!hasLanguageSelection(formData.value.buyer_language)) {
      errors.push('Buyer language is required')
      fieldErrorsObj.buyer_language = 'Language is required'
    }
    if (formData.value.buyer_phone && !isNonEmptyPhoneValid(formData.value.buyer_phone)) {
      errors.push('Buyer phone is invalid')
      fieldErrorsObj.buyer_phone = 'Invalid phone number'
    }
  }
  
  if (dealType.value === 'secondary') {
    if (!hasListingId.value) {
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
      
      // ✅ Seller city - مطلوب فقط للمقيمين
      if (formData.value.seller_residency_status === 'resident') {
        if (!formData.value.seller_city) {
          errors.push('Seller city is required')
          fieldErrorsObj.seller_city = 'City is required'
        }
      }
      
      // ✅ Seller country - مطلوب فقط لغير المقيمين
      if (formData.value.seller_residency_status !== 'resident' && formData.value.seller_residency_status) {
        if (!formData.value.seller_country) {
          errors.push('Seller country is required')
          fieldErrorsObj.seller_country = 'Country is required'
        }
      }
      
      if (!formData.value.seller_language) {
        errors.push('Seller language is required')
        fieldErrorsObj.seller_language = 'Language is required'
      }
      if (formData.value.seller_phone && !isNonEmptyPhoneValid(formData.value.seller_phone)) {
        errors.push('Seller phone is invalid')
        fieldErrorsObj.seller_phone = 'Invalid phone number'
      }
    }
  }
  
  if (dealType.value === 'rental') {
    if (!hasListingId.value) {
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
      
      // ✅ Tenant city - مطلوب فقط للمقيمين
      if (formData.value.tenant_residency_status === 'resident') {
        if (!formData.value.tenant_city) {
          errors.push('Tenant city is required')
          fieldErrorsObj.tenant_city = 'City is required'
        }
      }
      
      // ✅ Tenant country - مطلوب فقط لغير المقيمين
      if (formData.value.tenant_residency_status !== 'resident' && formData.value.tenant_residency_status) {
        if (!formData.value.tenant_country) {
          errors.push('Tenant country is required')
          fieldErrorsObj.tenant_country = 'Country is required'
        }
      }
      
      if (!formData.value.tenant_language) {
        errors.push('Tenant language is required')
        fieldErrorsObj.tenant_language = 'Language is required'
      }
      if (formData.value.tenant_phone && !isNonEmptyPhoneValid(formData.value.tenant_phone)) {
        errors.push('Tenant phone is invalid')
        fieldErrorsObj.tenant_phone = 'Invalid phone number'
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
      
      // ✅ Landlord city - مطلوب فقط للمقيمين
      if (formData.value.landlord_residency_status === 'resident') {
        if (!formData.value.landlord_city) {
          errors.push('Landlord city is required')
          fieldErrorsObj.landlord_city = 'City is required'
        }
      }
      
      // ✅ Landlord country - مطلوب فقط لغير المقيمين
      if (formData.value.landlord_residency_status !== 'resident' && formData.value.landlord_residency_status) {
        if (!formData.value.landlord_country) {
          errors.push('Landlord country is required')
          fieldErrorsObj.landlord_country = 'Country is required'
        }
      }
      
      if (!formData.value.landlord_language) {
        errors.push('Landlord language is required')
        fieldErrorsObj.landlord_language = 'Language is required'
      }
      if (formData.value.landlord_phone && !isNonEmptyPhoneValid(formData.value.landlord_phone)) {
        errors.push('Landlord phone is invalid')
        fieldErrorsObj.landlord_phone = 'Invalid phone number'
      }
    }
  }

  if (formData.value.developer_phone && !isNonEmptyPhoneValid(formData.value.developer_phone)) {
    errors.push('Developer phone is invalid')
    fieldErrorsObj.developer_phone = 'Invalid phone number'
  }
  
  // Set field errors
  fieldErrors.value = fieldErrorsObj
  validationErrors.value = errors
  
  return errors
}


// Validate and submit
async function validateAndSubmit() {
 console.log('========== START VALIDATION ==========')
  console.log('formData.value BEFORE validation:', JSON.stringify(formData.value, null, 2))
  // Show field errors
  showFieldErrors.value = true
  
  // Validate form from DealForm
  if (dealFormRef.value && dealFormRef.value.validateForm) {
    const { errors, fieldErrorsObj } = dealFormRef.value.validateForm()
    validationErrors.value = errors
    fieldErrors.value = fieldErrorsObj  
    
    if (errors.length > 0) {
      await nextTick()
      const firstErrorField = document.querySelector('.is-invalid')
      if (firstErrorField) {
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
      return
    }
  } else {
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
    const submitData = new FormData()
    
    // Add basic fields
    submitData.append('deal_type', dealType.value)
    submitData.append('stage_id', selectedStageId.value)
    
    if (props.leadId) {
      submitData.append('lead_id', props.leadId)
    }
    
    // Add form data fields
    Object.keys(formData.value).forEach(key => {
      const value = formData.value[key]
      if (value === null || value === undefined || value === '') return
      
      // Skip seller fields when hasListingId
      if (dealType.value === 'secondary' && hasListingId.value) {
        if (key.startsWith('seller_')) return
      }
      
      // Skip landlord fields when hasListingId
      if (dealType.value === 'rental' && hasListingId.value) {
        if (key.startsWith('landlord_')) return
      }
      
      // Handle documents
      if (key.includes('documents') && Array.isArray(value)) {
        value.forEach((doc, index) => {
          if (doc.file) {
            submitData.append(`documents[${index}]`, doc.file)
            submitData.append(`documents[${index}][category]`, doc.category)
            submitData.append(`documents[${index}][document_type]`, doc.document_type)
            if (doc.deal_party_id) {
              submitData.append(`documents[${index}][deal_party_id]`, doc.deal_party_id)
            }
          }
        })
      } else if (!key.includes('documents')) {
        submitData.append(key, value)
      }
    })
    
    // ========== ✅ MULTI PROPERTIES ==========
    if (dealFormRef.value && dealFormRef.value.getPropertiesData) {
      const propertiesData = dealFormRef.value.getPropertiesData()
      if (propertiesData && propertiesData.length > 0) {
        propertiesData.forEach((property, idx) => {
          Object.keys(property).forEach(key => {
            const value = property[key]
            if (value !== null && value !== undefined && value !== '') {
              // Handle file arrays
              if ((key === 'payment_proof' || key === 'spa_document') && Array.isArray(value)) {

                value.forEach((file, fileIndex) => {
                  if (file instanceof File) {
                    submitData.append(
                      `properties[${idx}][${key}][${fileIndex}]`,
                      file
                    )
                  }
                })

              } else {
                submitData.append(`properties[${idx}][${key}]`, value)
              }
            }
          })
        })
      }
    }
    
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
  formData.value = {
    // Common fields
    source: null,
    deal_name: '',
    unit_no: '',
    property_type_id: null,
    subcommunity_id: null,
    bedrooms: null,
    unit_size: '',
    // project_id: null,
    listing_id: null,
    developer_id: null,
    developer_name: null,
    developer_phone: '',
    area_id: null,
    property_link: '',
    property_reference: '',
    deal_commission: null,
    agent_share: null,
    company_share: null,
    responsible_person_id: null,
     deal_total_amount: null,
    // Buyer fields
    buyer_first_name: '',
    buyer_last_name: '',
    buyer_dob: '',
    buyer_phone: '',
    buyer_email: '',
    buyer_nationality: '',
    buyer_residency_status: '',
    buyer_city: '',
    buyer_country: 'United Arab Emirates',
    buyer_language: [],
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
    tenant_country: 'United Arab Emirates',
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
    landlord_country: 'United Arab Emirates',
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
  /*overflow: hidden !important;*/
  border: 1px solid #e5e7eb !important;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08) !important;
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
  /*overflow: hidden;*/
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
  align-items: center;
}

.close-btn {
  top: 8px;
    right: -61px;
    width: 83px;
    height: 49px;
    position: absolute;
    color: #fff;
    font-size: 18px;
    line-height: 1;
    box-shadow: #0f172a33 0 8px 16px;
    z-index: -1;
    display: flex;
    justify-content: center;
    align-items: center;
    border-width: 1px;
    border-style: solid;
    border-color: #4fa5f7;
    border-image: initial;
    border-radius: 999px;
    background: linear-gradient(90deg, #2f88ef, #5db8ff);
    padding: 0;
    transition: filter .2s;
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

.deal-progress-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: nowrap;
  min-height: 30px;
}
.deal-stage-pill {
    display: flex;
    align-items: center;
    min-width: 140px;
    max-width: 170px;
    padding: 2px 10px;
    /*border-radius: 30px;*/
    cursor: pointer;
    transition: background-color 0.1s ease, border-color 0.1s ease, color 0.1s ease;
    position: relative;
    overflow: hidden;
    /*border: 1px solid transparent;*/
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.55);
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    clip-path: polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%);
    height: 25px;
}

.deal-stage-pill:not(.active) {
    color: #544e4e;
}

.stage-text {
    font-family: Montserrat;
    font-weight: 400;
    font-size: 13px;
    color: #01062C;
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.deal-stage-pill.active .stage-text {
    color: #01062C;
    font-weight: 400;
}

@media (max-width: 768px) {
 .deal-stage-pill {
        min-width: 104px;
        max-width: 138px;
        padding: 1px 8px;
    }

    .stage-text {
        font-size: 11px;
        font-weight: 500;
    }
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
  display: flex !important;
  align-items: stretch !important;
}

:deep(.deal-form-container .custom-v-select .vs__selected),
:deep(.deal-form-container .custom-v-select .vs__search) {
  font-size: 13px;
}

/* Keep all placeholders smaller and consistent */
:deep(.deal-form-container .custom-input::placeholder),
:deep(.deal-form-container input::placeholder),
:deep(.deal-form-container textarea::placeholder),
:deep(.deal-form-container .form-control::placeholder),
:deep(.deal-form-container .custom-v-select .vs__search::placeholder),
:deep(.deal-form-container .custom-v-select-inline .vs__search::placeholder) {
  font-size: 11px !important;
  color: #9ca3af !important;
  opacity: 1;
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




.custom-dropdown {
  position: relative;
  width: 180px;
}

.custom-dropdown .selected {
  height: 36px;
  padding: 0 14px;
  border-radius: 20px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  cursor: pointer;
}
.dropdown-icon {
  font-size: 16px;
  color: #9ca3af;
  margin-left: 8px;
  transition: transform 0.2s ease, color 0.2s ease;
}

.selected.open .dropdown-icon {
  transform: rotate(180deg);
  color: #111827;
}


.custom-dropdown .dropdown {
  position: absolute;
  top: 110%;
  width: 100%;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  padding: 6px;
  z-index: 999;
}

.custom-dropdown .dropdown-item {
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 14px;
  display: flex;
  justify-content: space-between;
  cursor: pointer;
}

.custom-dropdown .dropdown-item:hover {
  background: #f3f4f6;
}

.custom-dropdown .dropdown-item.active {
  background: #f1f5f9;
  font-weight: 500;
}

/* check */
.custom-dropdown .check {
  color: orange;
  font-size: 14px;
}
</style>
