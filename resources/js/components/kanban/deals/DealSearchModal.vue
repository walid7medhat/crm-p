<template>
  <div v-if="asDropdown" class="deal-search-dropdown-panel deal-figma-ui">
    <div class="deal-search-container d-flex">
      <button class="close-btn" @click="emit('update:modelValue', false)">
        <iconify-icon icon="lucide:x" />
      </button>

      <div class="deal-sidebar-pills d-flex flex-column gap-3">
        <button
          v-for="pill in sidebarPills"
          :key="pill.id"
          type="button"
          class="deal-pill-btn"
          :class="{ active: activePill === pill.id }"
          @click="activePill = pill.id"
        >
          {{ pill.label }}
        </button>
      </div>

      <div class="form-content-wrapper flex-grow-1">
        <div class="search-sections-wrap">
          <!-- Deal Information Section -->
          <div class="search-section-card">
            <div class="search-section-title">Deal Information</div>
            <div class="row g-3">
              <div v-if="fieldSettings.name" class="col-md-6">
                <label class="form-label-custom">Name</label>
                <b-form-input v-model="form.deal_name" class="custom-input deal-input-placeholder" placeholder="Enter Deals Name" />
              </div>
              <div v-if="fieldSettings.end_date" class="col-md-6">
                <label class="form-label-custom">End Date</label>
                <v-select
                  v-model="form.end_date"
                  :options="datePresetOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Not Selected"
                  data-placeholder="Not Selected"
                  :searchable="true"
                  :clearable="true"
                  append-to-body
                >
              <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
                </v-select>
              </div>
              <div v-if="fieldSettings.stage_changed_by" class="col-md-6">
                <label class="form-label-custom">Stage Changed By</label>
                <v-select
                  v-model="form.stage_changed_by"
                  :options="personOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Person"
                  data-placeholder="Select Person"
                  :searchable="true"
                  :clearable="true"
                  append-to-body
                >
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div>
              <!-- <div v-if="fieldSettings.stage_group" class="col-md-6">
                <label class="form-label-custom">Stage Group</label>
                <v-select
                  v-model="form.stage_group"
                  :options="stageOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Stages"
                  data-placeholder="Select Stages"
                  :searchable="true"
                  :multiple="true"
                  :clearable="true"
                  append-to-body
                >
              
                <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
                </v-select>
              </div> -->
            </div>
          </div>

          <!-- Buyer Details Section -->
          <div class="search-section-card">
            <div class="search-section-title">Buyer Details</div>
            <div class="row g-3">
              <div v-if="fieldSettings.buyer_first_name" class="col-md-6">
                <label class="form-label-custom">First Name</label>
                <b-form-input v-model="form.buyer_first_name" class="custom-input" placeholder="Enter First Name" />
              </div>
              <div v-if="fieldSettings.buyer_last_name" class="col-md-6">
                <label class="form-label-custom">Last Name</label>
                <b-form-input v-model="form.buyer_last_name" class="custom-input" placeholder="Enter Last Name" />
              </div>
              <div v-if="fieldSettings.buyer_phone" class="col-md-6">
                <label class="form-label-custom">Phone Number</label>
                <CrmPhoneInput v-model="form.buyer_phone" placeholder="Enter Phone Number" />
              </div>
              <div v-if="fieldSettings.buyer_date_of_birth" class="col-md-6">
                <label class="form-label-custom">Date Of Birth</label>
                <AdvancedDatePicker v-model="form.buyer_dob" date-only placeholder="Select date" class="custom-input" />
              </div>
              <div v-if="fieldSettings.buyer_email" class="col-md-6">
                <label class="form-label-custom">Email</label>
                <b-form-input v-model="form.buyer_email" type="email" class="custom-input" placeholder="Enter Email" />
              </div>
              <div v-if="fieldSettings.buyer_residency_status" class="col-md-6">
                <label class="form-label-custom">Residency Status</label>
                <v-select
                  v-model="form.buyer_residency_status"
                  :options="residencyOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Status"
                  :clearable="true"
                    append-to-body
                >
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div>
              <div v-if="fieldSettings.buyer_country_residence" class="col-md-6">
                <label class="form-label-custom">Country Of Residence</label>
                <v-select
                  v-model="form.buyer_country"
                  :options="countryOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Country"
                  :clearable="true"
                    append-to-body
                >
                  <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              
                </v-select>
              </div>
              <div v-if="fieldSettings.buyer_nationality" class="col-md-6">
                <label class="form-label-custom">Nationality</label>
                <v-select
                  v-model="form.buyer_nationality"
                  :options="nationalityOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Nationality"
                  :clearable="true"
                    append-to-body
                >
                  <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              
              
               </v-select>
              </div>
              <div v-if="fieldSettings.buyer_city_residence" class="col-md-6">
                <label class="form-label-custom">City Of Residence</label>
                <b-form-input v-model="form.buyer_city" class="custom-input" placeholder="Enter City" />
              </div>
            </div>
          </div>

          <!-- Property Details Section -->
          <div class="search-section-card">
            <div class="search-section-title">Property Details</div>
            <div class="row g-3">
              <div v-if="fieldSettings.property_unit_no" class="col-md-6">
                <label class="form-label-custom">Unit No</label>
                <b-form-input v-model="form.unit_no" class="custom-input" placeholder="Enter Unit No" />
              </div>
              <div v-if="fieldSettings.property_type" class="col-md-6">
                <label class="form-label-custom">Property Type</label>
                <v-select
                  v-model="form.property_type_id"
                  :options="propertyTypes"
                  :reduce="opt => opt.id"
                  label="name"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Property Type"
                  data-placeholder="Select Property Type"
                  :clearable="true"
                    append-to-body
                >
              
                 <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div>
              <div v-if="fieldSettings.property_bedrooms" class="col-md-6">
                <label class="form-label-custom">Bedrooms</label>
                <v-select
                  v-model="form.bedrooms"
                  :options="bedroomOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Bedroom"
                  :clearable="true"
                    append-to-body
                 >
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              
                </v-select>
              </div>
              <div v-if="fieldSettings.property_project_name" class="col-md-6">
                <label class="form-label-custom">Project Name</label>
                <v-select
                  v-model="form.project_id"
                  :options="projects"
                  :reduce="opt => opt.id"
                  label="name"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Search Project.."
                  data-placeholder="Search Project.."
                  :filterable="true"
                  :searchable="true"
                  @search="searchProjects"
                    append-to-body
                >
              
                  <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div>
              <!-- <div v-if="fieldSettings.property_developer" class="col-md-6">
                <label class="form-label-custom">Developer</label>
                <v-select
                  v-model="form.developer_id"
                  :options="developers"
                  :reduce="opt => opt.id"
                  label="name"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Developer"
                  :clearable="true"
                    append-to-body
                >
              
                <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div> -->
              <div v-if="fieldSettings.property_area" class="col-md-6">
                <label class="form-label-custom">Property Address</label>
                  <v-select
                          v-model="form.area_id"
                          :options="localAreas.length ? localAreas : props.areas"
                          :reduce="opt => opt.id"
                          label="name"
                          class="custom-v-select deal-select-placeholder"
                          placeholder="Select Address"
                          :clearable="true"
                          :filterable="true"
                          :searchable="true"
                          @search="searchAreas"
                            append-to-body
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                <template #option="option">
                                    <div class="location-option">
                                        <i class="ri-map-pin-line location-option-icon"></i>
                                        <div class="location-option-text">
                                            <span class="location-option-name">{{ locationFirstLine(option) }}</span>
                                            <span class="location-option-subtitle">{{ locationSecondLine(option) }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="option" class="location-selected">
                                        <span class="location-selected-name">{{ locationFirstLine(option) }}</span>
                                        <span class="location-selected-subtitle">{{ locationSecondLine(option) }}</span>
                                    </div>
                                </template>
                            </v-select>
              </div>
              <div v-if="fieldSettings.property_sub_community" class="col-md-6">
                <label class="form-label-custom">Sub Community</label>
                <v-select
                  v-model="form.subcommunity_id"
                  :options="subCommunities"
                  :reduce="opt => opt.id"
                  label="name"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Subcommunity"
                  :clearable="true"
                  :filterable="true"
                  :searchable="true"
                  @search="searchSubCommunities"
                    append-to-body
                ><template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
                </v-select>
              </div>
              <div v-if="fieldSettings.property_unit_size" class="col-md-6">
                <label class="form-label-custom">Unit Size</label>
                <b-form-input v-model="form.unit_size" class="custom-input" placeholder="Enter Unit Size (sq. ft)" />
              </div>
            </div>
          </div>

          <!-- Assignment Section -->
          <div class="search-section-card">
            <div class="search-section-title">Assignment</div>
            <div class="row g-3">
              <div v-if="fieldSettings.responsible_person" class="col-md-6">
                <label class="form-label-custom">Responsible Person</label>
                <v-select
                  v-model="form.responsible_person_id"
                  :options="personOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-search-rp-select deal-select-placeholder"
                  placeholder="Select Person"
                  data-placeholder="Select Person"
                  :searchable="true"
                  :clearable="true"
                  append-to-body
                >
                 <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
                  <template #option="option">
                    <div v-if="option.value == null" class="deal-rp-opt-placeholder text-muted">Select Person</div>
                    <div v-else class="deal-rp-opt d-flex align-items-center gap-2">
                      <img :src="option.avatar || defaultAvatar" alt="" class="deal-rp-opt-avatar" />
                      <div class="deal-rp-opt-info min-w-0 flex-grow-1">
                        <div class="deal-rp-opt-name-row d-flex align-items-center flex-wrap gap-1">
                          <span class="user-item-name">{{ option.text }}</span>
                          <span v-if="option.role_name" class="user-position-badge">{{ option.role_name }}</span>
                        </div>
                        <div class="user-item-meta-line">
                          <span class="meta-value">{{ option.parent_name }}</span>
                          <span v-if="option.branch_name" class="meta-divider">|</span>
                          <span v-if="option.branch_name" class="meta-value">{{ option.branch_name }}</span>
                        </div>
                      </div>
                    </div>
                  </template>
                  <template #selected-option="option">
                    <div v-if="!option || option.value == null" class="deal-rp-opt-placeholder text-muted">Select Person</div>
                    <div v-else class="deal-rp-sel d-flex align-items-center gap-2 min-w-0">
                      <img :src="option.avatar || defaultAvatar" alt="" class="deal-rp-sel-avatar" />
                      <div class="min-w-0 flex-grow-1">
                        <div class="deal-rp-sel-name text-truncate fw-semibold">{{ option.text }}</div>
                        <div
                          v-if="option.parent_name || option.branch_name"
                          class="deal-rp-sel-meta text-truncate small text-muted"
                        >
                          {{ [option.parent_name, option.branch_name].filter(Boolean).join(' | ') }}
                        </div>
                      </div>
                    </div>
                  </template>
                </v-select>
              </div>
              <div v-if="fieldSettings.modified_by" class="col-md-6">
                <label class="form-label-custom">Modified By</label>
                <v-select
                  v-model="form.modified_by"
                  :options="personOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Select Person"
                  data-placeholder="Select Person"
                  :searchable="true"
                  :clearable="true"
                  append-to-body
                >
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div>
              <div v-if="fieldSettings.created_by" class="col-md-6">
                <label class="form-label-custom">Created By</label>
                <v-select
                  v-model="form.created_by_date"
                  :options="createdByDatePresetOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Any Date"
                  data-placeholder="Any Date"
                  :searchable="true"
                  :clearable="true"
                  append-to-body
                  @option:selected="onCreatedByDatePresetSelected"
                >
               <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
              </v-select>
              </div>
            </div>
          </div>
        </div>

        <div class="search-modal-footer d-flex align-items-center justify-content-between">
          <div class="d-flex gap-4">
            <a href="#" class="footer-link text-decoration-underline" @click.prevent="showFieldSettings = true">Add Field</a>
            <a href="#" class="footer-link text-secondary" @click.prevent="restoreDefaultSearchFields">Restore default fields</a>
          </div>
          <div class="d-flex gap-3">
            <button class="btn-reset" @click="resetForm">Reset</button>
            <button class="btn-search" @click="applySearch">Search</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <DealFilterFieldSettingsModal
    v-model="showFieldSettings"
    :selected-fields="fieldSettings"
    :tabs="fieldSettingsTabs"
    :sections="fieldSettingsSections"
    :defaults="defaultFieldSettings"
    @apply="applyFieldSettings"
  />

  <!-- Custom “Date Created” calendar (last option: Custom Date) -->
  <DateTimePicker
    :show="showCreatedByDatePicker"
    :model-value="createdByPickerDate"
    date-only
    @update:show="showCreatedByDatePicker = $event"
    @apply="onCreatedByCustomDateApply"
    @cancel="onCreatedByCustomDateCancel"
  />
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import DealFilterFieldSettingsModal from './DealFilterFieldSettingsModal.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import DateTimePicker from '../shared/DateTimePicker.vue'
import {
  parseToDate,
  toDateOnlyApiString,
  formatDateOnlyLong,
} from '@/composables/useAdvancedDateModel'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  asDropdown: { type: Boolean, default: true },
  currentQuery: { type: Object, default: null },
  dealType: { type: String, default: 'primary' },
  // Optional external data sources
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  areas: { type: Array, default: () => [] },
  subCommunities: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'search'])

const defaultAvatar = '/assets/images/placeholder-user.jpg'
const showFieldSettings = ref(false)
const activePill = ref('deals-in-progress')

const sidebarPills = [
  // { id: 'closed-deals', label: 'Closed Deals' },
  { id: 'deals-in-progress', label: 'Deals In Progress' },
  { id: 'my-deals', label: 'My Deals' },
]

const defaultFieldSettings = {
  name: true,
  end_date: true,
  stage_changed_by: true,
  stage_group: true,
  responsible_person: true,
  modified_by: true,
  created_by: true,
  buyer_first_name: true,
  buyer_last_name: true,
  buyer_phone: true,
  buyer_date_of_birth: false,
  buyer_email: false,
  buyer_residency_status: false,
  buyer_country_residence: false,
  buyer_nationality: false,
  buyer_city_residence: false,
  property_unit_no: true,
  property_type: true,
  property_bedrooms: false,
  property_project_name: true,
  property_developer: false,
  property_area: false,
  property_sub_community: false,
  property_unit_size: false,
}

const DEAL_SEARCH_FIELDS_SESSION_KEY = 'deal_search_field_settings_v1'

const buildSessionStorageKey = () => `${DEAL_SEARCH_FIELDS_SESSION_KEY}:${props.dealType || 'primary'}`

const normalizeFieldSettings = (raw) => {
  const normalized = { ...defaultFieldSettings }
  if (!raw || typeof raw !== 'object') return normalized

  Object.keys(defaultFieldSettings).forEach((key) => {
    if (Object.prototype.hasOwnProperty.call(raw, key)) {
      normalized[key] = !!raw[key]
    }
  })

  return normalized
}

const persistFieldSettingsToSession = (settings) => {
  try {
    sessionStorage.setItem(buildSessionStorageKey(), JSON.stringify(normalizeFieldSettings(settings)))
  } catch {
    // Ignore storage access errors (private mode / disabled storage)
  }
}

const hydrateFieldSettingsFromSession = () => {
  try {
    const raw = sessionStorage.getItem(buildSessionStorageKey())
    if (!raw) return
    const parsed = JSON.parse(raw)
    fieldSettings.value = normalizeFieldSettings(parsed)
  } catch {
    fieldSettings.value = { ...defaultFieldSettings }
  }
}

const fieldSettings = ref({ ...defaultFieldSettings })

const fieldSettingsTabs = [
  { id: 'deals', label: 'Deals' },
  { id: 'activity', label: 'Activity' },
]

const fieldSettingsSections = [
  {
    id: 'deal-information',
    tab: 'deals',
    label: 'Deal Information',
    fields: [
      { id: 'name', label: 'Deal Name' },
      { id: 'stage_group', label: 'Stage' },
      { id: 'responsible_person', label: 'Secondary Phone' },
      { id: 'stage_changed_by', label: 'Stage Changed By' },
      { id: 'modified_by', label: 'Modified By' },
      { id: 'end_date', label: 'Last Updated' },
      { id: 'created_by', label: 'Created By' },
    ],
  },
  {
    id: 'buyer-details',
    tab: 'deals',
    label: 'Buyer Details',
    fields: [
      { id: 'buyer_first_name', label: 'First Name' },
      { id: 'buyer_last_name', label: 'Last Name' },
      { id: 'buyer_date_of_birth', label: 'Date Of Birth' },
      { id: 'buyer_phone', label: 'Phone Number' },
      { id: 'buyer_email', label: 'Email' },
      { id: 'buyer_residency_status', label: 'Residency Status' },
      { id: 'buyer_country_residence', label: 'Country Of Residence' },
      { id: 'buyer_nationality', label: 'Nationality' },
      { id: 'buyer_city_residence', label: 'City Of Residence' },
    ],
  },
  {
    id: 'property-details',
    tab: 'deals',
    label: 'Property Details',
    fields: [
      { id: 'property_unit_no', label: 'Unit No' },
      { id: 'property_type', label: 'Property Type' },
      { id: 'property_bedrooms', label: 'Bedrooms' },
      { id: 'property_project_name', label: 'Project Name' },
      { id: 'property_developer', label: 'Developer' },
      { id: 'property_area', label: 'Area' },
      { id: 'property_sub_community', label: 'Sub Community' },
      { id: 'property_unit_size', label: 'Unit Size' },
    ],
  },
  {
    id: 'activity',
    tab: 'activity',
    label: 'Activity',
    fields: [
      { id: 'created_by', label: 'Date Created' },
      { id: 'name', label: 'Activity Source' },
      { id: 'stage_changed_by', label: 'Activity Type' },
      { id: 'responsible_person', label: 'Responsible Person' },
      { id: 'end_date', label: 'DeadLine' },
      { id: 'modified_by', label: 'Created By' },
      { id: 'stage_group', label: 'Status' },
    ],
  },
]

const form = ref({
  // Activity fields
  deal_name: '',
  end_date: null,
  stage_changed_by: null,
  stage_group: [],
  responsible_person_id: null,
  modified_by: null,
  created_by_date: null,
  // Buyer fields
  buyer_first_name: '',
  buyer_last_name: '',
  buyer_phone: '',
  buyer_dob: '',
  amount: '',
  currency: 'AED',
  buyer_email: '',
  buyer_residency_status: null,
  buyer_country: null,
  buyer_nationality: null,
  buyer_city: '',
  // Property fields
  unit_no: '',
  property_type_id: null,
  bedrooms: null,
  project_id: null,
  // developer_id: null,
  area_id: null,
  subcommunity_id: null,
  unit_size: '',
})

const people = ref([])
const stages = ref([])
const projects = ref([])

// Options for selects
const nationalityOptions = [
  { value: 'emirati', text: 'Emirati' },
  { value: 'saudi', text: 'Saudi' },
  { value: 'egyptian', text: 'Egyptian' },
  { value: 'indian', text: 'Indian' },
  { value: 'british', text: 'British' },
  { value: 'american', text: 'American' },
  { value: 'other', text: 'Other' },
]

const residencyOptions = [
  { value: 'citizen', text: 'Citizen' },
  { value: 'resident', text: 'Resident' },
  { value: 'investor', text: 'Investor' },
  { value: 'tourist', text: 'Tourist' },
  { value: 'other', text: 'Other' },
]

const countryOptions = [
  { value: 'AE', text: 'United Arab Emirates' },
  { value: 'SA', text: 'Saudi Arabia' },
  { value: 'EG', text: 'Egypt' },
  { value: 'IN', text: 'India' },
  { value: 'GB', text: 'United Kingdom' },
  { value: 'US', text: 'United States' },
  { value: 'other', text: 'Other' },
]

const currencyOptions = [
  { value: 'AED', text: 'AED' },
  { value: 'USD', text: 'USD' },
  { value: 'EUR', text: 'EUR' },
  { value: 'GBP', text: 'GBP' },
  { value: 'SAR', text: 'SAR' },
]

const bedroomOptions = [
  { value: 'studio', text: 'Studio' },
  { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' },
  { value: '5', text: '5 Bedrooms' },
  { value: '5+', text: '5+ Bedrooms' },
]

const personOptions = computed(() =>
  people.value.map((u) => ({
    value: u.id,
    text: u.name || 'Unknown',
    avatar: u.avatar || u.profile_image || null,
    role_name: u.role_name || u.role || null,
    parent_name: u.parent_name || '',
    branch_name: u.branch_name || '',
  })),
)

const stageOptions = computed(() => stages.value.map((s) => ({ value: s.id, text: s.name })))

const datePresetOptions = [
  { text: 'Any Date', value: 'any' },
  { text: 'Today', value: 'today' },
  { text: 'Yesterday', value: 'yesterday' },
  { text: 'This Week', value: 'this_week' },
  { text: 'This Month', value: 'this_month' },
  { text: 'Last Week', value: 'last_week' },
  { text: 'Last Month', value: 'last_month' },
]

/** “Date Created” presets + Custom Date (opens calendar modal). Label updates when a day is chosen. */
const createdByCustomYmd = ref('')
const showCreatedByDatePicker = ref(false)
const createdByPickerDate = ref(new Date())

const createdByDatePresetOptions = computed(() => {
  let customText = 'Custom Date'
  if (createdByCustomYmd.value && form.value.created_by_date === 'custom') {
    const pretty = formatDateOnlyLong(createdByCustomYmd.value, '')
    customText = pretty ? `Custom (${pretty})` : 'Custom Date'
  }
  return [...datePresetOptions, { text: customText, value: 'custom' }]
})

// Search functions for dynamic data
const searchProjects = async (search) => {
  if (!search && search !== '') return
  try {
    const response = await api.get('/listings/projects', { params: { search } })
    projects.value = response.data?.data ?? response.data ?? []
  } catch (e) {
    console.error('Error searching projects:', e)
  }
}
const locationFirstLine = (area) => area?.name || 'Unknown Area'
const locationSecondLine = (area) => {
    const parent = area?.parent || area?.area_parents_title || area?.parent_name
    const community = area?.community_name || area?.communityName
    const city = area?.city_name || area?.cityName
    if (parent) return parent
    if (community && city) return `${community}, ${city}`
    return community || city || ''
}
const searchAreas = async (search) => {
  if (!search && search !== '') return
  try {
    const response = await api.get('/listings/areas', { params: { search } })
    // Assuming the response structure
    const areasData = response.data?.data ?? response.data ?? []
    // Emit to parent if needed, otherwise update local ref
    if (props.areas?.length) {
      // If areas are passed as prop, we don't override
      return
    }
    // Otherwise we would need to manage areas locally
  } catch (e) {
    console.error('Error searching areas:', e)
  }
}

const searchSubCommunities = async (search) => {
  if (!search && search !== '') return
  try {
    const response = await api.get('/listings/subcommunities', { params: { search } })
    // Handle response
  } catch (e) {
    console.error('Error searching subcommunities:', e)
  }
}

const fetchUsers = async () => {
  try {
    const res = await api.get('/available-responsible-persons')
    const data = res?.data?.data || res?.data || []
    people.value = Array.isArray(data) ? data : []
  } catch {
    people.value = []
  }
}

const fetchStages = async () => {
  try {
    const res = await api.get('/stages', { params: { stage_type: 'deal', deal_type: props.dealType || 'primary' } })
    const data = res?.data?.data?.data || res?.data?.data || res?.data || []
    stages.value = Array.isArray(data) ? data : []
  } catch {
    stages.value = []
  }
}

function onCreatedByCustomDateApply(date) {
  if (date instanceof Date && !Number.isNaN(date.getTime())) {
    createdByCustomYmd.value = toDateOnlyApiString(date)
  }
  showCreatedByDatePicker.value = false
}

function onCreatedByCustomDateCancel() {
  showCreatedByDatePicker.value = false
}

function onCreatedByDatePresetSelected(option) {
  const val =
    typeof option === 'object' && option !== null && 'value' in option
      ? option.value
      : option
  if (val !== 'custom') return
  createdByPickerDate.value = parseToDate(createdByCustomYmd.value) || new Date()
  showCreatedByDatePicker.value = true
}

const resetForm = () => {
  createdByCustomYmd.value = ''
  showCreatedByDatePicker.value = false
  form.value = {
    deal_name: '',
    end_date: null,
    stage_changed_by: null,
    stage_group: [],
    responsible_person_id: null,
    modified_by: null,
    created_by_date: null,
    buyer_first_name: '',
    buyer_last_name: '',
    buyer_phone: '',
    buyer_dob: '',
    amount: '',
    currency: 'AED',
    buyer_email: '',
    buyer_residency_status: null,
    buyer_country: null,
    buyer_nationality: null,
    buyer_city: '',
    unit_no: '',
    property_type_id: null,
    bedrooms: null,
    project_id: null,
    // developer_id: null,
    area_id: null,
    subcommunity_id: null,
    unit_size: '',
  }
  emit('search', { query: null, activeFilters: [] })
}

const restoreDefaultSearchFields = () => {
  fieldSettings.value = { ...defaultFieldSettings }
  persistFieldSettingsToSession(fieldSettings.value)
  resetForm()
}

const applyFieldSettings = (settings) => {
  fieldSettings.value = normalizeFieldSettings({ ...fieldSettings.value, ...(settings || {}) })
  persistFieldSettingsToSession(fieldSettings.value)
}

const toYmd = (d) => {
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return null
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const presetRange = (preset) => {
  const now = new Date()
  let from = null
  let to = null
  if (preset === 'today') {
    from = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    to = from
  } else if (preset === 'yesterday') {
    from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1)
    to = from
  } else if (preset === 'this_week') {
    from = new Date(now)
    from.setDate(now.getDate() - now.getDay())
    to = new Date(from)
    to.setDate(from.getDate() + 6)
  } else if (preset === 'this_month') {
    from = new Date(now.getFullYear(), now.getMonth(), 1)
    to = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  } else if (preset === 'last_week') {
    to = new Date(now)
    to.setDate(now.getDate() - now.getDay() - 1)
    from = new Date(to)
    from.setDate(to.getDate() - 6)
  } else if (preset === 'last_month') {
    from = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    to = new Date(now.getFullYear(), now.getMonth(), 0)
  }
  if (!from || !to) return null
  return { from_date: toYmd(from), to_date: toYmd(to) }
}

const applySearch = () => {
  const query = {}
  const activeFilters = []

  const pushFilter = (id, label, value) => {
    if (value !== null && value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0)) {
      activeFilters.push({ id, label, value, queryKey: id })
    }
  }

  // Activity fields
  if (form.value.deal_name) {
    query.search = form.value.deal_name.trim()
    pushFilter('deal_name', 'Name', form.value.deal_name)
  }
  if (form.value.end_date && form.value.end_date !== 'any') {
    const range = presetRange(form.value.end_date)
    if (range) Object.assign(query, range)
    pushFilter('end_date', 'End Date', form.value.end_date)
  }
  if (form.value.stage_group?.length) {
    query.stage_id = form.value.stage_group[0]
    pushFilter('stage_group', 'Stage Group', form.value.stage_group.length)
  }
  if (form.value.responsible_person_id) {
    query.responsible_id = form.value.responsible_person_id
    const p = personOptions.value.find((u) => u.value === form.value.responsible_person_id)
    pushFilter('responsible_person', 'Responsible Person', p?.text || form.value.responsible_person_id)
  }
  if (form.value.modified_by) {
    query.modified_by = form.value.modified_by
    const p = personOptions.value.find((u) => u.value === form.value.modified_by)
    pushFilter('modified_by', 'Modified By', p?.text || form.value.modified_by)
  }
  if (form.value.created_by_date && form.value.created_by_date !== 'any') {
    if (form.value.created_by_date === 'custom') {
      if (createdByCustomYmd.value) {
        query.from_date = createdByCustomYmd.value
        query.to_date = createdByCustomYmd.value
        const chip =
          formatDateOnlyLong(createdByCustomYmd.value, '') || createdByCustomYmd.value
        pushFilter('created_by', 'Created By', chip)
      }
    } else {
      const range = presetRange(form.value.created_by_date)
      if (range) Object.assign(query, range)
      pushFilter('created_by', 'Created By', form.value.created_by_date)
    }
  }

  // Buyer fields
  if (form.value.buyer_first_name) {
    query.buyer_first_name = form.value.buyer_first_name
    pushFilter('buyer_first_name', 'Buyer First Name', form.value.buyer_first_name)
  }
  if (form.value.buyer_last_name) {
    query.buyer_last_name = form.value.buyer_last_name
    pushFilter('buyer_last_name', 'Buyer Last Name', form.value.buyer_last_name)
  }
  if (form.value.buyer_phone) {
    query.buyer_phone = form.value.buyer_phone
    pushFilter('buyer_phone', 'Buyer Phone', form.value.buyer_phone)
  }
  if (form.value.buyer_dob) {
    query.buyer_dob = form.value.buyer_dob
    pushFilter('buyer_dob', 'Buyer Date of Birth', form.value.buyer_dob)
  }
  if (form.value.amount) {
    query.amount = form.value.amount
    pushFilter('amount', 'Amount', form.value.amount)
  }
  if (form.value.currency && form.value.currency !== 'AED') {
    query.currency = form.value.currency
    pushFilter('currency', 'Currency', form.value.currency)
  }
  if (form.value.buyer_email) {
    query.buyer_email = form.value.buyer_email
    pushFilter('buyer_email', 'Buyer Email', form.value.buyer_email)
  }
  if (form.value.buyer_residency_status) {
    query.buyer_residency_status = form.value.buyer_residency_status
    pushFilter('buyer_residency_status', 'Buyer Residency Status', form.value.buyer_residency_status)
  }
  if (form.value.buyer_country) {
    query.buyer_country = form.value.buyer_country
    pushFilter('buyer_country', 'Buyer Country', form.value.buyer_country)
  }
  if (form.value.buyer_nationality) {
    query.buyer_nationality = form.value.buyer_nationality
    pushFilter('buyer_nationality', 'Buyer Nationality', form.value.buyer_nationality)
  }
  if (form.value.buyer_city) {
    query.buyer_city = form.value.buyer_city
    pushFilter('buyer_city', 'Buyer City', form.value.buyer_city)
  }

  // Property fields
  if (form.value.unit_no) {
    query.unit_no = form.value.unit_no
    pushFilter('unit_no', 'Unit No', form.value.unit_no)
  }
  if (form.value.property_type_id) {
    query.property_type_id = form.value.property_type_id
    pushFilter('property_type_id', 'Property Type', form.value.property_type_id)
  }
  if (form.value.bedrooms) {
    query.bedrooms = form.value.bedrooms
    pushFilter('bedrooms', 'Bedrooms', form.value.bedrooms)
  }
  if (form.value.project_id) {
    query.project_id = form.value.project_id
    pushFilter('project_id', 'Project', form.value.project_id)
  }
  if (form.value.developer_id) {
    query.developer_id = form.value.developer_id
    pushFilter('developer_id', 'Developer', form.value.developer_id)
  }
  if (form.value.area_id) {
    query.area_id = form.value.area_id
    pushFilter('area_id', 'Area', form.value.area_id)
  }
  if (form.value.subcommunity_id) {
    query.subcommunity_id = form.value.subcommunity_id
    pushFilter('subcommunity_id', 'Subcommunity', form.value.subcommunity_id)
  }
  if (form.value.unit_size) {
    query.unit_size = form.value.unit_size
    pushFilter('unit_size', 'Unit Size', form.value.unit_size)
  }
  if (activePill.value === 'my-deals') {
 
      query.my_deals = true
      pushFilter('my_deals', 'Deals Scope', 'My Deals')
    }

  emit('search', { query: Object.keys(query).length ? query : null, activeFilters })
  emit('update:modelValue', false)
}
watch(activePill, (val) => {
  if (val === 'my-deals') {
    form.value.responsible_person_id = null
  }
})

watch(
  () => form.value.created_by_date,
  (v) => {
    if (v !== 'custom') createdByCustomYmd.value = ''
  },
)
watch(
  () => props.currentQuery,
  (q) => {
    if (!q || typeof q !== 'object') return
    form.value.deal_name = q.search || ''
    form.value.stage_group = q.stage_id ? [q.stage_id] : []
    form.value.responsible_person_id = q.responsible_id || null
    // Add other field mappings as needed
  },
  { immediate: true },
)

watch(() => props.dealType, fetchStages)
watch(() => props.dealType, hydrateFieldSettingsFromSession)

onMounted(async () => {
  hydrateFieldSettingsFromSession()
  await Promise.all([fetchUsers(), fetchStages()])
})
</script>


<style scoped>
.deal-search-dropdown-panel {
  width: 1000px;
  max-width: calc(100vw - 32px);
  min-height: 460px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
  background: #fff;
  overflow: visible;
}

.deal-search-container {
  position: relative;
  min-height: 460px;
  background: #fff;
  border-radius: 12px;
  overflow: visible;
}

.close-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  left: auto;
  transform: none;
  width: 36px;
  height: 36px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
  color: #334155;
  font-size: 18px;
  font-weight: 500;
  padding: 0;
  box-shadow: none;
  z-index: 9999;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: auto;
  opacity: 0.95;
}

.close-btn :deep(iconify-icon) {
  width: 18px;
  height: 18px;
}

.deal-sidebar-pills {
  min-width: 221px;
  background: #f8fafc;
  padding: 16px 14px !important;
}

.deal-pill-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 100px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  min-height: 30px;
  padding: 0 12px;
  text-align: left;
  transition: all 0.2s;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  width: fit-content;
}

.deal-pill-btn.active {
  background: #01062c;
  border-color: #01062c;
  color: #fff;
}

.form-content-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 20px 14px !important;
}

.search-sections-wrap {
  flex: 1 1 auto;
  min-height: 0;
  max-height: 58vh;
  overflow-y: auto;
  padding-right: 4px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.search-section-card {
  border: 1px solid #f1f5f9;
  border-radius: 14px;
  padding: 10px 10px 6px;
  background: #fff;
  box-shadow: 1px 1px 5px 5px #00000005;
}

.search-section-title {
  font-size: 13px;
  font-weight: 600;
  color: #01062c;
  margin-bottom: 10px;
}

.form-label-custom {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: #000;
  margin-bottom: 4px;
}

/* Match control height so text + placeholders sit vertically centered (Bootstrap form-control padding fights fixed height) */
.custom-input:not(textarea) {
  height: 40px !important;
  min-height: 40px !important;
  padding: 0 12px !important;
  line-height: 38px !important;
  border-radius: 9px !important;
  border: 1px solid #e2e8f0 !important;
  font-size: 12px !important;
  color: #64748b !important;
  font-family: var(--deal-font, 'Montserrat', sans-serif);
  box-sizing: border-box !important;
}

.custom-input::placeholder {
  color: #94a3b8 !important;
  font-size: 12px !important;
  line-height: 38px !important;
}

.deal-input-placeholder::placeholder {
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  opacity: 1 !important;
}

:deep(.custom-v-select.deal-select-placeholder) {
  font-family: 'Montserrat', sans-serif;
}

:deep(.custom-v-select .vs__dropdown-toggle) {
  height: 40px !important;
  min-height: 40px !important;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0 8px !important;
  display: flex !important;
  align-items: stretch !important;
  box-sizing: border-box !important;
}

:deep(.custom-v-select .vs__selected-options) {
  flex-wrap: nowrap;
  overflow: hidden;
  max-width: calc(100% - 30px);
  min-width: 0;
  align-items: stretch !important;
  align-self: stretch !important;
  height: 100% !important;
}

/* Single-line selects: flex + line-height matches inner box height for optical center */
:deep(.custom-v-select .vs__selected) {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  padding: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  display: flex !important;
  align-items: center !important;
  align-self: stretch !important;
  height: 100% !important;
  min-width: 0;
  line-height: 38px !important;
  box-sizing: border-box;
}

:deep(.custom-v-select .vs__selected.vs__selected--disabled),
:deep(.custom-v-select .vs__selected:empty) {
  color: #94a3b8;
  font-size: 12px;
}

/* Let the search field share the row with the label; 100% width was forcing layout and off-center text */
:deep(.custom-v-select .vs__search) {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  padding: 0 4px;
  flex: 1 1 0% !important;
  min-width: 0 !important;
  width: auto !important;
  align-self: stretch !important;
  height: 100% !important;
  box-sizing: border-box !important;
  opacity: 1 !important;
}

:deep(.custom-v-select .vs__search::placeholder) {
  color: #94a3b8;
  font-size: 12px;
  opacity: 1;
}

:deep(.custom-v-select.deal-select-placeholder.vs--unsearchable:not(.vs--disabled) .vs__search) {
  cursor: pointer !important;
  color: #64748b !important;
  font-size: 12px !important;
}

:deep(.custom-v-select .vs__placeholder) {
  color: #94a3b8 !important;
  font-size: 12px !important;
  opacity: 1 !important;
  align-self: stretch !important;
  display: flex !important;
  align-items: center !important;
  height: 100% !important;
  margin: 0 !important;
  padding: 0 !important;
  line-height: 38px !important;
}

/* Inner line box = 40px row − borders — centers placeholder + typed value in the search field */
:deep(.custom-v-select.vs--single input.vs__search) {
  line-height: 38px !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  border: none !important;
  background: transparent !important;
}

/*
 * vue-select does not add .vs--has-value — old :not(.vs--has-value) matched always, so the
 * ::before overlay + opacity:0 on .vs__search ran even when a value was selected (broken).
 */
:deep(.deal-select-placeholder.vs--single) {
  position: relative !important;
}

:deep(.custom-v-select.deal-select-placeholder.vs--single .vs__selected) {
  color: #64748b !important;
}

:deep(.custom-v-select.deal-select-placeholder.vs--single .vs__placeholder),
:deep(.custom-v-select.deal-select-placeholder.vs--unsearchable .vs__search) {
  color: #94a3b8 !important;
  font-size: 12px !important;
}

:deep(.custom-v-select.deal-select-placeholder.vs--single .vs__search::placeholder) {
  color: #94a3b8 !important;
  font-size: 12px !important;
}

:deep(.vs__placeholder),
:deep(.vs__search::placeholder) {
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  opacity: 1 !important;
}

/* Force actual input element style (matches what works in inspect) */
:deep(.input.vs__search) {
  color: #64748b !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
}

:deep(.input.vs__search::placeholder) {
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  opacity: 1 !important;
}

:deep(.custom-v-select .vs__actions) {
  padding: 0 8px;
  align-self: stretch !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
  font-size: 13px;
  color: #cfdbec;
}

:deep(.custom-v-select .vs__dropdown-menu) {
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  padding: 0;
  z-index: 12050;
}

:deep(.custom-v-select .vs__dropdown-option) {
  padding: 5px 10px;
  font-size: 12px;
  color: #475569 !important;
  transition: all 0.2s;
}

:deep(.custom-v-select .vs__dropdown-option--highlight),
:deep(.custom-v-select .vs__dropdown-option--selected) {
  background: #faa300 !important;
  color: #fff !important;
}

:deep(.deal-search-rp-select .vs__dropdown-menu) {
  max-height: min(360px, 55vh) !important;
}

:deep(.deal-search-rp-select .vs__dropdown-option) {
  padding: 8px 10px !important;
  white-space: normal !important;
}

/* Same 40px toggle as other fields — do not force 44px min-height (pushes content off-center) */
:deep(.deal-search-rp-select .vs__selected) {
  line-height: 1.25 !important;
  white-space: normal !important;
  min-height: 100% !important;
  height: 100% !important;
  max-height: 100% !important;
  display: flex !important;
  align-items: center !important;
  align-self: stretch !important;
  padding: 0 !important;
  margin: 0 !important;
  overflow: hidden !important;
  box-sizing: border-box !important;
}

:deep(.deal-search-rp-select .vs__selected .deal-rp-sel),
:deep(.deal-search-rp-select .vs__selected .deal-rp-opt-placeholder) {
  width: 100%;
  min-width: 0;
}

.deal-rp-opt-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}

.deal-rp-opt-name {
  font-size: 12px;
  font-weight: 500;
  color: #0f172a;
}

.deal-rp-opt-meta {
  font-size: 10px;
  color: #64748b;
}

.deal-rp-sel-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}

.deal-rp-opt-name-row .user-item-name,
.deal-rp-sel-name {
  font-size: 12px;
  color: #0f172a;
}

.user-position-badge {
  font-size: 10px;
  line-height: 1;
  padding: 2px 6px;
  border-radius: 999px;
  background: #eef2ff;
  color: #3730a3;
}

.user-item-meta-line,
.deal-rp-sel-meta {
  font-size: 10px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta-divider {
  margin: 0 4px;
}

.search-modal-footer {
  flex-shrink: 0;
  border-top: 1px solid #eef2f7;
  padding-top: 12px !important;
  margin-top: 12px !important;
  background: #fff;
}

.footer-link {
  font-size: 14px;
  color: #3b82f6;
  font-weight: 500;
}

.btn-reset,
.btn-search {
  padding: 10px 25px;
  border: none;
  font-size: 14px;
  border-radius: 100px;
}

.btn-reset {
  background: #f4f4f4;
  color: #01062c;
}

.btn-search {
  background: #000;
  color: #fff;
}

@media (max-width: 1199px) {
  .deal-search-dropdown-panel {
    width: calc(100vw - 24px);
    max-width: calc(100vw - 24px);
  }

  .deal-search-container {
    min-height: auto;
  }

  .deal-sidebar-pills {
    min-width: 190px;
    padding: 14px !important;
  }
}

@media (max-width: 992px) {
  .deal-search-container {
    flex-direction: column;
  }

  .deal-sidebar-pills {
    width: 100%;
    min-width: 100%;
    border-right: none;
    border-bottom: 1px solid #e2e8f0;
    flex-direction: row !important;
    flex-wrap: wrap;
    gap: 8px !important;
    padding: 12px !important;
  }

  .form-content-wrapper {
    padding: 14px 12px !important;
  }
}

@media (max-width: 767px) {
  .deal-search-dropdown-panel {
    width: calc(100vw - 12px);
    max-width: calc(100vw - 12px);
    min-height: auto;
    border-radius: 10px;
  }

  .deal-search-container {
    border-radius: 10px;
  }

  .search-section-card .row {
    --bs-gutter-x: 0.75rem;
    --bs-gutter-y: 0.5rem;
  }

  .search-section-card .col-md-6 {
    width: 100%;
  }

  .close-btn {
    right: 6px;
  }

  .btn-reset,
  .btn-search {
    padding: 8px 16px;
    font-size: 13px;
  }

  .search-modal-footer {
    position: sticky;
    bottom: 0;
    z-index: 5;
    padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
  }
}
:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 13px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
}
</style>
<style>

.custom-v-select .vs__dropdown-option {
  padding: 5px 10px;
  font-size: 12px;
  color: #475569 !important;
  transition: all 0.2s;
}

.custom-v-select .vs__placeholder {
  color: #94a3b8 !important;
  font-size: 12px !important;
  opacity: 1 !important;
  align-self: stretch !important;
  display: flex !important;
  align-items: center !important;
  height: 100% !important;
  margin: 0 !important;
  line-height: 38px !important;
}

.custom-v-select.vs--single input.vs__search {
  line-height: 38px !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  border: none !important;
  background: transparent !important;
}

.custom-v-select .vs__dropdown-toggle {
  height: 40px !important;
  min-height: 40px !important;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0 8px !important;
  display: flex !important;
  align-items: stretch !important;
  box-sizing: border-box !important;
}
.custom-v-select .vs__search {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  padding: 0 4px !important;
  flex: 1 1 0% !important;
  min-width: 0 !important;
  width: auto !important;
  align-self: stretch !important;
  height: 100% !important;
  box-sizing: border-box !important;
  opacity: 1 !important;
}

.custom-v-select .vs__search::placeholder{
  color: #94a3b8;
  font-size: 12px;
  opacity: 1;
}
.advanced-date-trigger{
  height: 100% !important;
}

.location-option {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.location-option-icon {
    color: #64748b;
    margin-top: 2px;
}

.location-option-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.location-option-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.location-option-subtitle {
    font-size: 11px;
    color: #64748b;
}

.location-selected {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.location-selected-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.2;
}

.location-selected-subtitle {
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
}
</style>