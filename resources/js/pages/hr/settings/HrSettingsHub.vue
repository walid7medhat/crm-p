<template>
  <div class="hr-set-overlay" @click.self="emit('close')">
    <div class="hr-set-modal">
      <header class="hr-set-top">
        <div class="hr-set-top__copy">
          <p class="hr-set-title">Settings</p>
          <p class="hr-set-sub">Manage catalogs for each HR module</p>
        </div>
        <button type="button" class="hr-set-close" @click="emit('close')">
          <iconify-icon icon="lucide:x" />
        </button>
      </header>

      <div class="hr-set-layout">
        <aside class="hr-set-nav">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="hr-set-nav__item"
            :class="{ 'is-active': activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            <span class="hr-set-nav__icon"><iconify-icon :icon="tab.icon" /></span>
            <span>
              <strong>{{ tab.label }}</strong>
              <small>{{ tab.hint }}</small>
            </span>
          </button>
        </aside>

        <section class="hr-set-content">
          <div v-if="activeTab === 'employees'" class="hr-set-stack">
            <div class="hr-set-subnav">
              <button type="button" :class="{ 'is-active': employeeSub === 'departments' }" @click="employeeSub = 'departments'">Departments</button>
              <button type="button" :class="{ 'is-active': employeeSub === 'designations' }" @click="employeeSub = 'designations'">Designations</button>
              <button type="button" :class="{ 'is-active': employeeSub === 'branches' }" @click="employeeSub = 'branches'">Branches</button>
            </div>
            <HrSettingsCatalog
              v-if="employeeSub === 'departments'"
              ref="departmentsCatalog"
              title="Departments"
              description="Used on employee profiles, leave, assets, and recruitment jobs."
              add-label="Add department"
              search-placeholder="Search departments"
              empty-text="No departments yet."
              :items="sortedDepartments"
              :loading="loading.employees"
              :saving="saving.department"
              :fields="departmentFields"
              @save="(payload, editing) => saveDepartment(payload, editing)"
              @remove="removeDepartment"
            />
            <HrSettingsCatalog
              v-else-if="employeeSub === 'designations'"
              ref="designationsCatalog"
              title="Designations"
              description="Job titles shown on employee cards and filters."
              add-label="Add designation"
              search-placeholder="Search designations"
              empty-text="No designations yet."
              :items="sortedDesignations"
              :loading="loading.employees"
              :saving="saving.designation"
              :fields="designationFields"
              :subtitle="designationSubtitle"
              @save="(payload, editing) => saveDesignation(payload, editing)"
              @remove="removeDesignation"
            />
            <HrSettingsCatalog
              v-else
              ref="branchesCatalog"
              title="Branches"
              description="Office locations used when adding employees and assigning assets."
              add-label="Add branch"
              search-placeholder="Search branches"
              empty-text="No branches yet."
              :items="sortedBranches"
              :loading="loading.employees"
              :saving="saving.branch"
              :fields="branchFields"
              :subtitle="branchSubtitle"
              @save="(payload, editing) => saveBranch(payload, editing)"
              @remove="removeBranch"
            />
          </div>

          <div v-else-if="activeTab === 'documents'" class="hr-set-stack">
            <HrSettingsCatalog
              ref="documentsCatalog"
              title="Document types"
              description="These options appear when employees request documents from HR."
              add-label="Add document type"
              search-placeholder="Search document types"
              empty-text="No document types yet. Add Salary Certificate, Experience Letter, and more."
              :items="sortedDocumentTypes"
              :loading="loading.documents"
              :saving="saving.documentType"
              :fields="documentTypeFields"
              @save="(payload, editing) => saveDocumentType(payload, editing)"
              @remove="removeDocumentType"
            />
          </div>

          <div v-else-if="activeTab === 'expiry-alerts'" class="hr-set-stack">
            <section class="hr-set-panel">
              <div class="hr-set-panel__head">
                <p class="hr-set-panel__title">Expiry alert timing</p>
                <p>How many days before each document expires HR gets emailed and notified. Defaults to 15 days.</p>
              </div>
              <div v-if="loading.documentExpiry" class="hr-set-empty">Loading...</div>
              <form v-else class="hr-set-form is-open" @submit.prevent="saveDocumentExpirySettings">
                <div class="hr-set-form__grid is-wide">
                  <div class="hr-set-field">
                    <label>Passport <em class="required">*</em></label>
                    <input v-model.number="documentExpiryForm.passport_days" type="number" min="1" max="365" required />
                  </div>
                  <div class="hr-set-field">
                    <label>Labor Card <em class="required">*</em></label>
                    <input v-model.number="documentExpiryForm.labor_card_days" type="number" min="1" max="365" required />
                  </div>
                  <div class="hr-set-field">
                    <label>Emirates ID <em class="required">*</em></label>
                    <input v-model.number="documentExpiryForm.emirates_id_days" type="number" min="1" max="365" required />
                  </div>
                  <div class="hr-set-field">
                    <label>Residency Visa <em class="required">*</em></label>
                    <input v-model.number="documentExpiryForm.residency_days" type="number" min="1" max="365" required />
                  </div>
                </div>
                <div class="hr-set-form__actions">
                  <button type="submit" class="hr-set-btn hr-set-btn--primary" :disabled="saving.documentExpiry">
                    {{ saving.documentExpiry ? 'Saving...' : 'Save expiry alert settings' }}
                  </button>
                </div>
              </form>
            </section>
          </div>

          <div v-else-if="activeTab === 'leave'" class="hr-set-stack">
            <div class="hr-set-subnav">
              <button type="button" :class="{ 'is-active': leaveSub === 'all' }" @click="leaveSub = 'all'">All</button>
              <button type="button" :class="{ 'is-active': leaveSub === 'paid' }" @click="leaveSub = 'paid'">Paid</button>
              <button type="button" :class="{ 'is-active': leaveSub === 'half_paid' }" @click="leaveSub = 'half_paid'">Half paid</button>
              <button type="button" :class="{ 'is-active': leaveSub === 'unpaid' }" @click="leaveSub = 'unpaid'">Unpaid</button>
            </div>
            <HrSettingsCatalog
              ref="leaveCatalog"
              title="Leave types"
              description="Balances, apply-leave forms, and approval rules all use these types."
              add-label="Add leave type"
              search-placeholder="Search leave types"
              empty-text="No leave types in this group."
              :items="filteredLeaveTypes"
              :loading="loading.leave"
              :saving="saving.leaveType"
              :fields="leaveFields"
              :subtitle="leaveSubtitle"
              :badge="leaveBadge"
              :badge-class="leaveBadgeClass"
              @save="(payload, editing) => saveLeaveType(payload, editing)"
              @remove="removeLeaveType"
            />
          </div>

          <div v-else-if="activeTab === 'attendance'" class="hr-set-stack">
            <div class="hr-set-subnav">
              <button type="button" :class="{ 'is-active': attendanceSub === 'window' }" @click="attendanceSub = 'window'">Check-in window</button>
              <button type="button" :class="{ 'is-active': attendanceSub === 'departments' }" @click="attendanceSub = 'departments'">Departments</button>
            </div>
            <div v-if="loading.attendance" class="hr-set-empty">Loading attendance settings...</div>
            <form v-else class="hr-set-panel" @submit.prevent="saveAttendance">
              <template v-if="attendanceSub === 'window'">
                <div class="hr-set-panel__head">
                  <p class="hr-set-panel__title">Check-in window</p>
                  <p>Weekly day and time range when employees can submit the attendance code.</p>
                </div>
                <div class="hr-set-form__grid is-wide">
                  <div class="hr-set-field">
                    <label>Active day</label>
                    <select v-model.number="attendanceForm.day_of_week">
                      <option v-for="day in weekDays" :key="day.value" :value="day.value">{{ day.label }}</option>
                    </select>
                  </div>
                  <div class="hr-set-field">
                    <label>Window start</label>
                    <input v-model="attendanceForm.start_time" type="time" />
                  </div>
                  <div class="hr-set-field">
                    <label>Window end</label>
                    <input v-model="attendanceForm.end_time" type="time" />
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="hr-set-panel__head">
                  <p class="hr-set-panel__title">Departments required to check in</p>
                  <p>Leave empty to apply the window to every department.</p>
                </div>
                <div class="hr-set-chips">
                  <label v-for="dept in sortedDepartments" :key="dept.id" class="hr-set-chip">
                    <input type="checkbox" :value="dept.id" v-model="attendanceForm.department_ids" />
                    {{ dept.name }}
                  </label>
                  <span v-if="!departments.length" class="hr-set-help">Add departments in Employees settings first.</span>
                </div>
              </template>
              <div class="hr-set-form__actions">
                <button type="submit" class="hr-set-btn hr-set-btn--primary" :disabled="saving.attendance">
                  {{ saving.attendance ? 'Saving...' : 'Save attendance settings' }}
                </button>
              </div>
            </form>
          </div>

          <div v-else-if="activeTab === 'career'" class="hr-set-stack">
            <div class="hr-set-subnav">
              <button type="button" :class="{ 'is-active': careerSub === 'defaults' }" @click="careerSub = 'defaults'">Defaults</button>
              <button type="button" :class="{ 'is-active': careerSub === 'documents' }" @click="careerSub = 'documents'">Required documents</button>
              <button type="button" :class="{ 'is-active': careerSub === 'types' }" @click="careerSub = 'types'">Employment types</button>
            </div>
            <section v-if="careerSub === 'defaults'" class="hr-set-panel">
              <div class="hr-set-panel__head">
                <p class="hr-set-panel__title">Recruitment defaults</p>
                <p>Pre-fill new job posts. Hiring managers can still change them per job.</p>
              </div>
              <form class="hr-set-form is-open" @submit.prevent="saveCareerDefaults">
                <div class="hr-set-form__grid is-wide">
                  <div class="hr-set-field">
                    <label>Default job type</label>
                    <select v-model="careerForm.defaultJobType">
                      <option v-for="type in jobTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                  </div>
                  <div class="hr-set-field">
                    <label>Default openings</label>
                    <input v-model.number="careerForm.defaultOpenings" type="number" min="1" />
                  </div>
                  <div class="hr-set-field">
                    <label>Interview duration (minutes)</label>
                    <input v-model.number="careerForm.interviewDurationMinutes" type="number" min="15" step="15" />
                  </div>
                </div>
                <div class="hr-set-form__actions">
                  <button type="submit" class="hr-set-btn hr-set-btn--primary">Save career defaults</button>
                </div>
              </form>
            </section>
            <section v-else-if="careerSub === 'documents'" class="hr-set-panel">
              <div class="hr-set-panel__head">
                <p class="hr-set-panel__title">Default required documents</p>
                <p>Pulled from document types. Selected types are suggested on new jobs.</p>
              </div>
              <form @submit.prevent="saveCareerDefaults">
                <div class="hr-set-chips">
                  <label v-for="type in sortedDocumentTypes" :key="type.id" class="hr-set-chip">
                    <input type="checkbox" :value="type.id" v-model="careerForm.defaultRequiredDocumentTypeIds" />
                    {{ type.name }}
                  </label>
                  <span v-if="!documentTypes.length" class="hr-set-help">Add document types in the Documents tab.</span>
                </div>
                <div class="hr-set-form__actions">
                  <button type="submit" class="hr-set-btn hr-set-btn--primary">Save documents</button>
                </div>
              </form>
            </section>
            <section v-else class="hr-set-panel">
              <div class="hr-set-panel__head">
                <p class="hr-set-panel__title">Employment types</p>
                <p>These job types are available when creating a vacancy.</p>
              </div>
              <div class="hr-set-type-grid">
                <article v-for="type in jobTypes" :key="type.value" class="hr-set-type-card">
                  <strong>{{ type.label }}</strong>
                  <small>{{ type.hint }}</small>
                </article>
              </div>
            </section>
          </div>

          <div v-else-if="activeTab === 'assets'" class="hr-set-stack">
            <HrSettingsCatalog
              ref="assetsCatalog"
              title="Asset types"
              description="Categories used when adding company assets and filtering inventory."
              add-label="Add asset type"
              search-placeholder="Search asset types"
              empty-text="No asset types yet. Add Laptop, Phone, Vehicle, and more."
              :items="sortedAssetTypes"
              :loading="loading.assets"
              :saving="saving.assetType"
              :fields="assetTypeFields"
              @save="(payload, editing) => saveAssetType(payload, editing)"
              @remove="removeAssetType"
            />
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import HrSettingsCatalog from './HrSettingsCatalog.vue'
import {
  fetchDepartments,
  createDepartment,
  updateDepartment,
  deleteDepartment,
  fetchDesignations,
  createDesignation,
  updateDesignation,
  deleteDesignation,
  fetchBranches,
  createBranch,
  updateBranch,
  deleteBranch,
} from '@/services/employeesApi'
import {
  fetchDocumentTypes,
  createDocumentType,
  updateDocumentType,
  deleteDocumentType,
} from '@/services/documentRequestsApi'
import {
  fetchLeaveTypes,
  createLeaveType,
  updateLeaveType,
  deleteLeaveType,
} from '@/services/leaveApi'
import {
  fetchAssetTypes,
  createAssetType,
  updateAssetType,
  deleteAssetType,
} from '@/services/assetsApi'
import { fetchAttendanceSettings, updateAttendanceSettings } from '@/services/attendancesApi'
import { fetchDocumentExpirySettings, updateDocumentExpirySettings } from '@/services/documentExpirySettingsApi'

const CAREER_DEFAULTS_KEY = 'hr.career.defaults'

const props = defineProps({
  initialTab: { type: String, default: 'employees' },
})

const emit = defineEmits(['close'])

const tabs = [
  { id: 'employees', label: 'Employees', hint: 'Departments, titles, branches', icon: 'lucide:users' },
  { id: 'documents', label: 'Documents', hint: 'Requestable certificates', icon: 'lucide:file-text' },
  { id: 'expiry-alerts', label: 'Expiry Alerts', hint: 'Days before HR is notified', icon: 'lucide:calendar-clock' },
  { id: 'leave', label: 'Leave', hint: 'Types, days, attachments', icon: 'lucide:calendar-off' },
  { id: 'attendance', label: 'Attendance', hint: 'Check-in window', icon: 'lucide:calendar-check' },
  { id: 'career', label: 'Career', hint: 'Hiring defaults', icon: 'lucide:briefcase' },
  { id: 'assets', label: 'Assets', hint: 'Inventory categories', icon: 'lucide:laptop' },
]

const activeTab = ref(props.initialTab || 'employees')
const employeeSub = ref('departments')
const leaveSub = ref('all')
const attendanceSub = ref('window')
const careerSub = ref('defaults')
const departmentsCatalog = ref(null)
const designationsCatalog = ref(null)
const branchesCatalog = ref(null)
const documentsCatalog = ref(null)
const leaveCatalog = ref(null)
const assetsCatalog = ref(null)

const departments = ref([])
const designations = ref([])
const branches = ref([])
const documentTypes = ref([])
const leaveTypes = ref([])
const assetTypes = ref([])

function sortByName(list) {
  return [...(list || [])].sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''), undefined, { sensitivity: 'base' }))
}

const sortedDepartments = computed(() => sortByName(departments.value))
const sortedDesignations = computed(() => sortByName(designations.value))
const sortedBranches = computed(() => sortByName(branches.value))
const sortedDocumentTypes = computed(() => sortByName(documentTypes.value))
const sortedAssetTypes = computed(() => sortByName(assetTypes.value))
const filteredLeaveTypes = computed(() => {
  const list = sortByName(leaveTypes.value)
  if (leaveSub.value === 'all') return list
  return list.filter((item) => item.payment_type === leaveSub.value)
})

const loading = reactive({
  employees: false,
  documents: false,
  documentExpiry: false,
  leave: false,
  attendance: false,
  assets: false,
})
const saving = reactive({
  department: false,
  designation: false,
  branch: false,
  documentType: false,
  documentExpiry: false,
  leaveType: false,
  attendance: false,
  assetType: false,
})

const weekDays = [
  { value: 0, label: 'Sunday' },
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
]

const jobTypes = [
  { value: 'full_time', label: 'Full-time', hint: 'Standard contracted role' },
  { value: 'part_time', label: 'Part-time', hint: 'Reduced weekly hours' },
  { value: 'contract', label: 'Contract', hint: 'Fixed-term assignment' },
  { value: 'internship', label: 'Internship', hint: 'Training / graduate track' },
  { value: 'remote', label: 'Remote', hint: 'Location-independent' },
]

const departmentFields = [
  { key: 'name', label: 'Department name', type: 'text', required: true, placeholder: 'e.g. Sales' },
]
const designationFields = [
  { key: 'name', label: 'Designation', type: 'text', required: true, placeholder: 'e.g. Senior Agent' },
  { key: 'description', label: 'Description', type: 'textarea', placeholder: 'Optional role summary', full: true },
]
const branchFields = [
  { key: 'name', label: 'Branch name', type: 'text', required: true, placeholder: 'e.g. Dubai Marina' },
  { key: 'code', label: 'Code', type: 'text', required: true, placeholder: 'DXB-01' },
  { key: 'address', label: 'Address', type: 'text', placeholder: 'Optional', full: true },
]
const documentTypeFields = [
  { key: 'name', label: 'Document type', type: 'text', required: true, placeholder: 'e.g. Salary Certificate' },
]
const assetTypeFields = [
  { key: 'name', label: 'Asset type', type: 'text', required: true, placeholder: 'e.g. Laptop' },
]
const leaveFields = [
  { key: 'name', label: 'Leave type', type: 'text', required: true, placeholder: 'e.g. Annual Leave' },
  { key: 'payment_type', label: 'Payment', type: 'select', required: true, options: [
    { value: 'paid', label: 'Paid' },
    { value: 'half_paid', label: 'Half paid' },
    { value: 'unpaid', label: 'Unpaid' },
  ] },
  { key: 'default_days', label: 'Default days', type: 'number', required: true, min: 0, default: 30 },
  { key: 'requires_attachment', label: 'Attachment', type: 'toggle', onLabel: 'Attachment required', offLabel: 'No attachment' },
  { key: 'is_active', label: 'Availability', type: 'toggle', default: true, onLabel: 'Active', offLabel: 'Inactive' },
]

function designationSubtitle(item) {
  return item.description || ''
}

function branchSubtitle(item) {
  return [item.code, item.address].filter(Boolean).join(' · ')
}

function leaveBadge(item) {
  return item.is_active === false ? 'Inactive' : paymentLabel(item.payment_type)
}

function leaveBadgeClass(item) {
  return item.is_active === false ? 'is-muted' : 'is-info'
}

const attendanceForm = reactive({
  day_of_week: 6,
  start_time: '09:00',
  end_time: '10:00',
  department_ids: [],
})

const documentExpiryForm = reactive({
  passport_days: 15,
  labor_card_days: 15,
  emirates_id_days: 15,
  residency_days: 15,
})

const careerForm = reactive(loadCareerDefaults())

function notify(message, type = 'success') {
  window.$showNotification?.(message, type)
}

function uniqueByName(items) {
  const seen = new Set()
  const out = []
  for (const item of items || []) {
    if (!item) continue
    const name = String(item.name ?? item.label ?? '').trim().toLowerCase()
    const key = name || `id:${item.id ?? item.value}`
    if (seen.has(key)) continue
    seen.add(key)
    out.push(item)
  }
  return out
}

function unwrapList(payload) {
  if (Array.isArray(payload)) return uniqueByName(payload)
  if (Array.isArray(payload?.data)) return uniqueByName(payload.data)
  if (Array.isArray(payload?.data?.data)) return uniqueByName(payload.data.data)
  return []
}

function paymentLabel(value) {
  if (value === 'half_paid') return 'Half paid'
  if (value === 'unpaid') return 'Unpaid'
  return 'Paid'
}

function leaveSubtitle(item) {
  const days = item.default_days != null ? `${item.default_days} days` : ''
  const attach = item.requires_attachment ? 'Attachment required' : ''
  return [days, attach].filter(Boolean).join(' · ')
}

function loadCareerDefaults() {
  try {
    const raw = JSON.parse(localStorage.getItem(CAREER_DEFAULTS_KEY) || '{}')
    return {
      defaultJobType: raw.defaultJobType || 'full_time',
      defaultOpenings: Number(raw.defaultOpenings) || 1,
      interviewDurationMinutes: Number(raw.interviewDurationMinutes) || 45,
      defaultRequiredDocumentTypeIds: Array.isArray(raw.defaultRequiredDocumentTypeIds)
        ? raw.defaultRequiredDocumentTypeIds
        : [],
    }
  } catch {
    return {
      defaultJobType: 'full_time',
      defaultOpenings: 1,
      interviewDurationMinutes: 45,
      defaultRequiredDocumentTypeIds: [],
    }
  }
}

async function loadEmployeesCatalogs() {
  loading.employees = true
  try {
    const [dept, desig, branch] = await Promise.all([
      fetchDepartments(),
      fetchDesignations(),
      fetchBranches(),
    ])
    departments.value = dept || []
    designations.value = desig || []
    branches.value = branch || []
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to load employee catalogs', 'error')
  } finally {
    loading.employees = false
  }
}

async function loadDocuments() {
  loading.documents = true
  try {
    const result = await fetchDocumentTypes()
    documentTypes.value = unwrapList(result)
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to load document types', 'error')
  } finally {
    loading.documents = false
  }
}

async function loadDocumentExpirySettings() {
  loading.documentExpiry = true
  try {
    const settings = await fetchDocumentExpirySettings()
    Object.assign(documentExpiryForm, settings)
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to load expiry alert settings', 'error')
  } finally {
    loading.documentExpiry = false
  }
}

async function loadLeave() {
  loading.leave = true
  try {
    leaveTypes.value = await fetchLeaveTypes('', { all: true })
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to load leave types', 'error')
  } finally {
    loading.leave = false
  }
}

async function loadAttendance() {
  loading.attendance = true
  try {
    if (!departments.value.length) await loadEmployeesCatalogs()
    const settings = await fetchAttendanceSettings()
    attendanceForm.day_of_week = settings.day_of_week
    attendanceForm.start_time = settings.start_time
    attendanceForm.end_time = settings.end_time
    attendanceForm.department_ids = settings.department_ids
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to load attendance settings', 'error')
  } finally {
    loading.attendance = false
  }
}

async function loadAssets() {
  loading.assets = true
  try {
    assetTypes.value = await fetchAssetTypes()
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to load asset types', 'error')
  } finally {
    loading.assets = false
  }
}

async function loadTab(tab) {
  if (tab === 'employees') await loadEmployeesCatalogs()
  if (tab === 'documents' || tab === 'career') await loadDocuments()
  if (tab === 'expiry-alerts') await loadDocumentExpirySettings()
  if (tab === 'leave') await loadLeave()
  if (tab === 'attendance') await loadAttendance()
  if (tab === 'assets') await loadAssets()
}

watch(activeTab, (tab) => loadTab(tab), { immediate: false })
watch(() => props.initialTab, (tab) => {
  if (tab) activeTab.value = tab
})

onMounted(() => loadTab(activeTab.value))

function resolveRecordId(payload, editing) {
  const id = Number(payload?.id ?? editing?.id)
  return Number.isFinite(id) && id > 0 ? id : null
}

async function runSave({ key, create, update, reload, catalog, payload, editing }) {
  const id = resolveRecordId(payload, editing)
  const { id: _ignored, ...data } = payload || {}
  saving[key] = true
  try {
    if (id) await update(id, data)
    else await create(data)
    notify(id ? 'Updated successfully' : 'Added successfully')
    catalog?.closeForm?.()
    await reload()
  } catch (error) {
    const messages = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join('\n')
      : (error.response?.data?.message || 'Save failed')
    notify(messages, 'error')
  } finally {
    saving[key] = false
  }
}

async function runRemove({ key, remove, reload, item }) {
  saving[key] = true
  try {
    await remove(item.id)
    notify('Deleted successfully')
    await reload()
  } catch (error) {
    notify(error.response?.data?.message || 'Delete failed', 'error')
  } finally {
    saving[key] = false
  }
}

function saveDepartment(payload, editing) {
  return runSave({
    key: 'department',
    create: createDepartment,
    update: updateDepartment,
    reload: loadEmployeesCatalogs,
    catalog: departmentsCatalog.value,
    payload: { id: payload.id, name: payload.name },
    editing,
  })
}

function removeDepartment(item) {
  return runRemove({ key: 'department', remove: deleteDepartment, reload: loadEmployeesCatalogs, item })
}

function saveDesignation(payload, editing) {
  return runSave({
    key: 'designation',
    create: createDesignation,
    update: updateDesignation,
    reload: loadEmployeesCatalogs,
    catalog: designationsCatalog.value,
    payload: { id: payload.id, name: payload.name, description: payload.description || null },
    editing,
  })
}

function removeDesignation(item) {
  return runRemove({ key: 'designation', remove: deleteDesignation, reload: loadEmployeesCatalogs, item })
}

function saveBranch(payload, editing) {
  return runSave({
    key: 'branch',
    create: createBranch,
    update: updateBranch,
    reload: loadEmployeesCatalogs,
    catalog: branchesCatalog.value,
    payload: {
      id: payload.id,
      name: payload.name,
      code: payload.code,
      address: payload.address || null,
    },
    editing,
  })
}

function removeBranch(item) {
  return runRemove({ key: 'branch', remove: deleteBranch, reload: loadEmployeesCatalogs, item })
}

function saveDocumentType(payload, editing) {
  return runSave({
    key: 'documentType',
    create: createDocumentType,
    update: updateDocumentType,
    reload: loadDocuments,
    catalog: documentsCatalog.value,
    payload: { id: payload.id, name: payload.name },
    editing,
  })
}

function removeDocumentType(item) {
  return runRemove({ key: 'documentType', remove: deleteDocumentType, reload: loadDocuments, item })
}

function saveLeaveType(payload, editing) {
  return runSave({
    key: 'leaveType',
    create: createLeaveType,
    update: updateLeaveType,
    reload: loadLeave,
    catalog: leaveCatalog.value,
    payload: {
      id: payload.id,
      name: payload.name,
      payment_type: payload.payment_type,
      default_days: Number(payload.default_days) || 0,
      requires_attachment: !!payload.requires_attachment,
      is_active: payload.is_active !== false,
    },
    editing,
  })
}

function removeLeaveType(item) {
  return runRemove({ key: 'leaveType', remove: deleteLeaveType, reload: loadLeave, item })
}

function saveAssetType(payload, editing) {
  return runSave({
    key: 'assetType',
    create: createAssetType,
    update: updateAssetType,
    reload: loadAssets,
    catalog: assetsCatalog.value,
    payload: { id: payload.id, name: payload.name },
    editing,
  })
}

function removeAssetType(item) {
  return runRemove({ key: 'assetType', remove: deleteAssetType, reload: loadAssets, item })
}

async function saveAttendance() {
  saving.attendance = true
  try {
    await updateAttendanceSettings({
      day_of_week: Number(attendanceForm.day_of_week),
      start_time: attendanceForm.start_time,
      end_time: attendanceForm.end_time,
      department_ids: attendanceForm.department_ids.map(Number),
    })
    notify('Attendance settings saved')
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to save attendance settings', 'error')
  } finally {
    saving.attendance = false
  }
}

async function saveDocumentExpirySettings() {
  saving.documentExpiry = true
  try {
    await updateDocumentExpirySettings({
      passport_days: Number(documentExpiryForm.passport_days),
      labor_card_days: Number(documentExpiryForm.labor_card_days),
      emirates_id_days: Number(documentExpiryForm.emirates_id_days),
      residency_days: Number(documentExpiryForm.residency_days),
    })
    notify('Expiry alert settings saved')
  } catch (error) {
    notify(error.response?.data?.message || 'Failed to save expiry alert settings', 'error')
  } finally {
    saving.documentExpiry = false
  }
}

function saveCareerDefaults() {
  localStorage.setItem(CAREER_DEFAULTS_KEY, JSON.stringify({ ...careerForm }))
  notify('Career defaults saved')
}
</script>

<style scoped>
.hr-set-overlay {
  position: fixed;
  inset: 0;
  z-index: 12000;
  background: rgba(11, 7, 54, 0.48);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.hr-set-modal {
  width: min(1180px, 96vw);
  height: min(820px, 92vh);
  background: #fff;
  border-radius: 22px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 30px 80px rgba(11, 7, 54, 0.28);
}
.hr-set-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  background: #0b0736;
  color: #ffffff;
}
.hr-set-top__copy {
  min-width: 0;
}
.hr-set-title,
.hr-set-sub,
.hr-set-top p {
  color: #ffffff !important;
  margin: 0 !important;
  line-height: 1.3 !important;
}
.hr-set-title {
  font-size: 15px !important;
  font-weight: 700 !important;
}
.hr-set-sub {
  margin-top: 2px !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: rgba(255, 255, 255, 0.78) !important;
}
.hr-set-close {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.hr-set-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  min-height: 0;
  flex: 1;
}
.hr-set-nav {
  padding: 16px 12px;
  background: #f8f6fb;
  border-right: 1px solid #eee8f4;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.hr-set-nav__item {
  border: none;
  background: transparent;
  text-align: left;
  border-radius: 12px;
  padding: 10px;
  display: flex;
  gap: 10px;
  align-items: center;
  color: #4b5563;
}
.hr-set-nav__item.is-active {
  background: #fff;
  color: #0b0736;
  box-shadow: 0 8px 20px rgba(11, 7, 54, 0.08);
}
.hr-set-nav__icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #efeaf6;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.hr-set-nav__item.is-active .hr-set-nav__icon {
  background: #0b0736;
  color: #fff;
}
.hr-set-nav__item strong,
.hr-set-nav__item small {
  display: block;
}
.hr-set-nav__item strong { font-size: 13px; }
.hr-set-nav__item small { font-size: 11px; color: #9ca3af; }
.hr-set-content {
  overflow-y: auto;
  padding: 18px 20px 24px;
  background: #fcfbfe;
}
.hr-set-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.hr-set-subnav {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.hr-set-subnav button {
  border: 1px solid #eceff5;
  background: #fff;
  color: #4b5563;
  border-radius: 999px;
  height: 32px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 600;
}
.hr-set-subnav button.is-active {
  background: #0b0736;
  border-color: #0b0736;
  color: #fff;
}
.hr-set-panel,
:deep(.hr-set-catalog) {
  background: #fff;
  border: 1px solid #eee8f4;
  border-radius: 16px;
  padding: 16px;
}
.hr-set-panel__title,
.hr-set-panel__head h4,
:deep(.hr-set-catalog__title) {
  margin: 0;
  font-size: 14px !important;
  font-weight: 700 !important;
  color: #0b0736 !important;
  line-height: 1.3 !important;
}
.hr-set-panel__head p,
:deep(.hr-set-catalog__head p),
.hr-set-help {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}
:deep(.hr-set-catalog__head) {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 12px;
}
:deep(.hr-set-search) {
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #eceff5;
  border-radius: 999px;
  padding: 8px 12px;
  color: #9ca3af;
  margin-bottom: 12px;
}
:deep(.hr-set-search input) {
  border: none;
  outline: none;
  width: 100%;
  background: transparent;
  font-size: 13px;
  color: #111827;
}
.hr-set-btn,
:deep(.hr-set-btn) {
  border: 1px solid #e5e7eb;
  background: #f3f4f6;
  color: #111827;
  border-radius: 999px;
  height: 36px;
  padding: 0 14px;
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hr-set-btn--primary,
:deep(.hr-set-btn--primary) {
  background: #0b0736;
  border-color: #0b0736;
  color: #fff;
}
.hr-set-form,
:deep(.hr-set-form) {
  border: 1px dashed #ddd3e8;
  border-radius: 14px;
  padding: 14px;
  margin-bottom: 12px;
  background: #faf8fc;
}
.hr-set-form__grid,
:deep(.hr-set-form__grid) {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
:deep(.hr-set-form__grid.is-wide),
.hr-set-form__grid.is-wide {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.hr-set-field,
:deep(.hr-set-field) {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
:deep(.hr-set-field.is-full) { grid-column: 1 / -1; }
.hr-set-field label,
:deep(.hr-set-field label) {
  font-size: 12px;
  font-weight: 700;
  color: #0b0736;
}
.hr-set-field em,
:deep(.hr-set-field em) { color: #dc2626; }
.hr-set-field input:not([type="checkbox"]):not([type="radio"]),
.hr-set-field select,
.hr-set-field textarea,
:deep(.hr-set-field input:not([type="checkbox"]):not([type="radio"])),
:deep(.hr-set-field select),
:deep(.hr-set-field textarea) {
  height: 42px;
  border: 1px solid #eceff5;
  border-radius: 10px;
  padding: 0 12px;
  background: #fff;
  color: #111827;
}
:deep(.hr-set-field textarea),
.hr-set-field textarea {
  height: auto;
  padding: 10px 12px;
}
.hr-set-form__actions,
:deep(.hr-set-form__actions) {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
}
:deep(.hr-set-list) {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
:deep(.hr-set-item) {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #f0ecf5;
  border-radius: 12px;
}
:deep(.hr-set-item__body) { min-width: 0; flex: 1; }
:deep(.hr-set-item__body strong),
:deep(.hr-set-item__body small) { display: block; }
:deep(.hr-set-item__body strong) { font-size: 14px; color: #111827; }
:deep(.hr-set-item__body small) { font-size: 12px; color: #9ca3af; }
:deep(.hr-set-item__actions) { display: inline-flex; gap: 4px; }
:deep(.hr-set-item__actions button) {
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  color: #9ca3af;
  border-radius: 8px;
}
:deep(.hr-set-item__actions button:hover) { background: #f3f4f6; color: #4b5563; }
:deep(.hr-set-badge) {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f4f0f8;
  color: #733e87;
}
:deep(.hr-set-badge.is-info) { background: #eef2ff; color: #3730a3; }
:deep(.hr-set-badge.is-muted) { background: #f3f4f6; color: #6b7280; }
.hr-set-empty,
:deep(.hr-set-empty) {
  text-align: center;
  color: #9ca3af;
  padding: 24px 8px;
  font-size: 13px;
}
:deep(.hr-set-switch) {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  font-weight: 600;
  cursor: pointer;
}
:deep(.hr-set-switch input) {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}
:deep(.hr-set-switch__track) {
  width: 44px;
  height: 24px;
  border-radius: 999px;
  background: #d1d5db;
  position: relative;
  flex-shrink: 0;
  transition: background 0.15s ease;
}
:deep(.hr-set-switch__track::after) {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
  transition: transform 0.15s ease;
}
:deep(.hr-set-switch input:checked + .hr-set-switch__track) {
  background: #0b0736;
}
:deep(.hr-set-switch input:checked + .hr-set-switch__track::after) {
  transform: translateX(20px);
}
.hr-set-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.hr-set-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  border: 1px solid #eceff5;
  border-radius: 999px;
  background: #fff;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.hr-set-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
}
.hr-set-type-card {
  padding: 12px;
  border: 1px solid #eee8f4;
  border-radius: 12px;
  background: #fff;
}
.hr-set-type-card strong,
.hr-set-type-card small { display: block; }
.hr-set-type-card strong { font-size: 13px; color: #0b0736; }
.hr-set-type-card small { margin-top: 4px; font-size: 12px; color: #6b7280; }
@media (max-width: 860px) {
  .hr-set-overlay { padding: 0; }
  .hr-set-modal { width: 100vw; height: 100vh; border-radius: 0; }
  .hr-set-layout { grid-template-columns: 1fr; }
  .hr-set-nav { flex-direction: row; overflow-x: auto; border-right: none; border-bottom: 1px solid #eee8f4; }
  .hr-set-nav__item small { display: none; }
}
</style>
