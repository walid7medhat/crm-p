// في useLeaveAttendanceManagement.js

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
  
  // ✅ Pagination variables (للـ Frontend فقط)
  const attendancePage = ref(1)
  const attendancePerPage = ref(10)
  const leaveTablePage = ref(1)
  const leavePerPage = ref(10)
  
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

  // ✅ filteredAttendance - كل البيانات بعد التصفية
  const filteredAttendance = computed(() =>
    attendanceRows.value.filter((row) => {
      if (!matchesSearch(row, [row.name, row.employeeId, row.department, row.branch, row.empCode])) return false
      if (filters.value.department && row.department !== filters.value.department) return false
      if (filters.value.attendance_status && row.status !== filters.value.attendance_status) return false
      return true
    })
  )

  // ✅ pagedAttendance - البيانات المقسمة حسب الصفحة
  const pagedAttendance = computed(() => {
    const start = (attendancePage.value - 1) * attendancePerPage.value
    const end = start + attendancePerPage.value
    return filteredAttendance.value.slice(start, end)
  })

  // ✅ attendanceTotal - إجمالي عدد السجلات بعد التصفية
  const attendanceTotal = computed(() => filteredAttendance.value.length)

  // ✅ attendanceTotalPages - عدد الصفحات
  const attendanceTotalPages = computed(() => {
    return Math.max(1, Math.ceil(attendanceTotal.value / attendancePerPage.value))
  })

  // ✅ attendanceStartEntry - أول عنصر في الصفحة
  const attendanceStartEntry = computed(() => {
    if (!attendanceTotal.value) return 0
    return (attendancePage.value - 1) * attendancePerPage.value + 1
  })

  // ✅ attendanceEndEntry - آخر عنصر في الصفحة
  const attendanceEndEntry = computed(() => {
    return Math.min(attendancePage.value * attendancePerPage.value, attendanceTotal.value)
  })

  // ✅ attendancePaginationItems - عناصر الـ Pagination
  function buildPaginationItems(total, current) {
    if (total <= 1) return [{ type: 'page', n: 1 }]
    if (total <= 7) {
      return Array.from({ length: total }, (_, i) => ({ type: 'page', n: i + 1 }))
    }
    const items = []
    const pushDots = () => {
      if (items.length && items[items.length - 1].type === 'dots') return
      items.push({ type: 'dots' })
    }
    items.push({ type: 'page', n: 1 })
    const left = Math.max(2, current - 1)
    const right = Math.min(total - 1, current + 1)
    if (left > 2) pushDots()
    for (let i = left; i <= right; i += 1) items.push({ type: 'page', n: i })
    if (right < total - 1) pushDots()
    items.push({ type: 'page', n: total })
    return items
  }

  const attendancePaginationItems = computed(() =>
    buildPaginationItems(attendanceTotalPages.value, attendancePage.value)
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

  const pagedLeaves = computed(() => {
    const start = (leaveTablePage.value - 1) * leavePerPage.value
    return filteredLeaves.value.slice(start, start + leavePerPage.value)
  })
  const leaveTotalPages = computed(() => Math.max(1, Math.ceil(filteredLeaves.value.length / leavePerPage.value)))
  const leaveStartEntry = computed(() => (filteredLeaves.value.length ? (leaveTablePage.value - 1) * leavePerPage.value + 1 : 0))
  const leaveEndEntry = computed(() => Math.min(leaveTablePage.value * leavePerPage.value, filteredLeaves.value.length))
  const leavePaginationItems = computed(() => buildPaginationItems(leaveTotalPages.value, leaveTablePage.value))

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
    const params = {
      status: filters.value.attendance_status || undefined,
      department: filters.value.department || undefined,
      search: searchQuery.value.trim() || undefined,
    }
    if (filters.value.start_date || filters.value.end_date) {
      params.start_date = filters.value.start_date || filters.value.end_date
      params.end_date = filters.value.end_date || filters.value.start_date
    } else {
      const now = new Date()
      params.start_date = localDateStringYMD(new Date(now.getFullYear(), now.getMonth(), 1))
      params.end_date = selectedDate.value
    }
    const result = await fetchAttendanceRecords(params)

    attendanceSummary.value = result.summary || {
      total_employees: 0,
      present_today: 0,
      absent_today: 0,
      late_today: 0,
    }

    attendanceRows.value = result.rows || []
    attendancePage.value = 1
  }

  async function loadLeaves() {
    const params = { per_page: 50, status: filters.value.leave_status }
    if (filters.value.start_date) params.start_date = filters.value.start_date
    if (filters.value.end_date) params.end_date = filters.value.end_date
    if (filters.value.leave_type_id) params.leave_type_id = filters.value.leave_type_id

    let page = 1
    let lastPage = 1
    const all = []
    do {
      const result = await fetchLeaveRequests({ ...params, page })
      all.push(...(result.items || []))
      lastPage = result.lastPage || 1
      page += 1
    } while (page <= lastPage && page <= 20)

    leaveRows.value = all
    leavePage.value = 1
    leaveLastPage.value = 1
    leaveTablePage.value = 1
  }

  async function loadAll() {
    loading.value = true
    error.value = ''
    try {
      await Promise.all([
        loadAttendance(),
        loadLeaves(),
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

  // ✅ مراقبة تغيير الصفحة - فقط إعادة حساب الـ Pagination
  watch(attendancePage, () => {
    // Frontend pagination only; no refetch needed.
  })

  watch(attendancePerPage, () => {
    attendancePage.value = 1
  })

  watch(leavePerPage, () => {
    leaveTablePage.value = 1
  })

  watch(selectedDate, () => {
    attendancePage.value = 1
    loadAttendance()
  })

  watch(searchQuery, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      attendancePage.value = 1
    }, 200)
  })

  onMounted(async () => {
    await loadOptions()
    await loadAll()
  })

  return {
    // المتغيرات الأساسية
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
    
    // ✅ متغيرات Pagination
    attendancePage,
    attendancePerPage,
    attendanceTotal,
    attendanceTotalPages,
    attendanceStartEntry,
    attendanceEndEntry,
    attendancePaginationItems,
    pagedAttendance,
    leaveTablePage,
    leavePerPage,
    leaveTotalPages,
    leaveStartEntry,
    leaveEndEntry,
    leavePaginationItems,
    pagedLeaves,
    
    // الدوال
    loadAll,
    loadAttendance,
    loadLeaves,
    loadAnalytics,
    clearFilters,
  }
}