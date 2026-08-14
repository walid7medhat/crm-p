<template>
  <div class="rec-applicants" :class="{ 'rec-applicants--embedded': embedded }">
    <div class="rec-applicants-hero">
      <div>
        <p class="rec-interviews-crumb">Career <span>›</span> Manage Recruitments <span>›</span> {{ job?.title || 'Job' }}</p>
        <h6>All Applicants</h6>
      </div>
      <div class="rec-applicants-hero__actions">
        <div class="rec-interviews-search-wrap" ref="searchWrapRef">
          <label class="rec-interviews-search">
            <span class="rec-interviews-search__plus">+</span>
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search candidates"
              autocomplete="off"
              @input="searchQuery = $event.target.value"
              @focus="showFilters = true"
              @click="showFilters = true"
            />
            <span class="rec-interviews-search__icon"><iconify-icon icon="lucide:search" /></span>
          </label>
          <Teleport to="body">
            <ApplicantsSearchPopup
              v-if="showFilters"
              class="emp-search-popup--portal"
              :style="popupStyle"
              :search="searchQuery"
              :filters="filters"
              :applicants="applicants"
              @update:search="searchQuery = $event"
              @search="onPopupSearch"
              @reset="onClearFilters"
              @close="showFilters = false"
            />
          </Teleport>
        </div>
        <button type="button" class="rec-applicants-round" aria-label="Export" @click="exportList">
          <iconify-icon icon="lucide:download" />
        </button>
        <button type="button" class="rec-applicants-round" aria-label="Delete selected" @click="deleteSelected">
          <iconify-icon icon="lucide:trash-2" />
        </button>
      </div>
    </div>

    <article class="rec-job-banner">
      <div class="rec-job-banner__main">
        <span class="rec-job-banner__logo"><iconify-icon icon="lucide:building-2" /></span>
        <div>
          <h6>{{ job?.title }}</h6>
          <p>{{ job?.location }} | {{ job?.employmentType }}</p>
        </div>
        <span class="rec-job-banner__status" :class="`is-${job?.status}`">
          <i /> {{ job?.statusLabel || 'Open' }}
        </span>
      </div>
      <div class="rec-job-banner__meta">
        <span>Posted Date : {{ formatDate(job?.postedDate) }}</span>
        <span>Closing Date : {{ formatDate(job?.closingDate) }}</span>
        <strong>{{ applicants.length }} Applicants</strong>
      </div>
    </article>

    <div v-if="loading" class="rec-applicants-layout rec-applicants-layout--loading">
      <div v-for="n in 6" :key="n" class="emp-directory-table__skeleton" />
    </div>
    <div v-else class="rec-applicants-layout">
      <aside class="rec-app-list">
        <div class="rec-app-list__head">
          <label>
            <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
            {{ filteredApplicants.length }} Applicants
          </label>
          <button type="button" aria-label="Delete" @click="deleteSelected"><iconify-icon icon="lucide:trash-2" /></button>
        </div>
        <div class="rec-app-list__body">
          <button
            v-for="item in pagedApplicants"
            :key="item.id"
            type="button"
            class="rec-app-list__item"
            :class="{ 'is-active': selectedId === item.id }"
            @click="selectApplicant(item.id)"
          >
            <input type="checkbox" :checked="selectedIds.includes(item.id)" @click.stop @change="toggleSelect(item.id)" />
            <img :src="item.avatar" :alt="item.name" />
            <div class="rec-app-list__info">
              <strong>{{ item.name }}</strong>
              <p>{{ item.email }}</p>
              <p>{{ item.location }}</p>
              <small>
                <em :class="`is-${item.decision}`">{{ decisionLabel(item.decision) }}</em>
                {{ timeAgo(item.applicationDate) }}
              </small>
            </div>
            <span class="rec-app-list__mail" @click.stop="sendRejection(item)"><iconify-icon icon="lucide:mail" /></span>
          </button>
          <p v-if="!pagedApplicants.length" class="rec-interviews-empty">No applicants found.</p>
        </div>
        <div class="emp-directory-table__pagination rec-app-list__pager">
          <button type="button" class="emp-directory-table__page-btn" :disabled="page <= 1" @click="page -= 1">
            <iconify-icon icon="lucide:chevron-left" /> Previous
          </button>
          <button
            v-for="n in visiblePages"
            :key="n"
            type="button"
            class="emp-directory-table__page-number"
            :class="{ 'is-active': page === n }"
            @click="page = n"
          >{{ n }}</button>
          <button type="button" class="emp-directory-table__page-btn" :disabled="page >= totalPages" @click="page += 1">
            Next <iconify-icon icon="lucide:chevron-right" />
          </button>
        </div>
      </aside>

      <section v-if="selected" class="rec-app-detail">
        <div class="rec-app-detail__head">
          <div class="rec-app-detail__who">
            <img :src="selected.avatar" :alt="selected.name" />
            <div>
              <h6>{{ selected.name }}</h6>
              <p>{{ selected.location }}</p>
            </div>
          </div>
          <div class="rec-decision-chips">
            <button type="button" class="is-selected" :class="{ 'is-active': selected.decision === 'selected' }" @click="setDecision('selected')">Selected</button>
            <button type="button" class="is-rejected" :class="{ 'is-active': selected.decision === 'rejected' }" @click="setDecision('rejected')">Rejected</button>
            <button type="button" class="is-maybe" :class="{ 'is-active': selected.decision === 'maybe' }" @click="setDecision('maybe')">May be</button>
          </div>
        </div>
        <div class="rec-app-stats">
          <div><span>Applied at</span><strong>{{ formatLongDate(selected.applicationDate) }}</strong></div>
          <div><span>Availability Status</span><strong>{{ selected.availabilityStatus }}</strong></div>
          <div><span>Hiring Status</span><strong>{{ selected.hiringStatus }}</strong></div>
          <div><span>Interview Status</span><strong>{{ selected.interviewStatus }}</strong></div>
        </div>
        <div class="rec-app-detail__actions">
          <button type="button" @click="showSchedule = true"><iconify-icon icon="lucide:calendar" /> Schedule Interview</button>
          <button type="button" @click="sendRejection(selected)"><iconify-icon icon="lucide:mail" /> Send Rejection Mail</button>
          <button type="button" @click="openHistory"><iconify-icon icon="lucide:history" /> History</button>
        </div>

        <div class="rec-acc">
          <button type="button" class="rec-acc__head" @click="toggleSection('details')">
            Applicant Details
            <iconify-icon :icon="openSections.details ? 'lucide:chevrons-up-down' : 'lucide:chevrons-up-down'" />
          </button>
          <div v-if="openSections.details" class="rec-acc__body rec-acc__grid">
            <p><span>Email :</span><strong>{{ selected.email }}</strong></p>
            <p><span>Visa Status :</span><strong>{{ selected.visaStatus }}</strong></p>
            <p><span>Phone :</span><strong>{{ selected.phone }}</strong></p>
            <p><span>Visa Expiry :</span><strong>{{ selected.visaExpiry || '—' }}</strong></p>
            <p><span>Gender :</span><strong>{{ selected.gender }}</strong></p>
            <p><span>Notice Period :</span><strong>{{ selected.noticePeriod }}</strong></p>
            <p><span>Date of birth :</span><strong>{{ formatDate(selected.dateOfBirth) }}</strong></p>
            <p><span>Current Salary :</span><strong>{{ selected.currentSalary }}</strong></p>
            <p><span>Current Location :</span><strong>{{ selected.location }}</strong></p>
            <p><span>Expected Salary :</span><strong>{{ selected.expectedSalary }}</strong></p>
            <p><span>Nationality :</span><strong>{{ selected.nationality }}</strong></p>
            <p><span>Experience in UAE :</span><strong>{{ selected.uaeExperience }}</strong></p>
            <p><span>Total Experience :</span><strong>{{ selected.totalExperience }}</strong></p>
          </div>
        </div>

        <div class="rec-acc">
          <button type="button" class="rec-acc__head" @click="toggleSection('resume')">
            Resume
            <iconify-icon icon="lucide:chevrons-up-down" />
          </button>
          <div v-if="openSections.resume" class="rec-acc__body">
            <div v-if="selected.resumeUrl" class="rec-resume">
              <iframe :src="selected.resumeUrl" title="Resume" />
              <a :href="selected.resumeUrl" target="_blank" rel="noopener" class="rec-resume__download">
                <iconify-icon icon="lucide:download" />
              </a>
            </div>
            <p v-else class="rec-interviews-empty">No resume uploaded.</p>
          </div>
        </div>

        <div class="rec-acc">
          <button type="button" class="rec-acc__head" @click="toggleSection('qa')">
            Questions & Answers
            <iconify-icon icon="lucide:chevrons-up-down" />
          </button>
          <div v-if="openSections.qa" class="rec-acc__body">
            <div v-if="!qaItems.length" class="rec-interviews-empty">No questions answered.</div>
            <article v-for="(item, idx) in qaItems" :key="idx" class="rec-qa" :class="{ 'is-bad': !item.answer, 'is-good': !!item.answer }">
              <iconify-icon :icon="item.answer ? 'lucide:check-circle-2' : 'lucide:x-circle'" />
              <p><strong>{{ idx + 1 }} - {{ item.question }}</strong></p>
              <p><span>Answer :</span> {{ item.answer || 'Candidate Not Answered' }}</p>
              <p v-if="item.idea"><span>Idea Answer :</span> {{ item.idea }}</p>
            </article>
          </div>
        </div>

        <div class="rec-acc">
          <button type="button" class="rec-acc__head" @click="toggleSection('notes')">
            Applicant Notes
            <iconify-icon icon="lucide:chevrons-up-down" />
          </button>
          <div v-if="openSections.notes" class="rec-acc__body">
            <p>{{ selected.notes || 'No notes yet.' }}</p>
          </div>
        </div>
      </section>
      <section v-else class="rec-app-detail rec-app-detail--empty">
        <p>Select an applicant to view details.</p>
      </section>
    </div>

    <ScheduleInterviewModal
      :visible="showSchedule"
      :applicant="selected"
      :interviewers="interviewers"
      :branches="branches"
      :saving="savingSchedule"
      @close="showSchedule = false"
      @confirm="onScheduleConfirm"
    />
    <InterviewHistoryModal
      :visible="showHistory"
      :items="historyItems"
      @close="showHistory = false"
      @mark-done="onMarkDone"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import ApplicantsSearchPopup from '@/components/hr/recruitment/ApplicantsSearchPopup.vue'
import ScheduleInterviewModal from '@/components/hr/recruitment/ScheduleInterviewModal.vue'
import InterviewHistoryModal from '@/components/hr/recruitment/InterviewHistoryModal.vue'
import { fetchAgentEmployees } from '@/services/hrApi'
import { fetchBranches } from '@/services/employeesApi'
import {
  exportRecruitmentCsv,
  fetchApplicants,
  fetchInterviews,
  scheduleInterview,
  updateApplicantStatus,
  updateInterview,
} from '@/services/recruitmentApi'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'
import { isInsideHrSearchPopup, useHrSearchPopupPortal } from '@/composables/useHrSearchPopupPortal'

const props = defineProps({
  job: { type: Object, default: null },
  embedded: { type: Boolean, default: true },
})

const loading = ref(false)
const applicants = ref([])
const interviewers = ref([])
const branches = ref([])
const selectedId = ref(null)
const selectedIds = ref([])
const searchQuery = ref('')
const showFilters = ref(false)
const showSchedule = ref(false)
const showHistory = ref(false)
const savingSchedule = ref(false)
const historyItems = ref([])
const page = ref(1)
const perPage = 8
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)
const filters = reactive({
  candidate: '',
  applied_date: '',
  decision: '',
  interview_status: '',
  date_preset: '',
})
const openSections = reactive({ details: true, resume: false, qa: false, notes: false })

const selected = computed(() => applicants.value.find((item) => item.id === selectedId.value) || null)
const filteredApplicants = computed(() => applicants.value.filter(matchesFilters))
const totalPages = computed(() => Math.max(1, Math.ceil(filteredApplicants.value.length / perPage)))
const pagedApplicants = computed(() => {
  const start = (page.value - 1) * perPage
  return filteredApplicants.value.slice(start, start + perPage)
})
const visiblePages = computed(() => {
  const total = totalPages.value
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)
  return [1, 2, total]
})
const allSelected = computed(
  () => pagedApplicants.value.length > 0 && pagedApplicants.value.every((item) => selectedIds.value.includes(item.id)),
)
const qaItems = computed(() => {
  const questions = props.job?.customQuestions || props.job?.raw?.custom_questions || []
  const answers = selected.value?.answers || {}
  if (Array.isArray(questions) && questions.length) {
    return questions.map((item, idx) => {
      const question = typeof item === 'string' ? item : item.question
      const answer = answers[idx] ?? answers[question] ?? answers[String(idx)] ?? answers[`q${idx}`]
      return { question, answer: answer || '', idea: item.ideal_answer || item.idea_answer || '' }
    })
  }
  return Object.entries(answers).map(([question, answer]) => ({ question, answer, idea: '' }))
})

function matchesFilters(item) {
  const q = searchQuery.value.trim().toLowerCase()
  if (q && ![item.name, item.email, item.location].some((text) => String(text || '').toLowerCase().includes(q))) return false
  if (filters.candidate && item.name !== filters.candidate) return false
  if (filters.decision && item.decision !== filters.decision) return false
  if (filters.interview_status && item.interviewStatus !== filters.interview_status) return false
  const day = toYmd(item.applicationDate)
  if (filters.applied_date && day !== filters.applied_date) return false
  if (filters.date_preset) {
    const range = presetRange(filters.date_preset)
    if (range && (day < range.start || day > range.end)) return false
  }
  return true
}
function presetRange(id) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const ymd = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  if (id === 'today') return { start: ymd(today), end: ymd(today) }
  if (id === 'yesterday') {
    const d = new Date(today); d.setDate(d.getDate() - 1)
    return { start: ymd(d), end: ymd(d) }
  }
  if (id === 'this_week') {
    const start = new Date(today); start.setDate(start.getDate() - start.getDay())
    const end = new Date(start); end.setDate(end.getDate() + 6)
    return { start: ymd(start), end: ymd(end) }
  }
  if (id === 'this_month') {
    const start = new Date(today.getFullYear(), today.getMonth(), 1)
    const end = new Date(today.getFullYear(), today.getMonth() + 1, 0)
    return { start: ymd(start), end: ymd(end) }
  }
  return null
}
function toYmd(value) {
  if (!value) return ''
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (match) return `${match[1]}-${match[2]}-${match[3]}`
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
function formatDate(value) { return formatAttendanceDate(value) }
function formatLongDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })
}
function timeAgo(value) {
  if (!value) return ''
  const diff = Date.now() - new Date(value).getTime()
  const days = Math.max(0, Math.floor(diff / 86400000))
  if (days < 1) return 'Today'
  if (days === 1) return '1 Day Ago'
  if (days < 7) return `${days} Days Ago`
  const weeks = Math.floor(days / 7)
  return weeks === 1 ? '1 Week Ago' : `${weeks} Weeks Ago`
}
function decisionLabel(value) {
  if (value === 'selected') return 'Selected'
  if (value === 'rejected') return 'Rejected'
  if (value === 'maybe') return 'May be'
  return ''
}
function toggleSection(key) { openSections[key] = !openSections[key] }
function selectApplicant(id) { selectedId.value = id }
function toggleSelect(id) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((item) => item !== id)
    : [...selectedIds.value, id]
}
function toggleSelectAll(event) {
  selectedIds.value = event.target.checked ? pagedApplicants.value.map((item) => item.id) : []
}
function onPopupSearch(payload) {
  searchQuery.value = payload.search || payload.candidate || searchQuery.value
  filters.candidate = payload.candidate || ''
  filters.applied_date = payload.applied_date || ''
  filters.decision = payload.decision || ''
  filters.interview_status = payload.interview_status || ''
  filters.date_preset = payload.date_preset || ''
  page.value = 1
  showFilters.value = false
}
function onClearFilters() {
  searchQuery.value = ''
  filters.candidate = ''
  filters.applied_date = ''
  filters.decision = ''
  filters.interview_status = ''
  filters.date_preset = ''
  page.value = 1
  showFilters.value = false
}
async function setDecision(decision) {
  if (!selected.value) return
  const statusMap = { selected: 'shortlisted', rejected: 'rejected', maybe: 'pending' }
  const status = statusMap[decision]
  let reason = ''
  if (status === 'rejected') {
    const result = await Swal.fire({
      title: 'Send rejection mail?',
      input: 'textarea',
      inputPlaceholder: 'Rejection reason',
      showCancelButton: true,
      confirmButtonColor: '#0b0736',
    })
    if (!result.isConfirmed) return
    reason = result.value || 'Rejected by HR'
  }
  try {
    await updateApplicantStatus(selected.value.id, status, reason)
    await loadApplicants()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}
async function sendRejection(item) {
  const result = await Swal.fire({
    title: 'Send Rejection Mail?',
    text: item.name,
    input: 'textarea',
    inputPlaceholder: 'Rejection reason',
    showCancelButton: true,
    confirmButtonColor: '#0b0736',
  })
  if (!result.isConfirmed) return
  try {
    await updateApplicantStatus(item.id, 'rejected', result.value || 'Rejected by HR')
    await loadApplicants()
    Swal.fire({ icon: 'success', title: 'Rejection mail sent', timer: 1400, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}
async function onScheduleConfirm(payload) {
  savingSchedule.value = true
  try {
    await scheduleInterview(payload)
    showSchedule.value = false
    await loadApplicants()
    Swal.fire({ icon: 'success', title: 'Interview scheduled', timer: 1400, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to schedule', text: e?.response?.data?.message || e?.message })
  } finally {
    savingSchedule.value = false
  }
}
async function openHistory() {
  const result = await fetchInterviews({ per_page: 100, applicant_id: selected.value?.id, job_id: props.job?.id })
  historyItems.value = result.items
  showHistory.value = true
}
async function onMarkDone(item) {
  await updateInterview(item.id, { status: 'completed' })
  historyItems.value = historyItems.value.map((row) => (row.id === item.id ? { ...row, status: 'completed' } : row))
  await loadApplicants()
}
function exportList() {
  exportRecruitmentCsv(`applicants-${props.job?.id || 'job'}.csv`, filteredApplicants.value, [
    { label: 'Name', value: (r) => r.name },
    { label: 'Email', value: (r) => r.email },
    { label: 'Location', value: (r) => r.location },
    { label: 'Status', value: (r) => r.statusLabel },
    { label: 'Applied', value: (r) => r.applicationDate },
  ])
}
function deleteSelected() {
  Swal.fire({ icon: 'info', title: 'Applicants cannot be deleted from here', text: 'Change their status instead.' })
}
async function loadApplicants() {
  if (!props.job?.id) return
  loading.value = true
  try {
    const [result, users, branchList] = await Promise.all([
      fetchApplicants({ job_id: props.job.id, per_page: 200 }),
      fetchAgentEmployees().catch(() => []),
      fetchBranches().catch(() => []),
    ])
    applicants.value = result.items
    interviewers.value = users
    branches.value = branchList
    if (!selectedId.value && applicants.value[0]) selectedId.value = applicants.value[0].id
    else if (selectedId.value && !applicants.value.some((item) => item.id === selectedId.value)) {
      selectedId.value = applicants.value[0]?.id || null
    }
  } finally {
    loading.value = false
  }
}
function onDocClick(event) {
  if (isInsideHrSearchPopup(event)) return
  if (showFilters.value && !searchWrapRef.value?.contains(event.target)) showFilters.value = false
}

watch(() => props.job?.id, () => {
  selectedId.value = null
  loadApplicants()
})
watch(filteredApplicants, () => { page.value = 1 })

defineExpose({ openHistory })

onMounted(() => {
  loadApplicants()
  document.addEventListener('click', onDocClick)
})
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-recruitment.css';
</style>
