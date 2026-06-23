import { ref, computed, watch, onMounted } from 'vue'
import {
  fetchAttendanceRecords,
  fetchLeaveRequests,
  fetchLeaveStatistics,
  fetchLeaveTypes,
  fetchAgentEmployees,
  fetchPeriodReport,
  isEarlyDeparture,
} from '@/services/leaveAttendanceApi'
import { fetchDepartments } from '@/services/employeesApi'
import { localDateStringYMD } from '@/composables/useHrDashboard'

const DEFAULT_FILTERS = () => ({
  department: '',
  team: '',
  attendance_status: '',
  leave_type_id: '',
  manager_id: '',
  start_date: '',
  end_date: '',
})

export function useLeaveAttendanceManagement() {
  const loading = ref(false)
  const error = ref('')
  const searchQuery = ref('')
  const filters = ref(DEFAULT_FILTERS())
  const selectedDate = ref(localDateStringYMD())
  const activeView = ref('records')
  const attendanceRows = ref([])
  const attendanceSummary = ref({})
  const leaveRows = ref([])
  const leaveStats = ref({})
  const leaveTypes = ref([])
  const departments = ref([])
  const agents = ref([])
  const leavePage = ref(1)
  const leaveLastPage = ref(1)
  const loadingMoreLeaves = ref(false)
  const analyticsData = ref(null)
  const calendarMonth = ref(new Date())

  let searchTimer = null

  const activeFilterCount = computed(() =>
    Object.values(filters.value).filter((v) => v !== '' && v != null).length
  )

  const hasActiveFilters = computed(
    () => activeFilterCount.value > 0 || searchQuery.value.trim().length > 0
  )

  const earlyDepartures = computed(() =>
    attendanceRows.value.filter((r) => r.status === 'present' && isEarlyDeparture(r.checkOut)).length
  )

  const attendanceRate = computed(() => {
    const total = Number(attendanceSummary.value.total_employees) || attendanceRows.value.length
    const present = Number(attendanceSummary.value.present_today) ||
      attendanceRows.value.filter((r) => r.status === 'present' || r.status === 'late').length
    if (!total) return 0
    return Math.round((present / total) * 100)
  })

  const employeesOnLeaveToday = computed(() => {
    const today = selectedDate.value
    return leaveRows.value.filter((l) => {
      if (l.status !== 'approved') return false
      return l.startDate <= today && l.endDate >= today
    }).length
  })

  const kpiCards = computed(() => [
    { key: 'present', label: 'Present Today', value: attendanceSummary.value.present_today ?? 0, icon: 'lucide:user-check', bgColor: '#e8f8ed', iconColor: '#16a34a' },
    { key: 'absent', label: 'Absent Today', value: attendanceSummary.value.absent_today ?? 0, icon: 'lucide:user-x', bgColor: '#fee2e2', iconColor: '#dc2626' },
    { key: 'leave', label: 'On Leave', value: employeesOnLeaveToday.value, icon: 'lucide:calendar-off', bgColor: '#fff7ed', iconColor: '#ea580c' },
    { key: 'late', label: 'Late Arrivals', value: attendanceSummary.value.late_today ?? 0, icon: 'lucide:clock-alert', bgColor: '#fef3c7', iconColor: '#d97706' },
    { key: 'early', label: 'Early Departures', value: earlyDepartures.value, icon: 'lucide:log-out', bgColor: '#f4e8ff', iconColor: '#9333ea' },
    { key: 'rate', label: 'Attendance Rate', value: `${attendanceRate.value}%`, icon: 'lucide:percent', bgColor: 'rgba(115, 62, 135, 0.12)', iconColor: '#733E87' },
  ])

  function matchesSearch(row, fields) {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return true
    return fields.some((f) => String(f || '').toLowerCase().includes(q))
  }

  const filteredAttendance = computed(() =>
    attendanceRows.value.filter((row) => {
      if (!matchesSearch(row, [row.name, row.employeeId, row.department])) return false
      if (filters.value.department && row.department !== filters.value.department) return false
      if (filters.value.attendance_status && row.status !== filters.value.attendance_status) return false
      if (filters.value.manager_id) {
        const agent = agents.value.find((a) => String(a.id) === String(filters.value.manager_id))
        const teamIds = agents.value.filter((a) => String(a.parent_id) === String(filters.value.manager_id)).map((a) => a.id)
        if (agent && !teamIds.includes(row.raw?.user_id) && String(row.id) !== String(agent.id)) {
          // loose match by name in department
        }
      }
      return true
    })
  )

  const filteredLeaves = computed(() =>
    leaveRows.value.filter((row) => {
      if (!matchesSearch(row, [row.employeeName, row.empCode, row.leaveType])) return false
      if (filters.value.leave_type_id && String(row.leaveTypeId) !== String(filters.value.leave_type_id)) return false
      if (filters.value.start_date && row.endDate < filters.value.start_date) return false
      if (filters.value.end_date && row.startDate > filters.value.end_date) return false
      return true
    })
  )

  const leaveTrend = computed(() => {
    const stats = leaveStats.value
    return [
      { label: 'Approved', value: stats.approved ?? 0, color: '#16a34a' },
      { label: 'Pending', value: (stats.pending_parent ?? 0) + (stats.pending_hr ?? stats.pending ?? 0), color: '#d97706' },
      { label: 'Rejected', value: stats.rejected ?? 0, color: '#dc2626' },
    ]
  })

  const attendanceTrend = computed(() => {
    const s = attendanceSummary.value
    return [
      { label: 'Present', value: s.present_today ?? 0, color: '#16a34a' },
      { label: 'Absent', value: s.absent_today ?? 0, color: '#dc2626' },
      { label: 'Late', value: s.late_today ?? 0, color: '#d97706' },
    ]
  })

  async function loadOptions() {
    try {
      const [types, depts, users] = await Promise.all([
        fetchLeaveTypes(),
        fetchDepartments(),
        fetchAgentEmployees(),
      ])
      leaveTypes.value = types
      departments.value = depts
      agents.value = users
    } catch {
      leaveTypes.value = []
      departments.value = []
      agents.value = []
    }
  }

  async function loadAttendance() {
    const result = await fetchAttendanceRecords({
      date: selectedDate.value,
      status: filters.value.attendance_status || undefined,
    })
    attendanceSummary.value = result.summary || {}
    attendanceRows.value = result.rows
  }

  async function loadLeaves(reset = true) {
    const page = reset ? 1 : leavePage.value + 1
    const params = { page, per_page: 25, status: filters.value.leave_status }
    if (filters.value.start_date) params.start_date = filters.value.start_date
    if (filters.value.end_date) params.end_date = filters.value.end_date
    if (filters.value.leave_type_id) params.leave_type_id = filters.value.leave_type_id

    const result = await fetchLeaveRequests(params)
    if (reset) {
      leaveRows.value = result.items
    } else {
      leaveRows.value = [...leaveRows.value, ...result.items]
    }
    leavePage.value = result.currentPage
    leaveLastPage.value = result.lastPage
  }

  async function loadAll() {
    loading.value = true
    error.value = ''
    try {
      await Promise.all([
        loadAttendance(),
        loadLeaves(true),
        fetchLeaveStatistics().then((s) => { leaveStats.value = s }),
      ])
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || 'Failed to load data'
    } finally {
      loading.value = false
    }
  }

  async function loadAnalytics() {
    const end = selectedDate.value
    const start = filters.value.start_date || end.slice(0, 8) + '01'
    try {
      analyticsData.value = await fetchPeriodReport(start, end)
    } catch {
      analyticsData.value = null
    }
  }

  function clearFilters() {
    filters.value = DEFAULT_FILTERS()
    searchQuery.value = ''
    loadAll()
  }

  watch(selectedDate, () => loadAttendance())
  watch(searchQuery, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {}, 200)
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
    selectedDate,
    activeView,
    attendanceRows,
    attendanceSummary,
    leaveRows,
    leaveStats,
    leaveTypes,
    departments,
    agents,
    leavePage,
    leaveLastPage,
    loadingMoreLeaves,
    analyticsData,
    calendarMonth,
    activeFilterCount,
    hasActiveFilters,
    kpiCards,
    filteredAttendance,
    filteredLeaves,
    leaveTrend,
    attendanceTrend,
    loadAll,
    loadAttendance,
    loadLeaves,
    loadAnalytics,
    clearFilters,
  }
}
