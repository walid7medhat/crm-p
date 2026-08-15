import api from '@/plugins/axios'

// ==================== Attendance Management ====================

/**
 * @param {Object} params - 
 * @param {string} params.date - 
 * @param {string} params.start_date - 
 * @param {string} params.end_date -
 * @param {number} params.user_id - 
 * @param {string} params.status -
 * @param {number} params.per_page - 
 * @param {number} params.page -
 */
function normalizeAttendanceListResponse(root, params = {}) {
  const payload = root?.data ?? root

  if (payload?.employees && Array.isArray(payload.employees)) {
    return {
      employees: payload.employees,
      summary: payload.summary ?? null,
      date: payload.date ?? params.date ?? null,
      meta: {
        current_page: payload.current_page ?? 1,
        last_page: payload.last_page ?? 1,
        total: payload.total ?? payload.employees.length,
        per_page: payload.per_page ?? payload.employees.length,
      },
    }
  }

  if (payload?.data && Array.isArray(payload.data)) {
    return {
      employees: payload.data,
      summary: payload.summary ?? null,
      date: payload.date ?? params.date ?? null,
      meta: {
        current_page: payload.current_page ?? 1,
        last_page: payload.last_page ?? 1,
        total: payload.total ?? payload.data.length,
        per_page: payload.per_page ?? payload.data.length,
      },
    }
  }

  if (Array.isArray(payload)) {
    return {
      employees: payload,
      summary: null,
      date: params.date ?? null,
      meta: {
        current_page: 1,
        last_page: 1,
        total: payload.length,
        per_page: payload.length,
      },
    }
  }

  return {
    employees: [],
    summary: payload?.summary ?? null,
    date: payload?.date ?? params.date ?? null,
    meta: {
      current_page: 1,
      last_page: 1,
      total: 0,
      per_page: params.per_page ?? 15,
    },
  }
}

export const fetchAttendance = async (params = {}) => {
  try {
    const response = await api.get('/attendance', { params })
    return normalizeAttendanceListResponse(response.data, params)
  } catch (error) {
    console.error('Error fetching attendance:', error)
    throw error
  }
}

/**
 * 
 * @param {number} userId - 
 * @param {Object} params - 
 */
export const fetchEmployeeAttendance = async (userId, params = {}) => {
  try {
    const response = await api.get(`/attendance/employee/${userId}`, { params })
    return response.data.data || response.data || []
  } catch (error) {
    console.error('Error fetching employee attendance:', error)
    throw error
  }
}

/**
 *
 * @param {string} date - 
 */
export const fetchAttendanceSummary = async (date = null) => {
  try {
    const params = date ? { date } : {}
    const response = await api.get('/attendance/summary', { params })
    return response.data.data || response.data || {}
  } catch (error) {
    console.error('Error fetching attendance summary:', error)
    throw error
  }
}

/**
 * 
 * @param {Object} data -
 * @param {number} data.user_id - 
 * @param {string} data.date -
 * @param {string} data.check_in - 
 * @param {string} data.check_out -
 * @param {string} data.status - 
 * @param {string} data.break_duration -
 * @param {string} data.overtime -
 * @param {string} data.attendance_type - 
 * @param {string} data.description -
 * @param {File} data.attachment - 
 */
export const createAttendance = async (data) => {
  try {
    const formData = new FormData()
    Object.keys(data).forEach(key => {
      if (key === 'attachment' && data[key] instanceof File) {
        formData.append(key, data[key])
      } else if (data[key] !== null && data[key] !== undefined) {
        formData.append(key, data[key])
      }
    })
    
    const response = await api.post('/attendance', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data.data || response.data
  } catch (error) {
    console.error('Error creating attendance:', error)
    throw error
  }
}

/**
 * 
 * @param {number} id - 
 * @param {Object} data - 
 */
export const updateAttendance = async (id, data) => {
  try {
    const formData = new FormData()
    Object.keys(data).forEach(key => {
      if (key === 'attachment' && data[key] instanceof File) {
        formData.append(key, data[key])
      } else if (data[key] !== null && data[key] !== undefined) {
        formData.append(key, data[key])
      }
    })
    formData.append('_method', 'PUT')
    
    const response = await api.post(`/attendance/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data.data || response.data
  } catch (error) {
    console.error('Error updating attendance:', error)
    throw error
  }
}

/**
 * 
 * @param {number} id - 
 */
export const deleteAttendance = async (id) => {
  try {
    const response = await api.delete(`/attendance/${id}`)
    return response.data
  } catch (error) {
    console.error('Error deleting attendance:', error)
    throw error
  }
}

/**
 * 
 * @param {number} id -
 */
export const getAttendance = async (id) => {
  try {
    const response = await api.get(`/attendance/${id}`)
    return response.data.data || response.data
  } catch (error) {
    console.error('Error fetching attendance record:', error)
    throw error
  }
}

/**
 * 
 * @param {Object} data - 
 * @param {number} data.user_id - 
 * @param {string} data.type -
 * @param {string} data.attendance_type - 
 * @param {string} data.location - 
 * @param {string} data.notes - 
 */
export const recordCheckInOut = async (data) => {
  try {
    const response = await api.post('/attendance/check-in-out', data)
    return response.data.data || response.data
  } catch (error) {
    console.error('Error recording check-in/out:', error)
    throw error
  }
}

/**
 * 
 * @param {string} date - 
 */
export const fetchDailyAttendanceStats = async (date = null) => {
  try {
    const params = date ? { date } : {}
    const response = await api.get('/attendance/daily-stats', { params })
    return response.data.data || response.data || {}
  } catch (error) {
    console.error('Error fetching daily attendance stats:', error)
    throw error
  }
}

/**
 *
 * @param {Object} params -
 */
export const exportAttendance = async (params = {}) => {
  try {
    const response = await api.get('/attendance/export', { 
      params,
      responseType: 'blob' 
    })
    return response.data
  } catch (error) {
    console.error('Error exporting attendance:', error)
    throw error
  }
}

/**
 * 
 * @param {number} userId 
 * @param {string} month -
 */
export const fetchMonthlyAttendance = async (userId, month) => {
  try {
    const response = await api.get(`/attendance/monthly/${userId}`, {
      params: { month }
    })
    return response.data.data || response.data || []
  } catch (error) {
    console.error('Error fetching monthly attendance:', error)
    throw error
  }
}

function toHHmm(value) {
  if (!value) return '09:00'
  const text = String(value)
  return text.length >= 5 ? text.slice(0, 5) : text
}

export async function fetchAttendanceSettings() {
  const response = await api.get('/attendance/settings')
  const data = response.data?.data ?? response.data ?? {}
  return {
    day_of_week: Number(data.day_of_week ?? 6),
    start_time: toHHmm(data.start_time || '09:00:00'),
    end_time: toHHmm(data.end_time || '10:00:00'),
    department_ids: Array.isArray(data.department_ids) ? data.department_ids.map(Number) : [],
  }
}

export async function updateAttendanceSettings(payload) {
  const response = await api.put('/attendance/settings', payload)
  return response.data?.data ?? response.data
}

const attendancesApi = {
  profileHistory(months = 12) {
    return api.get('/profile/attendance-history', {
      params: { months },
    })
  },
}

export default attendancesApi


