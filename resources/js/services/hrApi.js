import api from '@/plugins/axios'
import axios from 'axios'

const attendanceApi = axios.create({
  baseURL:
    (typeof window !== 'undefined' && window.__ATTENDANCE_API_BASE_URL__) ||
    import.meta.env.VITE_ATTENDANCE_API_BASE_URL ||
    'http://127.0.0.1:8000/api',
})

attendanceApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

async function requestWithFallback(path, params = {}) {
  try {
    const { data } = await attendanceApi.get(path, { params })
    return data
  } catch (directError) {
    // Fallback to CRM API client in case of CORS/auth/port mismatch.
    const { data } = await api.get(path, { params })
    return data
  }
}

export async function fetchAttendanceToday(params = {}) {
  return requestWithFallback('/attendance/today', params)
}

export async function fetchAttendance(params = {}) {
  return requestWithFallback('/attendance', params)
}

export async function fetchLeadTotalCount() {
  const { data } = await api.get('/leads', { params: { per_page: 1, page: 1 } })
  const total =
    data?.meta?.total ??
    data?.data?.meta?.total ??
    data?.total ??
    0
  return Number(total) || 0
}

export async function fetchAgentEmployees(params = {}) {
  const { data } = await api.get('/users', {
    params: {
      per_page: 500,
      page: 1,
      ...params,
    },
  })

  const rows =
    data?.data?.data ??
    data?.data ??
    data?.users ??
    []

  return Array.isArray(rows) ? rows : []
}

