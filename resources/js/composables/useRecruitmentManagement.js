import { ref, computed, watch, onMounted } from 'vue'
import {
  fetchRecruitmentStatistics,
  fetchJobs,
  fetchApplicants,
  fetchApplicant,
  fetchInterviews,
  updateInterview,
  createJob,
  updateJob,
  deleteJob,
  PIPELINE_STAGES,
  resolvePipelineStage,
} from '@/services/recruitmentApi'
import { fetchDepartments, fetchBranches } from '@/services/employeesApi'
import { fetchAgentEmployees } from '@/services/hrApi'

const DEFAULT_FILTERS = () => ({
  department_id: '',
  branch_id: '',
  job_type: '',
  status: '',
  hiring_manager_id: '',
  experience_level: '',
})

export function useRecruitmentManagement() {
  const loading = ref(false)
  const error = ref('')
  const searchQuery = ref('')
  const filters = ref(DEFAULT_FILTERS())
  const activeView = ref('jobs')
  const statistics = ref({})
  const jobs = ref([])
  const applicants = ref([])
  const interviews = ref([])
  const departments = ref([])
  const recruiters = ref([])
  const branches = ref([])
  const selectedJob = ref(null)
  const selectedApplicant = ref(null)
  const applicantDetailLoading = ref(false)
  const jobsPage = ref(1)
  const jobsLastPage = ref(1)

  let searchTimer = null

  const activeFilterCount = computed(() =>
    Object.values(filters.value).filter((v) => v !== '' && v != null).length
  )

  const hasActiveFilters = computed(
    () => activeFilterCount.value > 0 || searchQuery.value.trim().length > 0
  )

  const kpiCards = computed(() => {
    const s = statistics.value
    return [
      { key: 'open', label: 'Open Positions', value: s.open_jobs ?? 0, icon: 'lucide:briefcase', bgColor: 'rgba(115, 62, 135, 0.12)', iconColor: '#733E87' },
      { key: 'active', label: 'Active Applications', value: s.active_applications ?? s.total_applicants ?? 0, icon: 'lucide:file-user', bgColor: '#f4e8ff', iconColor: '#9333ea' },
      { key: 'interviews', label: 'Interviews Scheduled', value: s.interviews_scheduled ?? s.upcoming_interviews ?? 0, icon: 'lucide:calendar-clock', bgColor: '#e0f2fe', iconColor: '#0284c7' },
      { key: 'offers', label: 'Offers Sent', value: s.offers_sent ?? 0, icon: 'lucide:send', bgColor: '#fff7ed', iconColor: '#ea580c' },
      { key: 'hires', label: 'Hires This Month', value: s.hires_this_month ?? 0, icon: 'lucide:user-check', bgColor: '#e8f8ed', iconColor: '#16a34a' },
    ]
  })

  const pipelineBoard = computed(() => {
    const board = {}
    PIPELINE_STAGES.forEach((stage) => {
      board[stage.id] = []
    })
    filteredApplicants.value.forEach((applicant) => {
      const stage = applicant.pipelineStage || resolvePipelineStage(applicant.raw)
      if (board[stage]) board[stage].push(applicant)
      else board.applied.push(applicant)
    })
    return board
  })

  function matchesSearch(texts) {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return true
    return texts.some((t) => String(t || '').toLowerCase().includes(q))
  }

  const filteredJobs = computed(() =>
    jobs.value.filter((job) => {
      if (!matchesSearch([job.title, job.department, job.location, job.recruiter])) return false
      if (filters.value.department_id && String(job.departmentId) !== String(filters.value.department_id)) return false
      if (filters.value.branch_id && String(job.branchId) !== String(filters.value.branch_id)) return false
      if (filters.value.job_type && job.jobType !== filters.value.job_type) return false
      if (filters.value.status && job.status !== filters.value.status) return false
      if (filters.value.hiring_manager_id && String(job.recruiterId) !== String(filters.value.hiring_manager_id)) return false
      return true
    })
  )

  const filteredApplicants = computed(() => {
    let list = applicants.value
    if (selectedJob.value) {
      list = list.filter((a) => String(a.jobId) === String(selectedJob.value.id))
    }
    return list.filter((applicant) => {
      if (!matchesSearch([applicant.name, applicant.appliedPosition, applicant.email])) return false
      if (filters.value.experience_level && applicant.experienceLevel !== filters.value.experience_level) return false
      return true
    })
  })
  const filteredInterviews = computed(() =>
  interviews.value.filter((interview) => {
    if (!matchesSearch([interview.applicantName, interview.jobTitle, interview.interviewerName])) return false
    if (selectedJob.value && String(interview.jobId) !== String(selectedJob.value.id)) return false
    if (filters.value.hiring_manager_id && String(interview.interviewerId) !== String(filters.value.hiring_manager_id)) return false
    return true
  })
)

  async function loadOptions() {
    try {
      const [depts, users, branchList] = await Promise.all([
        fetchDepartments(),
        fetchAgentEmployees(),
        fetchBranches(),
      ])
      departments.value = depts
      recruiters.value = users
      branches.value = branchList
    } catch {
      departments.value = []
      recruiters.value = []
      branches.value = []
    }
  }

async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    const [stats, jobsResult, applicantsResult, interviewsResult] = await Promise.all([
      fetchRecruitmentStatistics(),
      fetchJobs({ per_page: 100 }),
      fetchApplicants({ per_page: 200 }),
      fetchInterviews({ per_page: 100 }),
    ])
    statistics.value = stats
    jobs.value = jobsResult.items
    applicants.value = applicantsResult.items.map((a) => ({
      ...a,
      pipelineStage: resolvePipelineStage(a.raw),
    }))
    interviews.value = interviewsResult.items
    jobsPage.value = jobsResult.currentPage
    jobsLastPage.value = jobsResult.lastPage
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load recruitment data'
  } finally {
    loading.value = false
  }
}

async function reloadInterviews() {
  const result = await fetchInterviews({ per_page: 100 })
  interviews.value = result.items
}

async function saveInterviewUpdate(id, payload) {
  await updateInterview(id, payload)
  await reloadInterviews()
}

  async function loadApplicantDetail(id) {
    applicantDetailLoading.value = true
    try {
      selectedApplicant.value = await fetchApplicant(id)
    } catch {
      selectedApplicant.value = applicants.value.find((a) => a.id === id) || null
    } finally {
      applicantDetailLoading.value = false
    }
  }

  function selectJob(job) {
    selectedJob.value = job
    activeView.value = 'pipeline'
  }

  function clearFilters() {
    filters.value = DEFAULT_FILTERS()
    searchQuery.value = ''
  }
    async function saveJob(payload, jobId = null) {
      if (jobId) {
        await updateJob(jobId, payload)
      } else {
        await createJob(payload)
      }
      await loadAll()
    }

    async function removeJob(jobId) {
      await deleteJob(jobId)
      await loadAll()
    }
  watch(searchQuery, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {}, 250)
  })

  onMounted(async () => {
    await loadOptions()
    await loadAll()
  })

  return {
    loading,
    error,
    searchQuery,
    filters,
    activeView,
    statistics,
    jobs,
    applicants,
    interviews,
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
    loadAll,
    loadApplicantDetail,
    selectJob,
    clearFilters,
    PIPELINE_STAGES,
    saveJob,
    removeJob,
        reloadInterviews,
    saveInterviewUpdate,
  }
}
