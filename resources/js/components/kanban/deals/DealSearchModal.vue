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
                />
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
                />
              </div>
              <div v-if="fieldSettings.stage_group" class="col-md-6">
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
                />
              </div>
            </div>
          </div>

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
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon" />
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
                />
              </div>
              <div v-if="fieldSettings.created_by" class="col-md-6">
                <label class="form-label-custom">Created By</label>
                <v-select
                  v-model="form.created_by_date"
                  :options="datePresetOptions"
                  :reduce="opt => opt.value"
                  label="text"
                  class="custom-v-select deal-select-placeholder"
                  placeholder="Any Date"
                  data-placeholder="Any Date"
                  :searchable="true"
                  :clearable="true"
                  append-to-body
                />
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
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import DealFilterFieldSettingsModal from './DealFilterFieldSettingsModal.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  asDropdown: { type: Boolean, default: true },
  currentQuery: { type: Object, default: null },
  dealType: { type: String, default: 'primary' },
})

const emit = defineEmits(['update:modelValue', 'search'])

const defaultAvatar = '/assets/images/placeholder-user.jpg'
const showFieldSettings = ref(false)
const activePill = ref('deals-in-progress')

const sidebarPills = [
  { id: 'closed-deals', label: 'Closed Deals' },
  { id: 'deals-in-progress', label: 'Deals In Progress' },
  { id: 'my-deals', label: 'My Deals' },
  { id: 'test-deals', label: 'Test Deals' },
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
  buyer_amount_currency: false,
  buyer_email: false,
  buyer_residency_status: false,
  buyer_country_residence: false,
  buyer_nationality: false,
  buyer_city_residence: false,
  buyer_nationality_secondary: false,
  property_unit_no: true,
  property_type: true,
  property_bedrooms: false,
  property_project_name: true,
  property_developer: false,
  property_area: false,
  property_sub_community: false,
  property_building_cluster: false,
  property_unit_size: false,
}
const fieldSettings = ref({ ...defaultFieldSettings })

const fieldSettingsTabs = [
  { id: 'buyer', label: 'Buyer Details' },
  { id: 'contact', label: 'Contact' },
  { id: 'activity', label: 'Activity' },
]
const fieldSettingsSections = [
  {
    id: 'buyer-details',
    tab: 'buyer',
    label: 'Buyer Details',
    fields: [
      { id: 'buyer_first_name', label: 'First Name' },
      { id: 'buyer_last_name', label: 'Last Name' },
      { id: 'buyer_date_of_birth', label: 'Date Of Birth' },
      { id: 'buyer_amount_currency', label: 'Amount & Currency' },
      { id: 'buyer_phone', label: 'Phone Number' },
      { id: 'buyer_email', label: 'Email' },
      { id: 'buyer_residency_status', label: 'Residency Status' },
      { id: 'buyer_country_residence', label: 'Country Of Residence' },
      { id: 'buyer_nationality', label: 'Buyer Nationality' },
      { id: 'buyer_city_residence', label: 'City Of Residence' },
      { id: 'buyer_nationality_secondary', label: 'Nationality' },
    ],
  },
  {
    id: 'property-details',
    tab: 'contact',
    label: 'Property Details',
    fields: [
      { id: 'property_unit_no', label: 'Unit No' },
      { id: 'property_type', label: 'Property Type' },
      { id: 'property_bedrooms', label: 'Bedrooms' },
      { id: 'property_project_name', label: 'Project Name' },
      { id: 'property_developer', label: 'Developer' },
      { id: 'property_area', label: 'Area' },
      { id: 'property_sub_community', label: 'Sub Community' },
      { id: 'property_building_cluster', label: 'Building/Cluster' },
      { id: 'property_unit_size', label: 'Unit Size' },
    ],
  },
  {
    id: 'activity',
    tab: 'activity',
    label: 'Activity',
    fields: [
      { id: 'name', label: 'Name' },
      { id: 'stage_changed_by', label: 'Stage Changed By' },
      { id: 'created_by', label: 'Created By' },
      { id: 'responsible_person', label: 'Responsible Person' },
      { id: 'stage_group', label: 'Stage Group' },
      { id: 'modified_by', label: 'Modified By' },
      { id: 'end_date', label: 'End Date' },
    ],
  },
]

const form = ref({
  deal_name: '',
  end_date: null,
  stage_changed_by: null,
  stage_group: [],
  responsible_person_id: null,
  modified_by: null,
  created_by_date: null,
})

const people = ref([])
const stages = ref([])

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

const resetForm = () => {
  form.value = {
    deal_name: '',
    end_date: null,
    stage_changed_by: null,
    stage_group: [],
    responsible_person_id: null,
    modified_by: null,
    created_by_date: null,
  }
  emit('search', { query: null, activeFilters: [] })
}

const restoreDefaultSearchFields = () => {
  fieldSettings.value = { ...defaultFieldSettings }
  resetForm()
}

const applyFieldSettings = (settings) => {
  fieldSettings.value = { ...fieldSettings.value, ...(settings || {}) }
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

  const pushFilter = (id, label, value) => activeFilters.push({ id, label, value, queryKey: id })

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
  if (form.value.stage_changed_by && !query.responsible_id) {
    query.responsible_id = form.value.stage_changed_by
    const p = personOptions.value.find((u) => u.value === form.value.stage_changed_by)
    pushFilter('stage_changed_by', 'Stage Changed By', p?.text || form.value.stage_changed_by)
  }
  if (form.value.modified_by) {
    const p = personOptions.value.find((u) => u.value === form.value.modified_by)
    if (!query.search && p?.text) query.search = p.text
    pushFilter('modified_by', 'Modified By', p?.text || form.value.modified_by)
  }
  if (form.value.created_by_date && form.value.created_by_date !== 'any') {
    const range = presetRange(form.value.created_by_date)
    if (range) Object.assign(query, range)
    pushFilter('created_by', 'Created By', form.value.created_by_date)
  }

  emit('search', { query: Object.keys(query).length ? query : null, activeFilters })
  emit('update:modelValue', false)
}

watch(
  () => props.currentQuery,
  (q) => {
    if (!q || typeof q !== 'object') return
    form.value.deal_name = q.search || ''
    form.value.stage_group = q.stage_id ? [q.stage_id] : []
    form.value.responsible_person_id = q.responsible_id || null
  },
  { immediate: true },
)

watch(() => props.dealType, fetchStages)

onMounted(async () => {
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
  overflow: hidden;
}

.deal-search-container {
  min-height: 460px;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}

.close-btn {
  position: absolute;
  top: 0;
  right: 12px;
  border: none;
  background: transparent;
  font-size: 22px;
  color: #000;
  cursor: pointer;
  z-index: 10;
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

.custom-input {
  height: 40px !important;
  border-radius: 9px !important;
  border: 1px solid #e2e8f0 !important;
  font-size: 12px !important;
  color: #64748b !important;
  font-family: var(--deal-font, 'Montserrat', sans-serif);
}

.custom-input::placeholder {
  color: #94a3b8 !important;
  font-size: 12px !important;
}

.deal-input-placeholder::placeholder {
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  opacity: 1 !important;
}

::deep(.custom-v-select) {
  font-family: 'Montserrat', sans-serif;
}

::deep(.custom-v-select .vs__dropdown-toggle) {
  height: 40px;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0 8px;
}

::deep(.custom-v-select .vs__selected-options) {
  flex-wrap: nowrap;
  overflow: hidden;
  max-width: calc(100% - 30px);
  min-width: 0;
}

::deep(.custom-v-select .vs__selected) {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  padding: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
  max-width: 100%;
  line-height: 38px;
}

::deep(.custom-v-select .vs__selected.vs__selected--disabled),
::deep(.custom-v-select .vs__selected:empty) {
  color: #94a3b8;
  font-size: 12px;
}

::deep(.custom-v-select .vs__search) {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  padding: 0;
  min-width: 100% !important;
  width: 100% !important;
  flex: 1 0 auto !important;
  opacity: 1 !important;
}

::deep(.custom-v-select .vs__search::placeholder) {
  color: #94a3b8;
  font-size: 12px;
  opacity: 1;
}

::deep(.custom-v-select.vs--unsearchable:not(.vs--disabled) .vs__search) {
  cursor: pointer !important;
  color: #64748b !important;
  font-size: 12px !important;
}

::deep(.custom-v-select .vs__placeholder) {
  color: #94a3b8 !important;
  font-size: 12px !important;
  opacity: 1 !important;
}

::deep(.custom-v-select.vs--single .vs__selected) {
  color: #64748b;
}

::deep(.custom-v-select.vs--single:not(.vs--has-value) .vs__selected) {
  color: #94a3b8 !important;
  font-size: 12px !important;
}

::deep(.custom-v-select.vs--single .vs__selected),
::deep(.custom-v-select.vs--single .vs__placeholder),
::deep(.custom-v-select.vs--unsearchable .vs__search),
::deep(.custom-v-select .vs__search::placeholder) {
  color: #64748b !important;
  font-size: 12px !important;
}

::deep(.custom-v-select.vs--single:not(.vs--has-value) .vs__selected),
::deep(.custom-v-select.vs--single .vs__placeholder),
::deep(.custom-v-select.vs--unsearchable.vs--single:not(.vs--has-value) .vs__search::placeholder) {
  color: #94a3b8 !important;
}

::deep(.deal-select-placeholder .vs__placeholder),
::deep(.deal-select-placeholder .vs__search::placeholder),
::deep(.deal-select-placeholder.vs--single:not(.vs--has-value) .vs__selected) {
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  opacity: 1 !important;
}

/* Force actual input element style (matches what works in inspect) */
:deep(.deal-select-placeholder input.vs__search) {
  color: #64748b !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
}

:deep(.deal-select-placeholder input.vs__search::placeholder) {
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  opacity: 1 !important;
}

/* Hard fallback: always show placeholders for empty single selects */
::deep(.deal-select-placeholder.vs--single:not(.vs--has-value) .vs__selected-options) {
  position: relative;
}

::deep(.deal-select-placeholder.vs--single:not(.vs--has-value)::before) {
  content: attr(data-placeholder);
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8 !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  font-family: 'Montserrat', sans-serif !important;
  pointer-events: none;
  z-index: 3;
}

::deep(.deal-select-placeholder.vs--single:not(.vs--has-value) .vs__search) {
  opacity: 0 !important;
}

::deep(.custom-v-select .vs__actions) {
  padding: 0 8px;
}

::deep(.custom-v-select .vs__open-indicator-icon) {
  font-size: 13px;
  color: #cfdbec;
}

::deep(.custom-v-select .vs__dropdown-menu) {
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  padding: 0;
  z-index: 12050;
}

::deep(.custom-v-select .vs__dropdown-option) {
  padding: 5px 10px;
  font-size: 12px;
  color: #475569;
  transition: all 0.2s;
}

::deep(.custom-v-select .vs__dropdown-option--highlight),
::deep(.custom-v-select .vs__dropdown-option--selected) {
  background: #faa300 !important;
  color: #fff !important;
}

::deep(.deal-search-rp-select .vs__dropdown-menu) {
  max-height: min(360px, 55vh) !important;
}

::deep(.deal-search-rp-select .vs__dropdown-option) {
  padding: 8px 10px !important;
  white-space: normal !important;
}

::deep(.deal-search-rp-select .vs__selected) {
  line-height: 1.25 !important;
  white-space: normal !important;
  min-height: 44px;
  display: flex !important;
  align-items: center !important;
  padding-top: 4px !important;
  padding-bottom: 4px !important;
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
</style>
