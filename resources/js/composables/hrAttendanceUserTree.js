import { normalizeEmployeeId } from '@/utils/employeeId'

function agentRoleName(a) {
  const r = a?.role_name ?? (Array.isArray(a?.roles) ? a.roles[0] : null)
  return r ? String(r) : 'Unknown'
}

function agentNormKey(a) {
  return normalizeEmployeeId(a?.biometric_code ?? a?.employee_id ?? a?.id ?? a?.user_id)
}

function attendanceNormKey(row) {
  return normalizeEmployeeId(row?.employee_id ?? row?.user_id)
}

function defaultHrAttendance(nodeName) {
  return {
    status: 'absent',
    check_in: null,
    check_out: null,
    employee_name: nodeName,
  }
}

/**
 * Clone Users/Agents list into the same tree shape as TeamTree.vue (parent_id → children).
 * Then attach DB attendance rows by normalized employee id (biometric / id).
 */
export function buildHrAttendanceUserTree(agents, attendanceRows) {
  const agentsList = Array.isArray(agents) ? agents : []
  const rows = Array.isArray(attendanceRows) ? attendanceRows : []

  const usersMap = new Map()
  for (const u of agentsList) {
    const normKey = agentNormKey(u)
    usersMap.set(u.id, {
      id: u.id,
      name: u.name || 'Unnamed',
      email: u.email || '',
      avatar: u.avatar || null,
      parent_id: u.parent_id ?? null,
      parent_name: u.parent_name || null,
      role_name: agentRoleName(u),
      office_name: u.office_name || u.team?.name || '',
      employee_id_display: normKey || String(u.id),
      _norm_key: normKey,
      children: [],
      hr_attendance: defaultHrAttendance(u.name || 'Unnamed'),
    })
  }

  const roots = []
  for (const u of agentsList) {
    const node = usersMap.get(u.id)
    if (!node) continue
    if (u.parent_id && usersMap.has(u.parent_id)) {
      usersMap.get(u.parent_id).children.push(node)
    } else {
      roots.push(node)
    }
  }

  const byNormNode = new Map()
  const stack = [...roots]
  while (stack.length) {
    const n = stack.pop()
    if (n._norm_key) {
      byNormNode.set(n._norm_key, n)
    }
    for (const c of n.children || []) stack.push(c)
  }

  const matchedAttKeys = new Set()
  for (const row of rows) {
    const k = attendanceNormKey(row)
    if (!k) continue
    const node = byNormNode.get(k)
    if (node) {
      node.hr_attendance = {
        status: (row.status || 'absent').toString().toLowerCase(),
        check_in: row.check_in ?? null,
        check_out: row.check_out ?? null,
        employee_name: row.employee_name || node.name,
      }
      matchedAttKeys.add(k)
    }
  }

  const orphanLeaves = []
  for (const row of rows) {
    const k = attendanceNormKey(row)
    if (!k || matchedAttKeys.has(k)) continue
    orphanLeaves.push({
      id: `hr-orphan-${k}`,
      name: row.employee_name || 'Unknown',
      email: '',
      avatar: null,
      parent_id: null,
      role_name: 'Unknown',
      office_name: '',
      employee_id_display: k,
      _norm_key: k,
      children: [],
      hr_attendance: {
        status: (row.status || 'absent').toString().toLowerCase(),
        check_in: row.check_in ?? null,
        check_out: row.check_out ?? null,
        employee_name: row.employee_name || 'Unknown',
      },
      is_hr_orphan: true,
    })
  }

  const outRoots = [...roots]
  if (orphanLeaves.length) {
    outRoots.push({
      id: 'hr-unassigned-root',
      name: 'Unassigned Employees',
      email: '',
      avatar: null,
      parent_id: null,
      role_name: '—',
      office_name: 'Unassigned',
      employee_id_display: '',
      _norm_key: null,
      children: orphanLeaves,
      hr_attendance: defaultHrAttendance('Unassigned Employees'),
      is_hr_unassigned_bucket: true,
    })
  }

  return outRoots
}

function nodeMatchesFilters(node, search, teamFilter, statusFilter) {
  const st = String(node.hr_attendance?.status || 'absent').toLowerCase()
  const statusOk = statusFilter === 'all' || st === statusFilter
  const office = String(node.office_name || '').trim().toLowerCase()
  const tf = String(teamFilter || 'all')
  const teamOk =
    tf === 'all' ||
    (tf.toLowerCase() === 'unassigned' &&
      (!office ||
        office === 'unassigned' ||
        node.is_hr_unassigned_bucket ||
        node.is_hr_orphan)) ||
    tf.toLowerCase() === office
  const sn = search.trim().toLowerCase()
  const att = node.hr_attendance || {}
  const checkIn = att.check_in ? new Date(att.check_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }).toLowerCase() : ''
  const checkOut = att.check_out ? new Date(att.check_out).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }).toLowerCase() : ''
  const nameOk =
    !sn ||
    String(node.name || '')
      .toLowerCase()
      .includes(sn) ||
    String(node.employee_id_display || '')
      .toLowerCase()
      .includes(sn) ||
    String(node.email || '')
      .toLowerCase()
      .includes(sn) ||
    String(node.role_name || '')
      .toLowerCase()
      .includes(sn) ||
    String(node.office_name || '')
      .toLowerCase()
      .includes(sn) ||
    String(node.parent_name || '')
      .toLowerCase()
      .includes(sn) ||
    String(att.status || '')
      .toLowerCase()
      .includes(sn) ||
    checkIn.includes(sn) ||
    checkOut.includes(sn)
  return statusOk && teamOk && nameOk
}

/**
 * Keep nodes that match filters or have matching descendants (same behaviour as team filters).
 */
export function filterHrAttendanceTree(nodes, teamSearch, teamFilter, treeStatusFilter) {
  const search = (teamSearch || '').trim()
  const tf = teamFilter || 'all'
  const sf = (treeStatusFilter || 'all').toLowerCase()

  const walk = (list) => {
    const out = []
    for (const node of list) {
      const kids = walk(node.children || [])
      const selfOk = nodeMatchesFilters(node, search, tf, sf)
      if (selfOk || kids.length) {
        out.push({ ...node, children: kids })
      }
    }
    return out
  }

  return walk(nodes)
}
