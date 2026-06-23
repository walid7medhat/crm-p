<template>
  <div class="emp-mgmt rec-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
    <!-- KPIs -->
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

    <!-- View tabs -->
    <div class="rec-view-tabs">
      <button
        v-for="tab in viewTabs"
        :key="tab.id"
        type="button"
        class="rec-view-tab"
        :class="{ 'is-active': activeView === tab.id }"
        @click="switchView(tab.id)"
      >
        <iconify-icon :icon="tab.icon" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Toolbar -->
    <div class="emp-mgmt__toolbar">
      <div class="emp-mgmt__search-row">
        <div class="emp-mgmt__search">
          <iconify-icon icon="lucide:search" class="emp-mgmt__search-icon" />
          <input
            v-model="searchQuery"
            type="search"
            :placeholder="searchPlaceholder"
            autocomplete="off"
          />
        </div>
        <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = !showFilters">
          <iconify-icon icon="lucide:sliders-horizontal" />
          <span v-if="!isMobile">Filters{{ activeFilterCount ? ` (${activeFilterCount})` : '' }}</span>
        </button>
        <button v-if="!isMobile" type="button" class="emp-mgmt__toolbar-btn" @click="exportCurrent">
          <iconify-icon icon="lucide:download" />
          <span>Export</span>
        </button>
      </div>

      <div v-if="selectedJob && activeView !== 'jobs'" class="rec-selected-job">
        <button type="button" class="rec-selected-job__clear" @click="clearSelectedJob">
          <iconify-icon icon="lucide:x" />
        </button>
        <span>{{ selectedJob.title }}</span>
        <small>{{ selectedJob.department }} · {{ selectedJob.location }}</small>
      </div>

      <div class="emp-mgmt__chips">
        <button
          v-for="chip in quickChips"
          :key="chip.key + chip.value"
          type="button"
          class="emp-mgmt__chip"
          :class="{ 'is-active': filters[chip.key] === chip.value }"
          @click="toggleChip(chip.key, chip.value)"
        >
          {{ chip.label }}
        </button>
        <button v-if="hasActiveFilters" type="button" class="emp-mgmt__chip emp-mgmt__chip--clear" @click="onClearFilters">
          Clear all
        </button>
      </div>

      <div v-if="showFilters && !isMobile" class="emp-filter-desktop">
        <RecruitmentFilterFields
          v-model="localFilters"
          :departments="departments"
          :branches="branches"
          :recruiters="recruiters"
        />
        <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="emp-filter-sheet__clear" style="min-height:40px;padding:0 16px;border-radius:10px;" @click="onClearFilters">Clear</button>
          <button type="button" class="emp-filter-sheet__apply" style="min-height:40px;padding:0 20px;border-radius:10px;border:none;" @click="onApplyFilters">Apply</button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="emp-mgmt__grid">
      <div v-for="n in 6" :key="n" class="emp-skeleton" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h3>Could not load recruitment data</h3>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAll">Try again</button>
    </div>

    <!-- Jobs -->
    <template v-else-if="activeView === 'jobs'">
      <div v-if="!filteredJobs.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:briefcase" /></div>
        <h3>No job listings</h3>
        <p>{{ hasActiveFilters ? 'Try adjusting your search or filters.' : 'Post a job opening to start recruiting.' }}</p>
      </div>
      <div v-else class="emp-mgmt__grid rec-jobs-grid">
        <JobListingCard
          v-for="job in filteredJobs"
          :key="job.id"
          :job="job"
          @pipeline="openPipelineForJob"
          @select="openPipelineForJob"
        />
      </div>
    </template>

    <!-- Applicants list -->
    <template v-else-if="activeView === 'applicants'">
      <div class="rec-split-layout">
        <div class="rec-split-layout__list">
          <div v-if="!filteredApplicants.length" class="emp-empty">
            <div class="emp-empty__icon"><iconify-icon icon="lucide:users" /></div>
            <h3>No applicants</h3>
            <p>No candidates match your current filters.</p>
          </div>
          <div v-else class="rec-applicants-list">
            <ApplicantCard
              v-for="applicant in filteredApplicants"
              :key="applicant.id"
              :applicant="applicant"
              :active="selectedApplicant?.id === applicant.id"
              @select="onSelectApplicant"
              @move="openMoveModal"
              @reject="onRejectApplicant"
              @hire="onHireApplicant"
            />
          </div>
        </div>
        <ApplicantProfilePanel
          v-if="!isMobile && selectedApplicant"
          :applicant="selectedApplicant"
          :loading="applicantDetailLoading"
          @schedule="openScheduleModal"
          @move="openMoveModal"
          @reject="onRejectApplicant"
          @hire="onHireApplicant"
          @download="downloadResume"
        />
      </div>
    </template>

    <!-- Pipeline kanban -->
    <template v-else-if="activeView === 'pipeline'">
      <div v-if="!filteredApplicants.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:columns-3" /></div>
        <h3>Pipeline is empty</h3>
        <p>Select a job or wait for new applications.</p>
      </div>
      <RecruitmentKanbanBoard
        v-else
        :board="localBoard"
        :stages="PIPELINE_STAGES"
        :is-mobile="isMobile"
        @move-stage="onKanbanMove"
        @select-applicant="onSelectApplicant"
        @move-applicant="openMoveModal"
      />
    </template>

    <!-- Mobile profile sheet -->
    <Teleport to="body">
      <div v-if="isMobile && showProfileSheet" class="rec-profile-sheet" @click.self="showProfileSheet = false">
        <div class="rec-profile-sheet__backdrop" @click="showProfileSheet = false" />
        <div class="rec-profile-sheet__panel">
          <ApplicantProfilePanel
            :applicant="selectedApplicant"
            :loading="applicantDetailLoading"
            sheet
            @close="showProfileSheet = false"
            @schedule="openScheduleModal"
            @move="openMoveModal"
            @reject="onRejectApplicant"
            @hire="onHireApplicant"
            @download="downloadResume"
          />
        </div>
      </div>
    </Teleport>

    <!-- Mobile filter sheet -->
    <Teleport to="body">
      <div v-if="showFilters && isMobile" class="emp-filter-sheet" @click.self="showFilters = false">
        <div class="emp-filter-sheet__backdrop" @click="showFilters = false" />
        <div class="emp-filter-sheet__panel">
          <div class="emp-filter-sheet__handle" />
          <div class="emp-filter-sheet__head">
            <h3>Filter recruitment</h3>
            <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = false">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <RecruitmentFilterFields
            v-model="localFilters"
            :departments="departments"
            :branches="branches"
            :recruiters="recruiters"
          />
          <div class="emp-filter-sheet__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="onClearFilters">Clear all</button>
            <button type="button" class="emp-filter-sheet__apply" @click="onApplyFilters">Apply</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Move stage modal -->
    <Teleport to="body">
      <div v-if="showMoveModal" class="rec-modal-overlay" @click.self="showMoveModal = false">
        <div class="rec-modal">
          <h3>Move candidate</h3>
          <p v-if="moveTarget">{{ moveTarget.name }}</p>
          <div class="rec-stage-picker">
            <button
              v-for="stage in PIPELINE_STAGES"
              :key="stage.id"
              type="button"
              :class="{ active: moveStageId === stage.id }"
              @click="moveStageId = stage.id"
            >
              {{ stage.label }}
            </button>
          </div>
          <div class="rec-modal__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="showMoveModal = false">Cancel</button>
            <button type="button" class="emp-filter-sheet__apply" @click="confirmMove">Move</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Schedule interview modal -->
    <Teleport to="body">
      <div v-if="showScheduleModal" class="rec-modal-overlay" @click.self="showScheduleModal = false">
        <div class="rec-modal rec-modal--wide">
          <h3>Schedule interview</h3>
          <div class="rec-form-grid">
            <label>
              Interviewer
              <select v-model="scheduleForm.interviewer_id">
                <option value="">Select interviewer</option>
                <option v-for="r in recruiters" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </label>
            <label>
              Date & time
              <input v-model="scheduleForm.scheduled_at" type="datetime-local" />
            </label>
            <label>
              Type
              <select v-model="scheduleForm.type">
                <option value="online">Online</option>
                <option value="in_person">In person</option>
                <option value="phone">Phone</option>
              </select>
            </label>
            <label>
              Location / link
              <input v-model="scheduleForm.location" type="text" placeholder="Office or meeting link" />
            </label>
          </div>
          <div class="rec-modal__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="showScheduleModal = false">Cancel</button>
            <button type="button" class="emp-filter-sheet__apply" :disabled="scheduling" @click="confirmSchedule">
              {{ scheduling ? 'Scheduling…' : 'Schedule' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, reactive } from 'vue'
import Swal from 'sweetalert2'
import { useRecruitmentManagement } from '@/composables/useRecruitmentManagement'
import { MOBILE_LAYOUT_MAX_WIDTH } from '@/composables/useMobileNavigation'
import {
  updateApplicantStatus,
  scheduleInterview,
  stageToApiStatus,
  exportRecruitmentCsv,
  resolvePipelineStage,
} from '@/services/recruitmentApi'
import JobListingCard from '@/components/hr/recruitment/JobListingCard.vue'
import ApplicantCard from '@/components/hr/recruitment/ApplicantCard.vue'
import RecruitmentKanbanBoard from '@/components/hr/recruitment/RecruitmentKanbanBoard.vue'
import ApplicantProfilePanel from '@/components/hr/recruitment/ApplicantProfilePanel.vue'
import RecruitmentFilterFields from '@/components/hr/recruitment/RecruitmentFilterFields.vue'

defineProps({
  embedded: { type: Boolean, default: true },
})

const {
  loading,
  error,
  searchQuery,
  filters,
  activeView,
  departments,
  recruiters,
  branches,
  selectedJob,
  selectedApplicant,
  applicantDetailLoading,
  activeFilterCount,
  hasActiveFilters,
  kpiCards,
  pipelineBoard,
  filteredJobs,
  filteredApplicants,
  applicants,
  loadAll,
  loadApplicantDetail,
  selectJob,
  clearFilters,
  PIPELINE_STAGES,
} = useRecruitmentManagement()

const viewTabs = [
  { id: 'jobs', label: 'Jobs', icon: 'lucide:briefcase' },
  { id: 'applicants', label: 'Applicants', icon: 'lucide:users' },
  { id: 'pipeline', label: 'Pipeline', icon: 'lucide:columns-3' },
]

const quickChips = [
  { key: 'status', value: 'open', label: 'Open roles' },
  { key: 'job_type', value: 'full_time', label: 'Full-time' },
  { key: 'experience_level', value: 'Senior', label: 'Senior' },
]

const isMobile = ref(false)
const showFilters = ref(false)
const showProfileSheet = ref(false)
const showMoveModal = ref(false)
const showScheduleModal = ref(false)
const moveTarget = ref(null)
const moveStageId = ref('applied')
const scheduling = ref(false)
const localFilters = ref({ ...filters.value })
const localBoard = reactive({})

const scheduleForm = ref({
  applicant_id: '',
  interviewer_id: '',
  scheduled_at: '',
  type: 'online',
  location: '',
})

const searchPlaceholder = computed(() => {
  if (activeView.value === 'jobs') return 'Search job title, department, location, recruiter…'
  return 'Search applicant name, position, email…'
})

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function syncBoardFromPipeline() {
  PIPELINE_STAGES.forEach((stage) => {
    localBoard[stage.id] = [...(pipelineBoard.value[stage.id] || [])]
  })
}

watch(pipelineBoard, syncBoardFromPipeline, { deep: true, immediate: true })

function switchView(id) {
  activeView.value = id
}

function openPipelineForJob(job) {
  selectJob(job)
}

function clearSelectedJob() {
  selectedJob.value = null
}

function toggleChip(key, value) {
  filters.value[key] = filters.value[key] === value ? '' : value
}

function onApplyFilters() {
  filters.value = { ...localFilters.value }
  showFilters.value = false
}

function onClearFilters() {
  localFilters.value = {
    department_id: '',
    branch_id: '',
    job_type: '',
    status: '',
    hiring_manager_id: '',
    experience_level: '',
  }
  clearFilters()
  showFilters.value = false
}

async function onSelectApplicant(applicant) {
  await loadApplicantDetail(applicant.id)
  if (isMobile.value) showProfileSheet.value = true
}

function openMoveModal(applicant) {
  moveTarget.value = applicant
  moveStageId.value = applicant.pipelineStage || resolvePipelineStage(applicant.raw)
  showMoveModal.value = true
}

function openScheduleModal(applicant) {
  scheduleForm.value = {
    applicant_id: applicant.id,
    interviewer_id: recruiters.value[0]?.id || '',
    scheduled_at: '',
    type: 'online',
    location: '',
  }
  showScheduleModal.value = true
}

async function applyStatus(applicant, status, rejectionReason = '') {
  try {
    await updateApplicantStatus(applicant.id, status, rejectionReason)
    Swal.fire({ icon: 'success', title: 'Updated', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
    if (selectedApplicant.value?.id === applicant.id) {
      await loadApplicantDetail(applicant.id)
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function confirmMove() {
  if (!moveTarget.value) return
  const status = stageToApiStatus(moveStageId.value)
  showMoveModal.value = false
  await applyStatus(moveTarget.value, status)
  moveTarget.value = null
}

async function onKanbanMove({ applicant, stageId }) {
  const status = stageToApiStatus(stageId)
  if (applicant.status === status && applicant.pipelineStage === stageId) return
  await applyStatus(applicant, status)
}

async function onRejectApplicant(applicant) {
  const result = await Swal.fire({
    title: 'Reject candidate?',
    text: applicant.name,
    input: 'textarea',
    inputPlaceholder: 'Rejection reason (optional)',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  await applyStatus(applicant, 'rejected', result.value || '')
  showProfileSheet.value = false
}

async function onHireApplicant(applicant) {
  const result = await Swal.fire({
    title: 'Hire candidate?',
    text: applicant.name,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
  })
  if (!result.isConfirmed) return
  await applyStatus(applicant, 'hired')
  showProfileSheet.value = false
}

async function confirmSchedule() {
  if (!scheduleForm.value.interviewer_id || !scheduleForm.value.scheduled_at) {
    Swal.fire({ icon: 'warning', title: 'Missing fields', text: 'Select interviewer and date/time.' })
    return
  }
  scheduling.value = true
  try {
    await scheduleInterview({
      ...scheduleForm.value,
      meeting_link: scheduleForm.value.type === 'online' ? scheduleForm.value.location : undefined,
    })
    Swal.fire({ icon: 'success', title: 'Interview scheduled', timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' })
    showScheduleModal.value = false
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  } finally {
    scheduling.value = false
  }
}

function downloadResume(applicant) {
  if (!applicant?.resumeUrl) return
  window.open(applicant.resumeUrl, '_blank', 'noopener')
}

function exportCurrent() {
  if (activeView.value === 'jobs') {
    exportRecruitmentCsv('job-listings.csv', filteredJobs.value, [
      { label: 'Title', value: (r) => r.title },
      { label: 'Department', value: (r) => r.department },
      { label: 'Location', value: (r) => r.location },
      { label: 'Type', value: (r) => r.employmentType },
      { label: 'Applicants', value: (r) => r.applicantsCount },
      { label: 'Posted', value: (r) => r.postedDate },
      { label: 'Status', value: (r) => r.statusLabel },
    ])
    return
  }
  exportRecruitmentCsv('applicants.csv', filteredApplicants.value, [
    { label: 'Name', value: (r) => r.name },
    { label: 'Position', value: (r) => r.appliedPosition },
    { label: 'Experience', value: (r) => r.experienceLevel },
    { label: 'Applied', value: (r) => r.applicationDate },
    { label: 'Status', value: (r) => r.statusLabel },
  ])
}

onMounted(() => {
  syncMobile()
  window.addEventListener('resize', syncMobile)
  localFilters.value = { ...filters.value }
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobile)
})
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-recruitment.css';
</style>
