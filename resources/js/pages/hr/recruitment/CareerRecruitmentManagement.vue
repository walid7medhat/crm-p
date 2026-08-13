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
        <button   v-if="activeView === 'jobs'" type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="openCreateJob">
          <iconify-icon icon="lucide:plus" />
          <span>Add Job</span>
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
          <button type="button" class="emp-filter-sheet__apply primary" style="min-height:40px;padding:0 20px;border-radius:10px;border:none;" @click="onApplyFilters">Apply</button>
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
      <h6>Could not load recruitment data</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAll">Try again</button>
    </div>

    <!-- Jobs -->
    <template v-else-if="activeView === 'jobs'">
      <div v-if="!filteredJobs.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:briefcase" /></div>
        <h6>No job listings</h6>
        <p>{{ hasActiveFilters ? 'Try adjusting your search or filters.' : 'Post a job opening to start recruiting.' }}</p>
      </div>
      <div v-else class="emp-mgmt__grid rec-jobs-grid">
        <JobListingCard
          v-for="job in filteredJobs"
          :key="job.id"
          :job="job"
          @pipeline="openPipelineForJob"
          @select="openPipelineForJob"
          @edit="openEditJob"
          @delete="confirmDeleteJob"
        />
      </div>
    </template>

    <!-- Applicants list -->
    <template v-else-if="activeView === 'applicants'">
      <div class="rec-split-layout">
        <div class="rec-split-layout__list">
          <div v-if="!filteredApplicants.length" class="emp-empty">
            <div class="emp-empty__icon"><iconify-icon icon="lucide:users" /></div>
            <h6>No applicants</h6>
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
    <!-- Interviews -->
    <template v-else-if="activeView === 'interviews'">
      <div v-if="!filteredInterviews.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:calendar-clock" /></div>
        <h6>No interviews</h6>
        <p>Schedule an interview from an applicant's profile to see it here.</p>
      </div>
      <div v-else class="rec-interviews-list">
        <div v-for="interview in filteredInterviews" :key="interview.id" class="rec-interview-card">
          <div class="rec-interview-card__main">
            <div class="rec-interview-card__avatar">
              <iconify-icon icon="lucide:user" />
            </div>
            <div class="rec-interview-card__info">
              <strong>{{ interview.applicantName }}</strong>
              <span>{{ interview.jobTitle }}</span>
              <small>
                <iconify-icon icon="lucide:calendar" />
                {{ interview.scheduledAt ? new Date(interview.scheduledAt).toLocaleString() : '—' }}
              </small>
            </div>
          </div>
          <div class="rec-interview-card__meta">
            <span class="rec-interview-chip">{{ interview.typeLabel }}</span>
            <span class="rec-interview-chip">Interviewer: {{ interview.interviewerName }}</span>
            <span
              class="rec-interview-status"
              :class="`rec-interview-status--${interview.status}`"
            >
              {{ interview.statusLabel }}
            </span>
          </div>
          <div v-if="interview.status === 'completed'" class="rec-interview-feedback">
            <span v-if="interview.rating">Rating: {{ interview.rating }}/5</span>
            <p v-if="interview.feedback">{{ interview.feedback }}</p>
          </div>
          <div v-if="interview.status === 'scheduled'" class="rec-interview-card__actions">
            <button type="button" class="emp-mgmt__toolbar-btn" @click="openRescheduleInterview(interview)">
              <iconify-icon icon="lucide:calendar-cog" /> Reschedule
            </button>
            <button type="button" class="emp-mgmt__toolbar-btn" @click="markInterviewCompleted(interview)">
              <iconify-icon icon="lucide:check" /> Complete
            </button>
            <button type="button" class="emp-mgmt__toolbar-btn" @click="markInterviewNoShow(interview)">
              <iconify-icon icon="lucide:user-x" /> No-show
            </button>
            <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--danger" @click="cancelInterview(interview)">
              <iconify-icon icon="lucide:x" /> Cancel
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Pipeline kanban -->
    <template v-else-if="activeView === 'pipeline'">
      <div v-if="!filteredApplicants.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:columns-3" /></div>
        <h6>Pipeline is empty</h6>
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
            <h6>Filter recruitment</h6>
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
          <h6>Move candidate</h6>
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
          <h6>Schedule interview</h6>
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

      <Teleport to="body">
        <div v-if="showJobModal" class="rec-modal-overlay" @click.self="showJobModal = false">
          <div class="rec-modal rec-job-modal">
            <div class="rec-job-modal__head">
              <h6>{{ editingJobId ? 'Edit Job Opening' : 'Create New Job Opening' }}</h6>
              <button type="button" class="rec-job-modal__close" @click="showJobModal = false">
                <iconify-icon icon="lucide:x" />
              </button>
            </div>

            <div class="rec-job-modal__body">
              <section class="rec-job-modal__section">
                <h6 class="rec-job-modal__section-title">Job Details</h6>
                <div class="rec-form-grid">
                  <label>
                    Job Title *
                    <input v-model="jobForm.title" type="text" placeholder="Enter Job Title" />
                  </label>
                  <label>
                    Closing Date *
                    <input v-model="jobForm.closing_date" type="date" />
                  </label>
                  <label>
                    Branch *
                    <select v-model="jobForm.branch_id">
                      <option value="">Not Selected</option>
                      <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                  </label>
                  <label>
                    Department *
                    <select v-model="jobForm.department_id">
                      <option value="">Not Selected</option>
                      <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                  </label>
                  <label>
                    Status *
                    <select v-model="jobForm.status">
                      <option value="draft">Draft</option>
                      <option value="open">Open</option>
                      <option value="on_hold">On Hold</option>
                      <option value="closed">Closed</option>
                    </select>
                  </label>
                  <label>
                    Job Type *
                    <select v-model="jobForm.job_type">
                      <option value="full_time">Full-time</option>
                      <option value="part_time">Part-time</option>
                      <option value="contract">Contract</option>
                      <option value="internship">Internship</option>
                      <option value="remote">Remote</option>
                    </select>
                  </label>
                  <label>
                    Hiring Manager
                    <select v-model="jobForm.hiring_manager_id">
                      <option value="">Select manager</option>
                      <option v-for="r in recruiters" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                  </label>
                  <label>
                    Openings *
                    <input v-model.number="jobForm.openings" type="number" min="1" />
                  </label>
                </div>

                <label class="rec-job-modal__full">
                  Skills *
                  <div class="rec-tag-input">
                    <span v-for="(skill, idx) in jobForm.skills" :key="skill + idx" class="rec-tag-chip">
                      {{ skill }}
                      <button type="button" @click="removeSkill(idx)"><iconify-icon icon="lucide:x" /></button>
                    </span>
                    <input
                      v-model="skillInputValue"
                      type="text"
                      placeholder="Add Skills"
                      @keydown.enter.prevent="addSkill"
                    />
                    <button type="button" class="rec-tag-add-btn" @click="addSkill">
                      <iconify-icon icon="lucide:plus" /> Add Skills
                    </button>
                  </div>
                </label>
              </section>

              <section class="rec-job-modal__section">
                <h6 class="rec-job-modal__section-title">Add More Details</h6>
                <textarea
                  v-model="jobForm.description"
                  class="rec-job-modal__description"
                  rows="6"
                  placeholder="Type Job Description & Requirement…"
                ></textarea>
              </section>

              <section class="rec-job-modal__section">
                <h6 class="rec-job-modal__section-title">Question Details</h6>
                <p class="rec-job-modal__hint">Add skill keyword (max 10) to make your job more visible to the right candidates</p>

                <label class="rec-job-modal__full">
                  Need to show option?
                  <div class="rec-tag-input rec-tag-input--static">
                    <button
                      v-for="doc in REQUIRED_DOCUMENT_OPTIONS"
                      :key="doc.value"
                      type="button"
                      class="rec-tag-chip rec-tag-chip--toggle"
                      :class="{ 'is-active': isRequiredDocumentSelected(doc.value) }"
                      @click="toggleRequiredDocument(doc.value)"
                    >
                      {{ doc.label }}
                      <iconify-icon :icon="isRequiredDocumentSelected(doc.value) ? 'lucide:check' : 'lucide:plus'" />
                    </button>
                  </div>
                </label>

                <label class="rec-job-modal__full">
                  Custom Questions *
                  <textarea
                    v-model="customQuestionInputValue"
                    rows="2"
                    placeholder="Add your custom Question"
                    @keydown.enter.prevent="addCustomQuestion"
                  ></textarea>
                </label>
                <button type="button" class="rec-tag-add-btn" @click="addCustomQuestion">
                  <iconify-icon icon="lucide:plus" /> Add Question
                </button>

                <div class="rec-question-list">
                  <div v-for="(q, idx) in jobForm.custom_questions" :key="idx" class="rec-question-row">
                    <span>{{ q.question }}</span>
                    <button type="button" @click="removeCustomQuestion(idx)">
                      <iconify-icon icon="lucide:trash-2" />
                    </button>
                  </div>
                </div>
              </section>
            </div>

            <div class="rec-modal__actions">
              <button type="button" class="emp-filter-sheet__clear" @click="jobForm = defaultJobForm()">Clear</button>
              <button type="button" class="emp-filter-sheet__apply" :disabled="savingJob" @click="confirmSaveJob">
                {{ savingJob ? 'Saving…' : 'Confirm' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>
      <Teleport to="body">
  <div v-if="showInterviewModal" class="rec-modal-overlay" @click.self="showInterviewModal = false">
    <div class="rec-modal rec-modal--wide">
      <h6>Reschedule interview</h6>
      <p v-if="editingInterview">{{ editingInterview.applicantName }}</p>
      <div class="rec-form-grid">
        <label>
          Interviewer
          <select v-model="interviewForm.interviewer_id">
            <option value="">Select interviewer</option>
            <option v-for="r in recruiters" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </label>
        <label>
          Date & time
          <input v-model="interviewForm.scheduled_at" type="datetime-local" />
        </label>
        <label>
          Type
          <select v-model="interviewForm.type">
            <option value="online">Online</option>
            <option value="in_person">In person</option>
            <option value="phone">Phone</option>
          </select>
        </label>
        <label>
          Location / link
          <input v-model="interviewForm.location" type="text" placeholder="Office or meeting link" />
        </label>
      </div>
      <div class="rec-modal__actions">
        <button type="button" class="emp-filter-sheet__clear" @click="showInterviewModal = false">Cancel</button>
        <button type="button" class="emp-filter-sheet__apply" :disabled="savingInterview" @click="confirmRescheduleInterview">
          {{ savingInterview ? 'Saving…' : 'Save' }}
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
  updateInterview,
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
  filteredInterviews, 
  applicants,
  loadAll,
  loadApplicantDetail,
  selectJob,
  clearFilters,
  PIPELINE_STAGES,
  saveJob,
  removeJob,
} = useRecruitmentManagement()

const viewTabs = [
  { id: 'jobs', label: 'Jobs', icon: 'lucide:briefcase' },
  { id: 'applicants', label: 'Applicants', icon: 'lucide:users' },
  { id: 'interviews', label: 'Interviews', icon: 'lucide:calendar-clock' },
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


const showJobModal = ref(false)
const editingJobId = ref(null)
const savingJob = ref(false)
const jobForm = ref(defaultJobForm())
const skillInputValue = ref('')
const customQuestionInputValue = ref('')

const showInterviewModal = ref(false)
const editingInterview = ref(null)
const savingInterview = ref(false)
const interviewForm = ref({
  interviewer_id: '',
  scheduled_at: '',
  type: 'online',
  location: '',
})

const scheduleForm = ref({
  applicant_id: '',
  interviewer_id: '',
  scheduled_at: '',
  type: 'online',
  location: '',
})

const searchPlaceholder = computed(() => {
  if (activeView.value === 'jobs') return 'Search job title, department, location, recruiter…'
  if (activeView.value === 'interviews') return 'Search applicant, job, interviewer…'
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

const REQUIRED_DOCUMENT_OPTIONS = [
  { value: 'profile_image', label: 'Profile Image' },
  { value: 'resume', label: 'Resume' },
  { value: 'cover_letter', label: 'Cover Letter' },
  { value: 'terms_and_condition', label: 'Terms and Condition' },
]

function defaultJobForm() {
  return {
    title: '',
    description: '',
    department_id: '',
    branch_id: '',
    hiring_manager_id: '',
    job_type: 'full_time',
    status: 'draft',
    openings: 1,
    closing_date: '',
    skills: [],
    required_documents: [],
    custom_questions: [], 
  }
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

function addSkill() {
  const value = skillInputValue.value.trim()
  if (!value) return
  if (!jobForm.value.skills.includes(value)) {
    jobForm.value.skills.push(value)
  }
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

function addCustomQuestion() {
  const value = customQuestionInputValue.value.trim()
  if (!value) return
  jobForm.value.custom_questions.push({ question: value, type: 'text', required: false })
  customQuestionInputValue.value = ''
}

function removeCustomQuestion(index) {
  jobForm.value.custom_questions.splice(index, 1)
}

function openCreateJob() {
  editingJobId.value = null
  jobForm.value = defaultJobForm()
  skillInputValue.value = ''
  customQuestionInputValue.value = ''
  showJobModal.value = true
}

function openEditJob(job) {
  editingJobId.value = job.id
  jobForm.value = {
    title: job.title,
    description: job.description || job.raw?.description || '',
    department_id: job.departmentId || '',
    branch_id: job.branchId || '',
    hiring_manager_id: job.recruiterId || '',
    job_type: job.jobType || 'full_time',
    status: job.raw?.status || 'draft',
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
  if (!jobForm.value.title) {
    Swal.fire({ icon: 'warning', title: 'Title is required' })
    return
  }
  if (!jobForm.value.openings || jobForm.value.openings < 1) {
    Swal.fire({ icon: 'warning', title: 'Openings must be at least 1' })
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
      job_type: jobForm.value.job_type,
      status: jobForm.value.status,
      openings: jobForm.value.openings,
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

function toDatetimeLocalValue(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function openRescheduleInterview(interview) {
  editingInterview.value = interview
  interviewForm.value = {
    interviewer_id: interview.interviewerId || '',
    scheduled_at: toDatetimeLocalValue(interview.scheduledAt),
    type: interview.type || 'online',
    location: interview.location || interview.meetingLink || '',
  }
  showInterviewModal.value = true
}

async function confirmRescheduleInterview() {
  if (!editingInterview.value) return
  if (!interviewForm.value.interviewer_id || !interviewForm.value.scheduled_at) {
    Swal.fire({ icon: 'warning', title: 'Missing fields', text: 'Select interviewer and date/time.' })
    return
  }
  savingInterview.value = true
  try {
    await updateInterview(editingInterview.value.id, {
      interviewer_id: interviewForm.value.interviewer_id,
      scheduled_at: interviewForm.value.scheduled_at,
      type: interviewForm.value.type,
      location: interviewForm.value.location,
      meeting_link: interviewForm.value.type === 'online' ? interviewForm.value.location : undefined,
      status: 'scheduled',
    })
    Swal.fire({ icon: 'success', title: 'Interview updated', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    showInterviewModal.value = false
    editingInterview.value = null
    await loadAll()
  } catch (e) {
    const errors = e?.response?.data?.errors
    const msg = errors ? Object.values(errors).flat().join('\n') : (e?.response?.data?.message || e?.message)
    Swal.fire({ icon: 'error', title: 'Failed to update interview', text: msg })
  } finally {
    savingInterview.value = false
  }
}

async function markInterviewCompleted(interview) {
  const result = await Swal.fire({
    title: 'Mark as completed',
    html:
      '<textarea id="swal-feedback" class="swal2-textarea" placeholder="Feedback (optional)"></textarea>' +
      '<input id="swal-rating" type="number" min="1" max="5" class="swal2-input" placeholder="Rating 1-5 (optional)">',
    showCancelButton: true,
    confirmButtonText: 'Save',
    preConfirm: () => ({
      feedback: document.getElementById('swal-feedback')?.value || '',
      rating: document.getElementById('swal-rating')?.value || null,
    }),
  })
  if (!result.isConfirmed) return
  try {
    await updateInterview(interview.id, {
      status: 'completed',
      feedback: result.value.feedback,
      rating: result.value.rating ? Number(result.value.rating) : null,
    })
    Swal.fire({ icon: 'success', title: 'Interview completed', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function markInterviewNoShow(interview) {
  const result = await Swal.fire({
    title: 'Mark as no-show?',
    text: interview.applicantName,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await updateInterview(interview.id, { status: 'no_show' })
    Swal.fire({ icon: 'success', title: 'Marked as no-show', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function cancelInterview(interview) {
  const result = await Swal.fire({
    title: 'Cancel this interview?',
    text: interview.applicantName,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Yes, cancel it',
  })
  if (!result.isConfirmed) return
  try {
    await updateInterview(interview.id, { status: 'cancelled' })
    Swal.fire({ icon: 'success', title: 'Interview cancelled', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
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
