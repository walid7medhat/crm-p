import api from '@/plugins/axios'

/**
 * Attendance must go through the CRM API base URL (same axios instance as the rest of the app).
 * Calling another origin (e.g. :8000 from :8010) triggers browser CORS unless that server sends
 * Access-Control-Allow-Origin — which breaks SPAs in dev/production.
 *
 * Production multi-host (OIA/Luxvant): axios uses same-origin `/api` via getApiBaseUrl().
 * Local Vite DEV may override with VITE_API_BASE_URL when the SPA origin differs from Laravel.
 */
export async function fetchAttendanceToday(params = {}) {
  const { data } = await api.get('/attendance/today', { params })
  return data
}

export async function fetchAttendance(params = {}) {
  const response = await api.get('/attendance', { params })

  if (response.status !== 200) {
    throw new Error(`API returned status ${response.status}`)
  }

  if (!response.data) {
    throw new Error('No data in response')
  }

  if (response.data?.success === false) {
    throw new Error(response.data.message || 'API returned error')
  }

  return response.data
}

export async function fetchLeadTotalCount() {
  // Dedicated count endpoint — never loads lead rows via GET /leads.
  const { data } = await api.get('/leads/total-count')
  const total =
    data?.data?.total ??
    data?.total ??
    data?.data?.data?.total ??
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
