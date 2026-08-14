import api from '@/plugins/axios'

function unwrapList(payload) {
  const data = payload?.data
  if (data?.data && Array.isArray(data.data)) {
    return { items: data.data, meta: data.meta || null }
  }
  if (Array.isArray(data)) {
    return { items: data, meta: null }
  }
  if (Array.isArray(payload)) {
    return { items: payload, meta: null }
  }
  return { items: [], meta: null }
}

function uniqueByName(items) {
  const seen = new Set()
  const out = []
  for (const item of items || []) {
    if (!item) continue
    const name = String(item.name ?? item.label ?? '').trim().toLowerCase()
    const key = name || `id:${item.id ?? item.value}`
    if (seen.has(key)) continue
    seen.add(key)
    out.push(item)
  }
  return out
}

function unwrapPaginated(payload) {
  const root = payload?.data
  if (root?.data && Array.isArray(root.data)) {
    return {
      items: root.data,
      currentPage: root.current_page ?? 1,
      lastPage: root.last_page ?? 1,
      total: root.total ?? root.data.length,
      perPage: root.per_page ?? root.data.length,
    }
  }
  const { items } = unwrapList(payload)
  return {
    items,
    currentPage: 1,
    lastPage: 1,
    total: items.length,
    perPage: items.length,
  }
}

export function normalizeEmployee(emp) {
  if (!emp) return null
  const profile = emp.employee_profile || {}
  return {
    id: emp.id,
    name: emp.name || profile.employee_name || '—',
    email: emp.email || '—',
    phone: emp.phone || emp.personal_phone || '—',
    personalPhone: emp.personal_phone || '—',
    avatar: emp.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(emp.name || 'E')}&background=733e87&color=fff`,
    status: emp.status || 'active',
    employmentStatus: profile.employment_status || 'active',
    employeeCode: profile.employee_code || `EMP-${emp.id}`,
    designation: profile.designation?.name || '—',
    designationId: profile.designation?.id || null,
    department: profile.department?.name || '—',
    departmentId: profile.department?.id || null,
    branch: profile.branch_name || '—',
    branchId: profile.company_branch_id || null,
    joiningDate: profile.joining_date || null,
    manager: emp.parent?.name || '—',
    managerId: emp.parent?.id || null,
    role: emp.role?.name || '—',
    nationality: emp.nationality || '—',
    salaryType: emp.salary?.type || emp.salary_type || null,
    salaryAmount: emp.salary?.amount || emp.salary_amount || null,
    raw: emp,
  }
}

export async function fetchEmployees(params = {}) {
  const response = await api.get('/employees', { params })
  const page = unwrapPaginated(response.data)
  return {
    ...page,
    items: page.items.map(normalizeEmployee),
  }
}

export async function fetchEmployee(id) {
  const response = await api.get(`/employees/${id}`)
  const data = response.data?.data ?? response.data
  return normalizeEmployee(data)
}

export async function fetchEmployeeRaw(id) {
  const response = await api.get(`/employees/${id}`)
  return response.data?.data ?? response.data
}

export async function fetchEmployeeStatistics() {
  const response = await api.get('/employees/statistics')
  return response.data?.data ?? {}
}

export async function deleteEmployee(id) {
  await api.delete(`/employees/${id}`)
}

export async function fetchDepartments() {
  const response = await api.get('/departments', { params: { per_page: 200 } })
  const { items } = unwrapList(response.data)
  return uniqueByName(items)
}

export async function fetchDesignations() {
  const response = await api.get('/designations', { params: { per_page: 200 } })
  const { items } = unwrapList(response.data)
  return uniqueByName(items)
}

export async function fetchBranches() {
  const response = await api.get('/company-branches', { params: { per_page: 200 } })
  const { items } = unwrapList(response.data)
  return uniqueByName(items)
}

export async function fetchManagers() {
  const response = await api.get('/employees', { params: { per_page: 200, employment_status: 'active' } })
  const page = unwrapPaginated(response.data)
  return page.items.map(normalizeEmployee)
}

export async function fetchEmployeeLeaveBalance(userId) {
  const response = await api.get(`/leaves/balance/${userId}`)
  return response.data?.data ?? []
}

export async function fetchEmployeeAssets(userId) {
  const response = await api.get(`/assets/employee/${userId}/assets`)
  return response.data?.data ?? []
}


/**
 * Create new employee
 */
export async function createEmployee(formData) {
  const response = await api.post('/employees', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
  return response.data?.data ?? response.data
}

/**
 * Update existing employee
 */
export async function updateEmployee(id, formData) {
  const response = await api.post(`/employees/${id}/?_method=PUT`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
  return response.data?.data ?? response.data
}





