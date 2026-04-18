import api from '@/plugins/axios'

/**
 * Attendance must go through the CRM API base URL (same axios instance as the rest of the app).
 * Calling another origin (e.g. :8000 from :8010) triggers browser CORS unless that server sends
 * Access-Control-Allow-Origin — which breaks SPAs in dev/production.
 *
 * To point at a different host, set VITE_API_BASE_URL (or window.__API_BASE_URL__) to that host
 * and expose /api/attendance there with CORS, or add a reverse proxy so the browser only talks to one origin.
 */
export async function fetchAttendanceToday(params = {}) {
  const { data } = await api.get('/attendance/today', { params })
  return data
}

export async function fetchAttendance(params = {}) {
  const { data } = await api.get('/attendance', { params })
  return data
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
