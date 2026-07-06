import api from '@/plugins/axios'
import { fetchAttendance, fetchAgentEmployees } from '@/services/hrApi'

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


export function normalizeAttendanceRow(row) {
  if (!row) {
    console.warn('⚠️ Empty row passed to normalizeAttendanceRow');
    return null;
  }
  
  console.log('🔄 Normalizing row:', row);
  
  const checkIn = row.check_in ?? row.first_checkin ?? null;
  const checkOut = row.check_out ?? row.last_checkout ?? null;
  
  const employeeId = row.employee_id ?? row.user_id ?? row.id;
  const empCode = row.employee_code ?? row.biometric_code ?? row.emp_code ?? employeeId;
  
  const name = row.employee_name ?? row.name ?? row.user?.name ?? 'Unknown';
  
  const status = (row.status || 'absent').toLowerCase();
  
  const department = row.department ?? row.department_name ?? row.user?.employee_profile?.department?.name ?? '—';
  
  const branch = row.branch || row.branch_name || row.user?.employee_profile?.branch?.name || department;
  
  const normalized = {
    id: employeeId,
    employeeId: employeeId,
    empCode: empCode ? `ID : #${String(empCode).replace(/^#/, '')}` : '—',
    name: name,
    department: department,
    branch: branch,
    attendanceType: row.attendance_type || mapAttendanceType(status),
    email: row.email ?? row.user?.email ?? '',
    status: status,
    checkIn: checkIn,
    checkOut: checkOut,
    workingHours: calcWorkingHours(checkIn, checkOut),
    overtimeHours: formatOt(row.overtime_minutes ?? row.ot_minutes),
    date: row.date ?? row.attendance_date,
    avatar: row.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=733e87&color=fff`,
    raw: row,
  };
  
  console.log('✅ Normalized row:', normalized);
  return normalized;
}

function mapAttendanceType(status) {
  const map = {
    present: 'Office',
    late: 'Office',
    absent: '—',
    on_leave: 'Paid Time Off',
  }
  return map[status] || 'Office'
}

export function formatAttendanceDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  // تنسيق التاريخ بصيغة: 02 Jul 2026 (مناسبة للإمارات)
  return d.toLocaleDateString('en-GB', { 
    day: '2-digit', 
    month: 'short', 
    year: 'numeric',
    timeZone: 'Asia/Dubai' 
  })
}

export function formatAttendanceTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (!Number.isNaN(d.getTime())) {
    // تنسيق الوقت بصيغة 12-hour مع AM/PM (مناسب للإمارات)
    return d.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit', 
      hour12: true,
      timeZone: 'Asia/Dubai'
    })
  }
  const m = String(value).match(/(\d{1,2}):(\d{2})/)
  if (!m) return String(value)
  let h = Number(m[1])
  const min = m[2]
  const ampm = h >= 12 ? 'PM' : 'AM'
  h = h % 12 || 12
  return `${String(h).padStart(2, '0')}:${min} ${ampm}`
}

export function normalizeLeaveRow(req) {
  if (!req) return null
  const user = req.user || {}
  return {
    id: req.id,
    employeeName: user.name || '—',
    employeeId: user.id,
    empCode: user.employee_profile?.employee_code || `EMP-${user.id}`,
    leaveType: req.leave_type?.name || req.leaveType?.name || '—',
    leaveTypeId: req.leave_type_id,
    startDate: req.start_date,
    endDate: req.end_date,
    duration: req.days ?? '—',
    status: req.status,
    statusLabel: formatLeaveStatus(req.status),
    reason: req.reason || '—',
    avatar: user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'E')}&background=733e87&color=fff`,
    canApproveParent: req.status === 'pending_parent',
    canApproveHr: req.status === 'pending_hr',
    canReject: ['pending_parent', 'pending_hr'].includes(req.status),
    raw: req,
  }
}

export function formatLeaveStatus(status) {
  const map = {
    pending_parent: 'Pending Manager',
    pending_hr: 'Pending HR',
    approved: 'Approved',
    rejected: 'Rejected',
    cancelled: 'Cancelled',
  }
  return map[status] || status || '—'
}

export function calcWorkingHours(checkIn, checkOut) {
  if (!checkIn || !checkOut) return '—'
  const start = parseTimeToMinutes(checkIn)
  const end = parseTimeToMinutes(checkOut)
  if (start == null || end == null || end <= start) return '—'
  const mins = end - start
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m ? `${h}h ${m}m` : `${h}h`
}

function parseTimeToMinutes(value) {
  if (!value) return null
  const s = String(value)
  const match = s.match(/(\d{1,2}):(\d{2})/)
  if (!match) return null
  return Number(match[1]) * 60 + Number(match[2])
}

function formatOt(minutes) {
  if (!minutes) return '—'
  const n = Number(minutes)
  if (!Number.isFinite(n) || n <= 0) return '—'
  const h = Math.floor(n / 60)
  const m = n % 60
  return m ? `${h}h ${m}m` : `${h}h`
}

export function isEarlyDeparture(checkOut) {
  const mins = parseTimeToMinutes(checkOut)
  if (mins == null) return false
  return mins < 17 * 60
}


export async function fetchAttendanceRecords(params = {}) {
  try {
    console.log('📊 fetchAttendanceRecords called with:', params);
    
    const data = await fetchAttendance(params);
    console.log('📊 Raw data from fetchAttendance:', data);
    
    const payload = data?.data ?? data;
    console.log('📊 Payload:', payload);
    
    let employees = [];
    
    if (payload?.data && Array.isArray(payload.data)) {
      employees = payload.data;
      console.log('📊 Found employees in payload.data:', employees.length);
    } else if (payload?.employees && Array.isArray(payload.employees)) {
      employees = payload.employees;
      console.log('📊 Found employees in payload.employees:', employees.length);
    } else if (Array.isArray(payload)) {
      employees = payload;
      console.log('📊 Payload is array:', employees.length);
    } else {
      console.warn('⚠️ No employees found in payload structure:', Object.keys(payload));
    }
    
    console.log('📊 Employees array:', employees);
    console.log('📊 Employees count:', employees.length);
    
    let rows = [];
    if (Array.isArray(employees) && employees.length > 0) {
      rows = employees.map(normalizeAttendanceRow).filter(Boolean);
    }
    
    console.log('📊 Normalized rows:', rows);
    console.log('📊 Rows count:', rows.length);
    
    const summary = {
      total_employees: rows.length,
      present_today: rows.filter(r => r.status === 'present').length,
      absent_today: rows.filter(r => r.status === 'absent').length,
      late_today: rows.filter(r => r.status === 'late').length,
    };
    
    console.log('📊 Summary:', summary);
    
    return {
      summary,
      rows,
      date: payload?.date || params?.date || null,
    };
    
  } catch (error) {
    console.error('❌ Error in fetchAttendanceRecords:', error);
    return {
      summary: {
        total_employees: 0,
        present_today: 0,
        absent_today: 0,
        late_today: 0,
      },
      rows: [],
      date: params?.date || null,
    };
  }
}

export async function fetchLeaveRequests(params = {}) {
  const response = await api.get('/leaves', { params })
  const page = unwrapPaginated(response.data)
  return {
    ...page,
    items: page.items.map(normalizeLeaveRow).filter(Boolean),
  }
}

export async function fetchLeaveStatistics() {
  const response = await api.get('/leaves/statistics')
  return response.data?.data ?? {}
}

export async function fetchLeaveTypes() {
  const response = await api.get('/leaves/types')
  const data = response.data?.data
  return Array.isArray(data) ? data : []
}

export async function approveLeaveParent(id) {
  await api.post(`/leaves/${id}/approve-parent`)
}

export async function approveLeaveHr(id) {
  await api.post(`/leaves/${id}/approve-hr`)
}

export async function rejectLeaveParent(id, reason) {
  await api.post(`/leaves/${id}/reject-parent`, { rejection_reason: reason })
}

export async function rejectLeaveHr(id, reason) {
  await api.post(`/leaves/${id}/reject-hr`, { rejection_reason: reason })
}

export async function fetchPeriodReport(startDate, endDate) {
  const response = await api.get('/attendance/period-report', {
    params: { start_date: startDate, end_date: endDate },
  })
  return response.data?.data ?? response.data ?? {}
}

export { fetchAgentEmployees }

export function exportCsv(filename, rows, columns) {
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
