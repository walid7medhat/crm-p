import api from '@/plugins/axios'

export const PIPELINE_STAGES = [
  { id: 'applied', label: 'Applied', apiStatus: 'pending', color: '#64748b' },
  { id: 'screening', label: 'Screening', apiStatus: 'shortlisted', color: '#3b82f6' },
  { id: 'interview', label: 'Interview', apiStatus: 'interview', color: '#9333ea' },
  { id: 'assessment', label: 'Assessment', apiStatus: 'shortlisted', color: '#0891b2' },
  { id: 'offer', label: 'Offer', apiStatus: 'interview', color: '#d97706' },
  { id: 'hired', label: 'Hired', apiStatus: 'hired', color: '#16a34a' },
  { id: 'rejected', label: 'Rejected', apiStatus: 'rejected', color: '#dc2626' },
]

function unwrapPaginated(payload) {
  const root = payload?.data
  if (root?.data && Array.isArray(root.data)) {
    return {
      items: root.data,
      currentPage: root.current_page ?? 1,
      lastPage: root.last_page ?? 1,
      total: root.total ?? root.data.length,
    }
  }
  if (Array.isArray(root)) {
    return { items: root, currentPage: 1, lastPage: 1, total: root.length }
  }
  return { items: [], currentPage: 1, lastPage: 1, total: 0 }
}

const JOB_TYPE_LABELS = {
  full_time: 'Full-time',
  part_time: 'Part-time',
  contract: 'Contract',
  internship: 'Internship',
  remote: 'Remote',
}

function toDateOnly(value) {
  if (!value) return null
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return null
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function normalizeJob(job) {
  if (!job) return null
  return {
    id: job.id,
    title: job.title,
    department: job.department?.name || '—',
    departmentId: job.department_id,
    location: job.branch?.name || job.branch_name || '—',
    branchId: job.branch_id,
    employmentType: JOB_TYPE_LABELS[job.job_type] || job.job_type || '—',
    jobType: job.job_type,
    applicantsCount: job.applicants_count ?? job.applicants?.length ?? 0,
    postedDate: toDateOnly(job.posted_date),
    closingDate: toDateOnly(job.closing_date),
    status: job.status,
    statusLabel: formatJobStatus(job.status),
    openings: job.openings,
    skills: Array.isArray(job.skills) ? job.skills : [],
    customQuestions: Array.isArray(job.custom_questions) ? job.custom_questions : [],
    requiredDocuments: Array.isArray(job.required_documents) ? job.required_documents : [],
    description: job.description || '',
    recruiter: job.hiring_manager?.name || '—',
    recruiterId: job.hiring_manager_id,
    raw: job,
  }
}

export function normalizeApplicant(applicant) {
  if (!applicant) return null
  const job = applicant.job || {}
  return {
    id: applicant.id,
    name: applicant.full_name,
    email: applicant.email,
    phone: applicant.phone || '—',
    appliedPosition: job.title || '—',
    jobId: applicant.job_id,
    experienceLevel: formatExperience(applicant.total_experience_years),
    applicationDate: applicant.applied_at,
    status: applicant.status,
    statusLabel: formatApplicantStatus(applicant.status),
    pipelineStage: resolvePipelineStage(applicant),
    avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(applicant.full_name || 'A')}&background=733e87&color=fff`,
    resumeUrl: applicant.resume_url || (applicant.resume_path ? `/storage/${applicant.resume_path}` : null),
    nationality: applicant.nationality || '—',
    notes: applicant.additional_notes || '',
    interviews: applicant.interviews || [],
    answers: applicant.answers || {},
    raw: applicant,
  }
}

export function resolvePipelineStage(applicant) {
  const status = applicant.status
  const interviews = applicant.interviews || []
  const hasScheduled = interviews.some((i) => i.status === 'scheduled')
  const hasCompleted = interviews.some((i) => i.status === 'completed')

  if (status === 'rejected' || status === 'withdrawn') return 'rejected'
  if (status === 'hired') return 'hired'
  if (status === 'interview') {
    return hasCompleted && !hasScheduled ? 'offer' : 'interview'
  }
  if (status === 'shortlisted') {
    return hasCompleted ? 'assessment' : 'screening'
  }
  return 'applied'
}

export function stageToApiStatus(stageId) {
  const stage = PIPELINE_STAGES.find((s) => s.id === stageId)
  return stage?.apiStatus || 'pending'
}

export function formatJobStatus(status) {
  const map = {
    draft: 'Draft',
    open: 'Open',
    on_hold: 'On Hold',
    closed: 'Closed',
    cancelled: 'Cancelled',
  }
  return map[status] || status || '—'
}

export function formatApplicantStatus(status) {
  const map = {
    pending: 'Applied',
    shortlisted: 'Screening',
    interview: 'Interview',
    hired: 'Hired',
    rejected: 'Rejected',
    withdrawn: 'Withdrawn',
  }
  return map[status] || status || '—'
}

function formatExperience(years) {
  if (years == null || years === '') return '—'
  const n = Number(years)
  if (!Number.isFinite(n)) return String(years)
  if (n < 2) return 'Junior'
  if (n < 5) return 'Mid-level'
  if (n < 10) return 'Senior'
  return 'Lead'
}

export async function fetchRecruitmentStatistics() {
  const response = await api.get('/recruitment/admin/statistics')
  return response.data?.data ?? {}
}

export async function fetchJobs(params = {}) {
  const response = await api.get('/recruitment/admin/jobs', { params })
  const page = unwrapPaginated(response.data)
  return { ...page, items: page.items.map(normalizeJob) }
}

export async function fetchJob(id) {
  const response = await api.get(`/recruitment/admin/jobs/${id}`)
  return normalizeJob(response.data?.data)
}

export async function fetchApplicants(params = {}) {
  const response = await api.get('/recruitment/admin/applicants', { params })
  const page = unwrapPaginated(response.data)
  return { ...page, items: page.items.map(normalizeApplicant) }
}

export async function fetchApplicant(id) {
  const response = await api.get(`/recruitment/admin/applicants/${id}`)
  const data = response.data?.data
  if (data?.resume_path && !data.resume_url) {
    data.resume_url = `/storage/${data.resume_path}`
  }
  return normalizeApplicant(data)
}

export async function fetchInterviews(params = {}) {
  const response = await api.get('/recruitment/admin/interviews', { params })
  const page = unwrapPaginated(response.data)
  return { ...page, items: page.items.map(normalizeInterview) }
}

export async function updateInterview(id, payload) {
  const response = await api.put(`/recruitment/admin/interviews/${id}`, payload)
  return normalizeInterview(response.data?.data)
}

export async function updateApplicantStatus(id, status, rejectionReason = '') {
  await api.put(`/recruitment/admin/applicants/${id}/status`, {
    status,
    rejection_reason: rejectionReason || undefined,
  })
}

export async function scheduleInterview(payload) {
  const response = await api.post('/recruitment/admin/interviews', payload)
  return response.data?.data
}

export function exportRecruitmentCsv(filename, rows, columns) {
  const header = columns.map((c) => c.label).join(',')
  const body = rows.map((row) =>
    columns.map((c) => `"${String(c.value(row) ?? '').replace(/"/g, '""')}"`).join(',')
  ).join('\n')
  const blob = new Blob([`${header}\n${body}`], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}


export async function createJob(payload) {
  const response = await api.post('/recruitment/admin/jobs', payload)
  return normalizeJob(response.data?.data)
}

export async function updateJob(id, payload) {
  const response = await api.put(`/recruitment/admin/jobs/${id}`, payload)
  return normalizeJob(response.data?.data)
}

export async function deleteJob(id) {
  const response = await api.delete(`/recruitment/admin/jobs/${id}`)
  return response.data
}


export function normalizeInterview(interview) {
  if (!interview) return null
  return {
    id: interview.id,
    applicantId: interview.applicant_id,
    applicantName: interview.applicant?.full_name || '—',
    applicantEmail: interview.applicant?.email || '',
    jobId: interview.job_id,
    jobTitle: interview.job?.title || interview.applicant?.job?.title || '—',
    interviewerId: interview.interviewer_id,
    interviewerName: interview.interviewer?.name || '—',
    scheduledAt: interview.scheduled_at,
    type: interview.type,
    typeLabel: formatInterviewType(interview.type),
    location: interview.location || '',
    meetingLink: interview.meeting_link || '',
    status: interview.status,
    statusLabel: formatInterviewStatus(interview.status),
    feedback: interview.feedback || '',
    rating: interview.rating || null,
    raw: interview,
  }
}

export function formatInterviewType(type) {
  const map = { online: 'Online', in_person: 'In Person', phone: 'Phone' }
  return map[type] || type || '—'
}

export function formatInterviewStatus(status) {
  const map = { scheduled: 'Scheduled', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No Show' }
  return map[status] || status || '—'
}