import { computed, nextTick, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { fetchAgentEmployees, fetchAttendance, fetchLeadTotalCount } from '@/services/hrApi'
import { normalizeEmployeeId } from '@/utils/employeeId'
import { buildHrAttendanceUserTree, filterHrAttendanceTree } from '@/composables/hrAttendanceUserTree'

/**
 * Pipeline debug (console + strip UI) when any of:
 * - Vite dev server (`import.meta.env.DEV`)
 * - Built assets: set `VITE_HR_PIPELINE_DEBUG=1` in `.env` then `npm run build`
 * - Any time: open HR with `?hr_debug=1` (reads `window.location` each call)
 */
export function hrPipelineDebugEnabled() {
  if (import.meta.env.DEV) return true
  const envFlag = import.meta.env.VITE_HR_PIPELINE_DEBUG
  if (envFlag === '1' || envFlag === 'true') return true
  if (typeof window === 'undefined') return false
  try {
    return new URLSearchParams(window.location.search).get('hr_debug') === '1'
  } catch {
    return false
  }
}

/** Local calendar YYYY-MM-DD (not UTC-shifted ISO date). */
export function localDateStringYMD(d = new Date()) {
  return d.toLocaleDateString('en-CA')
}

/**
 * Normalize different backend / proxy response shapes into { employees, summary, date }.
 * Fixes empty UI when API returns { status, data: [...] } instead of { data: { employees } }.
 */
function parseDateOnly(value) {
  if (value == null || value === '') return null
  if (typeof value === 'string') return value.slice(0, 10)
  if (typeof value === 'object' && value instanceof Date && !Number.isNaN(value.getTime())) {
    return value.toISOString().slice(0, 10)
  }
  return String(value).slice(0, 10)
}

function toOptionalIntMinutes(value) {
  if (value == null || value === '') return null
  const n = Number(value)
  if (!Number.isFinite(n)) return null
  return Math.round(n)
}

function mapLegacyAttendanceRow(row) {
  if (!row || typeof row !== 'object') return null
  const user = row.user || {}
  const uid = row.employee_id ?? row.user_id ?? user.id ?? row.emp_code
  if (uid == null || uid === '') return null
  const name = row.employee_name ?? user.name ?? 'Unknown'
  const statusRaw = (row.status || 'present').toString().toLowerCase()
  let break_minutes = toOptionalIntMinutes(
    row.break_minutes ?? row.break_mins ?? row.break_duration_minutes ?? row.break_duration,
  )
  if (break_minutes == null && typeof row.break === 'number') break_minutes = toOptionalIntMinutes(row.break)
  let overtime_minutes = toOptionalIntMinutes(
    row.overtime_minutes ?? row.ot_minutes ?? row.overtime_mins ?? row.overtime_duration,
  )
  if (overtime_minutes == null && row.ot != null && typeof row.ot !== 'object') {
    overtime_minutes = toOptionalIntMinutes(row.ot)
  }
  if (overtime_minutes == null && typeof row.overtime === 'number') {
    overtime_minutes = toOptionalIntMinutes(row.overtime)
  }
  const break_label =
    typeof row.break_label === 'string' && row.break_label.trim() ? row.break_label.trim() : null
  const ot_label = typeof row.ot_label === 'string' && row.ot_label.trim() ? row.ot_label.trim() : null
  return {
    employee_id: uid,
    employee_id_normalized: normalizeEmployeeId(uid),
    employee_name: name,
    status: ['present', 'absent', 'late'].includes(statusRaw) ? statusRaw : 'present',
    check_in: row.check_in ?? row.first_checkin ?? null,
    check_out: row.check_out ?? row.last_checkout ?? null,
    date: parseDateOnly(row.date ?? row.attendance_date),
    department: row.department ?? user.department,
    email: row.email ?? user.email,
    break_minutes,
    overtime_minutes,
    break_label,
    ot_label,
  }
}

/** Force any payload fragment into a plain array. */
function coerceArray(value) {
  if (value == null) return []
  if (Array.isArray(value)) return value
  if (typeof value === 'object' && Array.isArray(value.data)) return value.data
  return []
}

/**
 * Extract employee rows from every known API envelope (including Laravel paginator + double data).
 */
function extractEmployeesFromRaw(raw) {
  if (raw == null) return []

  const tryMap = (arr) => coerceArray(arr).map(mapLegacyAttendanceRow).filter(Boolean)

  const candidates = [
    raw?.data?.employees,
    raw?.data?.employees?.data,
    raw?.employees,
    raw?.result?.employees,
    Array.isArray(raw?.data) ? raw.data : null,
    raw?.data?.data,
    raw?.records,
    raw?.data?.records,
  ]

  for (const c of candidates) {
    const mapped = tryMap(c)
    if (mapped.length) return mapped
  }

  // Single object mistaken for list
  if (raw?.data && typeof raw.data === 'object' && !Array.isArray(raw.data) && raw.data.user_id) {
    const one = mapLegacyAttendanceRow(raw.data)
    return one ? [one] : []
  }

  return []
}

function summarizeEmployees(list) {
  const employees = Array.isArray(list) ? list : []
  return {
    total_employees: employees.length,
    present_today: employees.filter((e) => e.status === 'present').length,
    absent_today: employees.filter((e) => e.status === 'absent').length,
    late_today: employees.filter((e) => e.status === 'late').length,
  }
}

function coerceEmployeesFromResponseShape(response) {
  let list =
    response?.data?.employees ??
    response?.data?.data ??
    response?.data ??
    response?.employees ??
    []
  if (!Array.isArray(list)) {
    list = coerceArray(list)
  }
  return list
}

function normalizeAttendanceApiResponse(raw) {
  if (raw == null) {
    return { employees: [], summary: null, date: null }
  }

  let summary = null
  let date = null

  // { success: true, data: { employees, summary, date } }
  if (raw.data && typeof raw.data === 'object' && !Array.isArray(raw.data)) {
    summary = raw.data.summary || null
    date = raw.data.date || raw.data.attendance_date || null
    if (Array.isArray(raw.data.employees) && raw.data.employees.length > 0) {
      const mapped = raw.data.employees.map((r) => mapLegacyAttendanceRow(r) || r).filter(Boolean)
      return { employees: mapped, summary: raw.data.summary || null, date }
    }
  }

  const shaped = coerceEmployeesFromResponseShape(raw)
  if (shaped.length) {
    const mapped = shaped.map((r) => mapLegacyAttendanceRow(r) || r).filter(Boolean)
    if (mapped.length) {
      return {
        employees: mapped,
        summary: raw.data?.summary ?? raw.summary ?? summarizeEmployees(mapped),
        date: date || raw.date || raw.data?.date || null,
      }
    }
  }

  const extracted = extractEmployeesFromRaw(raw)
  if (extracted.length) {
    return {
      employees: extracted,
      summary: raw.data?.summary ?? raw.summary ?? null,
      date: date || raw.date || raw.data?.date || null,
    }
  }

  // Top-level array (unlikely)
  if (Array.isArray(raw)) {
    const employees = raw.map(mapLegacyAttendanceRow).filter(Boolean)
    return { employees, summary: null, date: null }
  }

  return { employees: [], summary: null, date: null }
}

function findAgentForAttendance(agents, att) {
  const target = normalizeEmployeeId(att?.employee_id ?? att?.user_id)
  if (!target) return null
  return (
    agents.find((a) => {
      const keys = [a?.employee_id, a?.biometric_code, a?.id, a?.user_id]
      return keys.some((k) => normalizeEmployeeId(k) === target)
    }) || null
  )
}

export function useHrDashboard() {
  const loading = ref(false)
  const loadingAgents = ref(false)
  const error = ref('')
  const dateFilter = ref(localDateStringYMD())
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

  async function loadLeadsCount() {
    try {
      totalLeads.value = await fetchLeadTotalCount()
    } catch (e) {
      totalLeads.value = 0
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
    const agents = agentEmployees.value
    return employees.value.map((attendanceRow) => {
      const agent = findAgentForAttendance(agents, attendanceRow)

      // Source of truth: always use HR Attendance API name.
      const employeeName = attendanceRow?.employee_name || 'Unknown'
      const teamName =
        agent?.team?.name ||
        agent?.team_name ||
        agent?.department ||
        agent?.office_name ||
        'Unassigned'

      const employeeIdNorm =
        attendanceRow?.employee_id_normalized ||
        normalizeEmployeeId(attendanceRow?.employee_id ?? attendanceRow?.user_id)

      return {
        employee_id: attendanceRow?.employee_id ?? null,
        employee_id_normalized: employeeIdNorm || String(attendanceRow?.employee_id ?? ''),
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
        const idNorm = String(employee.employee_id_normalized || '').toLowerCase()
        return name.includes(q) || id.includes(q) || idNorm.includes(q)
      })
      .sort((a, b) => {
        const ra = statusRank[a.status] ?? 99
        const rb = statusRank[b.status] ?? 99
        if (ra !== rb) return ra - rb
        return String(a.employee_name || '').localeCompare(String(b.employee_name || ''))
      })
  })

  const groupedTeams = computed(() => {
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

  /**
   * Same hierarchy as Users TeamTree: parent_id → children, with DB attendance attached by normalized employee id.
   */
  const hrAttendanceTeamTree = computed(() => {
    const raw = buildHrAttendanceUserTree(agentEmployees.value, employees.value)
    return filterHrAttendanceTree(raw, teamSearch.value, teamFilter.value, treeStatusFilter.value)
  })

  function logPipelineState(source = '') {
    if (!hrPipelineDebugEnabled()) return
    const selectedDate = dateFilter.value || localDateStringYMD()
    const base = String(api.defaults.baseURL || '').replace(/\/$/, '')
    console.log('API URL:', `/attendance?date=${selectedDate}`)
    console.log('API URL (resolved):', `${base}/attendance?date=${encodeURIComponent(selectedDate)}`)

    const attendance = employees.value
    const agents = agentEmployees.value
    const mergedData = mergedEmployees.value
    const groupedTeamsArr = groupedTeams.value
    const hierarchy = hrAttendanceTeamTree.value
    const filtered = filteredMergedEmployees.value

    console.log('ATTENDANCE COUNT:', attendance.length)
    console.log('AGENTS COUNT:', agents.length)
    console.log('MERGED COUNT:', mergedData.length)
    console.log('GROUPED TEAMS COUNT:', groupedTeamsArr.length)
    console.log('[HR] USER TREE ROOTS:', hierarchy.length)
    console.log('[HR] FILTERED MERGED COUNT:', filtered.length)

    console.log('ATTENDANCE SAMPLE:', attendance[0])
    console.log('AGENT SAMPLE:', agents[0])
    console.log('MERGED SAMPLE:', mergedData[0])
    console.log('GROUPED SAMPLE:', groupedTeamsArr[0])
    if (source) console.log('[HR] snapshot source:', source)

    if (attendance.length === 0) {
      console.warn('[HR PIPELINE] attendance.length === 0 → API returned nothing for this date, request failed, or normalization produced []')
      return
    }
    if (mergedData.length === 0 && attendance.length > 0) {
      console.warn('[HR PIPELINE] merged empty but attendance > 0 → merge/map layer (unexpected for this composable)')
    }
    if (filtered.length === 0 && mergedData.length > 0) {
      console.warn('[HR PIPELINE] filtered merged empty but merged > 0 → TEAM VIEW filters (team/status/search) removed all rows')
    }
    if (groupedTeamsArr.length === 0 && filtered.length > 0) {
      console.warn('[HR PIPELINE] groupedTeams empty but filtered > 0 → grouping layer bug')
    }
    if (hierarchy.length === 0 && filtered.length > 0) {
      console.warn('[HR PIPELINE] hrAttendanceTeamTree empty but filtered merged > 0 → tree filter may be too strict')
    }
  }

  async function loadAttendance() {
    loading.value = true
    error.value = ''
    try {
      const selectedDate = dateFilter.value || localDateStringYMD()
      const params = {
        date: selectedDate,
        status: statusFilter.value,
      }
      const response = await fetchAttendance(params)
      const normalized = normalizeAttendanceApiResponse(response)
      const list = Array.isArray(normalized.employees) ? normalized.employees : []
      employees.value = list
      summary.value = normalized.summary || summarizeEmployees(list)
      await nextTick()
      logPipelineState('loadAttendance')
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load attendance data'
      employees.value = []
      if (hrPipelineDebugEnabled()) {
        console.error('[HR] loadAttendance HTTP error:', e?.response?.status, e?.response?.data, e?.message)
      }
      await nextTick()
      logPipelineState('loadAttendance-error')
    } finally {
      loading.value = false
    }
  }

  async function loadAgentData() {
    loadingAgents.value = true
    try {
      agentEmployees.value = await fetchAgentEmployees()
    } catch (e) {
      agentEmployees.value = []
      if (hrPipelineDebugEnabled()) {
        console.error('[HR] loadAgentData error:', e?.message)
      }
    } finally {
      loadingAgents.value = false
    }
    await nextTick()
    logPipelineState('loadAgentData')
  }

  watch(
    [employees, agentEmployees, groupedTeams, mergedEmployees, filteredMergedEmployees, hrAttendanceTeamTree, teamFilter, treeStatusFilter, teamSearch],
    () => {
      if (!hrPipelineDebugEnabled()) return
      nextTick(() => logPipelineState('watch'))
    },
    { deep: true }
  )

  watch(
    () => [employees.value.length, agentEmployees.value.length],
    () => {
      if (!hrPipelineDebugEnabled()) return
      const agents = agentEmployees.value
      nextTick(() => {
        employees.value.slice(0, 25).forEach((att) => {
          const agent = findAgentForAttendance(agents, att)
          console.log('RAW HR ID:', att?.employee_id)
          console.log('NORMALIZED HR ID:', normalizeEmployeeId(att?.employee_id ?? att?.user_id))
          console.log('NORMALIZED AGENT ID:', normalizeEmployeeId(agent?.employee_id ?? agent?.biometric_code ?? agent?.id))
        })
      })
    }
  )

  const teamOptions = computed(() => {
    const fromMerged = mergedEmployees.value.map((e) => e.team_name || 'Unassigned')
    const fromAgents = agentEmployees.value
      .map((a) => (a.office_name || a.team?.name || '').trim())
      .filter(Boolean)
    const unique = new Set(['Unassigned', ...fromAgents, ...fromMerged])
    const rest = Array.from(unique)
      .filter((x) => x && x !== 'all')
      .sort((a, b) => a.localeCompare(b))
    return ['all', ...rest]
  })

  watch(
    hrAttendanceTeamTree,
    (roots) => {
      if (!hrPipelineDebugEnabled() || !roots?.length) return
      const first = roots[0]
      console.log('USER:', { id: first?.id, name: first?.name, parent_id: first?.parent_id, role: first?.role_name })
      console.log('PARENT ID:', first?.parent_id)
      console.log('ATTACHED NODE:', { id: first?.id, hr_attendance: first?.hr_attendance })
    },
    { deep: true }
  )

  return {
    loading,
    loadingAgents,
    error,
    dateFilter,
    statusFilter,
    employees,
    /** Alias for template / debug (same ref as `employees`). */
    attendance: employees,
    agentEmployees,
    /** Alias for template / debug (same computed as `mergedEmployees`). */
    mergedData: mergedEmployees,
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
    groupedTeams,
    hrAttendanceTeamTree,
    teamOptions,
    loadAttendance,
    loadAgentData,
    loadLeadsCount,
    distributeLeads,
  }
}

