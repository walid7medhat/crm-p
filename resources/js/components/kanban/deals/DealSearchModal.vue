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
                <label class="form-label-custom">Deal Name</label>
                <b-form-input v-model="form.deal_name" class="custom-input deal-input-placeholder" placeholder="Enter Deals Name" />
              </div>
              <div v-if="fieldSettings.stage_changed_by" class="col-md-6">
                <label class="form-label-custom">Stage Changed By</label>
                <v-select
                  v-model="form.stage_changed_by"
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
                <label class="form-label-custom"> Name</label>
                <b-form-input v-model="form.buyer_first_name" class="custom-input" placeholder="Enter Name" />
              </div>
              <!--<div v-if="fieldSettings.buyer_last_name" class="col-md-6">-->
              <!--  <label class="form-label-custom">Last Name</label>-->
              <!--  <b-form-input v-model="form.buyer_last_name" class="custom-input" placeholder="Enter Last Name" />-->
              <!--</div>-->
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
                  <v-select
                      v-model="form.buyer_city"
                      :options="uaeCityOptions"
                      :reduce="opt => opt.value"
                      label="text"
                      class="custom-v-select deal-select-placeholder"
                      placeholder="Select City"
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
            </div>
          </div>

          <!-- Property Details Section -->
          <div class="search-section-card">
            <div class="search-section-title">Property Details</div>
            <div class="row g-3">
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
              <div v-if="fieldSettings.property_type" class="col-md-6">
                <label class="form-label-custom">Property Type</label>
                <v-select
                  v-model="form.property_type_id"
                  :options="localPropertyTypes"
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
              <div v-if="fieldSettings.property_unit_no" class="col-md-6">
                <label class="form-label-custom">Unit No</label>
                <b-form-input v-model="form.unit_no" class="custom-input" placeholder="Enter Unit No" />
              </div>
              <div v-if="fieldSettings.property_unit_size" class="col-md-6">
                <label class="form-label-custom">Unit Size</label>
                <b-form-input v-model="form.unit_size" class="custom-input" placeholder="Enter Unit Size (sq. ft)" />
              </div>
              <!-- <div v-if="fieldSettings.property_project_name" class="col-md-6">
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
              </div> -->
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
              <!-- <div v-if="fieldSettings.property_sub_community" class="col-md-6">
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
              </div> -->
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
             <div v-if="fieldSettings.created_by" class="col-md-6">
                <label class="form-label-custom">Created By</label>
                <button type="button" class="custom-date-trigger" @click="openDatePicker('created_by')">
                    <span>{{ createdByDateDisplay }}</span>
                    <iconify-icon icon="lucide:calendar-days" />
                </button>
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
  <!-- Custom Date Modal for Created By -->
<div v-if="showCreatedByDateModal" class="lr-modal-backdrop" @click.stop>
    <div class="lr-date-modal">
        <div class="lr-date-left">
            <button
                v-for="preset in datePresets"
                :key="preset.value"
                type="button"
                class="lr-date-preset"
                :class="{ active: tempPreset === preset.value }"
                @click="selectTempPresetRange(preset.value)"
            >
                {{ preset.label }}
            </button>
        </div>

        <div class="lr-date-right">
            <div class="lr-calendar-head">
                <button type="button" @click="changeMonth(-1)"><iconify-icon icon="lucide:chevron-left" /></button>
                <div>{{ monthLabel }}</div>
                <button type="button" @click="changeMonth(1)"><iconify-icon icon="lucide:chevron-right" /></button>
            </div>

            <div class="lr-weekdays">
                <span v-for="d in weekDays" :key="d">{{ d }}</span>
            </div>

            <div class="lr-calendar-grid">
                <button
                    v-for="cell in calendarCells"
                    :key="cell.key"
                    type="button"
                    class="lr-day"
                    :class="{
                        muted: !cell.currentMonth,
                        selected: isSelectedDate(cell.date),
                        inrange: isInRange(cell.date)
                    }"
                    @click="pickTempDate(cell.date)"
                >
                    {{ cell.day }}
                </button>
            </div>

            <div class="lr-date-actions large">
                <button type="button" class="btn-cancel" @click="closeDatePicker">Cancel</button>
                <button type="button" class="btn-apply" @click="applyTempDateRange">Apply</button>
            </div>
        </div>
    </div>
</div>
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
// Date range for Created By
const createdByDateRange = ref({ from: '', to: '' })
const showCreatedByDateModal = ref(false)
const activeDateField = ref('') // 'created_by'
const tempDateRange = ref({ from: '', to: '' })
const tempPreset = ref('')
const tempStartDate = ref(null)
const tempEndDate = ref(null)
const selectedPreset = ref('')
const startDate = ref(null)
const endDate = ref(null)
const calendarMonth = ref(new Date())
const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']


const createdByDateDisplay = computed(() => {
    if (createdByDateRange.value.from && createdByDateRange.value.to) {
        if (createdByDateRange.value.from === createdByDateRange.value.to) {
            return formatDateOnlyLong(createdByDateRange.value.from, '') || createdByDateRange.value.from
        }
        const fromFormatted = formatDateOnlyLong(createdByDateRange.value.from, '') || createdByDateRange.value.from
        const toFormatted = formatDateOnlyLong(createdByDateRange.value.to, '') || createdByDateRange.value.to
        return `${fromFormatted} - ${toFormatted}`
    }
    if (createdByDateRange.value.from) return `From ${formatDateOnlyLong(createdByDateRange.value.from, '') || createdByDateRange.value.from}`
    if (createdByDateRange.value.to) return `Until ${formatDateOnlyLong(createdByDateRange.value.to, '') || createdByDateRange.value.to}`
    return 'Any Date'
})


// Date presets
const datePresets = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'last_week', label: 'Last Week' },
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'custom_date', label: 'Custom Date' },
]

const monthLabel = computed(() => calendarMonth.value.toLocaleString('en-US', { month: 'long', year: 'numeric' }))

const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
const formatYmd = (d) => d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : ''
const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
const inRange = (d, a, b) => a && b && startOfDay(d) >= startOfDay(a) && startOfDay(d) <= startOfDay(b)

const calendarCells = computed(() => {
    const y = calendarMonth.value.getFullYear()
    const m = calendarMonth.value.getMonth()
    const first = new Date(y, m, 1)
    const offset = first.getDay()
    const daysInMonth = new Date(y, m + 1, 0).getDate()
    const prevDays = new Date(y, m, 0).getDate()
    const cells = []

    for (let i = offset - 1; i >= 0; i -= 1) {
        const day = prevDays - i
        const date = new Date(y, m - 1, day)
        cells.push({ key: `p-${day}`, day, date, currentMonth: false })
    }
    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = new Date(y, m, day)
        cells.push({ key: `c-${day}`, day, date, currentMonth: true })
    }
    while (cells.length < 42) {
        const day = cells.length - (offset + daysInMonth) + 1
        const date = new Date(y, m + 1, day)
        cells.push({ key: `n-${day}`, day, date, currentMonth: false })
    }
    return cells
})

function openDatePicker(fieldId = 'created_by') {
    activeDateField.value = fieldId
    
    // Load current range
    let currentRange = { ...createdByDateRange.value }
    tempDateRange.value = { ...currentRange }
    tempStartDate.value = currentRange.from ? new Date(currentRange.from) : null
    tempEndDate.value = currentRange.to ? new Date(currentRange.to) : null
    
    // Find preset if matches
    tempPreset.value = ''
    for (const preset of datePresets) {
        if (preset.value !== 'custom_date') {
            const range = getDateRangeFromPreset(preset.value)
            if (range.from === currentRange.from && range.to === currentRange.to) {
                tempPreset.value = preset.value
                break
            }
        }
    }
    if (tempDateRange.value.from && tempDateRange.value.to && !tempPreset.value) {
        tempPreset.value = 'custom_date'
    }
    
    showCreatedByDateModal.value = true
}

function getDateRangeFromPreset(preset) {
    const now = new Date()
    let from = null
    let to = null
    
    switch (preset) {
        case 'today':
            from = toYmd(now)
            to = toYmd(now)
            break
        case 'yesterday':
            const yesterday = new Date(now)
            yesterday.setDate(now.getDate() - 1)
            from = toYmd(yesterday)
            to = toYmd(yesterday)
            break
        case 'this_week': {
            const start = new Date(now)
            const day = now.getDay()
            const diff = day === 0 ? 6 : day - 1
            start.setDate(now.getDate() - diff)
            const end = new Date(start)
            end.setDate(start.getDate() + 6)
            from = toYmd(start)
            to = toYmd(end)
            break
        }
        case 'last_week': {
            const start = new Date(now)
            const day = now.getDay()
            const diff = day === 0 ? 6 : day - 1
            start.setDate(now.getDate() - diff - 7)
            const end = new Date(start)
            end.setDate(start.getDate() + 6)
            from = toYmd(start)
            to = toYmd(end)
            break
        }
        case 'this_month':
            from = toYmd(new Date(now.getFullYear(), now.getMonth(), 1))
            to = toYmd(new Date(now.getFullYear(), now.getMonth() + 1, 0))
            break
        case 'last_month':
            from = toYmd(new Date(now.getFullYear(), now.getMonth() - 1, 1))
            to = toYmd(new Date(now.getFullYear(), now.getMonth(), 0))
            break
        case 'last_year':
            from = toYmd(new Date(now.getFullYear() - 1, 0, 1))
            to = toYmd(new Date(now.getFullYear() - 1, 11, 31))
            break
    }
    return { from, to }
}

function toYmd(d) {
    if (!d) return null
    const date = new Date(d)
    if (Number.isNaN(date.getTime())) return null
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function selectTempPresetRange(preset) {
    tempPreset.value = preset
    if (preset === 'custom_date') return
    
    const range = getDateRangeFromPreset(preset)
    if (range.from && range.to) {
        tempDateRange.value = range
        tempStartDate.value = new Date(range.from)
        tempEndDate.value = new Date(range.to)
    }
}

function pickTempDate(date) {
    if (!tempStartDate.value || (tempStartDate.value && tempEndDate.value)) {
        tempStartDate.value = date
        tempEndDate.value = null
        tempPreset.value = 'custom_date'
        return
    }
    if (date < tempStartDate.value) {
        tempEndDate.value = tempStartDate.value
        tempStartDate.value = date
    } else {
        tempEndDate.value = date
    }
    // Update range
    if (tempStartDate.value && tempEndDate.value) {
        tempDateRange.value = {
            from: toYmd(tempStartDate.value),
            to: toYmd(tempEndDate.value)
        }
    } else if (tempStartDate.value && !tempEndDate.value) {
        tempDateRange.value = {
            from: toYmd(tempStartDate.value),
            to: toYmd(tempStartDate.value)
        }
    }
    tempPreset.value = 'custom_date'
}

function applyTempDateRange() {
    if (activeDateField.value === 'created_by') {
        createdByDateRange.value = { ...tempDateRange.value }
        // Update form value for search
        if (tempDateRange.value.from || tempDateRange.value.to) {
            form.value.created_by_date = 'custom'
        } else {
            form.value.created_by_date = null
        }
    }
    showCreatedByDateModal.value = false
}

function closeDatePicker() {
    showCreatedByDateModal.value = false
}

function changeMonth(delta) {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + delta, 1)
}

const isSelectedDate = (date) => sameDay(date, tempStartDate.value) || sameDay(date, tempEndDate.value)
const isInRange = (date) => inRange(date, tempStartDate.value, tempEndDate.value)
const defaultFieldSettings = {
  name: true,
  stage_changed_by: true,
  stage_group: true,
  responsible_person: true,
  modified_by: true,
  created_by: true,
  buyer_first_name: true,
//   buyer_last_name: true,
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
      { id: 'created_by', label: 'Created By' },
    ],
  },
  {
    id: 'buyer-details',
    tab: 'deals',
    label: 'Buyer Details',
    fields: [
      { id: 'buyer_first_name', label: 'Name' },
    //   { id: 'buyer_last_name', label: 'Last Name' },
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
      { id: 'property_area', label: 'Property Address' },
      { id: 'property_type', label: 'Property Type' },
      { id: 'property_bedrooms', label: 'Bedrooms' },
      { id: 'property_unit_no', label: 'Unit No' },
      { id: 'property_unit_size', label: 'Unit Size' },
      // { id: 'property_project_name', label: 'Project Name' },
      { id: 'property_developer', label: 'Developer' },
      // { id: 'property_sub_community', label: 'Sub Community' },
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
      { id: 'modified_by', label: 'Created By' },
      { id: 'stage_group', label: 'Status' },
    ],
  },
]

const form = ref({
  // Activity fields
  deal_name: '',
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
    { value: 'qatari', text: 'Qatari' },
  { value: 'kuwaiti', text: 'Kuwaiti' },
  { value: 'bahraini', text: 'Bahraini' },
  { value: 'omani', text: 'Omani' },
  { value: 'american', text: 'American' },
  { value: 'canadian', text: 'Canadian' },
  { value: 'british', text: 'British' },
  { value: 'french', text: 'French' },
  { value: 'german', text: 'German' },
  { value: 'italian', text: 'Italian' },
  { value: 'spanish', text: 'Spanish' },
  { value: 'dutch', text: 'Dutch' },
  { value: 'swedish', text: 'Swedish' },
  { value: 'norwegian', text: 'Norwegian' },
  { value: 'danish', text: 'Danish' },
  { value: 'finnish', text: 'Finnish' },
  { value: 'polish', text: 'Polish' },
  { value: 'ukrainian', text: 'Ukrainian' },
  { value: 'russian', text: 'Russian' },
    { value: 'other', text: 'Other' },

]
// UAE City Options
const uaeCityOptions = [
    { value: 'Abu Dhabi', text: 'Abu Dhabi' },
    { value: 'Dubai', text: 'Dubai' },
    { value: 'Sharjah', text: 'Sharjah' },
    { value: 'Ajman', text: 'Ajman' },
    { value: 'Ras Al Khaimah', text: 'Ras Al Khaimah' },
    { value: 'Umm Al Quwain', text: 'Umm Al Quwain' },
    { value: 'Fujairah', text: 'Fujairah' },
    { value: 'Al Ain', text: 'Al Ain' }
]
const residencyOptions = [
  { value: 'resident', text: 'Resident' },
{ value: 'non_resident', text: 'Non Resident' }
]


const countryOptions = [
  { value: "Afghanistan", text: "Afghanistan" },
  { value: "Albania", text: "Albania" },
  { value: "Algeria", text: "Algeria" },
  { value: "Andorra", text: "Andorra" },
  { value: "Angola", text: "Angola" },
  { value: "Argentina", text: "Argentina" },
  { value: "Armenia", text: "Armenia" },
  { value: "Australia", text: "Australia" },
  { value: "Austria", text: "Austria" },
  { value: "Azerbaijan", text: "Azerbaijan" },
  { value: "Bahrain", text: "Bahrain" },
  { value: "Bangladesh", text: "Bangladesh" },
  { value: "Belarus", text: "Belarus" },
  { value: "Belgium", text: "Belgium" },
  { value: "Belize", text: "Belize" },
  { value: "Benin", text: "Benin" },
  { value: "Bhutan", text: "Bhutan" },
  { value: "Bolivia", text: "Bolivia" },
  { value: "Bosnia and Herzegovina", text: "Bosnia and Herzegovina" },
  { value: "Botswana", text: "Botswana" },
  { value: "Brazil", text: "Brazil" },
  { value: "Brunei", text: "Brunei" },
  { value: "Bulgaria", text: "Bulgaria" },
  { value: "Burkina Faso", text: "Burkina Faso" },
  { value: "Burundi", text: "Burundi" },
  { value: "Cambodia", text: "Cambodia" },
  { value: "Cameroon", text: "Cameroon" },
  { value: "Canada", text: "Canada" },
  { value: "Chad", text: "Chad" },
  { value: "Chile", text: "Chile" },
  { value: "China", text: "China" },
  { value: "Colombia", text: "Colombia" },
  { value: "Comoros", text: "Comoros" },
  { value: "Congo", text: "Congo" },
  { value: "Costa Rica", text: "Costa Rica" },
  { value: "Croatia", text: "Croatia" },
  { value: "Cuba", text: "Cuba" },
  { value: "Cyprus", text: "Cyprus" },
  { value: "Czech Republic", text: "Czech Republic" },
  { value: "Denmark", text: "Denmark" },
  { value: "Djibouti", text: "Djibouti" },
  { value: "Dominican Republic", text: "Dominican Republic" },
  { value: "Ecuador", text: "Ecuador" },
  { value: "Egypt", text: "Egypt" },
  { value: "El Salvador", text: "El Salvador" },
  { value: "Estonia", text: "Estonia" },
  { value: "Ethiopia", text: "Ethiopia" },
  { value: "Finland", text: "Finland" },
  { value: "France", text: "France" },
  { value: "Gabon", text: "Gabon" },
  { value: "Georgia", text: "Georgia" },
  { value: "Germany", text: "Germany" },
  { value: "Ghana", text: "Ghana" },
  { value: "Greece", text: "Greece" },
  { value: "Guatemala", text: "Guatemala" },
  { value: "Haiti", text: "Haiti" },
  { value: "Honduras", text: "Honduras" },
  { value: "Hungary", text: "Hungary" },
  { value: "Iceland", text: "Iceland" },
  { value: "India", text: "India" },
  { value: "Indonesia", text: "Indonesia" },
  { value: "Iran", text: "Iran" },
  { value: "Iraq", text: "Iraq" },
  { value: "Ireland", text: "Ireland" },
  { value: "Israel", text: "Israel" },
  { value: "Italy", text: "Italy" },
  { value: "Jamaica", text: "Jamaica" },
  { value: "Japan", text: "Japan" },
  { value: "Jordan", text: "Jordan" },
  { value: "Kazakhstan", text: "Kazakhstan" },
  { value: "Kenya", text: "Kenya" },
  { value: "Kuwait", text: "Kuwait" },
  { value: "Kyrgyzstan", text: "Kyrgyzstan" },
  { value: "Laos", text: "Laos" },
  { value: "Latvia", text: "Latvia" },
  { value: "Lebanon", text: "Lebanon" },
  { value: "Libya", text: "Libya" },
  { value: "Lithuania", text: "Lithuania" },
  { value: "Luxembourg", text: "Luxembourg" },
  { value: "Madagascar", text: "Madagascar" },
  { value: "Malaysia", text: "Malaysia" },
  { value: "Maldives", text: "Maldives" },
  { value: "Mali", text: "Mali" },
  { value: "Malta", text: "Malta" },
  { value: "Mexico", text: "Mexico" },
  { value: "Moldova", text: "Moldova" },
  { value: "Monaco", text: "Monaco" },
  { value: "Mongolia", text: "Mongolia" },
  { value: "Montenegro", text: "Montenegro" },
  { value: "Morocco", text: "Morocco" },
  { value: "Mozambique", text: "Mozambique" },
  { value: "Myanmar", text: "Myanmar" },
  { value: "Namibia", text: "Namibia" },
  { value: "Nepal", text: "Nepal" },
  { value: "Netherlands", text: "Netherlands" },
  { value: "New Zealand", text: "New Zealand" },
  { value: "Nicaragua", text: "Nicaragua" },
  { value: "Niger", text: "Niger" },
  { value: "Nigeria", text: "Nigeria" },
  { value: "North Korea", text: "North Korea" },
  { value: "Norway", text: "Norway" },
  { value: "Oman", text: "Oman" },
  { value: "Pakistan", text: "Pakistan" },
  { value: "Panama", text: "Panama" },
  { value: "Paraguay", text: "Paraguay" },
  { value: "Peru", text: "Peru" },
  { value: "Philippines", text: "Philippines" },
  { value: "Poland", text: "Poland" },
  { value: "Portugal", text: "Portugal" },
  { value: "Qatar", text: "Qatar" },
  { value: "Romania", text: "Romania" },
  { value: "Russia", text: "Russia" },
  { value: "Rwanda", text: "Rwanda" },
  { value: "Saudi Arabia", text: "Saudi Arabia" },
  { value: "Senegal", text: "Senegal" },
  { value: "Serbia", text: "Serbia" },
  { value: "Singapore", text: "Singapore" },
  { value: "Slovakia", text: "Slovakia" },
  { value: "Slovenia", text: "Slovenia" },
  { value: "Somalia", text: "Somalia" },
  { value: "South Africa", text: "South Africa" },
  { value: "South Korea", text: "South Korea" },
  { value: "Spain", text: "Spain" },
  { value: "Sri Lanka", text: "Sri Lanka" },
  { value: "Sudan", text: "Sudan" },
  { value: "Sweden", text: "Sweden" },
  { value: "Switzerland", text: "Switzerland" },
  { value: "Syria", text: "Syria" },
  { value: "Taiwan", text: "Taiwan" },
  { value: "Tanzania", text: "Tanzania" },
  { value: "Thailand", text: "Thailand" },
  { value: "Tunisia", text: "Tunisia" },
  { value: "Turkey", text: "Turkey" },
  { value: "Uganda", text: "Uganda" },
  { value: "Ukraine", text: "Ukraine" },
  { value: "United Arab Emirates", text: "United Arab Emirates" },
  { value: "United Kingdom", text: "United Kingdom" },
  { value: "United States", text: "United States" },
  { value: "Uruguay", text: "Uruguay" },
  { value: "Uzbekistan", text: "Uzbekistan" },
  { value: "Venezuela", text: "Venezuela" },
  { value: "Vietnam", text: "Vietnam" },
  { value: "Yemen", text: "Yemen" },
  { value: "Zambia", text: "Zambia" },
  { value: "Zimbabwe", text: "Zimbabwe" },
  { value: "Other", text: "Other" }
];

const languageOptions = [
  { value: 'arabic', text: 'Arabic' },
  { value: 'english', text: 'English' },
  { value: 'french', text: 'French' },
  { value: 'spanish', text: 'Spanish' },
  { value: 'german', text: 'German' },
  { value: 'italian', text: 'Italian' },
  { value: 'portuguese', text: 'Portuguese' },
  { value: 'russian', text: 'Russian' },
  { value: 'chinese', text: 'Chinese (Mandarin)' },
  { value: 'japanese', text: 'Japanese' },
  { value: 'korean', text: 'Korean' },
  { value: 'hindi', text: 'Hindi' },
  { value: 'urdu', text: 'Urdu' },
  { value: 'bengali', text: 'Bengali' },
  { value: 'turkish', text: 'Turkish' },
  { value: 'persian', text: 'Persian (Farsi)' },
  { value: 'swahili', text: 'Swahili' },
  { value: 'hausa', text: 'Hausa' },
  { value: 'amharic', text: 'Amharic' },
  { value: 'dutch', text: 'Dutch' },
  { value: 'greek', text: 'Greek' },
  { value: 'hebrew', text: 'Hebrew' },
  { value: 'thai', text: 'Thai' },
  { value: 'vietnamese', text: 'Vietnamese' },
  { value: 'malay', text: 'Malay' },
  { value: 'indonesian', text: 'Indonesian' },
  { value: 'filipino', text: 'Filipino (Tagalog)' },
  { value: 'polish', text: 'Polish' },
  { value: 'ukrainian', text: 'Ukrainian' },
  { value: 'czech', text: 'Czech' },
  { value: 'romanian', text: 'Romanian' },
  { value: 'hungarian', text: 'Hungarian' },
  { value: 'swedish', text: 'Swedish' },
  { value: 'norwegian', text: 'Norwegian' },
  { value: 'danish', text: 'Danish' },
  { value: 'finnish', text: 'Finnish' },
  { value: 'other', text: 'Other' }
];

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
    const areasData = response.data.data || response.data || []
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
// إضافة متغيرات لتخزين البيانات
const localAreas = ref([])
const localPropertyTypes = ref([])
const localDevelopers = ref([])
const isLoadingData = ref(false)

// دالة لجلب المناطق
const fetchAreasData = async () => {
    try {
        const res = await api.get('/listings/areas/?has_listings=true')
        const data = res.data.data || res.data || []
        localAreas.value = data.map(area => ({
            id: area.id,
            name: area.name || area.title,
            area_parents_title: area.area_parents_title || ''
        }))
        console.log('Areas loaded:', localAreas.value.length)
    } catch (error) {
        console.error('Error fetching areas:', error)
        localAreas.value = []
    }
}

// دالة لجلب أنواع العقارات
const fetchPropertyTypesData = async () => {
    try {
        const res = await api.get('/listings/property-types')
        const data = res.data.data || res.data
        localPropertyTypes.value = data.map(type => ({
            id: type.id,
            name: type.name
        }))
        console.log('Property types loaded:', localPropertyTypes.value.length)
    } catch (error) {
        console.error('Error fetching property types:', error)
        localPropertyTypes.value = []
    }
}

const fetchDevelopersData = async () => {
    try {
        const res = await api.get('/listings/developers')
        const data = res.data.data || res.data || []
        localDevelopers.value = data.map(dev => ({
            id: dev.id,
            name: dev.name
        }))
        console.log('Developers loaded:', localDevelopers.value.length)
    } catch (error) {
        console.error('Error fetching developers:', error)
        localDevelopers.value = []
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
    createdByDateRange.value = { from: '', to: '' } 
  form.value = {
    deal_name: '',
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

  // queryKey defaults to id, but pass it explicitly when the actual query param differs
  // (e.g. deal_name → query.search) so removeFilter() in kanban_deal.vue can delete the
  // right slot from the API query.
  const pushFilter = (id, label, value, queryKey = id) => {
    if (value !== null && value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0)) {
      activeFilters.push({ id, label, value, queryKey })
    }
  }

  // Activity fields
  if (form.value.deal_name) {
    query.search = form.value.deal_name.trim()
    pushFilter('deal_name', 'Name', form.value.deal_name, 'search')
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
  if (form.value.stage_changed_by) {
    query.stage_changed_by = form.value.stage_changed_by
    const p = personOptions.value.find((u) => u.value === form.value.stage_changed_by)
    pushFilter('stage_changed_by', 'Stage Changed By', p?.text || form.value.stage_changed_by)
  }
  if (form.value.modified_by) {
    query.modified_by = form.value.modified_by
    const p = personOptions.value.find((u) => u.value === form.value.modified_by)
    pushFilter('modified_by', 'Modified By', p?.text || form.value.modified_by)
  }
 // في دالة applySearch، استبدل جزء created_by_date بهذا:
if (createdByDateRange.value.from || createdByDateRange.value.to) {
    if (createdByDateRange.value.from) query.created_from = createdByDateRange.value.from
    if (createdByDateRange.value.to) query.created_to = createdByDateRange.value.to
    pushFilter('created_by', 'Created By', createdByDateDisplay.value)
}
  // Buyer fields
  if (form.value.buyer_first_name) {
    query.buyer_first_name = form.value.buyer_first_name
    pushFilter('buyer_first_name', 'Buyer Name', form.value.buyer_first_name)
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

  await Promise.all([fetchUsers(), fetchStages(),fetchAreasData(),
        fetchPropertyTypesData(),
        fetchDevelopersData()])
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
    top: 8px;
    right: -61px;
    width: 83px;
    height: 49px;
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

.custom-date-trigger {
    width: 100%;
    height: 40px;
    border-radius: 9px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 12px;
    color: #64748B;
    font-family: 'Montserrat';
}

.custom-date-trigger:hover {
    border-color: #cbd5e1;
}

/* LR Modal Backdrop & Date Modal Styles */
.lr-modal-backdrop {
    position: inherit;
    inset: 0;
    background: rgba(2, 6, 23, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10070;
    padding: 12px;
}

.lr-date-modal {
    width: min(860px, 96vw);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 25px 80px rgba(2, 6, 23, 0.25);
    display: grid;
    grid-template-columns: 220px 1fr;
    overflow: hidden;
}

.lr-date-left {
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lr-date-preset {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    padding: 7px 10px;
    font-size: 12px;
    color: #334155;
    text-align: left;
    transition: all .15s ease;
    cursor: pointer;
}

.lr-date-preset.active {
    background: #01062C;
    border-color: #01062C;
    color: #fff;
}

.lr-date-preset:hover:not(.active) {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.lr-date-right {
    padding: 14px;
}

.lr-calendar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 700;
    color: #0f172a;
}

.lr-calendar-head button {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 9px;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
    cursor: pointer;
    transition: all 0.2s;
}

.lr-calendar-head button:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.lr-calendar-head button iconify-icon {
    font-size: 16px;
    line-height: 1;
}

.lr-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-bottom: 6px;
}

.lr-weekdays span {
    text-align: center;
    font-size: 11px;
    color: #64748b;
    font-weight: 700;
}

.lr-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
}

.lr-day {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    min-height: 34px;
    font-size: 12px;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    transition: all 0.2s;
}

.lr-day:hover:not(.muted) {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.lr-day.muted {
    opacity: .45;
}

.lr-day.selected {
    background: #01062C;
    border-color: #01062C;
    color: #fff;
}

.lr-day.inrange:not(.selected) {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.lr-date-actions.large {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 13px;
    color: #01062C;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: #e8e8e8;
}

.btn-apply {
    background: #000;
    border: none;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 13px;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-apply:hover {
    background: #1a1a1a;
}

/* Responsive */
@media (max-width: 640px) {
    .lr-date-modal {
        grid-template-columns: 1fr;
        width: 95vw;
    }
    
    .lr-date-left {
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .lr-date-preset {
        padding: 5px 10px;
        font-size: 11px;
    }
    
    .lr-calendar-grid {
        gap: 4px;
    }
    
    .lr-day {
        min-height: 30px;
        font-size: 11px;
    }
    
    .lr-weekdays span {
        font-size: 10px;
    }
}
</style>