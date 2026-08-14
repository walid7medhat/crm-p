<template>
  <div class="rec-interviews" :class="{ 'rec-interviews--embedded': embedded }">
    <div class="rec-interviews-head">
      <div class="rec-interviews-head__left">
        <p class="rec-interviews-crumb">Career <span>›</span> Interviews</p>
        <h6>Scheduled Interviews</h6>
      </div>
      <div class="rec-interviews-head__right">
        <div class="rec-interviews-search-wrap" ref="searchWrapRef">
          <label class="rec-interviews-search">
            <span class="rec-interviews-search__plus" aria-hidden="true">+</span>
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search Interviews"
              autocomplete="off"
              @input="searchQuery = $event.target.value"
              @focus="showFilters = true"
              @click="showFilters = true"
            />
            <span class="rec-interviews-search__icon" aria-hidden="true">
              <iconify-icon icon="lucide:search" />
            </span>
          </label>
          <Teleport to="body">
            <InterviewsSearchPopup
              v-if="showFilters"
              class="emp-search-popup--portal"
              :style="popupStyle"
              :search="searchQuery"
              :filters="filters"
              :interviews="interviews"
              :branches="branches"
              @update:search="searchQuery = $event"
              @search="onPopupSearch"
              @reset="onClearFilters"
              @close="showFilters = false"
            />
          </Teleport>
        </div>
        <div class="rec-interviews-date-wrap" ref="dateWrapRef">
          <button type="button" class="rec-interviews-date" @click.stop="toggleDatePicker">
            <span :class="{ 'is-placeholder': !dateLabel }">{{ dateLabel || 'dd/mm/yyyy' }}</span>
            <iconify-icon icon="lucide:calendar" />
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <HrDateRangePicker
        v-if="showDatePicker"
        class="hr-range-picker--portal"
        :style="datePickerStyle"
        :start-date="filters.start_date"
        :end-date="filters.end_date"
        @apply="onDateApply"
        @cancel="showDatePicker = false"
      />
    </Teleport>

    <div v-if="loading" class="rec-interviews-card rec-interviews-card--loading">
      <div v-for="n in 5" :key="n" class="emp-directory-table__skeleton" />
    </div>
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Could not load interviews</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadInterviews">Try again</button>
    </div>
    <div v-else class="rec-interviews-card">
      <section class="rec-interviews-section">
        <h6>Pending Scheduled Interviews</h6>
        <div v-if="!pendingInterviews.length" class="rec-interviews-empty">
          <p>No pending interviews.</p>
          <button type="button" class="rec-interviews-empty__cta" @click="openCreate">Schedule Interview</button>
        </div>
        <article v-for="item in pendingInterviews" :key="item.id" class="rec-interview-row">
          <div class="rec-interview-row__cell">
            <strong>{{ item.applicantName }}</strong>
            <span>Candidate Name</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.jobTitle }}</strong>
            <span>Opening</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatDate(item.scheduledAt) }}</strong>
            <span>Scheduled Date</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatTimeRange(item) }}</strong>
            <span>Scheduled Time</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatDate(item.createdAt) }}</strong>
            <span>Created On</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.branch }}</strong>
            <span>Branch</span>
          </div>
          <div class="rec-interview-row__actions">
            <button v-if="item.status === 'scheduled'" type="button" class="rec-interview-done" @click="markDone(item)">
              <iconify-icon icon="lucide:check-circle-2" />
              Mark Done
            </button>
            <span v-else class="rec-interview-completed">{{ item.statusLabel }}</span>
            <button type="button" class="rec-interview-icon" aria-label="Edit" @click="openEdit(item)">
              <iconify-icon icon="lucide:pencil" />
            </button>
            <button type="button" class="rec-interview-icon" aria-label="Delete" @click="confirmDelete(item)">
              <iconify-icon icon="lucide:trash-2" />
            </button>
          </div>
        </article>
      </section>

      <section class="rec-interviews-section">
        <h6>Completed Interviews</h6>
        <div v-if="!completedInterviews.length" class="rec-interviews-empty">No completed interviews.</div>
        <article v-for="item in completedInterviews" :key="item.id" class="rec-interview-row">
          <div class="rec-interview-row__cell">
            <strong>{{ item.applicantName }}</strong>
            <span>Candidate Name</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.jobTitle }}</strong>
            <span>Opening</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatDate(item.scheduledAt) }}</strong>
            <span>Scheduled Date</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatTimeRange(item) }}</strong>
            <span>Scheduled Time</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatDate(item.createdAt) }}</strong>
            <span>Created On</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.branch }}</strong>
            <span>Branch</span>
          </div>
          <div class="rec-interview-row__actions">
            <span class="rec-interview-completed">
              <iconify-icon icon="lucide:check-circle-2" />
              Completed
            </span>
            <button type="button" class="rec-interview-icon" aria-label="Edit" @click="openEdit(item)">
              <iconify-icon icon="lucide:pencil" />
            </button>
            <button type="button" class="rec-interview-icon" aria-label="Delete" @click="confirmDelete(item)">
              <iconify-icon icon="lucide:trash-2" />
            </button>
          </div>
        </article>
      </section>
    </div>

    <button v-if="isMobile" type="button" class="emp-fab" aria-label="Schedule interview" @click="openCreate">
      <iconify-icon icon="lucide:plus" />
    </button>

    <ScheduleInterviewModal
      :visible="showSchedule"
      allow-pick-applicant
      :jobs="jobs"
      :applicants="eligibleApplicants"
      :interviewers="interviewers"
      :branches="branches"
      :saving="savingSchedule"
      @close="showSchedule = false"
      @confirm="onScheduleConfirm"
    />

    <Teleport to="body">
      <div v-if="showEditModal" class="rec-create-job-overlay" @click.self="showEditModal = false">
        <div class="rec-create-job-modal rec-interview-edit-modal">
          <div class="rec-create-job-modal__head">
            <h6>Edit Interview</h6>
            <button type="button" class="rec-create-job-modal__close" @click="showEditModal = false">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <div class="rec-create-job-modal__body">
            <section class="rec-create-job-panel">
              <div class="rec-create-job-grid">
                <div class="rec-create-job-field">
                  <label>Candidate Name</label>
                  <input :value="editForm.applicantName" type="text" disabled />
                </div>
                <div class="rec-create-job-field">
                  <label>Opening</label>
                  <input :value="editForm.jobTitle" type="text" disabled />
                </div>
                <div class="rec-create-job-field">
                  <label>Scheduled Date <em>*</em></label>
                  <HrFancyDateField v-model="editForm.date" placeholder="dd/mm/yyyy" />
                </div>
                <div class="rec-create-job-field">
                  <label>Start Time</label>
                  <input v-model="editForm.startTime" type="time" />
                </div>
                <div class="rec-create-job-field">
                  <label>End Time</label>
                  <input v-model="editForm.endTime" type="time" />
                </div>
                <div class="rec-create-job-field">
                  <label>Branch / Location</label>
                  <input v-model="editForm.location" type="text" placeholder="Enter Branch" />
                </div>
                <div class="rec-create-job-field">
                  <label>Type</label>
                  <SearchableSelect v-model="editForm.type" :options="typeOptions" placeholder="Not Selected" :append-to-body="false" :clearable="false" />
                </div>
                <div class="rec-create-job-field">
                  <label>Status</label>
                  <SearchableSelect v-model="editForm.status" :options="editStatusOptions" placeholder="Not Selected" :append-to-body="false" :clearable="false" />
                </div>
              </div>
            </section>
          </div>
          <div class="rec-create-job-modal__footer">
            <button type="button" class="rec-create-job-clear" @click="showEditModal = false">Cancel</button>
            <button type="button" class="rec-create-job-confirm" :disabled="saving" @click="saveEdit">
              {{ saving ? 'Saving…' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue'
import Swal from 'sweetalert2'
import InterviewsSearchPopup from '@/components/hr/recruitment/InterviewsSearchPopup.vue'
import ScheduleInterviewModal from '@/components/hr/recruitment/ScheduleInterviewModal.vue'
import HrDateRangePicker from '@/components/hr/shared/HrDateRangePicker.vue'
import HrFancyDateField from '@/components/hr/shared/HrFancyDateField.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import { MOBILE_LAYOUT_MAX_WIDTH } from '@/composables/useMobileNavigation'
import { isInsideHrSearchPopup, useHrSearchPopupPortal } from '@/composables/useHrSearchPopupPortal'
import { deleteInterview, fetchApplicants, fetchInterviews, fetchJobs, scheduleInterview, updateInterview } from '@/services/recruitmentApi'
import { fetchBranches } from '@/services/employeesApi'
import { fetchAgentEmployees } from '@/services/hrApi'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'

defineProps({
  embedded: { type: Boolean, default: true },
})

const loading = ref(false)
const error = ref('')
const interviews = ref([])
const branches = ref([])
const jobs = ref([])
const applicants = ref([])
const interviewers = ref([])
const searchQuery = ref('')
const showFilters = ref(false)
const showDatePicker = ref(false)
const showEditModal = ref(false)
const showSchedule = ref(false)
const saving = ref(false)
const savingSchedule = ref(false)
const isMobile = ref(false)
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)
const dateWrapRef = ref(null)
const datePickerStyle = ref({})
const filters = reactive({
  candidate: '',
  job_title: '',
  branch: '',
  status: '',
  start_date: '',
  end_date: '',
})
const editForm = reactive(emptyEditForm())

const typeOptions = [
  { value: 'online', label: 'Online' },
  { value: 'in_person', label: 'In Person' },
  { value: 'phone', label: 'Phone' },
]
const editStatusOptions = [
  { value: 'scheduled', label: 'Pending' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'no_show', label: 'No Show' },
]

const dateLabel = computed(() => {
  if (!filters.start_date && !filters.end_date) return ''
  const start = formatDisplayDate(filters.start_date)
  const end = formatDisplayDate(filters.end_date || filters.start_date)
  return start === end ? start : `${start} - ${end}`
})

const filteredInterviews = computed(() =>
  interviews.value.filter((item) => {
    const q = searchQuery.value.trim().toLowerCase()
    if (q && ![item.applicantName, item.jobTitle, item.branch, item.interviewerName].some((text) => String(text || '').toLowerCase().includes(q))) {
      return false
    }
    if (filters.candidate && item.applicantName !== filters.candidate) return false
    if (filters.job_title && item.jobTitle !== filters.job_title) return false
    if (filters.branch && item.branch !== filters.branch) return false
    if (filters.status && item.status !== filters.status) return false
    const day = toYmd(item.scheduledAt)
    if (filters.start_date && day && day < filters.start_date) return false
    if (filters.end_date && day && day > filters.end_date) return false
    return true
  }),
)

const pendingInterviews = computed(() =>
  filteredInterviews.value.filter((item) => item.status !== 'completed'),
)
const eligibleApplicants = computed(() =>
  applicants.value.filter((item) => ['pending', 'shortlisted'].includes(item.status)),
)
const completedInterviews = computed(() =>
  filteredInterviews.value.filter((item) => item.status === 'completed'),
)

function emptyEditForm() {
  return {
    id: null,
    applicantName: '',
    jobTitle: '',
    date: '',
    startTime: '',
    endTime: '',
    location: '',
    type: 'in_person',
    status: 'scheduled',
  }
}

function formatDate(value) {
  return formatAttendanceDate(value)
}

function formatDisplayDate(value) {
  if (!value) return ''
  const [y, m, d] = String(value).split('-')
  if (!y || !m || !d) return value
  return `${d}/${m}/${y}`
}

function toYmd(value) {
  if (!value) return ''
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (match) return `${match[1]}-${match[2]}-${match[3]}`
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function toTimeInput(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

function formatTimeRange(item) {
  const start = formatTime(item.scheduledAt)
  const end = formatTime(item.endTime)
  if (start && end) return `${start} - ${end}`
  return start || '—'
}

function formatTime(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: 'Asia/Dubai',
  })
}

function onPopupSearch(payload) {
  searchQuery.value = payload.search || payload.candidate || searchQuery.value
  filters.candidate = payload.candidate || ''
  filters.job_title = payload.job_title || ''
  filters.branch = payload.branch || ''
  filters.status = payload.status || ''
  showFilters.value = false
}

function onClearFilters() {
  searchQuery.value = ''
  filters.candidate = ''
  filters.job_title = ''
  filters.branch = ''
  filters.status = ''
  filters.start_date = ''
  filters.end_date = ''
  showFilters.value = false
}

function onDateApply({ start, end }) {
  filters.start_date = start || ''
  filters.end_date = end || start || ''
  showDatePicker.value = false
}

function toggleDatePicker() {
  showDatePicker.value = !showDatePicker.value
  if (showDatePicker.value) {
    showFilters.value = false
    nextTick(positionDatePicker)
  }
}

function positionDatePicker() {
  const el = dateWrapRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const width = Math.min(620, window.innerWidth - 24)
  const estimatedHeight = 420
  let left = rect.right - width
  if (left < 12) left = 12
  if (left + width > window.innerWidth - 12) left = Math.max(12, window.innerWidth - width - 12)
  let top = rect.bottom + 8
  if (top + estimatedHeight > window.innerHeight - 12 && rect.top > estimatedHeight) {
    top = Math.max(12, rect.top - estimatedHeight - 8)
  }
  datePickerStyle.value = {
    top: `${top}px`,
    left: `${left}px`,
    width: `${width}px`,
  }
}

function openEdit(item) {
  Object.assign(editForm, {
    id: item.id,
    applicantName: item.applicantName,
    jobTitle: item.jobTitle,
    date: toYmd(item.scheduledAt),
    startTime: toTimeInput(item.scheduledAt),
    endTime: toTimeInput(item.endTime),
    location: item.location || item.branch || '',
    type: item.type || 'in_person',
    status: item.status || 'scheduled',
  })
  showEditModal.value = true
}

async function saveEdit() {
  if (!editForm.id || !editForm.date) {
    Swal.fire({ icon: 'warning', title: 'Scheduled date is required' })
    return
  }
  saving.value = true
  try {
    const start = editForm.startTime || '09:00'
    const payload = {
      scheduled_at: `${editForm.date} ${start}:00`,
      end_time: editForm.endTime ? `${editForm.date} ${editForm.endTime}:00` : null,
      type: editForm.type,
      location: editForm.location || null,
      status: editForm.status,
    }
    const updated = await updateInterview(editForm.id, payload)
    interviews.value = interviews.value.map((item) => (item.id === updated.id ? updated : item))
    showEditModal.value = false
    Swal.fire({ icon: 'success', title: 'Interview updated', timer: 1400, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to update', text: e?.response?.data?.message || e?.message })
  } finally {
    saving.value = false
  }
}

async function markDone(item) {
  try {
    const updated = await updateInterview(item.id, { status: 'completed' })
    interviews.value = interviews.value.map((row) => (row.id === updated.id ? updated : row))
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function confirmDelete(item) {
  const result = await Swal.fire({
    title: 'Delete this interview?',
    text: item.applicantName,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await deleteInterview(item.id)
    interviews.value = interviews.value.filter((row) => row.id !== item.id)
    Swal.fire({ icon: 'success', title: 'Interview deleted', timer: 1400, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to delete', text: e?.response?.data?.message || e?.message })
  }
}

async function openCreate() {
  showSchedule.value = true
  await loadScheduleOptions()
}

async function loadScheduleOptions() {
  try {
    const [jobPage, applicantPage, users] = await Promise.all([
      fetchJobs({ per_page: 200 }).catch(() => ({ items: [] })),
      fetchApplicants({ per_page: 200 }).catch(() => ({ items: [] })),
      fetchAgentEmployees().catch(() => []),
    ])
    jobs.value = jobPage.items || []
    applicants.value = applicantPage.items || []
    interviewers.value = users
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Could not load scheduling options', text: e?.response?.data?.message || e?.message })
  }
}

async function onScheduleConfirm(payload) {
  savingSchedule.value = true
  try {
    await scheduleInterview(payload)
    showSchedule.value = false
    await loadInterviews()
    Swal.fire({ icon: 'success', title: 'Interview scheduled', timer: 1400, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to schedule', text: e?.response?.data?.message || e?.message })
  } finally {
    savingSchedule.value = false
  }
}

async function loadInterviews() {
  loading.value = true
  error.value = ''
  try {
    const [result, branchList] = await Promise.all([
      fetchInterviews({ per_page: 200 }),
      fetchBranches().catch(() => []),
    ])
    interviews.value = result.items
    branches.value = branchList
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load interviews'
  } finally {
    loading.value = false
  }
}

function onDocClick(event) {
  if (isInsideHrSearchPopup(event) || event.target.closest?.('.hr-range-picker')) return
  if (showFilters.value && !searchWrapRef.value?.contains(event.target)) showFilters.value = false
  if (showDatePicker.value && !dateWrapRef.value?.contains(event.target)) showDatePicker.value = false
}

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

onMounted(() => {
  syncMobile()
  loadInterviews()
  document.addEventListener('click', onDocClick)
  window.addEventListener('resize', positionDatePicker)
  window.addEventListener('resize', syncMobile, { passive: true })
  window.addEventListener('scroll', positionDatePicker, true)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  window.removeEventListener('resize', positionDatePicker)
  window.removeEventListener('resize', syncMobile)
  window.removeEventListener('scroll', positionDatePicker, true)
})

defineExpose({ openCreate })
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-recruitment.css';
</style>
