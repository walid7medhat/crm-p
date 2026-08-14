<template>
  <div class="emp-mgmt rec-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
    <section class="emp-mgmt__stats">
      <div class="emp-mgmt__stats-grid rec-stats-grid">
        <article v-for="stat in kpiCards" :key="stat.key" class="emp-stat-card">
          <div>
            <p class="emp-stat-card__value">{{ stat.value }}</p>
            <p class="emp-stat-card__label">{{ stat.label }}</p>
          </div>
          <span class="emp-stat-card__icon" :style="{ background: stat.bgColor, color: stat.iconColor }">
            <iconify-icon :icon="stat.icon" />
          </span>
        </article>
      </div>
    </section>

    <div v-if="loading" class="emp-directory-table emp-directory-table--loading">
      <div v-for="n in 6" :key="n" class="emp-directory-table__skeleton" />
    </div>
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Could not load recruitment data</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAll">Try again</button>
    </div>
    <JobsTable
      v-else
      :jobs="pagedJobs"
      :all-jobs="jobs"
      v-model:page="jobsTablePage"
      v-model:per-page="jobsPerPage"
      v-model:selected-ids="selectedJobIds"
      v-model:search-query="searchQuery"
      :filters="filters"
      :departments="departments"
      :branches="branches"
      :total="filteredJobs.length"
      :total-pages="jobsTotalPages"
      :start-entry="jobsStartEntry"
      :end-entry="jobsEndEntry"
      :pagination-items="jobsPaginationItems"
      :has-active-filters="hasActiveFilters"
      @export="exportCurrent"
      @apply-filters="onPopupSearch"
      @clear-filters="onClearFilters"
      @edit="openEditJob"
      @delete="confirmDeleteJob"
      @view-applicants="$emit('view-applicants', $event)"
      @reject-mail="onRejectMail"
      @history="onJobHistory"
    />

    <button v-if="isMobile" type="button" class="emp-fab" aria-label="Add job" @click="openCreateJob">
      <iconify-icon icon="lucide:plus" />
    </button>

    <Teleport to="body">
      <div v-if="showJobModal" class="rec-create-job-overlay" @click.self="showJobModal = false">
        <div class="rec-create-job-modal">
          <div class="rec-create-job-modal__head">
            <h6>{{ editingJobId ? 'Edit Job Opening' : 'Create New Job Opening' }}</h6>
            <button type="button" class="rec-create-job-modal__close" @click="showJobModal = false">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>

          <div class="rec-create-job-modal__body">
            <section class="rec-create-job-panel">
              <h6>Job Details</h6>
              <div class="rec-create-job-grid">
                <div class="rec-create-job-field">
                  <label>Job Title <em>*</em></label>
                  <input v-model="jobForm.title" type="text" placeholder="Enter Job Title" />
                </div>
                <div class="rec-create-job-field">
                  <label>Closing Date <em>*</em></label>
                  <HrFancyDateField v-model="jobForm.closing_date" placeholder="-- / -- / --" />
                </div>
                <div class="rec-create-job-field">
                  <label>Branch <em>*</em></label>
                  <SearchableSelect v-model="jobForm.branch_id" :options="branchSelectOptions" placeholder="Not Selected" :append-to-body="false" :clearable="false" />
                </div>
                <div class="rec-create-job-field">
                  <label>Department <em>*</em></label>
                  <SearchableSelect v-model="jobForm.department_id" :options="departmentSelectOptions" placeholder="Not Selected" :append-to-body="false" :clearable="false" />
                </div>
                <div class="rec-create-job-field">
                  <label>Status <em>*</em></label>
                  <SearchableSelect v-model="jobForm.status" :options="statusSelectOptions" placeholder="Not Selected" :append-to-body="false" :clearable="false" />
                </div>
                <div class="rec-create-job-field">
                  <label>Job Type <em>*</em></label>
                  <SearchableSelect v-model="jobForm.job_type" :options="jobTypeSelectOptions" placeholder="Not Selected" :append-to-body="false" :clearable="false" />
                </div>
                <div class="rec-create-job-field rec-create-job-field--full">
                  <label>Skills <em>*</em></label>
                  <div class="rec-skill-box">
                    <span v-for="(skill, idx) in jobForm.skills" :key="skill + idx" class="rec-skill-chip">
                      {{ skill }}
                      <button type="button" @click="removeSkill(idx)"><iconify-icon icon="lucide:x" /></button>
                    </span>
                    <input
                      v-model="skillInputValue"
                      type="text"
                      placeholder="+ Add Skills"
                      @keydown.enter.prevent="addSkill"
                    />
                  </div>
                </div>
              </div>
            </section>

            <section class="rec-create-job-panel">
              <h6>Add More Details</h6>
              <div class="rec-editor">
                <div class="rec-editor__toolbar">
                  <button type="button" @click="wrapDescription('**', '**')"><b>B</b></button>
                  <button type="button" @click="wrapDescription('_', '_')"><i>I</i></button>
                  <button type="button" @click="wrapDescription('<u>', '</u>')"><u>U</u></button>
                  <button type="button" @click="wrapDescription('[', '](url)')"><iconify-icon icon="lucide:link" /></button>
                  <button type="button" @click="wrapDescription('~~', '~~')"><s>S</s></button>
                  <button type="button" @click="wrapDescription('\n- ', '')"><iconify-icon icon="lucide:list" /></button>
                  <button type="button" @click="wrapDescription('\n1. ', '')"><iconify-icon icon="lucide:list-ordered" /></button>
                  <button type="button"><iconify-icon icon="lucide:code" /></button>
                </div>
                <textarea
                  ref="descriptionRef"
                  v-model="jobForm.description"
                  rows="7"
                  placeholder="Type Job Description & Requirement…"
                />
              </div>
            </section>

            <section class="rec-create-job-panel">
              <h6>Question Details</h6>
              <p class="rec-create-job-hint">Add skill keyword (max 10) to make your job more visible to the right candidates.</p>
              <div class="rec-create-job-field rec-create-job-field--full">
                <label>Need to show option?</label>
                <div class="rec-option-pills">
                  <button
                    v-for="doc in REQUIRED_DOCUMENT_OPTIONS"
                    :key="doc.value"
                    type="button"
                    class="rec-option-pill"
                    :class="{ 'is-active': isRequiredDocumentSelected(doc.value) }"
                    @click="toggleRequiredDocument(doc.value)"
                  >
                    {{ doc.label }}
                    <iconify-icon :icon="isRequiredDocumentSelected(doc.value) ? 'lucide:check' : 'lucide:plus'" />
                  </button>
                </div>
              </div>
              <div class="rec-create-job-field rec-create-job-field--full">
                <label>Custom Questions <em>*</em></label>
                <textarea v-model="customQuestionInputValue" rows="3" placeholder="Add your custom Question" />
              </div>
              <div class="rec-suggested-questions">
                <button
                  v-for="question in SUGGESTED_QUESTIONS"
                  :key="question"
                  type="button"
                  class="rec-option-pill rec-option-pill--wide"
                  @click="addSuggestedQuestion(question)"
                >
                  {{ question }} <iconify-icon icon="lucide:plus" />
                </button>
              </div>
              <div v-if="jobForm.custom_questions.length" class="rec-question-list">
                <div v-for="(q, idx) in jobForm.custom_questions" :key="idx" class="rec-question-row">
                  <span>{{ q.question }}</span>
                  <button type="button" @click="removeCustomQuestion(idx)"><iconify-icon icon="lucide:trash-2" /></button>
                </div>
              </div>
            </section>
          </div>

          <div class="rec-create-job-modal__footer">
            <button type="button" class="rec-create-job-clear" @click="jobForm = defaultJobForm()">Clear</button>
            <button type="button" class="rec-create-job-confirm" :disabled="savingJob" @click="confirmSaveJob">
              {{ savingJob ? 'Saving…' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <InterviewHistoryModal
      :visible="showJobHistory"
      :items="jobHistoryItems"
      @close="showJobHistory = false"
      @mark-done="onMarkHistoryDone"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import Swal from 'sweetalert2'
import { useRecruitmentManagement } from '@/composables/useRecruitmentManagement'
import { MOBILE_LAYOUT_MAX_WIDTH } from '@/composables/useMobileNavigation'
import { exportRecruitmentCsv, fetchApplicants, fetchInterviews, updateApplicantStatus, updateInterview } from '@/services/recruitmentApi'
import JobsTable from '@/components/hr/recruitment/JobsTable.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import HrFancyDateField from '@/components/hr/shared/HrFancyDateField.vue'
import InterviewHistoryModal from '@/components/hr/recruitment/InterviewHistoryModal.vue'

defineProps({
  embedded: { type: Boolean, default: true },
})
defineEmits(['view-applicants'])

const {
  loading,
  error,
  searchQuery,
  filters,
  jobs,
  departments,
  branches,
  hasActiveFilters,
  kpiCards,
  filteredJobs,
  pagedJobs,
  jobsTablePage,
  jobsPerPage,
  jobsTotalPages,
  jobsStartEntry,
  jobsEndEntry,
  jobsPaginationItems,
  loadAll,
  clearFilters,
  saveJob,
  removeJob,
} = useRecruitmentManagement()

const isMobile = ref(false)
const selectedJobIds = ref([])
const showJobModal = ref(false)
const editingJobId = ref(null)
const savingJob = ref(false)
const jobForm = ref(defaultJobForm())
const showJobHistory = ref(false)
const jobHistoryItems = ref([])
const skillInputValue = ref('')
const customQuestionInputValue = ref('')
const descriptionRef = ref(null)

const REQUIRED_DOCUMENT_OPTIONS = [
  { value: 'profile_image', label: 'Profile Image' },
  { value: 'resume', label: 'Resume' },
  { value: 'cover_letter', label: 'Cover Letter' },
  { value: 'terms_and_condition', label: 'Terms and Condition' },
]

const SUGGESTED_QUESTIONS = [
  'A team member is frustrated with a project. What\'s your approach?',
  'If a customer is angry and yelling at you, what is the best response?',
  'Residing in UAE?',
  'New',
]

const statusSelectOptions = [
  { value: 'draft', label: 'Draft' },
  { value: 'open', label: 'Open' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'closed', label: 'Closed' },
]
const jobTypeSelectOptions = [
  { value: 'full_time', label: 'Full-time' },
  { value: 'part_time', label: 'Part-time' },
  { value: 'contract', label: 'Contract' },
  { value: 'internship', label: 'Internship' },
  { value: 'remote', label: 'Remote' },
]
const departmentSelectOptions = computed(() =>
  departments.value.map((item) => ({ value: item.id, label: item.name }))
)
const branchSelectOptions = computed(() =>
  branches.value.map((item) => ({ value: item.id, label: item.name }))
)

function defaultJobForm() {
  return {
    title: '',
    description: '',
    department_id: '',
    branch_id: '',
    hiring_manager_id: '',
    job_type: '',
    status: '',
    openings: 1,
    closing_date: '',
    skills: [],
    required_documents: [],
    custom_questions: [],
  }
}

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function onPopupSearch(payload) {
  searchQuery.value = payload.title || payload.search || ''
  filters.value = {
    ...filters.value,
    title: payload.title || '',
    posted_date: payload.posted_date || '',
    closing_date: payload.closing_date || '',
    department_id: payload.department_id || '',
    type: payload.type || '',
    job_type: payload.type && payload.type !== 'closed' ? payload.type : '',
    status: payload.type === 'closed' ? 'closed' : '',
    branch_id: payload.branch_id || '',
  }
  jobsTablePage.value = 1
}

function onClearFilters() {
  searchQuery.value = ''
  clearFilters()
  jobsTablePage.value = 1
}

function exportCurrent() {
  exportRecruitmentCsv('job-listings.csv', filteredJobs.value, [
    { label: 'Title', value: (r) => r.title },
    { label: 'Department', value: (r) => r.department },
    { label: 'Location', value: (r) => r.location },
    { label: 'Type', value: (r) => r.employmentType },
    { label: 'Openings', value: (r) => r.openings },
    { label: 'Applicants', value: (r) => r.applicantsCount },
    { label: 'Posted', value: (r) => r.postedDate },
    { label: 'Status', value: (r) => r.statusLabel },
  ])
}

function addSkill() {
  const value = skillInputValue.value.trim()
  if (!value) return
  if (jobForm.value.skills.length >= 10) return
  if (!jobForm.value.skills.includes(value)) jobForm.value.skills.push(value)
  skillInputValue.value = ''
}

function removeSkill(index) {
  jobForm.value.skills.splice(index, 1)
}

function toggleRequiredDocument(value) {
  const list = jobForm.value.required_documents
  const idx = list.indexOf(value)
  if (idx === -1) list.push(value)
  else list.splice(idx, 1)
}

function isRequiredDocumentSelected(value) {
  return jobForm.value.required_documents.includes(value)
}

function addSuggestedQuestion(question) {
  if (jobForm.value.custom_questions.some((q) => q.question === question)) return
  jobForm.value.custom_questions.push({ question, type: 'text', required: false })
}

function addCustomQuestion() {
  const value = customQuestionInputValue.value.trim()
  if (!value) return
  jobForm.value.custom_questions.push({ question: value, type: 'text', required: false })
  customQuestionInputValue.value = ''
}

function removeCustomQuestion(index) {
  jobForm.value.custom_questions.splice(index, 1)
}

function wrapDescription(before, after) {
  const el = descriptionRef.value
  if (!el) {
    jobForm.value.description = `${jobForm.value.description || ''}${before}${after}`
    return
  }
  const start = el.selectionStart ?? jobForm.value.description.length
  const end = el.selectionEnd ?? start
  const text = jobForm.value.description || ''
  const selected = text.slice(start, end)
  jobForm.value.description = `${text.slice(0, start)}${before}${selected}${after}${text.slice(end)}`
}

function toDateInputValue(value) {
  if (!value) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function openCreateJob() {
  editingJobId.value = null
  jobForm.value = defaultJobForm()
  skillInputValue.value = ''
  customQuestionInputValue.value = ''
  showJobModal.value = true
}

function openEditJob(job) {
  if (!job) return
  editingJobId.value = job.id
  jobForm.value = {
    title: job.title,
    description: job.description || job.raw?.description || '',
    department_id: job.departmentId || '',
    branch_id: job.branchId || '',
    hiring_manager_id: job.recruiterId || '',
    job_type: job.jobType || '',
    status: job.raw?.status || '',
    openings: job.openings || 1,
    closing_date: toDateInputValue(job.closingDate),
    skills: Array.isArray(job.skills) ? [...job.skills] : [],
    required_documents: Array.isArray(job.requiredDocuments) ? [...job.requiredDocuments] : [],
    custom_questions: Array.isArray(job.customQuestions)
      ? job.customQuestions.map((q) => ({
          question: q.question || '',
          type: q.type || 'text',
          required: !!q.required,
        }))
      : [],
  }
  skillInputValue.value = ''
  customQuestionInputValue.value = ''
  showJobModal.value = true
}

async function confirmSaveJob() {
  addCustomQuestion()
  if (!jobForm.value.title) {
    Swal.fire({ icon: 'warning', title: 'Title is required' })
    return
  }
  if (!jobForm.value.job_type) {
    Swal.fire({ icon: 'warning', title: 'Job type is required' })
    return
  }
  savingJob.value = true
  try {
    await saveJob({
      title: jobForm.value.title,
      description: jobForm.value.description,
      department_id: jobForm.value.department_id || null,
      branch_id: jobForm.value.branch_id || null,
      hiring_manager_id: jobForm.value.hiring_manager_id || null,
      job_type: jobForm.value.job_type || 'full_time',
      status: jobForm.value.status || 'open',
      openings: jobForm.value.openings || 1,
      closing_date: jobForm.value.closing_date || null,
      skills: jobForm.value.skills,
      required_documents: jobForm.value.required_documents,
      custom_questions: jobForm.value.custom_questions,
    }, editingJobId.value)
    Swal.fire({ icon: 'success', title: editingJobId.value ? 'Job updated' : 'Job created', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    showJobModal.value = false
  } catch (e) {
    const errors = e?.response?.data?.errors
    const msg = errors ? Object.values(errors).flat().join('\n') : (e?.response?.data?.message || e?.message)
    Swal.fire({ icon: 'error', title: 'Failed to save job', text: msg })
  } finally {
    savingJob.value = false
  }
}

async function confirmDeleteJob(job) {
  if (!job) return
  const result = await Swal.fire({
    title: 'Delete this job?',
    text: job.title,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await removeJob(job.id)
    Swal.fire({ icon: 'success', title: 'Job deleted', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || 'Cannot delete job with existing applicants' })
  }
}

async function onRejectMail(job) {
  if (!job) return
  const result = await Swal.fire({
    title: 'Send Rejection Mail?',
    text: `Send a rejection email to pending applicants for ${job.title}?`,
    input: 'textarea',
    inputPlaceholder: 'Rejection reason',
    showCancelButton: true,
    confirmButtonColor: '#0b0736',
  })
  if (!result.isConfirmed) return
  try {
    const list = await fetchApplicants({ job_id: job.id, per_page: 200 })
    const targets = list.items.filter((item) => !['rejected', 'hired', 'withdrawn'].includes(item.status))
    if (!targets.length) {
      Swal.fire({ icon: 'info', title: 'No applicants to reject' })
      return
    }
    await Promise.all(targets.map((item) => updateApplicantStatus(item.id, 'rejected', result.value || 'Rejected by HR')))
    Swal.fire({ icon: 'success', title: `Rejection mail sent to ${targets.length} applicant(s)`, timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function onJobHistory(job) {
  if (!job) return
  const result = await fetchInterviews({ job_id: job.id, per_page: 100 })
  jobHistoryItems.value = result.items
  showJobHistory.value = true
}

async function onMarkHistoryDone(item) {
  await updateInterview(item.id, { status: 'completed' })
  jobHistoryItems.value = jobHistoryItems.value.map((row) => (row.id === item.id ? { ...row, status: 'completed' } : row))
}

watch(filteredJobs, () => {
  jobsTablePage.value = 1
  selectedJobIds.value = []
})

onMounted(() => {
  syncMobile()
  window.addEventListener('resize', syncMobile, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobile)
})

defineExpose({ openCreateJob })
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-recruitment.css';
</style>
