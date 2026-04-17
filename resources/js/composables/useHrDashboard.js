import { computed, ref } from 'vue'
import { fetchAgentEmployees, fetchAttendance, fetchAttendanceToday, fetchLeadTotalCount } from '@/services/hrApi'

export function useHrDashboard() {
  const loading = ref(false)
  const loadingAgents = ref(false)
  const error = ref('')
  const dateFilter = ref(new Date().toISOString().slice(0, 10))
  const statusFilter = ref('all')
  const employees = ref([])
  const agentEmployees = ref([])
  const summary = ref({
    total_employees: 0,
    present_today: 0,
    absent_today: 0,
    late_today: 0,
  })
  const totalLeads = ref(0)
  const distributionResult = ref([])
  const teamSearch = ref('')
  const teamFilter = ref('all')
  const treeStatusFilter = ref('all')

  const presentEmployees = computed(() => employees.value.filter((e) => e.status === 'present'))

  async function loadAttendance(todayOnly = false) {
    loading.value = true
    error.value = ''
    try {
      const params = {
        date: dateFilter.value,
        status: statusFilter.value,
      }
      const response = todayOnly ? await fetchAttendanceToday(params) : await fetchAttendance(params)
      const payload = response?.data || {}
      employees.value = Array.isArray(payload.employees) ? payload.employees : []
      summary.value = payload.summary || summary.value
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load attendance data'
      employees.value = []
    } finally {
      loading.value = false
    }
  }

  async function loadLeadsCount() {
    try {
      totalLeads.value = await fetchLeadTotalCount()
    } catch (e) {
      totalLeads.value = 0
    }
  }

  async function loadAgentData() {
    loadingAgents.value = true
    try {
      agentEmployees.value = await fetchAgentEmployees()
    } catch (e) {
      agentEmployees.value = []
    } finally {
      loadingAgents.value = false
    }
  }

  function distributeLeads() {
    const present = presentEmployees.value
    const leads = Number(totalLeads.value) || 0
    if (!present.length || leads <= 0) {
      distributionResult.value = []
      return []
    }

    const base = Math.floor(leads / present.length)
    let remainder = leads % present.length

    const result = present.map((employee) => {
      const assigned = base + (remainder > 0 ? 1 : 0)
      if (remainder > 0) remainder -= 1
      return {
        employee_id: employee.employee_id,
        employee_name: employee.employee_name,
        assigned_leads: assigned,
      }
    })

    distributionResult.value = result
    return result
  }

  const chartSeries = computed(() => [summary.value.present_today, summary.value.absent_today, summary.value.late_today])

  const mergedEmployees = computed(() => {
    const byId = new Map(
      agentEmployees.value.map((agent) => {
        const id =
          agent?.employee_id ??
          agent?.id ??
          agent?.user_id ??
          null
        return [String(id), agent]
      })
    )

    return employees.value.map((attendanceRow) => {
      const employeeId = String(attendanceRow?.employee_id ?? '')
      const agent = byId.get(employeeId) || null

      // Source of truth: always use HR Attendance API name.
      const employeeName = attendanceRow?.employee_name || 'Unknown'
      const teamName =
        agent?.team?.name ||
        agent?.team_name ||
        agent?.department ||
        agent?.office_name ||
        'Unassigned'

      return {
        employee_id: attendanceRow?.employee_id ?? null,
        employee_name: employeeName,
        status: attendanceRow?.status || 'absent',
        check_in: attendanceRow?.check_in ?? null,
        check_out: attendanceRow?.check_out ?? null,
        attendance_indicator: attendanceRow?.check_in ? 'checked-in' : 'no-check-in',
        team_name: teamName,
        agent_record: agent,
        attendance_record: attendanceRow,
      }
    })
  })

  const statusRank = {
    present: 0,
    late: 1,
    absent: 2,
  }

  const filteredMergedEmployees = computed(() => {
    const q = teamSearch.value.trim().toLowerCase()

    return mergedEmployees.value
      .filter((employee) => {
        if (teamFilter.value !== 'all' && employee.team_name !== teamFilter.value) return false
        if (treeStatusFilter.value !== 'all' && employee.status !== treeStatusFilter.value) return false

        if (!q) return true
        const name = String(employee.employee_name || '').toLowerCase()
        const id = String(employee.employee_id || '').toLowerCase()
        return name.includes(q) || id.includes(q)
      })
      .sort((a, b) => {
        const ra = statusRank[a.status] ?? 99
        const rb = statusRank[b.status] ?? 99
        if (ra !== rb) return ra - rb
        return String(a.employee_name || '').localeCompare(String(b.employee_name || ''))
      })
  })

  const groupedTeamTree = computed(() => {
    const grouped = new Map()
    filteredMergedEmployees.value.forEach((employee) => {
      const key = employee.team_name || 'Unassigned'
      if (!grouped.has(key)) grouped.set(key, [])
      grouped.get(key).push(employee)
    })

    return Array.from(grouped.entries())
      .map(([team_name, members]) => ({
        team_name,
        members,
      }))
      .sort((a, b) => a.team_name.localeCompare(b.team_name))
  })

  const teamOptions = computed(() => {
    const unique = Array.from(new Set(mergedEmployees.value.map((e) => e.team_name || 'Unassigned')))
    return ['all', ...unique.sort((a, b) => a.localeCompare(b))]
  })

  return {
    loading,
    loadingAgents,
    error,
    dateFilter,
    statusFilter,
    employees,
    agentEmployees,
    summary,
    totalLeads,
    distributionResult,
    presentEmployees,
    chartSeries,
    teamSearch,
    teamFilter,
    treeStatusFilter,
    mergedEmployees,
    filteredMergedEmployees,
    groupedTeamTree,
    teamOptions,
    loadAttendance,
    loadAgentData,
    loadLeadsCount,
    distributeLeads,
  }
}

