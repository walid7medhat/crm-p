<template>
  <div class="deal-form-container">
    <!-- Source and Deal Name (Common for all) -->
    <section class="form-section">
      <h6 class="section-title mb-3">Source and Deal Name</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label-custom">Source <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.source" 
              :options="sources" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Source" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Deal Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.deal_name" placeholder="Enter Deal Name" class="custom-input" />
          </div>
        </div>
      </div>
    </section>

    <!-- Buyer Section (for Primary & Secondary) -->
    <section v-if="dealType === 'primary' || dealType === 'secondary'" class="form-section">
      <h6 class="section-title mb-3">Buyer Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_first_name" placeholder="Enter First Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_last_name" placeholder="Enter Last Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth</label>
            <b-form-input v-model="form.buyer_dob" type="date" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_phone" placeholder="Enter Phone" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_email" type="email" placeholder="Enter Email" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality</label>
            <v-select 
              v-model="form.buyer_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status</label>
            <v-select 
              v-model="form.buyer_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence</label>
            <b-form-input v-model="form.buyer_city" placeholder="Enter City" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.buyer_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language</label>
            <v-select 
              v-model="form.buyer_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Amount <span class="text-danger">*</span></label>
            <div class="input-group-custom">
              <b-form-input v-model="form.amount" type="number" placeholder="Enter Amount" class="custom-input" />
              <v-select 
                v-model="form.currency" 
                :options="currencyOptions" 
                :reduce="o => o.value" 
                label="text" 
                :clearable="false" 
                class="custom-v-select-inline" 
              />
            </div>
          </div>
        </div>

        <!-- Buyer Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Buyer Documents</label>
          <DocumentUpload 
            v-model="form.buyer_documents"
            category="buyer"
            :document-types="buyerDocTypes"
          />
        </div>
      </div>
    </section>

    <!-- Seller Section (for Secondary only) -->
    <section v-if="dealType === 'secondary'" class="form-section">
      <h6 class="section-title mb-3">Seller Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.seller_first_name" placeholder="Enter First Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.seller_last_name" placeholder="Enter Last Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth</label>
            <b-form-input v-model="form.seller_dob" type="date" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
            <b-form-input v-model="form.seller_phone" placeholder="Enter Phone" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email <span class="text-danger">*</span></label>
            <b-form-input v-model="form.seller_email" type="email" placeholder="Enter Email" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality</label>
            <v-select 
              v-model="form.seller_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status</label>
            <v-select 
              v-model="form.seller_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence</label>
            <b-form-input v-model="form.seller_city" placeholder="Enter City" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.seller_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language</label>
            <v-select 
              v-model="form.seller_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select" 
            />
          </div>
        </div>

        <!-- Seller Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Seller Documents</label>
          <DocumentUpload 
            v-model="form.seller_documents"
            category="seller"
            :document-types="sellerDocTypes"
          />
        </div>
      </div>
    </section>

    <!-- Tenant Section (for Rental) -->
    <section v-if="dealType === 'rental'" class="form-section">
      <h6 class="section-title mb-3">Tenant Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.tenant_first_name" placeholder="Enter First Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.tenant_last_name" placeholder="Enter Last Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth</label>
            <b-form-input v-model="form.tenant_dob" type="date" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone</label>
            <b-form-input v-model="form.tenant_phone" placeholder="Enter Phone" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email</label>
            <b-form-input v-model="form.tenant_email" type="email" placeholder="Enter Email" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality</label>
            <v-select 
              v-model="form.tenant_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status</label>
            <v-select 
              v-model="form.tenant_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence</label>
            <b-form-input v-model="form.tenant_city" placeholder="Enter City" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.tenant_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language</label>
            <v-select 
              v-model="form.tenant_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select" 
            />
          </div>
        </div>

        <!-- Tenant Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Tenant Documents</label>
          <DocumentUpload 
            v-model="form.tenant_documents"
            category="tenant"
            :document-types="tenantDocTypes"
          />
        </div>
      </div>
    </section>

    <!-- Landlord Section (for Rental) -->
    <section v-if="dealType === 'rental'" class="form-section">
      <h6 class="section-title mb-3">Landlord Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.landlord_first_name" placeholder="Enter First Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.landlord_last_name" placeholder="Enter Last Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth</label>
            <b-form-input v-model="form.landlord_dob" type="date" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone</label>
            <b-form-input v-model="form.landlord_phone" placeholder="Enter Phone" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email</label>
            <b-form-input v-model="form.landlord_email" type="email" placeholder="Enter Email" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality</label>
            <v-select 
              v-model="form.landlord_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status</label>
            <v-select 
              v-model="form.landlord_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence</label>
            <b-form-input v-model="form.landlord_city" placeholder="Enter City" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.landlord_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language</label>
            <v-select 
              v-model="form.landlord_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select" 
            />
          </div>
        </div>

        <!-- Landlord Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Landlord Documents</label>
          <DocumentUpload 
            v-model="form.landlord_documents"
            category="landlord"
            :document-types="landlordDocTypes"
          />
        </div>
      </div>
    </section>

    <!-- Property Details (Common for all) -->
    <section class="form-section">
      <h6 class="section-title mb-3">Property Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Unit No <span class="text-danger">*</span></label>
            <b-form-input v-model="form.unit_no" placeholder="Enter Unit No" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Type <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.property_type_id" 
              :options="propertyTypes" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Property Type" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Bedrooms</label>
            <v-select 
              v-model="form.bedrooms" 
              :options="bedroomOptions" 
              :reduce="o => o.value" 
              label="text" 
              placeholder="Select Bedroom" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Project Name</label>
            <v-select 
              v-model="form.project_id" 
              :options="projects" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Search Project..." 
              class="custom-v-select"
              :filterable="false"
              @search="searchProjects"
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Developer</label>
            <v-select 
              v-model="form.developer_id" 
              :options="developers" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Developer" 
              class="custom-v-select" 
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Area</label>
            <v-select 
              v-model="form.area_id" 
              :options="areas" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Search Area..." 
              class="custom-v-select"
              :filterable="false"
              @search="onSearchAreas"
            />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Unit Size</label>
            <b-form-input v-model="form.unit_size" placeholder="Enter Unit Size (sq. ft)" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Link</label>
            <b-form-input v-model="form.property_link" placeholder="Enter Property Link" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Reference</label>
            <b-form-input v-model="form.property_reference" placeholder="Enter Reference" class="custom-input" />
          </div>
        </div>

        <!-- Property Documents -->
        <!--<div class="mt-3">-->
        <!--  <label class="form-label-custom">Property Documents</label>-->
        <!--  <DocumentUpload -->
        <!--    v-model="form.property_documents"-->
        <!--    category="property"-->
        <!--    :document-types="propertyDocTypes"-->
        <!--  />-->
        <!--</div>-->
      </div>
    </section>

    <!-- Deal Financials (Common for all) -->
    <section class="form-section">
      <h6 class="section-title mb-3">Deal Financials</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Deal Total Amount</label>
            <div class="input-group-custom">
              <b-form-input v-model="form.deal_total_amount" type="number" placeholder="Enter Amount" class="custom-input" />
              <v-select 
                v-model="form.currency" 
                :options="currencyOptions" 
                :reduce="o => o.value" 
                label="text" 
                :clearable="false" 
                class="custom-v-select-inline" 
              />
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Deal Commission %</label>
            <b-form-input v-model="form.deal_commission" type="number" placeholder="Enter Commission %" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Agent Share %</label>
            <b-form-input v-model="form.agent_share" type="number" placeholder="Enter Agent Share %" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Company Share %</label>
            <b-form-input v-model="form.company_share" type="number" placeholder="Enter Company Share %" class="custom-input" />
          </div>
        </div>
      </div>
    </section>

    <!-- Responsible Person (using ResponsiblePersonSelector) -->
    <div class="col-12">
      <ResponsiblePersonSelector 
        v-model="form.responsible_person_id" 
        :users="users" 
        :responsible-person="responsiblePerson" 
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { BFormInput, BFormCheckbox } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue' 
import api from '@/plugins/axios'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  dealType: { type: String, default: 'primary' },
  users: { type: Array, default: () => [] },
  sources: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  areas: { type: Array, default: () => [] },
  usersLoading: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'search-areas'])

const form = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

// حساب responsiblePerson من users
const responsiblePerson = computed(() => {
  const id = form.value?.responsible_person_id
  if (!id || !props.users.length) return null
  return props.users.find(u => u.id === id) || null
})

// Projects state
const projects = ref([])

const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)
const propertyDocUploadRef = ref(null)

// دالة لمسح كل الملفات
function clearAllDocuments() {
  console.log('Clearing all documents...')
  if (buyerDocUploadRef.value) buyerDocUploadRef.value.clearAllFiles()
  if (sellerDocUploadRef.value) sellerDocUploadRef.value.clearAllFiles()
  if (tenantDocUploadRef.value) tenantDocUploadRef.value.clearAllFiles()
  if (landlordDocUploadRef.value) landlordDocUploadRef.value.clearAllFiles()
  if (propertyDocUploadRef.value) propertyDocUploadRef.value.clearAllFiles()
}

// expose الدالة للـ parent
defineExpose({
  clearAllDocuments
})
// Document type options
const buyerDocTypes = [
  { id: 'national_id', name: 'National ID' },
  { id: 'passport', name: 'Passport' },
  { id: 'visa', name: 'Visa' },
  { id: 'kyc', name: 'KYC' },
  { id: 'payment_proof', name: 'Payment Proof' }
]

const sellerDocTypes = [
  { id: 'national_id', name: 'National ID' },
  { id: 'passport', name: 'Passport' },
  { id: 'title_deed', name: 'Title Deed' }
]

const tenantDocTypes = [
  { id: 'national_id', name: 'National ID' },
  { id: 'passport', name: 'Passport' },
  { id: 'visa', name: 'Visa' },
  { id: 'tenancy_contract', name: 'Tenancy Contract' },
  { id: 'ejari', name: 'Ejari' }
]

const landlordDocTypes = [
  { id: 'national_id', name: 'National ID' },
  { id: 'passport', name: 'Passport' },
  { id: 'title_deed', name: 'Title Deed' }
]

const propertyDocTypes = [
  { id: 'title_deed', name: 'Title Deed' },
  { id: 'spa', name: 'SPA' },
  { id: 'noc', name: 'NOC' },
  { id: 'floor_plan', name: 'Floor Plan' }
]

// Options for selects
const nationalityOptions = [
  { value: 'emirati', text: 'Emirati' },
  { value: 'saudi', text: 'Saudi' },
  { value: 'egyptian', text: 'Egyptian' },
  { value: 'jordanian', text: 'Jordanian' },
  { value: 'lebanese', text: 'Lebanese' },
  { value: 'syrian', text: 'Syrian' },
  { value: 'palestinian', text: 'Palestinian' },
  { value: 'iraqi', text: 'Iraqi' },
  { value: 'yemeni', text: 'Yemeni' },
  { value: 'omani', text: 'Omani' },
  { value: 'qatari', text: 'Qatari' },
  { value: 'kuwaiti', text: 'Kuwaiti' },
  { value: 'bahraini', text: 'Bahraini' },
  { value: 'british', text: 'British' },
  { value: 'american', text: 'American' },
  { value: 'canadian', text: 'Canadian' },
  { value: 'australian', text: 'Australian' },
  { value: 'indian', text: 'Indian' },
  { value: 'pakistani', text: 'Pakistani' },
  { value: 'other', text: 'Other' }
]

const residencyOptions = [
  { value: 'citizen', text: 'Citizen' },
  { value: 'resident', text: 'Resident' },
  { value: 'investor', text: 'Investor' },
  { value: 'tourist', text: 'Tourist' },
  { value: 'student', text: 'Student' },
  { value: 'other', text: 'Other' }
]

const countryOptions = [
  { value: 'AE', text: 'United Arab Emirates' },
  { value: 'SA', text: 'Saudi Arabia' },
  { value: 'EG', text: 'Egypt' },
  { value: 'JO', text: 'Jordan' },
  { value: 'LB', text: 'Lebanon' },
  { value: 'SY', text: 'Syria' },
  { value: 'PS', text: 'Palestine' },
  { value: 'IQ', text: 'Iraq' },
  { value: 'YE', text: 'Yemen' },
  { value: 'OM', text: 'Oman' },
  { value: 'QA', text: 'Qatar' },
  { value: 'KW', text: 'Kuwait' },
  { value: 'BH', text: 'Bahrain' },
  { value: 'GB', text: 'United Kingdom' },
  { value: 'US', text: 'United States' },
  { value: 'CA', text: 'Canada' },
  { value: 'AU', text: 'Australia' },
  { value: 'IN', text: 'India' },
  { value: 'PK', text: 'Pakistan' },
  { value: 'other', text: 'Other' }
]

const languageOptions = [
  { value: 'ar', text: 'Arabic' },
  { value: 'en', text: 'English' },
  { value: 'fr', text: 'French' },
  { value: 'es', text: 'Spanish' },
  { value: 'de', text: 'German' },
  { value: 'it', text: 'Italian' },
  { value: 'ru', text: 'Russian' },
  { value: 'zh', text: 'Chinese' },
  { value: 'hi', text: 'Hindi' },
  { value: 'ur', text: 'Urdu' },
  { value: 'other', text: 'Other' }
]

const currencyOptions = [
  { value: 'AED', text: 'AED - UAE Dirham' },
  { value: 'USD', text: 'USD - US Dollar' },
  { value: 'EUR', text: 'EUR - Euro' },
  { value: 'GBP', text: 'GBP - British Pound' },
  { value: 'SAR', text: 'SAR - Saudi Riyal' },
  { value: 'QAR', text: 'QAR - Qatari Riyal' },
  { value: 'KWD', text: 'KWD - Kuwaiti Dinar' },
  { value: 'BHD', text: 'BHD - Bahraini Dinar' },
  { value: 'OMR', text: 'OMR - Omani Rial' },
  { value: 'EGP', text: 'EGP - Egyptian Pound' }
]

const bedroomOptions = [
  { value: 'studio', text: 'Studio' },
  { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' },
  { value: '5', text: '5 Bedrooms' },
  { value: '5+', text: '5+ Bedrooms' }
]

// Search projects
async function searchProjects(search) {
  if (!search) return
  try {
    const response = await api.get('/listings/projects', { params: { search } })
    projects.value = response.data?.data ?? response.data ?? []
  } catch (e) {
    console.error('Error searching projects:', e)
  }
}

// Search areas
function onSearchAreas(search) {
  emit('search-areas', search)
}
</script>
<style scoped>
.section-title { font-size: 14px !important; font-weight: 600; color: #01062C; font-family: 'Montserrat'; }
.form-card { background: #fff; border: 1px solid #F3F3F3; box-shadow: 1px 1px 5px rgba(0,0,0,0.03); }
.radius-12 { border-radius: 12px; }
.form-label-custom { font-size: 13px; font-weight: 500; color: #000; margin-bottom: 6px; display: block; font-family: 'Montserrat'; }
.custom-input { height: 42px !important; border-radius: 10px !important; border: 1px solid #E2E8F0 !important; font-size: 13px !important; font-family: 'Montserrat'; }
.input-group-custom { display: flex; border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; }
.input-group-custom .custom-input { border: none !important; flex: 1; border-radius: 10px 0 0 10px !important; }
:deep(.custom-v-select-inline) { min-width: 120px; }
:deep(.custom-v-select-inline .vs__dropdown-toggle) { height: 42px; border: none; border-left: 1px solid #E2E8F0; border-radius: 0 10px 10px 0; }
.doc-tabs { gap: 8px; }
.doc-tab { padding: 6px 14px; border-radius: 100px; border: 1px solid #E2E8F0; background: #fff; font-size: 12px; color: #64748B; cursor: pointer; }
.doc-tab.active { background: #0F172A; color: #fff; border-color: #0F172A; }
.upload-zone { border-style: dashed !important; border-color: #E2E8F0 !important; background: #F8FAFC; }
.upload-icon { font-size: 32px; color: #94A3B8; }
.upload-text { font-size: 13px; color: #475569; margin: 0; }
.tag-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #E2E8F0; border-radius: 100px; font-size: 12px; }
.tag-remove { cursor: pointer; font-size: 14px; }
.btn-tag-search { background: transparent; border: none; color: #3B82F6; font-size: 13px; cursor: pointer; }
.add-custom-field-link { font-size: 13px; color: #3B82F6; text-decoration: underline; }
.form-section{
    margin-top:10px;
}
</style>