<template>
  <div class="row g-4 p-4">
    <!-- Buyer Details -->
    <div class="col-12">
      <h6 class="section-title mb-3">Buyer Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Buyer First Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_first_name" placeholder="Enter First Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Last Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_last_name" placeholder="Enter Last Name" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Date Of Birth <span class="text-danger">*</span></label>
            <AdvancedDatePicker v-model="form.buyer_dob" date-only placeholder="Select date" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Phone Number <span class="text-danger">*</span></label>
            <CrmPhoneInput v-model="form.buyer_phone" placeholder="Enter Phone Number" :auto-format="false" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Email <span class="text-danger">*</span></label>
            <b-form-input v-model="form.buyer_email" type="email" placeholder="Enter Your Email" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Nationality <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_nationality" :options="nationalityOptions" :reduce="o => o.value" label="text" placeholder="Select Nationality" class="custom-v-select" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Residency Status <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_residency_status" :options="residencyOptions" :reduce="o => o.value" label="text" placeholder="Select Status" class="custom-v-select" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer City Of Residence</label>
            <b-form-input v-model="form.buyer_city" placeholder="Enter Buyer City" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Country Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_country" :options="countryOptions" :reduce="o => o.value" label="text" placeholder="Not Selected" class="custom-v-select" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Language <span class="text-danger">*</span></label>
            <v-select
              :model-value="normalizeLanguageSelection(form.buyer_language)"
              @update:modelValue="updateBuyerLanguage"
              :options="languageOptions"
              :reduce="o => o.value"
              label="text"
              placeholder="Select Language(s)"
              class="custom-v-select buyer-language-select"
              :multiple="true"
              :searchable="true"
              :close-on-select="false"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Buyer Documents -->
    <div class="col-12">
      <h6 class="section-title mb-3">Upload Buyer Documents</h6>
      <div class="form-card p-3 radius-12">
        <div class="doc-tabs d-flex gap-2 mb-3 flex-wrap">
          <button v-for="t in buyerDocTabs" :key="t.id" class="doc-tab" :class="{ active: form.buyer_doc_type === t.id }" @click="setDocType('buyer_doc_type', t.id)">{{ t.name }}</button>
        </div>
        <div class="upload-zone border rounded p-4 text-center">
          <iconify-icon icon="lucide:cloud-upload" class="upload-icon"></iconify-icon>
          <p class="upload-text mb-2">Drag and drop your files</p>
          <p class="upload-hint text-muted small mb-2">JPEG, PNG and PDF formats, up to 50MB</p>
          <button type="button" class="btn btn-outline-secondary btn-sm">Select File</button>
        </div>
      </div>
    </div>

    <!-- Property Details -->
    <div class="col-12">
      <h6 class="section-title mb-3">Property Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Unit No <span class="text-danger">*</span></label>
            <b-form-input v-model="form.unit_no" placeholder="Enter Unit No" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Type <span class="text-danger">*</span></label>
            <v-select v-model="form.property_type" :options="propertyTypeOptions" :reduce="o => o.value" label="text" placeholder="Not Selected" class="custom-v-select" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Bedrooms <span class="text-danger">*</span></label>
            <v-select v-model="form.bedrooms" :options="bedroomOptions" :reduce="o => o.value" label="text" placeholder="Select Bedroom" class="custom-v-select" />
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Project Name <span class="text-danger">*</span></label>
            <div class="tag-input-wrap d-flex flex-wrap align-items-center gap-2 p-2 border rounded">
              <span v-for="tag in (form.project_names || [])" :key="tag" class="tag-pill">{{ tag }} <iconify-icon icon="lucide:x" class="tag-remove" @click="removeTag('project_names', tag)"></iconify-icon></span>
              <button type="button" class="btn-tag-search">+ Search</button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Developer</label>
            <div class="tag-input-wrap d-flex flex-wrap align-items-center gap-2 p-2 border rounded">
              <span v-for="tag in (form.developers || [])" :key="tag" class="tag-pill">{{ tag }} <iconify-icon icon="lucide:x" class="tag-remove" @click="removeTag('developers', tag)"></iconify-icon></span>
              <button type="button" class="btn-tag-search">+ Search</button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Area</label>
            <div class="tag-input-wrap d-flex flex-wrap align-items-center gap-2 p-2 border rounded">
              <span v-for="tag in (form.areas || [])" :key="tag" class="tag-pill">{{ tag }} <iconify-icon icon="lucide:x" class="tag-remove" @click="removeTag('areas', tag)"></iconify-icon></span>
              <button type="button" class="btn-tag-search">+ Search</button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Sub Community <span class="text-danger">*</span></label>
            <div class="tag-input-wrap d-flex flex-wrap align-items-center gap-2 p-2 border rounded">
              <span v-for="tag in (form.sub_communities || [])" :key="tag" class="tag-pill">{{ tag }} <iconify-icon icon="lucide:x" class="tag-remove" @click="removeTag('sub_communities', tag)"></iconify-icon></span>
              <button type="button" class="btn-tag-search">+ Search</button>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Unit Size</label>
            <b-form-input v-model="form.unit_size" placeholder="Enter Unit Size" class="custom-input" />
          </div>
        </div>
      </div>
    </div>

    <!-- Deal Financials -->
    <div class="col-12">
      <h6 class="section-title mb-3">Deal Financials</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Agent Share % <span class="text-danger">*</span></label>
            <b-form-input v-model="form.agent_share" type="number" placeholder="Enter Agent Share %" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Company Share % <span class="text-danger">*</span></label>
            <b-form-input v-model="form.company_share" type="number" placeholder="Enter Company Share %" class="custom-input" />
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Deal Total Commission % <span class="text-danger">*</span></label>
            <b-form-input v-model="form.deal_commission" type="number" placeholder="Enter Commission %" class="custom-input" />
          </div>
        </div>
      </div>
    </div>

    <!-- Responsible Person -->
    <div class="col-12">
      <ResponsiblePersonSelector
        v-model="form.responsible_person_id"
        :users="users"
        :responsible-person="responsiblePerson"
      />
    </div>
    <div class="col-12 mt-2">
      <a href="#" class="add-custom-field-link" @click.prevent>Add Custom Field</a>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import { normalizeLanguageSelection } from '@/composables/useLanguageMultiSelect'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  users: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue'])

const form = computed({
  get: () => ({ buyer_doc_type: 'national_id', ...props.modelValue }),
  set: (v) => emit('update:modelValue', v)
})

const responsiblePerson = computed(() => {
  const id = form.value?.responsible_person_id
  if (!id || !props.users.length) return null
  return props.users.find(u => u.id === id) || null
})

function setDocType(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function removeTag(key, tag) {
  const obj = { ...props.modelValue }
  const arr = (obj[key] || []).filter(t => t !== tag)
  emit('update:modelValue', { ...obj, [key]: arr })
}

function updateBuyerLanguage(value) {
  emit('update:modelValue', {
    ...props.modelValue,
    buyer_language: normalizeLanguageSelection(value),
  })
}

const buyerDocTabs = [
  { id: 'national_id', name: 'Buyer National ID' },
  { id: 'passport', name: 'Buyer Passport' },
  { id: 'kyc', name: 'Buyer KYC' },
  { id: 'spa', name: 'Buyer SPA' },
  { id: 'payment_proof', name: 'Buyer Payment Proof' }
]

const nationalityOptions = [{ value: null, text: 'Select Nationality' }]
const residencyOptions = [{ value: null, text: 'Select Status' }]
const countryOptions = [{ value: null, text: 'Not Selected' }]
const languageOptions = [{ value: null, text: 'Select Language' }]
const propertyTypeOptions = [{ value: null, text: 'Not Selected' }]
const bedroomOptions = [{ value: null, text: 'Select Bedroom' }, { value: '1', text: '1' }, { value: '2', text: '2' }, { value: '3', text: '3' }, { value: '4', text: '4' }, { value: '5+', text: '5+' }]
</script>

<style scoped>
.section-title { font-size: 16px; font-weight: 600; color: var(--deal-navy-deep, #01062c); font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); letter-spacing: -0.02em; }
.form-card { background: #fff; border: 1px solid #F3F3F3; box-shadow: 1px 1px 5px rgba(0,0,0,0.03); }
.radius-12 { border-radius: 12px; }
.form-label-custom { font-size: 12px; font-weight: 500; color: var(--deal-text-muted, #64748b); margin-bottom: 6px; display: block; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input { height: 44px !important; min-height: 44px; border-radius: var(--deal-input-r, 10px) !important; border: 1px solid #E2E8F0 !important; font-size: 14px !important; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.input-group-custom { display: flex; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; }
.input-group-custom .custom-input { border: none !important; flex: 1; border-radius: var(--deal-input-r, 10px) 0 0 var(--deal-input-r, 10px) !important; }
:deep(.custom-v-select-inline) { min-width: 120px; }
:deep(.custom-v-select .vs__dropdown-toggle) { height: 44px !important; min-height: 44px !important; border-radius: var(--deal-input-r, 10px); border: 1px solid #E2E8F0; font-size: 14px; }
:deep(.custom-v-select-inline .vs__dropdown-toggle) { height: 44px !important; min-height: 44px !important; border: none; border-left: 1px solid #E2E8F0; border-radius: 0 var(--deal-input-r, 10px) var(--deal-input-r, 10px) 0; }
:deep(.buyer-language-select .vs__selected) { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
:deep(.buyer-language-select .vs__dropdown-option--highlight) { background: #eff6ff; color: #1e3a8a; }
:deep(.buyer-language-select .vs__dropdown-option--selected) { background: #dbeafe; color: #1d4ed8; font-weight: 600; }
.doc-tabs { gap: 8px; }
.doc-tab { padding: 6px 14px; border-radius: 100px; border: 1px solid #E2E8F0; background: #fff; font-size: 12px; color: #64748B; cursor: pointer; }
.doc-tab.active { background: #0F172A; color: #fff; border-color: #0F172A; }
.upload-zone { border-style: dashed !important; border-color: #E2E8F0 !important; background: #F8FAFC; }
.upload-icon { font-size: 32px; color: #94A3B8; }
.upload-text { font-size: 14px; color: #475569; margin: 0; }
.tag-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #E2E8F0; border-radius: 100px; font-size: 12px; }
.tag-remove { cursor: pointer; font-size: 14px; }
.btn-tag-search { background: transparent; border: none; color: var(--deal-navy, #0f172a); font-size: 14px; font-weight: 500; cursor: pointer; }
.add-custom-field-link { font-size: 14px; color: var(--deal-navy, #0f172a); font-weight: 500; text-decoration: underline; }
</style>
